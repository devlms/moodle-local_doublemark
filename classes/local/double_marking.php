<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_doublemark\local;

use moodle_exception;

/**
 * Double marking on one assignment: settings, the two markers of each student, their marks,
 * the agreed mark and its release to the assignment.
 *
 * Marks live in this plugin's tables until they are released, then go through
 * assign::save_grade() like a grade typed by the lead, so the gradebook, marking
 * workflow and feedback plugins behave as usual.
 *
 * @package    local_doublemark
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class double_marking {
    /** A student without two markers yet. */
    public const UNALLOCATED = 'unallocated';
    /** The lead set the agreed mark. */
    public const RESOLVED = 'resolved';
    /** The agreed mark is in the assignment. */
    public const RELEASED = 'released';

    /** @var int Course module id. */
    public readonly int $cmid;

    /** @var \stdClass|null Settings row, null when double marking is off. */
    private ?\stdClass $config;

    /** @var int Students the last release() could not write a grade for (locked, overridden, workflow). */
    public int $skipped = 0;

    /**
     * Use for_cmid().
     *
     * @param \assign $assign
     */
    private function __construct(
        /** @var \assign The assignment. */
        private readonly \assign $assign,
    ) {
        global $DB;
        $this->cmid = (int) $assign->get_course_module()->id;
        $this->config = $DB->get_record('local_doublemark_config', ['cmid' => $this->cmid]) ?: null;
    }

    /**
     * Double marking for an assignment.
     *
     * @param int $cmid
     * @return self
     */
    public static function for_cmid(int $cmid): self {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'assign');
        return new self(new \assign(\context_module::instance($cm->id), $cm, $course));
    }

    /**
     * The assignment.
     *
     * @return \assign
     */
    public function assign(): \assign {
        return $this->assign;
    }

    /**
     * Whether double marking is on for this assignment.
     *
     * @return bool
     */
    public function is_enabled(): bool {
        return $this->config !== null;
    }

    /**
     * Largest gap that still counts as agreement.
     *
     * @return float
     */
    public function tolerance(): float {
        return (float) ($this->config->tolerance ?? 0);
    }

    /**
     * How agreeing marks become the agreed mark.
     *
     * @return string One of reconciler::RULES.
     */
    public function rule(): string {
        return $this->config->agreerule ?? reconciler::AVERAGE;
    }

    /**
     * Highest grade of the assignment.
     *
     * @return float
     */
    public function max_grade(): float {
        return (float) $this->assign->get_instance()->grade;
    }

    /**
     * Why this assignment cannot use double marking, as a string key, or null when it can.
     *
     * @return string|null
     */
    public function unavailable_reason(): ?string {
        $instance = $this->assign->get_instance();
        if ((float) $instance->grade <= 0) {
            return 'unavailable:scale';
        }
        if (!empty($instance->teamsubmission)) {
            return 'unavailable:team';
        }
        if (get_grading_manager($this->assign->get_context(), 'mod_assign', 'submissions')->get_active_method()) {
            return 'unavailable:advancedgrading';
        }
        // Moodle 5.3 has its own multiple marking; the two must not both write grades.
        if (method_exists($this->assign, 'is_using_multiple_marking') && $this->assign->is_using_multiple_marking()) {
            return 'unavailable:multiplemarking';
        }
        return null;
    }

    /**
     * Turn double marking on, or change its settings.
     *
     * @param float $tolerance
     * @param string $rule
     */
    public function configure(float $tolerance, string $rule): void {
        global $DB, $USER;
        if ($reason = $this->unavailable_reason()) {
            throw new moodle_exception($reason, 'local_doublemark');
        }
        if ($tolerance < 0 || $tolerance > $this->max_grade()) {
            throw new moodle_exception('error:badtolerance', 'local_doublemark');
        }
        if (!in_array($rule, reconciler::RULES, true)) {
            throw new \coding_exception("Unknown agreement rule '$rule'");
        }
        $row = (object) ['cmid' => $this->cmid, 'tolerance' => $tolerance, 'agreerule' => $rule,
            'usermodified' => (int) $USER->id, 'timemodified' => time()];
        if ($this->config) {
            $row->id = $this->config->id;
            $DB->update_record('local_doublemark_config', $row);
        } else {
            $row->id = $DB->insert_record('local_doublemark_config', $row);
        }
        $this->config = $row;
    }

    /**
     * Turn double marking off. Allocations and marks stay, so turning it back on loses nothing.
     */
    public function disable(): void {
        global $DB;
        $DB->delete_records('local_doublemark_config', ['cmid' => $this->cmid]);
        $this->config = null;
    }

    /**
     * Remove rows whose assignment no longer exists, e.g. after a course was deleted (which removes modules
     * without a course_module_deleted event).
     */
    public static function delete_orphans(): void {
        global $DB;
        foreach (['config', 'alloc', 'mark', 'agreed'] as $table) {
            $DB->delete_records_select(
                "local_doublemark_$table",
                'NOT EXISTS (SELECT 1 FROM {course_modules} cm WHERE cm.id = cmid)'
            );
        }
    }

    /**
     * Whether feedback from the lead can reach students: the assignment's feedback comments are on.
     *
     * @return bool
     */
    public function feedback_enabled(): bool {
        $comments = $this->assign->get_feedback_plugin_by_type('comments');
        return $comments && $comments->is_enabled();
    }

    /**
     * Remove double marking data of one assignment.
     *
     * @param int $cmid
     */
    public static function delete_for_cmid(int $cmid): void {
        global $DB;
        foreach (['config', 'alloc', 'mark', 'agreed'] as $table) {
            $DB->delete_records("local_doublemark_$table", ['cmid' => $cmid]);
        }
    }

    /**
     * People who can be markers: everyone with local/doublemark:mark on the assignment.
     *
     * @return \stdClass[] User records keyed by id, sorted by name.
     */
    public function markers(): array {
        $fields = 'u.id' . \core_user\fields::for_name()->get_sql('u')->selects;
        return get_users_by_capability($this->assign->get_context(), 'local/doublemark:mark', $fields, 'u.lastname, u.firstname');
    }

    /**
     * Students of the assignment, as the assignment itself lists them.
     *
     * @return \stdClass[] Keyed by user id.
     */
    private function students(): array {
        return $this->assign->list_participants(0, false);
    }

    /**
     * Set the two markers of a student.
     *
     * @param int $userid Student.
     * @param int $marker1
     * @param int $marker2
     */
    public function allocate(int $userid, int $marker1, int $marker2): void {
        global $DB;
        if (!isset($this->students()[$userid])) {
            throw new moodle_exception('error:notstudent', 'local_doublemark');
        }
        $markers = $this->markers();
        if (!isset($markers[$marker1]) || !isset($markers[$marker2])) {
            throw new moodle_exception('error:notmarker', 'local_doublemark');
        }
        if ($marker1 === $marker2) {
            throw new moodle_exception('error:samemarker', 'local_doublemark');
        }
        $row = (object) ['cmid' => $this->cmid, 'userid' => $userid, 'marker1' => $marker1, 'marker2' => $marker2,
            'timemodified' => time()];
        $existing = $DB->get_record('local_doublemark_alloc', ['cmid' => $this->cmid, 'userid' => $userid]);
        if ($existing) {
            // A marker who has already marked stays: someone else would see that mark as their own.
            $marked = $DB->get_records_menu(
                'local_doublemark_mark',
                ['cmid' => $this->cmid, 'userid' => $userid],
                '',
                'position, markerid'
            );
            foreach ([1 => $marker1, 2 => $marker2] as $position => $marker) {
                if (isset($marked[$position]) && (int) $existing->{'marker' . $position} !== $marker) {
                    throw new moodle_exception('error:markerlocked', 'local_doublemark');
                }
            }
            $row->id = $existing->id;
            $DB->update_record('local_doublemark_alloc', $row);
        } else {
            $DB->insert_record('local_doublemark_alloc', $row);
        }
    }

    /**
     * Give every student without two markers a pair, choosing the least loaded markers first.
     *
     * @param int[] $markerids At least two people who can mark.
     * @return int How many students got markers.
     */
    public function share_out(array $markerids): int {
        global $DB;
        $markerids = array_values(array_unique(array_map('intval', $markerids)));
        $markers = $this->markers();
        if (count($markerids) < 2) {
            throw new moodle_exception('error:fewmarkers', 'local_doublemark');
        }
        foreach ($markerids as $id) {
            if (!isset($markers[$id])) {
                throw new moodle_exception('error:notmarker', 'local_doublemark');
            }
        }
        $allocs = $DB->get_records('local_doublemark_alloc', ['cmid' => $this->cmid], '', 'userid, marker1, marker2');
        $load = array_fill_keys($markerids, 0);
        foreach ($allocs as $a) {
            foreach ([(int) $a->marker1, (int) $a->marker2] as $m) {
                if (isset($load[$m])) {
                    $load[$m]++;
                }
            }
        }
        $count = 0;
        foreach (array_keys($this->students()) as $userid) {
            $a = $allocs[$userid] ?? null;
            if ($a && $a->marker1 && $a->marker2) {
                continue;
            }
            // Least loaded first; ties keep the order the lead gave.
            $order = $markerids;
            usort($order, fn($x, $y) => [$load[$x], array_search($x, $markerids)] <=> [$load[$y], array_search($y, $markerids)]);
            [$first, $second] = $order;
            $this->allocate((int) $userid, $first, $second);
            $load[$first]++;
            $load[$second]++;
            $count++;
        }
        return $count;
    }

    /**
     * Record the current user's mark for a student they are allocated to.
     *
     * @param int $userid Student.
     * @param float $grade
     * @param string $comment Private, for the other marker and the lead.
     */
    public function record_mark(int $userid, float $grade, string $comment): void {
        global $DB, $USER;
        require_capability('local/doublemark:mark', $this->assign->get_context());
        $alloc = $DB->get_record('local_doublemark_alloc', ['cmid' => $this->cmid, 'userid' => $userid]);
        $position = match (true) {
            $alloc && (int) $alloc->marker1 === (int) $USER->id => 1,
            $alloc && (int) $alloc->marker2 === (int) $USER->id => 2,
            default => 0,
        };
        if (!$position) {
            throw new moodle_exception('error:notallocated', 'local_doublemark');
        }
        if (
            $DB->record_exists_select(
                'local_doublemark_agreed',
                'cmid = ? AND userid = ? AND timereleased > 0',
                [$this->cmid, $userid]
            )
        ) {
            throw new moodle_exception('error:released', 'local_doublemark');
        }
        if ($grade < 0 || $grade > $this->max_grade()) {
            throw new moodle_exception('error:range', 'local_doublemark', '', format_float($this->max_grade(), -1));
        }
        $now = time();
        $existing = $DB->get_record('local_doublemark_mark', ['cmid' => $this->cmid, 'userid' => $userid, 'position' => $position]);
        $row = (object) ['cmid' => $this->cmid, 'userid' => $userid, 'position' => $position, 'markerid' => (int) $USER->id,
            'grade' => $grade, 'comment' => $comment, 'timemodified' => $now];
        if ($existing) {
            $row->id = $existing->id;
            $DB->update_record('local_doublemark_mark', $row);
            // The lead decided on the old marks; a changed mark sends the student back for a new decision.
            if ((float) $existing->grade !== $grade) {
                $DB->delete_records_select(
                    'local_doublemark_agreed',
                    "cmid = ? AND userid = ? AND source = 'lead' AND timereleased = 0",
                    [$this->cmid, $userid]
                );
            }
        } else {
            $row->timecreated = $now;
            $DB->insert_record('local_doublemark_mark', $row);
        }
        \local_doublemark\event\mark_recorded::create([
            'context' => $this->assign->get_context(),
            'relateduserid' => $userid,
            'other' => ['position' => $position],
        ])->trigger();
    }

    /**
     * The lead sets the agreed mark of a student whose two marks are in.
     *
     * @param int $userid Student.
     * @param float $grade
     * @param string $note Why, for staff.
     * @param string $feedback For the student, sent with the grade.
     */
    public function agree(int $userid, float $grade, string $note, string $feedback): void {
        global $USER;
        require_capability('local/doublemark:manage', $this->assign->get_context());
        $row = $this->rows()[$userid] ?? null;
        if (!$row || !in_array($row->status, [reconciler::AGREED, reconciler::RECONCILE, self::RESOLVED], true)) {
            throw new moodle_exception('error:notmarked', 'local_doublemark');
        }
        if ($grade < 0 || $grade > $this->max_grade()) {
            throw new moodle_exception('error:range', 'local_doublemark', '', format_float($this->max_grade(), -1));
        }
        $this->save_agreed($userid, ['grade' => $grade, 'note' => $note, 'feedback' => $feedback, 'source' => 'lead',
            'agreedby' => (int) $USER->id, 'timeagreed' => time()]);
        \local_doublemark\event\mark_agreed::create([
            'context' => $this->assign->get_context(),
            'relateduserid' => $userid,
        ])->trigger();
    }

    /**
     * Insert or update the agreed row of a student.
     *
     * @param int $userid
     * @param array $fields
     */
    private function save_agreed(int $userid, array $fields): void {
        global $DB;
        $existing = $DB->get_record('local_doublemark_agreed', ['cmid' => $this->cmid, 'userid' => $userid]);
        $row = (object) (['cmid' => $this->cmid, 'userid' => $userid] + $fields);
        if ($existing) {
            $row->id = $existing->id;
            $DB->update_record('local_doublemark_agreed', $row);
        } else {
            $DB->insert_record('local_doublemark_agreed', $row);
        }
    }

    /**
     * Write every agreed mark that is not in the assignment yet to the assignment.
     *
     * @return int How many marks were released.
     */
    public function release(): int {
        global $USER;
        require_capability('local/doublemark:manage', $this->assign->get_context());
        // The assignment refuses grades from someone who cannot grade; check before the first one.
        require_capability('mod/assign:grade', $this->assign->get_context());
        $instance = $this->assign->get_instance();
        $comments = $this->feedback_enabled() ? $this->assign->get_feedback_plugin_by_type('comments') : null;
        $count = 0;
        $this->skipped = 0;
        foreach ($this->rows() as $userid => $row) {
            if (!in_array($row->status, [reconciler::AGREED, self::RESOLVED], true)) {
                continue;
            }
            $data = (object) ['grade' => $row->agreed, 'attemptnumber' => -1,
                'sendstudentnotifications' => (bool) $instance->sendstudentnotifications];
            if (!empty($instance->markingworkflow)) {
                $data->workflowstate = ASSIGN_MARKING_WORKFLOW_STATE_RELEASED;
            }
            if ($comments && $comments->is_enabled()) {
                // The comments plugin reads its editor on every save; keep what is there when there is no new feedback.
                $grade = $this->assign->get_user_grade($userid, false);
                $text = $row->feedback !== '' ? nl2br(s($row->feedback))
                    : ($grade ? (string) $comments->get_editor_text('comments', $grade->id) : '');
                $data->assignfeedbackcomments_editor = ['text' => $text, 'format' => FORMAT_HTML];
            }
            $this->assign->save_grade($userid, $data);
            // The assignment skips locked, overridden or workflow-locked grades without saying so: check it landed.
            $saved = $this->assign->get_user_grade($userid, false);
            if (!$saved || $saved->grade === null || abs((float) $saved->grade - (float) $row->agreed) > 0.00001) {
                $this->skipped++;
                continue;
            }
            $fields = ['timereleased' => time(), 'releasedby' => (int) $USER->id];
            if ($row->status === reconciler::AGREED) {
                $fields += ['grade' => $row->agreed, 'source' => 'rule', 'agreedby' => 0, 'timeagreed' => time()];
            }
            $this->save_agreed($userid, $fields);
            $count++;
        }
        if ($count) {
            \local_doublemark\event\marks_released::create([
                'context' => $this->assign->get_context(),
                'other' => ['count' => $count],
            ])->trigger();
        }
        return $count;
    }

    /**
     * Every student with markers, marks, status and agreed mark.
     *
     * @return \stdClass[] Keyed by student id, in the assignment's participant order.
     */
    public function rows(): array {
        global $DB;
        $params = ['cmid' => $this->cmid];
        $allocs = $DB->get_records('local_doublemark_alloc', $params, '', 'userid, marker1, marker2');
        $agreed = $DB->get_records('local_doublemark_agreed', $params, '', 'userid, grade, feedback, note, source, timereleased');
        $marks = [];
        foreach ($DB->get_records('local_doublemark_mark', $params) as $m) {
            $marks[$m->userid][(int) $m->position] = (object) ['grade' => (float) $m->grade, 'comment' => (string) $m->comment,
                'markerid' => (int) $m->markerid, 'timemodified' => (int) $m->timemodified];
        }
        $graded = $DB->get_fieldset_select(
            'assign_grades',
            'DISTINCT userid',
            'assignment = ? AND grade >= 0',
            [$this->assign->get_instance()->id]
        );
        $graded = array_flip(array_map('intval', $graded));
        $anonymous = $this->assign->is_blind_marking();

        $rows = [];
        foreach ($this->students() as $userid => $user) {
            $userid = (int) $userid;
            $a = $allocs[$userid] ?? null;
            $ag = $agreed[$userid] ?? null;
            $mark1 = $marks[$userid][1] ?? null;
            $mark2 = $marks[$userid][2] ?? null;
            $released = $ag && (int) $ag->timereleased > 0;
            [$status, $ruleagreed] = reconciler::resolve($mark1?->grade, $mark2?->grade, $this->tolerance(), $this->rule());
            if ($released) {
                $status = self::RELEASED;
            } else if (!$a || !$a->marker1 || !$a->marker2) {
                $status = self::UNALLOCATED;
            } else if ($ag && $ag->source === 'lead' && $status !== reconciler::MARKING) {
                $status = self::RESOLVED;
            }
            $rows[$userid] = (object) [
                'userid' => $userid,
                'user' => $user,
                'anonymous' => $anonymous,
                'name' => $anonymous
                    ? get_string('participant', 'assign') . ' ' . $this->assign->get_uniqueid_for_user($userid)
                    : fullname($user),
                'marker1' => $a ? (int) $a->marker1 : 0,
                'marker2' => $a ? (int) $a->marker2 : 0,
                'mark1' => $mark1,
                'mark2' => $mark2,
                'gap' => $mark1 && $mark2 ? round(abs($mark1->grade - $mark2->grade), 2) : null,
                'status' => $status,
                'agreed' => $ag && ($released || $status === self::RESOLVED) ? (float) $ag->grade : $ruleagreed,
                'source' => $ag ? $ag->source : null,
                'feedback' => $ag ? (string) $ag->feedback : '',
                'note' => $ag ? (string) $ag->note : '',
                // A grade typed in the assignment itself, outside double marking.
                'gradedoutside' => isset($graded[$userid]) && !$released,
            ];
        }
        return $rows;
    }

    /**
     * The students one marker marks, with their own mark and, once both are in, the other one.
     *
     * @param int $markerid
     * @return \stdClass[] Keyed by student id.
     */
    public function for_marker(int $markerid): array {
        $mine = [];
        foreach ($this->rows() as $userid => $row) {
            $position = match ($markerid) {
                $row->marker1 => 1,
                $row->marker2 => 2,
                default => 0,
            };
            if (!$position) {
                continue;
            }
            $both = $row->mark1 && $row->mark2;
            $mine[$userid] = (object) [
                'row' => $row,
                'position' => $position,
                'own' => $position === 1 ? $row->mark1 : $row->mark2,
                'other' => $both ? ($position === 1 ? $row->mark2 : $row->mark1) : null,
            ];
        }
        return $mine;
    }
}
