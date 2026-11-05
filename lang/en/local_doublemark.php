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
 * Strings for local_doublemark.
 *
 * @package    local_doublemark
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['doublemark:manage'] = 'Set up double marking, agree and release marks';
$string['doublemark:mark'] = 'Mark as one of two independent markers';
$string['error:badtolerance'] = 'The tolerance must be between 0 and the highest grade of the assignment.';
$string['error:fewmarkers'] = 'Choose at least two markers.';
$string['error:notallocated'] = 'You are not one of the two markers of this student.';
$string['error:notmarked'] = 'Both markers have to mark before a mark can be agreed.';
$string['error:notmarker'] = 'That person cannot mark this assignment.';
$string['error:notstudent'] = 'That person is not a student of this assignment.';
$string['error:range'] = 'Enter a mark between 0 and {$a}.';
$string['error:released'] = 'This mark is already in the assignment and can no longer change.';
$string['error:samemarker'] = 'The first and second marker must be different people.';
$string['event:markagreed'] = 'Agreed mark set';
$string['event:markrecorded'] = 'Independent mark recorded';
$string['event:marksreleased'] = 'Agreed marks released';
$string['pluginname'] = 'Double marking';
$string['unavailable:advancedgrading'] = 'This assignment uses a rubric or marking guide. Double marking works with simple point grades for now.';
$string['unavailable:scale'] = 'This assignment is graded with a scale. Double marking needs a point grade.';
$string['unavailable:team'] = 'This assignment takes group submissions. Double marking works with individual submissions for now.';
