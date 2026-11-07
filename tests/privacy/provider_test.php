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

namespace local_doublemark\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use local_doublemark\local\double_marking;
use local_doublemark\local\reconciler;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_doublemark\privacy\provider::class)]
/**
 * Tests for the privacy provider.
 *
 * @package    local_doublemark
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_doublemark\privacy\provider
 */
final class provider_test extends provider_testcase {
    /** @var float*/
    private double_marking $dm;
    /** @var \context_module */
    private \context_module $context;
    /** @var \stdClass[] */
    private array $u = [];

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $this->u['lead'] = $gen->create_and_enrol($course, 'editingteacher');
        $this->u['m1'] = $gen->create_and_enrol($course, 'teacher');
        $this->u['m2'] = $gen->create_and_enrol($course, 'teacher');
        $this->u['s'] = $gen->create_and_enrol($course, 'student');
        $this->u['other'] = $gen->create_and_enrol($course, 'student');
        $assign = $gen->create_module('assign', ['course' => $course->id, 'grade' => 100]);
        $this->context = \context_module::instance($assign->cmid);
        $this->setUser($this->u['lead']);
        $this->dm = double_marking::for_cmid((int) $assign->cmid);
        $this->dm->configure(5, reconciler::AVERAGE);
        foreach (['s', 'other'] as $key) {
            $this->dm->allocate((int) $this->u[$key]->id, (int) $this->u['m1']->id, (int) $this->u['m2']->id);
        }
        $this->setUser($this->u['m1']);
        $this->dm->record_mark((int) $this->u['s']->id, 40, 'Thin on sources.');
        $this->dm->record_mark((int) $this->u['other']->id, 70, '');
        $this->setUser($this->u['m2']);
        $this->dm->record_mark((int) $this->u['s']->id, 60, 'Better than it looks.');
        $this->setUser($this->u['lead']);
        $this->dm->agree((int) $this->u['s']->id, 52, 'Third read.', 'Use more sources.');
    }

    public function test_contexts_for_students_markers_and_leads(): void {
        foreach (['s', 'm1', 'm2', 'lead'] as $key) {
            $ids = provider::get_contexts_for_userid((int) $this->u[$key]->id)->get_contextids();
            $this->assertSame([(int) $this->context->id], array_map('intval', $ids), $key);
        }
        $nobody = $this->getDataGenerator()->create_user();
        $this->assertSame([], provider::get_contexts_for_userid((int) $nobody->id)->get_contextids());
    }

    public function test_users_in_context(): void {
        $list = new userlist($this->context, 'local_doublemark');
        provider::get_users_in_context($list);
        $ids = array_map('intval', $list->get_userids());
        sort($ids);
        $expected = array_map(fn($k) => (int) $this->u[$k]->id, ['lead', 'm1', 'm2', 's', 'other']);
        sort($expected);
        $this->assertSame($expected, $ids);
    }

    public function test_export_for_the_student(): void {
        $this->export_context_data_for_user((int) $this->u['s']->id, $this->context, 'local_doublemark');
        $data = writer::with_context($this->context)->get_data([get_string('pluginname', 'local_doublemark')]);
        $this->assertCount(2, $data->marks);
        $this->assertSame('Thin on sources.', $data->marks[0]['comment']);
        $this->assertEquals(52, $data->agreed['grade']);
        $this->assertSame('Use more sources.', $data->agreed['feedback']);
    }

    public function test_export_for_a_marker_has_only_their_marks(): void {
        $this->export_context_data_for_user((int) $this->u['m2']->id, $this->context, 'local_doublemark');
        $data = writer::with_context($this->context)->get_data([get_string('pluginname', 'local_doublemark')]);
        $this->assertCount(1, $data->marksgiven);
        $this->assertEquals(60, $data->marksgiven[0]['grade']);
    }

    public function test_delete_for_the_student_removes_their_rows_only(): void {
        global $DB;
        $list = new approved_contextlist($this->u['s'], 'local_doublemark', [$this->context->id]);
        provider::delete_data_for_user($list);
        $this->assertFalse($DB->record_exists('local_doublemark_mark', ['userid' => $this->u['s']->id]));
        $this->assertFalse($DB->record_exists('local_doublemark_agreed', ['userid' => $this->u['s']->id]));
        $this->assertFalse($DB->record_exists('local_doublemark_alloc', ['userid' => $this->u['s']->id]));
        $this->assertTrue($DB->record_exists('local_doublemark_mark', ['userid' => $this->u['other']->id]));
    }

    public function test_delete_for_a_marker_keeps_the_marks_but_not_who_gave_them(): void {
        global $DB;
        $list = new approved_contextlist($this->u['m1'], 'local_doublemark', [$this->context->id]);
        provider::delete_data_for_user($list);
        $this->assertFalse($DB->record_exists('local_doublemark_mark', ['markerid' => $this->u['m1']->id]));
        $this->assertFalse($DB->record_exists('local_doublemark_alloc', ['marker1' => $this->u['m1']->id]));
        // The student's marks stay: deleting them would change an agreed grade.
        $this->assertSame(3, $DB->count_records('local_doublemark_mark'));
    }

    public function test_delete_for_users_and_whole_context(): void {
        global $DB;
        provider::delete_data_for_users(new approved_userlist(
            $this->context,
            'local_doublemark',
            [(int) $this->u['other']->id, (int) $this->u['lead']->id]
        ));
        $this->assertFalse($DB->record_exists('local_doublemark_mark', ['userid' => $this->u['other']->id]));
        $this->assertSame(0, (int) $DB->get_field('local_doublemark_agreed', 'agreedby', ['userid' => $this->u['s']->id]));
        $this->assertTrue($DB->record_exists('local_doublemark_mark', ['userid' => $this->u['s']->id]));

        provider::delete_data_for_all_users_in_context($this->context);
        foreach (['config', 'alloc', 'mark', 'agreed'] as $table) {
            $this->assertSame(0, $DB->count_records("local_doublemark_$table"), $table);
        }
    }
}
