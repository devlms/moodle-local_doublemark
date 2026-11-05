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

/**
 * Marking: a marker's list of students, or one student's submission with the marker's own mark.
 *
 * @package    local_doublemark
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_doublemark\local\display;
use local_doublemark\local\double_marking;

$cmid = required_param('cmid', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'assign');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('local/doublemark:mark', $context);

$dm = double_marking::for_cmid($cmid);
if (!$dm->is_enabled()) {
    throw new moodle_exception('notenabled', 'local_doublemark');
}
$listurl = new moodle_url('/local/doublemark/mark.php', ['cmid' => $cmid]);
$PAGE->set_url($userid ? new moodle_url($listurl, ['userid' => $userid]) : $listurl);
$PAGE->set_title(get_string('yourmarking', 'local_doublemark') . ': ' . format_string($cm->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->disable();

if (!$userid) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('yourmarking', 'local_doublemark'));
    $list = new \local_doublemark\output\marker_list($dm, (int) $USER->id);
    echo $OUTPUT->render_from_template('local_doublemark/marker_list', $list->export_for_template($OUTPUT));
    echo $OUTPUT->footer();
    die();
}

$mine = $dm->for_marker((int) $USER->id)[$userid] ?? null;
if (!$mine) {
    throw new moodle_exception('error:notallocated', 'local_doublemark');
}
$row = $mine->row;
$locked = $row->status === double_marking::RELEASED;
$PAGE->navbar->add(get_string('yourmarking', 'local_doublemark'), $listurl);
$PAGE->navbar->add($row->name);

$form = null;
if (!$locked) {
    $form = new \local_doublemark\form\mark_form(
        $PAGE->url,
        ['maxgrade' => display::grade($dm->max_grade())],
        'post',
        '',
        ['class' => 'full-width-labels']
    );
    $form->set_data(['cmid' => $cmid, 'userid' => $userid, 'grade' => $mine->own?->grade, 'comment' => $mine->own?->comment]);
    if ($form->is_cancelled()) {
        redirect($listurl);
    }
    if ($data = $form->get_data()) {
        $dm->record_mark($userid, (float) $data->grade, (string) $data->comment);
        redirect(
            $listurl,
            get_string('marksaved', 'local_doublemark', $row->name),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading($row->name);
$role = get_string($mine->position === 1 ? 'firstmarker' : 'secondmarker', 'local_doublemark');
$intro = get_string('youaremarker', 'local_doublemark', core_text::strtolower($role));
echo html_writer::tag('p', $intro, ['class' => 'local-doublemark-summary']);
echo html_writer::start_div('local-doublemark-workspace');
echo html_writer::start_tag('section', ['class' => 'local-doublemark-submission',
    'aria-label' => get_string('submission', 'local_doublemark')]);
echo $OUTPUT->heading(get_string('submission', 'local_doublemark'), 3);
echo display::submission($dm, $userid);
echo html_writer::end_tag('section');
echo html_writer::start_tag('section', ['class' => 'local-doublemark-side',
    'aria-label' => get_string('yourmarkshort', 'local_doublemark')]);
if ($mine->other) {
    echo $OUTPUT->heading(get_string('bothmarks', 'local_doublemark'), 3);
    echo $OUTPUT->render_from_template('local_doublemark/compare', display::compare($row, $dm->markers()));
} else {
    echo html_writer::tag('p', get_string('hiddenexplain', 'local_doublemark'), ['class' => 'local-doublemark-hint']);
}
if ($form) {
    echo $OUTPUT->heading(get_string('yourmarkshort', 'local_doublemark'), 3);
    $form->display();
} else {
    echo $OUTPUT->notification(get_string('error:released', 'local_doublemark'), 'info', false);
}
echo html_writer::end_tag('section');
echo html_writer::end_div();
echo $OUTPUT->footer();
