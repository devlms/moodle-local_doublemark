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

namespace local_doublemark\output;

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use local_doublemark\local\display;
use local_doublemark\local\double_marking;
use local_doublemark\local\reconciler;

/**
 * The lead's view of an assignment: who marks whom, both marks, the gap and what is left to do.
 *
 * @package    local_doublemark
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overview implements renderable, templatable {
    /** @var array Filter tiles and the statuses each one covers, in display order. */
    public const GROUPS = [
        'marking' => [double_marking::UNALLOCATED, reconciler::MARKING],
        'reconcile' => [reconciler::RECONCILE],
        'ready' => [reconciler::AGREED, double_marking::RESOLVED],
        'released' => [double_marking::RELEASED],
    ];

    /** @var string Active filter: all or a key of GROUPS. */
    private string $filter;

    /**
     * Create the overview.
     *
     * @param double_marking $dm
     * @param string $filter
     */
    public function __construct(
        /** @var float*/
        private readonly double_marking $dm,
        string $filter,
    ) {
        $this->filter = isset(self::GROUPS[$filter]) ? $filter : 'all';
    }

    /**
     * Data for local_doublemark/overview.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $rows = $this->dm->rows();
        $markers = $this->dm->markers();
        $name = fn(int $id) => isset($markers[$id]) ? fullname($markers[$id]) : '';
        $url = fn(array $params) => (new \moodle_url('/local/doublemark/manage.php', ['cmid' => $this->dm->cmid] + $params))
            ->out(false);

        $counts = [];
        foreach (self::GROUPS as $group => $statuses) {
            $counts[$group] = count(array_filter($rows, fn($r) => in_array($r->status, $statuses, true)));
        }
        $tiles = [];
        foreach (self::GROUPS as $group => $statuses) {
            $tiles[] = [
                'name' => $group,
                'label' => get_string('group:' . $group, 'local_doublemark'),
                'count' => $counts[$group],
                // Width of the stage in the strip: its share of all students.
                'share' => $counts[$group],
                'needsyou' => $group === 'reconcile' && $counts[$group] > 0,
                'url' => $url(['filter' => $this->filter === $group ? 'all' : $group]),
                'active' => $this->filter === $group,
            ];
        }

        $shown = $this->filter === 'all' ? $rows
            : array_filter($rows, fn($r) => in_array($r->status, self::GROUPS[$this->filter], true));
        // Those who need the lead first, then marks ready to release, then marking, then released.
        $rank = array_flip(array_merge(
            self::GROUPS['reconcile'],
            self::GROUPS['ready'],
            self::GROUPS['marking'],
            self::GROUPS['released']
        ));
        $order = array_flip(array_keys($shown));
        uksort($shown, fn($a, $b) => [$rank[$shown[$a]->status] ?? 9, $order[$a]] <=> [$rank[$shown[$b]->status] ?? 9, $order[$b]]);
        $list = [];
        foreach ($shown as $row) {
            $agreeurl = new \moodle_url('/local/doublemark/agree.php', ['cmid' => $this->dm->cmid, 'userid' => $row->userid]);
            $canagree = in_array($row->status, [reconciler::AGREED, reconciler::RECONCILE, double_marking::RESOLVED], true);
            $list[] = [
                'userid' => $row->userid,
                'name' => $row->name,
                'picture' => $row->anonymous ? '' : $output->user_picture($row->user, ['size' => 30, 'link' => false,
                    'alttext' => false]),
                'marker1' => $name($row->marker1),
                'marker2' => $name($row->marker2),
                'mark1' => display::grade($row->mark1?->grade),
                'mark2' => display::grade($row->mark2?->grade),
                'gap' => display::grade($row->gap),
                'wide' => $row->gap !== null && $row->gap > $this->dm->tolerance(),
                'agreed' => display::grade($row->agreed),
                'status' => $row->status,
                'statuslabel' => display::status_label($row->status),
                'badge' => display::badge($row->status),
                'needsyou' => $row->status === reconciler::RECONCILE,
                'canagree' => $canagree,
                'agreeurl' => $agreeurl->out(false),
                'gradedoutside' => $row->gradedoutside,
            ];
        }
        $releasecount = $counts['ready'];
        $next = match (true) {
            !$rows => '',
            $releasecount > 0 => get_string('releasehelp', 'local_doublemark'),
            $counts['reconcile'] > 0 => get_string('next:reconcile', 'local_doublemark', $counts['reconcile']),
            $counts['marking'] > 0 => get_string('next:marking', 'local_doublemark', $counts['marking']),
            default => get_string('next:done', 'local_doublemark'),
        };
        return [
            'tiles' => $tiles,
            'rows' => array_values($list),
            'hasrows' => !empty($list),
            'emptymessage' => get_string($rows ? 'emptygroup' : 'nostudents', 'local_doublemark'),
            'rulesummary' => get_string(
                'rulesummary:' . $this->dm->rule(),
                'local_doublemark',
                display::grade($this->dm->tolerance())
            ),
            'next' => $next,
            'releasecount' => $releasecount,
            'canrelease' => $releasecount > 0,
            'releaselabel' => get_string('release', 'local_doublemark', $releasecount),
            'releaseurl' => (new \moodle_url('/local/doublemark/manage.php'))->out(false),
            'cmid' => $this->dm->cmid,
            'sesskey' => sesskey(),
        ];
    }
}
