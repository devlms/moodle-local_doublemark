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
 * The lead agrees the mark of one student: both marks, both comments, the submission.
 *
 * @package    local_doublemark
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_doublemark\local\display;
use local_doublemark\local\double_marking;
use local_doublemark\local\reconciler;

$cmid = required_param('cmid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'assign');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('local/doublemark:manage', $context);

$dm = double_marking::for_cmid($cmid);
$row = $dm->rows()[$userid] ?? null;
if (!$row || !in_array($row->status, [reconciler::AGREED, reconciler::RECONCILE, double_marking::RESOLVED], true)) {
    throw new moodle_exception('error:notmarked', 'local_doublemark');
}
$back = new moodle_url('/local/doublemark/manage.php', ['cmid' => $cmid]);
$PAGE->set_url(new moodle_url('/local/doublemark/agree.php', ['cmid' => $cmid, 'userid' => $userid]));
$PAGE->set_title(get_string('agreefor', 'local_doublemark', $row->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->disable();
$PAGE->navbar->add(get_string('pluginname', 'local_doublemark'), $back);
$PAGE->navbar->add($row->name);

$form = new \local_doublemark\form\agree_form(
    $PAGE->url,
    ['maxgrade' => display::grade($dm->max_grade())],
    'post',
    '',
    ['class' => 'full-width-labels']
);
$suggested = $row->agreed ?? round(($row->mark1->grade + $row->mark2->grade) / 2, 2);
$form->set_data(['cmid' => $cmid, 'userid' => $userid, 'grade' => $suggested, 'feedback' => $row->feedback,
    'note' => $row->note]);
if ($form->is_cancelled()) {
    redirect($back);
}
if ($data = $form->get_data()) {
    $dm->agree($userid, (float) $data->grade, (string) $data->note, (string) $data->feedback);
    redirect($back, get_string('agreedsaved', 'local_doublemark', $row->name), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('agreefor', 'local_doublemark', $row->name));
echo html_writer::tag(
    'p',
    html_writer::span(display::status_label($row->status), display::badge($row->status)) . ' ' .
    get_string('rulesummary:' . $dm->rule(), 'local_doublemark', display::grade($dm->tolerance())),
    ['class' => 'local-doublemark-summary']
);
echo html_writer::start_div('local-doublemark-workspace');
echo html_writer::start_tag('section', ['class' => 'local-doublemark-submission',
    'aria-label' => get_string('submission', 'local_doublemark')]);
echo $OUTPUT->heading(get_string('submission', 'local_doublemark'), 3);
echo display::submission($dm, $userid);
echo html_writer::end_tag('section');
echo html_writer::start_tag('section', ['class' => 'local-doublemark-side',
    'aria-label' => get_string('agreed', 'local_doublemark')]);
echo $OUTPUT->heading(get_string('bothmarks', 'local_doublemark'), 3);
echo $OUTPUT->render_from_template('local_doublemark/compare', display::compare($row, $dm->markers()));
echo $OUTPUT->heading(get_string('agreed', 'local_doublemark'), 3);
$form->display();
echo html_writer::end_tag('section');
echo html_writer::end_div();
echo $OUTPUT->footer();
