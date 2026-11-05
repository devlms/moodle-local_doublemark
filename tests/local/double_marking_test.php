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

/**
 * Tests for double marking on one assignment: setup, allocation, marks, agreement and release.
 *
 * @package    local_doublemark
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_doublemark\local\double_marking
 */
final class double_marking_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;
    /** @var \stdClass Lead (editing teacher). */
    private \stdClass $lead;
    /** @var \stdClass */
    private \stdClass $m1;
    /** @var \stdClass */
    private \stdClass $m2;
    /** @var \stdClass */
    private \stdClass $s1;
    /** @var \stdClass */
    private \stdClass $s2;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $this->lead = $gen->create_and_enrol($this->course, 'editingteacher', ['firstname' => 'Lea', 'lastname' => 'Lead']);
        $this->m1 = $gen->create_and_enrol($this->course, 'teacher', ['firstname' => 'Mia', 'lastname' => 'One']);
        $this->m2 = $gen->create_and_enrol($this->course, 'teacher', ['firstname' => 'Max', 'lastname' => 'Two']);
        $this->s1 = $gen->create_and_enrol($this->course, 'student', ['firstname' => 'Sara', 'lastname' => 'Student']);
        $this->s2 = $gen->create_and_enrol($this->course, 'student', ['firstname' => 'Sam', 'lastname' => 'Student']);
    }

    /**
     * An assignment with double marking on.
     *
     * @param array $settings Assignment settings.
     * @param float $tolerance
     * @param string $rule
     * @return double_marking
     */
    private function setup_assignment(
        array $settings = [],
        float $tolerance = 5,
        string $rule = reconciler::AVERAGE
    ): double_marking {
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id, 'grade' => 100,
            'assignfeedback_comments_enabled' => 1] + $settings);
        $this->setUser($this->lead);
        $dm = double_marking::for_cmid((int) $assign->cmid);
        $dm->configure($tolerance, $rule);
        return $dm;
    }

    public function test_configure_and_read_back(): void {
        $dm = $this->setup_assignment([], 7.5, reconciler::HIGHER);
        $this->assertTrue($dm->is_enabled());
        $again = double_marking::for_cmid($dm->cmid);
        $this->assertTrue($again->is_enabled());
        $this->assertEquals(7.5, $again->tolerance());
        $this->assertSame(reconciler::HIGHER, $again->rule());
        $this->assertEquals(100, $again->max_grade());
    }

    public function test_not_enabled_until_configured(): void {
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id, 'grade' => 100]);
        $this->assertFalse(double_marking::for_cmid((int) $assign->cmid)->is_enabled());
    }

    public function test_scales_rubrics_and_group_submissions_cannot_use_it(): void {
        $gen = $this->getDataGenerator();
        $points = $gen->create_module('assign', ['course' => $this->course->id, 'grade' => 100]);
        $this->assertNull(double_marking::for_cmid((int) $points->cmid)->unavailable_reason());

        $scale = $gen->create_scale();
        $scaled = $gen->create_module('assign', ['course' => $this->course->id, 'grade' => -$scale->id]);
        $this->assertSame('unavailable:scale', double_marking::for_cmid((int) $scaled->cmid)->unavailable_reason());

        $team = $gen->create_module('assign', ['course' => $this->course->id, 'grade' => 100, 'teamsubmission' => 1]);
        $this->assertSame('unavailable:team', double_marking::for_cmid((int) $team->cmid)->unavailable_reason());

        $rubric = $gen->create_module('assign', ['course' => $this->course->id, 'grade' => 100]);
        $context = \context_module::instance($rubric->cmid);
        $manager = get_grading_manager($context, 'mod_assign', 'submissions');
        $manager->set_active_method('rubric');
        $this->assertSame('unavailable:advancedgrading', double_marking::for_cmid((int) $rubric->cmid)->unavailable_reason());

        $this->expectException(\moodle_exception::class);
        double_marking::for_cmid((int) $scaled->cmid)->configure(5, reconciler::AVERAGE);
    }

    public function test_markers_are_people_who_can_mark(): void {
        $dm = $this->setup_assignment();
        $ids = array_map('intval', array_keys($dm->markers()));
        sort($ids);
        $expected = [(int) $this->lead->id, (int) $this->m1->id, (int) $this->m2->id];
        sort($expected);
        $this->assertSame($expected, $ids);
    }

    public function test_allocate_needs_two_different_markers(): void {
        $dm = $this->setup_assignment();
        $dm->allocate((int) $this->s1->id, (int) $this->m1->id, (int) $this->m2->id);
        $row = $dm->rows()[$this->s1->id];
        $this->assertSame((int) $this->m1->id, $row->marker1);
        $this->assertSame((int) $this->m2->id, $row->marker2);

        $this->expectException(\moodle_exception::class);
        $dm->allocate((int) $this->s1->id, (int) $this->m1->id, (int) $this->m1->id);
    }

    public function test_allocate_rejects_people_who_cannot_mark_and_non_students(): void {
        $dm = $this->setup_assignment();
        try {
            $dm->allocate((int) $this->s1->id, (int) $this->s2->id, (int) $this->m2->id);
            $this->fail('A student was allowed to mark');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:notmarker', $e->errorcode);
        }
        $this->expectException(\moodle_exception::class);
        $dm->allocate((int) $this->m1->id, (int) $this->lead->id, (int) $this->m2->id);
    }

    public function test_share_out_gives_each_student_two_markers_and_balances_the_load(): void {
        $dm = $this->setup_assignment();
        $gen = $this->getDataGenerator();
        for ($i = 0; $i < 4; $i++) {
            $gen->create_and_enrol($this->course, 'student');
        }
        $count = $dm->share_out([(int) $this->m1->id, (int) $this->m2->id, (int) $this->lead->id]);
        $this->assertSame(6, $count);
        $load = [];
        foreach ($dm->rows() as $row) {
            $this->assertNotSame(0, $row->marker1);
            $this->assertNotSame(0, $row->marker2);
            $this->assertNotSame($row->marker1, $row->marker2);
            $load[$row->marker1] = ($load[$row->marker1] ?? 0) + 1;
            $load[$row->marker2] = ($load[$row->marker2] ?? 0) + 1;
        }
        // 6 students x 2 marks over 3 markers.
        $this->assertSame([4, 4, 4], array_values($load));
        // Students already allocated keep their markers.
        $this->assertSame(0, $dm->share_out([(int) $this->m1->id, (int) $this->m2->id]));
    }

    public function test_share_out_needs_two_markers(): void {
        $dm = $this->setup_assignment();
        $this->expectException(\moodle_exception::class);
        $dm->share_out([(int) $this->m1->id]);
    }

    public function test_status_moves_from_unallocated_to_agreed(): void {
        $dm = $this->setup_assignment();
        $this->assertSame(double_marking::UNALLOCATED, $dm->rows()[$this->s1->id]->status);
        $dm->allocate((int) $this->s1->id, (int) $this->m1->id, (int) $this->m2->id);
        $this->assertSame(reconciler::MARKING, $dm->rows()[$this->s1->id]->status);

        $this->setUser($this->m1);
        $dm->record_mark((int) $this->s1->id, 62, 'Good structure, thin on sources.');
        $row = $dm->rows()[$this->s1->id];
        $this->assertSame(reconciler::MARKING, $row->status);
        $this->assertEquals(62, $row->mark1->grade);
        $this->assertNull($row->mark2);

        $this->setUser($this->m2);
        $dm->record_mark((int) $this->s1->id, 66, '');
        $row = $dm->rows()[$this->s1->id];
        $this->assertSame(reconciler::AGREED, $row->status);
        $this->assertEquals(64, $row->agreed);
        $this->assertEquals(4, $row->gap);
    }

    public function test_marks_outside_tolerance_wait_for_the_lead(): void {
        $dm = $this->setup_assignment();
        $dm->allocate((int) $this->s1->id, (int) $this->m1->id, (int) $this->m2->id);
        $this->setUser($this->m1);
        $dm->record_mark((int) $this->s1->id, 48, '');
        $this->setUser($this->m2);
        $dm->record_mark((int) $this->s1->id, 70, '');
        $row = $dm->rows()[$this->s1->id];
        $this->assertSame(reconciler::RECONCILE, $row->status);
        $this->assertNull($row->agreed);

        $this->setUser($this->lead);
        $dm->agree((int) $this->s1->id, 61, 'Read it a third time. Second marker missed the method section.', 'Well argued.');
        $row = $dm->rows()[$this->s1->id];
        $this->assertSame(double_marking::RESOLVED, $row->status);
        $this->assertEquals(61, $row->agreed);
        $this->assertSame('Well argued.', $row->feedback);
        $this->assertSame('Read it a third time. Second marker missed the method section.', $row->note);
    }

    public function test_only_the_allocated_markers_can_mark(): void {
        $dm = $this->setup_assignment();
        $dm->allocate((int) $this->s1->id, (int) $this->m1->id, (int) $this->m2->id);
        $this->setUser($this->lead);
        $this->expectException(\moodle_exception::class);
        $dm->record_mark((int) $this->s1->id, 50, '');
    }

    public function test_marks_must_be_in_range(): void {
        $dm = $this->setup_assignment();
        $dm->allocate((int) $this->s1->id, (int) $this->m1->id, (int) $this->m2->id);
        $this->setUser($this->m1);
        $this->expectException(\moodle_exception::class);
        $dm->record_mark((int) $this->s1->id, 101, '');
    }

    public function test_a_marker_can_change_their_mark_before_release(): void {
        $dm = $this->setup_assignment();
        $dm->allocate((int) $this->s1->id, (int) $this->m1->id, (int) $this->m2->id);
        $this->setUser($this->m1);
        $dm->record_mark((int) $this->s1->id, 50, 'first go');
        $dm->record_mark((int) $this->s1->id, 55, 'second go');
        $row = $dm->rows()[$this->s1->id];
        $this->assertEquals(55, $row->mark1->grade);
        $this->assertSame('second go', $row->mark1->comment);
    }

    public function test_the_other_mark_stays_hidden_until_both_are_in(): void {
        $dm = $this->setup_assignment();
        $dm->allocate((int) $this->s1->id, (int) $this->m1->id, (int) $this->m2->id);
        $this->setUser($this->m1);
        $dm->record_mark((int) $this->s1->id, 60, 'mine');

        $this->setUser($this->m2);
        $mine = $dm->for_marker((int) $this->m2->id)[$this->s1->id];
        $this->assertSame(2, $mine->position);
        $this->assertNull($mine->own);
        $this->assertNull($mine->other, 'The first mark leaked before the second marker marked');

        $dm->record_mark((int) $this->s1->id, 64, 'theirs');
        $mine = $dm->for_marker((int) $this->m2->id)[$this->s1->id];
        $this->assertEquals(64, $mine->own->grade);
        $this->assertEquals(60, $mine->other->grade);
        $this->assertSame('mine', $mine->other->comment);

        // Students someone does not mark are not in their list.
        $this->assertSame([], $dm->for_marker((int) $this->lead->id));
    }

    public function test_release_writes_agreed_marks_to_the_assignment(): void {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $dm = $this->setup_assignment();
        $dm->allocate((int) $this->s1->id, (int) $this->m1->id, (int) $this->m2->id);
        $dm->allocate((int) $this->s2->id, (int) $this->m1->id, (int) $this->m2->id);
        $this->setUser($this->m1);
        $dm->record_mark((int) $this->s1->id, 60, '');
        $dm->record_mark((int) $this->s2->id, 40, '');
        $this->setUser($this->m2);
        $dm->record_mark((int) $this->s1->id, 64, '');
        $dm->record_mark((int) $this->s2->id, 80, '');

        $this->setUser($this->lead);
        $dm->agree((int) $this->s1->id, 62, '', 'Clear and well sourced.');
        // The second student needs reconciliation, so only the first goes.
        $this->assertSame(1, $dm->release());

        $grades = grade_get_grades(
            $this->course->id,
            'mod',
            'assign',
            $dm->assign()->get_instance()->id,
            [(int) $this->s1->id, (int) $this->s2->id]
        );
        $items = $grades->items[0]->grades;
        $this->assertEquals(62, $items[$this->s1->id]->grade);
        $this->assertNull($items[$this->s2->id]->grade);
        $this->assertSame(double_marking::RELEASED, $dm->rows()[$this->s1->id]->status);
        $this->assertStringContainsString('Clear and well sourced.', $items[$this->s1->id]->feedback);

        // Released marks are locked.
        $this->setUser($this->m1);
        try {
            $dm->record_mark((int) $this->s1->id, 10, '');
            $this->fail('Changed a released mark');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:released', $e->errorcode);
        }
        // Releasing again sends nothing new.
        $this->setUser($this->lead);
        $this->assertSame(0, $dm->release());
    }

    public function test_release_marks_agreed_by_the_rule_and_respects_marking_workflow(): void {
        $dm = $this->setup_assignment(['markingworkflow' => 1]);
        $dm->allocate((int) $this->s1->id, (int) $this->m1->id, (int) $this->m2->id);
        $this->setUser($this->m1);
        $dm->record_mark((int) $this->s1->id, 70, '');
        $this->setUser($this->m2);
        $dm->record_mark((int) $this->s1->id, 72, '');
        $this->setUser($this->lead);
        $this->assertSame(1, $dm->release());
        $flags = $dm->assign()->get_user_flags((int) $this->s1->id, false);
        $this->assertSame(ASSIGN_MARKING_WORKFLOW_STATE_RELEASED, $flags->workflowstate);
        $this->assertEquals(71, $dm->assign()->get_user_grade((int) $this->s1->id, false)->grade);
        $this->assertSame('rule', $dm->rows()[$this->s1->id]->source);
    }

    public function test_grades_entered_in_the_assignment_directly_are_flagged(): void {
        $dm = $this->setup_assignment();
        $dm->allocate((int) $this->s1->id, (int) $this->m1->id, (int) $this->m2->id);
        $this->setAdminUser();
        $dm->assign()->save_grade((int) $this->s1->id, (object) ['grade' => 55, 'attemptnumber' => -1,
            'assignfeedbackcomments_editor' => ['text' => '', 'format' => FORMAT_HTML]]);
        $this->assertTrue($dm->rows()[$this->s1->id]->gradedoutside);
        $this->assertFalse($dm->rows()[$this->s2->id]->gradedoutside);
    }

    public function test_settings_can_be_changed_and_turned_off(): void {
        $dm = $this->setup_assignment();
        $dm->configure(10, reconciler::LEAD);
        $this->assertSame(reconciler::LEAD, double_marking::for_cmid($dm->cmid)->rule());
        $dm->disable();
        $this->assertFalse(double_marking::for_cmid($dm->cmid)->is_enabled());
    }

    public function test_deleting_the_assignment_removes_its_data(): void {
        global $DB;
        $dm = $this->setup_assignment();
        $dm->allocate((int) $this->s1->id, (int) $this->m1->id, (int) $this->m2->id);
        $this->setUser($this->m1);
        $dm->record_mark((int) $this->s1->id, 60, '');
        course_delete_module($dm->cmid);
        foreach (['config', 'alloc', 'mark', 'agreed'] as $table) {
            $this->assertFalse($DB->record_exists("local_doublemark_$table", ['cmid' => $dm->cmid]), $table);
        }
    }

    public function test_anonymous_submissions_hide_names(): void {
        $dm = $this->setup_assignment(['blindmarking' => 1]);
        $row = $dm->rows()[$this->s1->id];
        $this->assertStringStartsWith('Participant ', $row->name);
        $this->assertTrue($row->anonymous);

        $open = $this->setup_assignment();
        $this->assertSame('Sara Student', $open->rows()[$this->s1->id]->name);
    }
}
