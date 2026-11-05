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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * A marker's independent mark and private comment.
 *
 * Custom data: maxgrade (float).
 *
 * @package    local_doublemark
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mark_form extends \moodleform {
    /**
     * Fields.
     */
    protected function definition() {
        $mform = $this->_form;
        foreach (['cmid', 'userid'] as $name) {
            $mform->addElement('hidden', $name);
            $mform->setType($name, PARAM_INT);
        }
        $mform->addElement('float', 'grade', get_string('yourmark', 'local_doublemark', $this->_customdata['maxgrade']));
        $mform->addRule('grade', null, 'required', null, 'client');
        $mform->addElement('textarea', 'comment', get_string('yourcomment', 'local_doublemark'), ['rows' => 6]);
        $mform->setType('comment', PARAM_TEXT);
        $mform->addHelpButton('comment', 'yourcomment', 'local_doublemark');
        $this->add_action_buttons(true, get_string('savemark', 'local_doublemark'));
    }

    /**
     * Grade within range.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $max = (float) $this->_customdata['maxgrade'];
        if ($data['grade'] < 0 || $data['grade'] > $max) {
            $errors['grade'] = get_string('error:range', 'local_doublemark', format_float($max, -1));
        }
        return $errors;
    }
}
