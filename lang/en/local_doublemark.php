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

$string['actions'] = 'Actions';
$string['agreed'] = 'Agreed';
$string['agreedmark'] = 'Agreed mark (out of {$a})';
$string['agreedsaved'] = 'Agreed mark saved for {$a}. Release it from the overview when you are ready.';
$string['agreefor'] = 'Agree the mark for {$a}';
$string['agreerule'] = 'When the marks are close';
$string['agreerule_help'] = 'How two marks within the tolerance become the agreed mark. Choose "The lead always decides" to agree every mark yourself.';
$string['bothmarks'] = 'Both marks';
$string['changemark'] = 'Change';
$string['choose'] = 'Choose...';
$string['decide'] = 'Decide';
$string['doublemark:manage'] = 'Set up double marking, agree and release marks';
$string['doublemark:mark'] = 'Mark as one of two independent markers';
$string['emptygroup'] = 'Nobody in this group.';
$string['enabled'] = 'Double marking';
$string['enabled_desc'] = 'Two markers mark each submission on their own. Neither sees the other\'s mark until both are in.';
$string['error:badtolerance'] = 'The tolerance must be between 0 and the highest grade of the assignment.';
$string['error:fewmarkers'] = 'Choose at least two markers.';
$string['error:markerlocked'] = 'A marker who has already marked this student cannot be replaced.';
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
$string['feedback'] = 'Feedback for the student';
$string['feedback_help'] = 'Sent with the grade when you release it, through the assignment\'s feedback comments. Leave it empty to keep any feedback already there.';
$string['feedbackoff'] = 'Feedback comments are off in this assignment, so feedback cannot reach students. Turn them on in the assignment settings to send feedback with the mark.';
$string['filter'] = 'Show students';
$string['firstmarker'] = 'First marker';
$string['firstmarkerfor'] = 'First marker for {$a}';
$string['gap'] = 'Gap';
$string['gradedoutside'] = 'A grade was entered in the assignment directly. Releasing replaces it.';
$string['group:marking'] = 'Being marked';
$string['group:ready'] = 'Ready to release';
$string['group:reconcile'] = 'Marks differ';
$string['group:released'] = 'Released';
$string['hiddenexplain'] = 'The other marker\'s mark stays hidden until you have both marked. Mark on your own judgement.';
$string['hiddenmark'] = 'Hidden until both are in';
$string['intro'] = 'Two markers mark each submission on their own and cannot see each other\'s mark until both are in. Close marks agree by the rule you choose. Marks that differ come to you. Nothing reaches the assignment until you release it.';
$string['lockedhelp'] = 'Markers cannot change once one of them has marked.';
$string['markerssaved'] = 'Markers saved for students: {$a}.';
$string['marksaved'] = 'Your mark for {$a} is saved.';
$string['markthis'] = 'Mark';
$string['mine:alldone'] = 'You have marked everyone allocated to you.';
$string['mine:none'] = 'Nobody is allocated to you for this assignment.';
$string['mine:summary'] = '{$a} left to mark';
$string['mine:todo'] = 'To mark';
$string['mine:waiting'] = 'Waiting for the other marker';
$string['needmarkers'] = 'Double marking needs at least two people who can mark this assignment. Give a second teacher the Non-editing teacher role in the course.';
$string['nostudents'] = 'This assignment has no students yet.';
$string['nosubmission'] = 'Nothing submitted yet.';
$string['note'] = 'Note for staff';
$string['note_help'] = 'Why you chose this mark. Markers and other leads see it; the student does not.';
$string['notenabled'] = 'Double marking is not turned on for this assignment.';
$string['notyet'] = 'Not marked yet';
$string['othermark'] = 'Other mark';
$string['pluginname'] = 'Double marking';
$string['privacy:metadata:agreed'] = 'The agreed mark of each student and when it went to the assignment.';
$string['privacy:metadata:agreed:agreedby'] = 'The lead who set the agreed mark.';
$string['privacy:metadata:agreed:feedback'] = 'Feedback for the student.';
$string['privacy:metadata:agreed:grade'] = 'The agreed mark.';
$string['privacy:metadata:agreed:note'] = 'The lead\'s note for staff about the mark.';
$string['privacy:metadata:agreed:releasedby'] = 'The person who released the mark to the assignment.';
$string['privacy:metadata:agreed:source'] = 'Whether the agreed mark came from the agreement rule or from the lead.';
$string['privacy:metadata:agreed:timeagreed'] = 'When the mark was agreed.';
$string['privacy:metadata:agreed:timereleased'] = 'When the agreed mark was released to the assignment.';
$string['privacy:metadata:agreed:userid'] = 'The student.';
$string['privacy:metadata:alloc'] = 'The two markers of each student.';
$string['privacy:metadata:alloc:marker1'] = 'The first marker.';
$string['privacy:metadata:alloc:marker2'] = 'The second marker.';
$string['privacy:metadata:alloc:userid'] = 'The student.';
$string['privacy:metadata:config'] = 'Double marking settings of an assignment.';
$string['privacy:metadata:config:usermodified'] = 'Who last changed the settings.';
$string['privacy:metadata:mark'] = 'Each marker\'s independent mark and private comment.';
$string['privacy:metadata:mark:comment'] = 'The marker\'s private comment.';
$string['privacy:metadata:mark:grade'] = 'The mark.';
$string['privacy:metadata:mark:markerid'] = 'The marker.';
$string['privacy:metadata:mark:position'] = 'Whether this is the first or the second mark.';
$string['privacy:metadata:mark:timemodified'] = 'When the mark was last changed.';
$string['privacy:metadata:mark:userid'] = 'The student.';
$string['release'] = 'Release agreed marks ({$a})';
$string['released'] = 'Agreed marks released to the assignment: {$a}.';
$string['releasehelp'] = 'Agreed marks stay here until you release them. Releasing writes them to the assignment and the gradebook, with your feedback, and locks them.';
$string['releaseskipped'] = 'Not released, because the assignment would not take the grade (locked or overridden in the gradebook, or locked by the marking workflow): {$a}.';
$string['review'] = 'Review';
$string['rule:average'] = 'Average of the two marks';
$string['rule:first'] = 'First marker\'s mark';
$string['rule:higher'] = 'Higher of the two marks';
$string['rule:lead'] = 'The lead always decides';
$string['rulesummary:average'] = 'Marks within {$a} points of each other agree on their average. Bigger gaps come to you.';
$string['rulesummary:first'] = 'Marks within {$a} points of each other agree on the first marker\'s mark. Bigger gaps come to you.';
$string['rulesummary:higher'] = 'Marks within {$a} points of each other agree on the higher mark. Bigger gaps come to you.';
$string['rulesummary:lead'] = 'You agree every mark yourself once both markers are done.';
$string['saveagreed'] = 'Save agreed mark';
$string['savemark'] = 'Save my mark';
$string['savemarkers'] = 'Save markers';
$string['secondmarker'] = 'Second marker';
$string['secondmarkerfor'] = 'Second marker for {$a}';
$string['settingssaved'] = 'Settings saved.';
$string['sharedout'] = 'Students given two markers: {$a}.';
$string['shareout'] = 'Share out students without markers';
$string['shareout_help'] = 'Each student gets two different markers. Students go to whoever has the fewest so far, so the work stays even.';
$string['shareoutbutton'] = 'Share out';
$string['status'] = 'Status';
$string['status:agreed'] = 'Agreed';
$string['status:marking'] = 'Being marked';
$string['status:reconcile'] = 'Marks differ';
$string['status:released'] = 'Released';
$string['status:resolved'] = 'Decided by lead';
$string['status:unallocated'] = 'No markers yet';
$string['student'] = 'Student';
$string['submission'] = 'Submission';
$string['submittedon'] = 'Submitted {$a}';
$string['tab:markers'] = 'Markers';
$string['tab:overview'] = 'Overview';
$string['tab:settings'] = 'Settings';
$string['tolerance'] = 'Tolerance';
$string['tolerance_help'] = 'The largest gap between the two marks, in points, that still counts as agreement. A bigger gap goes to you to decide.';
$string['unavailable:advancedgrading'] = 'This assignment uses a rubric or marking guide. Double marking works with simple point grades for now.';
$string['unavailable:multiplemarking'] = 'This assignment uses Moodle\'s own multiple marking. Use one or the other, not both.';
$string['unavailable:scale'] = 'This assignment is graded with a scale. Double marking needs a point grade.';
$string['unavailable:team'] = 'This assignment takes group submissions. Double marking works with individual submissions for now.';
$string['view'] = 'View';
$string['youare'] = 'You are';
$string['youaremarker'] = 'You are the {$a} for this student.';
$string['yourcomment'] = 'Your comment';
$string['yourcomment_help'] = 'Private. The other marker sees it once both marks are in, and the lead sees it when agreeing the mark. Students never see it.';
$string['yourmark'] = 'Your mark (out of {$a})';
$string['yourmarking'] = 'Your marking';
$string['yourmarkshort'] = 'Your mark';
