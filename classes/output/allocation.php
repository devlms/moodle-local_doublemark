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

/**
 * Who marks whom: a first and second marker per student, and the markers to share students out to.
 *
 * @package    local_doublemark
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class allocation implements renderable, templatable {
    /**
     * Create the view.
     *
     * @param \local_doublemark\local\double_marking $dm
     */
    public function __construct(
        /** @var \local_doublemark\local\double_marking Double marking of the assignment. */
        private readonly \local_doublemark\local\double_marking $dm,
    ) {
    }

    /**
     * Data for local_doublemark/allocation.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $markers = $this->dm->markers();
        $options = fn(int $chosen) => array_values(array_map(fn($m) => [
            'id' => (int) $m->id,
            'name' => fullname($m),
            'selected' => (int) $m->id === $chosen,
        ], $markers));
        $rows = [];
        $unallocated = 0;
        foreach ($this->dm->rows() as $row) {
            $missing = !$row->marker1 || !$row->marker2;
            $unallocated += $missing ? 1 : 0;
            $rows[] = [
                'userid' => $row->userid,
                'name' => $row->name,
                'label1' => get_string('firstmarkerfor', 'local_doublemark', $row->name),
                'label2' => get_string('secondmarkerfor', 'local_doublemark', $row->name),
                'picture' => $row->anonymous ? '' : $output->user_picture($row->user, ['size' => 30, 'link' => false,
                    'alttext' => false]),
                'options1' => $options($row->marker1),
                'options2' => $options($row->marker2),
                'missing' => $missing,
                // Once a mark is in, changing who marks would orphan it.
                'locked' => $row->mark1 !== null || $row->mark2 !== null,
            ];
        }
        return [
            'rows' => $rows,
            'hasrows' => !empty($rows),
            'unallocated' => $unallocated,
            'markers' => array_values(array_map(fn($m) => ['id' => (int) $m->id, 'name' => fullname($m)], $markers)),
            'enoughmarkers' => count($markers) >= 2,
            'cmid' => $this->dm->cmid,
            'sesskey' => sesskey(),
            'actionurl' => (new \moodle_url('/local/doublemark/manage.php'))->out(false),
        ];
    }
}
