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
 * The lead's page for one assignment: overview, markers and settings.
 *
 * @package    local_doublemark
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_doublemark\local\double_marking;

$cmid = required_param('cmid', PARAM_INT);
$tab = optional_param('tab', 'overview', PARAM_ALPHA);
$filter = optional_param('filter', 'all', PARAM_ALPHA);
$action = optional_param('action', '', PARAM_ALPHA);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'assign');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('local/doublemark:manage', $context);

$dm = double_marking::for_cmid($cmid);
if (!$dm->is_enabled() && $tab !== 'settings') {
    $tab = 'settings';
}
$url = new moodle_url('/local/doublemark/manage.php', ['cmid' => $cmid, 'tab' => $tab]);
$PAGE->set_url($url);
$PAGE->set_title(get_string('pluginname', 'local_doublemark') . ': ' . format_string($cm->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->disable();

if ($action !== '' && confirm_sesskey()) {
    $back = new moodle_url('/local/doublemark/manage.php', ['cmid' => $cmid, 'tab' => $tab]);
    if ($action === 'release') {
        $count = $dm->release();
        if ($dm->skipped) {
            redirect($back, get_string('released', 'local_doublemark', $count) . ' ' .
                get_string('releaseskipped', 'local_doublemark', $dm->skipped), null, \core\output\notification::NOTIFY_WARNING);
        }
        redirect($back, get_string('released', 'local_doublemark', $count), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    if ($action === 'shareout') {
        $count = $dm->share_out(optional_param_array('markers', [], PARAM_INT));
        redirect($back, get_string('sharedout', 'local_doublemark', $count), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    if ($action === 'allocate') {
        $first = optional_param_array('marker1', [], PARAM_INT);
        $second = optional_param_array('marker2', [], PARAM_INT);
        $rows = $dm->rows();
        $saved = 0;
        $problems = [];
        foreach ($first as $userid => $marker1) {
            $marker2 = (int) ($second[$userid] ?? 0);
            $row = $rows[$userid] ?? null;
            if (!$row || ($row->marker1 === (int) $marker1 && $row->marker2 === $marker2) || (!$marker1 && !$marker2)) {
                continue;
            }
            try {
                $dm->allocate((int) $userid, (int) $marker1, $marker2);
                $saved++;
            } catch (moodle_exception $e) {
                $problems[] = $row->name . ': ' . $e->getMessage();
            }
        }
        if ($problems) {
            redirect($back, implode('<br>', array_map('s', $problems)), null, \core\output\notification::NOTIFY_ERROR);
        }
        redirect($back, get_string('markerssaved', 'local_doublemark', $saved), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

$settings = null;
$reason = $dm->unavailable_reason();
if ($tab === 'settings' && !$reason) {
    $settings = new \local_doublemark\form\settings_form($url, ['maxgrade' => $dm->max_grade()]);
    $settings->set_data(['cmid' => $cmid, 'enabled' => (int) $dm->is_enabled(), 'tolerance' => $dm->tolerance(),
        'agreerule' => $dm->rule()]);
    if ($data = $settings->get_data()) {
        if ($data->enabled) {
            $dm->configure((float) $data->tolerance, $data->agreerule);
            $next = new moodle_url('/local/doublemark/manage.php', ['cmid' => $cmid, 'tab' => 'markers']);
        } else {
            $dm->disable();
            $next = $url;
        }
        redirect($next, get_string('settingssaved', 'local_doublemark'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_doublemark'));
$tabs = [];
if ($dm->is_enabled()) {
    foreach (['overview', 'markers'] as $name) {
        $tabs[] = new tabobject(
            $name,
            new moodle_url('/local/doublemark/manage.php', ['cmid' => $cmid, 'tab' => $name]),
            get_string('tab:' . $name, 'local_doublemark')
        );
    }
}
$tabs[] = new tabobject(
    'settings',
    new moodle_url('/local/doublemark/manage.php', ['cmid' => $cmid, 'tab' => 'settings']),
    get_string('tab:settings', 'local_doublemark')
);
echo $OUTPUT->tabtree($tabs, $tab);

if ($tab === 'overview') {
    $overview = new \local_doublemark\output\overview($dm, $filter);
    echo $OUTPUT->render_from_template('local_doublemark/overview', $overview->export_for_template($OUTPUT));
} else if ($tab === 'markers') {
    $allocation = new \local_doublemark\output\allocation($dm);
    echo $OUTPUT->render_from_template('local_doublemark/allocation', $allocation->export_for_template($OUTPUT));
} else if ($reason) {
    echo $OUTPUT->notification(get_string($reason, 'local_doublemark'), \core\output\notification::NOTIFY_INFO, false);
} else {
    echo html_writer::tag('p', get_string('intro', 'local_doublemark'), ['class' => 'local-doublemark-intro']);
    $settings->display();
}
echo $OUTPUT->footer();
