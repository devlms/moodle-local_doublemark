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
 * Navigation.
 *
 * @package    local_doublemark
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add "Double marking" to an assignment's menu: the lead's page for managers, the marking list for markers.
 *
 * @param settings_navigation $nav
 * @param context $context
 */
function local_doublemark_extend_settings_navigation(settings_navigation $nav, context $context) {
    global $DB, $PAGE;
    if ($context->contextlevel != CONTEXT_MODULE || !$PAGE->cm || $PAGE->cm->modname !== 'assign') {
        return;
    }
    $node = $nav->find('modulesettings', navigation_node::TYPE_SETTING);
    if (!$node) {
        return;
    }
    $cmid = (int) $PAGE->cm->id;
    if (has_capability('local/doublemark:manage', $context)) {
        $url = new moodle_url('/local/doublemark/manage.php', ['cmid' => $cmid]);
    } else if (
        has_capability('local/doublemark:mark', $context)
            && $DB->record_exists('local_doublemark_config', ['cmid' => $cmid])
    ) {
        $url = new moodle_url('/local/doublemark/mark.php', ['cmid' => $cmid]);
    } else {
        return;
    }
    $node->add(
        get_string('pluginname', 'local_doublemark'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'local_doublemark',
        new pix_icon('i/grades', '')
    );
}
