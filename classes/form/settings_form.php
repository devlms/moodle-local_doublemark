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

namespace local_doublemark\form;

use local_doublemark\local\reconciler;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Turn double marking on or off and choose how two marks become one.
 *
 * Custom data: maxgrade (float).
 *
 * @package    local_doublemark
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class settings_form extends \moodleform {
    /**
     * Fields.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);
        $mform->addElement('hidden', 'tab', 'settings');
        $mform->setType('tab', PARAM_ALPHA);

        $mform->addElement(
            'advcheckbox',
            'enabled',
            get_string('enabled', 'local_doublemark'),
            get_string('enabled_desc', 'local_doublemark')
        );
        $mform->addElement('float', 'tolerance', get_string('tolerance', 'local_doublemark'));
        $mform->setDefault('tolerance', 5);
        $mform->addHelpButton('tolerance', 'tolerance', 'local_doublemark');
        $mform->hideIf('tolerance', 'enabled');
        $rules = [];
        foreach (reconciler::RULES as $rule) {
            $rules[$rule] = get_string('rule:' . $rule, 'local_doublemark');
        }
        $mform->addElement('select', 'agreerule', get_string('agreerule', 'local_doublemark'), $rules);
        $mform->addHelpButton('agreerule', 'agreerule', 'local_doublemark');
        $mform->hideIf('agreerule', 'enabled');
        $this->add_action_buttons(false);
    }

    /**
     * Tolerance between 0 and the highest grade.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!empty($data['enabled']) && ($data['tolerance'] < 0 || $data['tolerance'] > $this->_customdata['maxgrade'])) {
            $errors['tolerance'] = get_string('error:badtolerance', 'local_doublemark');
        }
        return $errors;
    }
}
