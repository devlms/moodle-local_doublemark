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
 * Labels, badges and number formats shared by the pages.
 *
 * @package    local_doublemark
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class display {
    /** @var string[] Badge classes per status. Text colours pass WCAG AA on Moodle 4.5 and 5.x. */
    private const BADGES = [
        double_marking::UNALLOCATED => 'bg-light text-dark border',
        reconciler::MARKING => 'bg-secondary text-dark',
        reconciler::RECONCILE => 'bg-warning text-dark',
        reconciler::AGREED => 'bg-success text-white',
        double_marking::RESOLVED => 'bg-primary text-white',
        double_marking::RELEASED => 'bg-dark text-white',
    ];

    /**
     * Label of a status.
     *
     * @param string $status
     * @return string
     */
    public static function status_label(string $status): string {
        return get_string('status:' . $status, 'local_doublemark');
    }

    /**
     * Badge classes of a status.
     *
     * @param string $status
     * @return string
     */
    public static function badge(string $status): string {
        return 'badge ' . (self::BADGES[$status] ?? 'bg-light text-dark border');
    }

    /**
     * A grade without trailing zeros, or an empty string.
     *
     * @param float|null $grade
     * @return string
     */
    public static function grade(?float $grade): string {
        return $grade === null ? '' : format_float($grade, 2, true, true);
    }

    /**
     * The student's submission as the assignment's own submission plugins show it, read only.
     *
     * Rendered here instead of linking to the assignment's grader, so markers never see
     * the grade box there and the two marks stay apart.
     *
     * @param double_marking $dm
     * @param int $userid
     * @return string HTML
     */
    public static function submission(double_marking $dm, int $userid): string {
        global $OUTPUT;
        $assign = $dm->assign();
        $submission = $assign->get_user_submission($userid, false);
        if (!$submission || $submission->status === ASSIGN_SUBMISSION_STATUS_NEW) {
            return $OUTPUT->notification(get_string('nosubmission', 'local_doublemark'), 'info', false);
        }
        $html = '';
        foreach ($assign->get_submission_plugins() as $plugin) {
            if ($plugin->is_enabled() && $plugin->is_visible() && !$plugin->is_empty($submission)) {
                $html .= \html_writer::tag('h4', $plugin->get_name(), ['class' => 'local-doublemark-subhead']);
                $html .= \html_writer::div($plugin->view($submission), 'local-doublemark-submission-body');
            }
        }
        $when = get_string('submittedon', 'local_doublemark', userdate($submission->timemodified));
        return \html_writer::tag('p', $when, ['class' => 'local-doublemark-pending']) . $html;
    }

    /**
     * Both marks side by side, for the agree page and for a marker once both are in.
     *
     * @param \stdClass $row A row of double_marking::rows().
     * @param \stdClass[] $markers double_marking::markers().
     * @return array Context for local_doublemark/compare.
     */
    public static function compare(\stdClass $row, array $markers): array {
        $cards = [];
        foreach ([1, 2] as $position) {
            $mark = $position === 1 ? $row->mark1 : $row->mark2;
            $markerid = $position === 1 ? $row->marker1 : $row->marker2;
            $cards[] = [
                'title' => get_string($position === 1 ? 'firstmarker' : 'secondmarker', 'local_doublemark'),
                'marker' => isset($markers[$markerid]) ? fullname($markers[$markerid]) : '',
                'mark' => self::grade($mark?->grade),
                'comment' => $mark ? $mark->comment : '',
                'when' => $mark ? userdate($mark->timemodified, get_string('strftimedatetimeshort', 'langconfig')) : '',
            ];
        }
        return ['cards' => $cards, 'gap' => self::grade($row->gap), 'hasgap' => $row->gap !== null];
    }
}
