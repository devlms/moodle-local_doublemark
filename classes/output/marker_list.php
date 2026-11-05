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

/**
 * One marker's students: their own mark, and the other marker's once both are in.
 *
 * @package    local_doublemark
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class marker_list implements renderable, templatable {
    /**
     * Create the list.
     *
     * @param double_marking $dm
     * @param int $markerid
     */
    public function __construct(
        /** @var float*/
        private readonly double_marking $dm,
        /** @var int The marker. */
        private readonly int $markerid,
    ) {
    }

    /**
     * Data for local_doublemark/marker_list.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $rows = [];
        $todo = 0;
        foreach ($this->dm->for_marker($this->markerid) as $userid => $item) {
            $row = $item->row;
            if ($row->status === double_marking::RELEASED) {
                $label = display::status_label($row->status);
                $badge = display::badge($row->status);
            } else if (!$item->own) {
                $label = get_string('mine:todo', 'local_doublemark');
                $badge = 'badge bg-warning text-dark';
                $todo++;
            } else if (!$item->other) {
                $label = get_string('mine:waiting', 'local_doublemark');
                $badge = 'badge bg-light text-dark border';
            } else {
                $label = display::status_label($row->status);
                $badge = display::badge($row->status);
            }
            $rows[] = [
                'userid' => $userid,
                'name' => $row->name,
                'picture' => $row->anonymous ? '' : $output->user_picture($row->user, ['size' => 30, 'link' => false,
                    'alttext' => false]),
                'role' => get_string($item->position === 1 ? 'firstmarker' : 'secondmarker', 'local_doublemark'),
                'own' => display::grade($item->own?->grade),
                'other' => display::grade($item->other?->grade),
                'statuslabel' => $label,
                'badge' => $badge,
                'locked' => $row->status === double_marking::RELEASED,
                'markurl' => (new \moodle_url('/local/doublemark/mark.php', ['cmid' => $this->dm->cmid, 'userid' => $userid]))
                    ->out(false),
            ];
        }
        return [
            'rows' => $rows,
            'hasrows' => !empty($rows),
            'todo' => $todo,
            'summary' => get_string($todo ? 'mine:summary' : 'mine:alldone', 'local_doublemark', $todo),
            'maxgrade' => display::grade($this->dm->max_grade()),
        ];
    }
}
