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

use local_doublemark\local\double_marking;
use local_doublemark\local\reconciler;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_doublemark\output\overview::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_doublemark\output\marker_list::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_doublemark\output\allocation::class)]
/**
 * Tests for the data behind the lead's overview and the marker's list.
 *
 * @package    local_doublemark
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_doublemark\output\overview
 * @covers     \local_doublemark\output\marker_list
 * @covers     \local_doublemark\output\allocation
 */
final class output_test extends \advanced_testcase {
    /** @var float*/
    private double_marking $dm;
    /** @var \stdClass[] */
    private array $u = [];

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $this->u['lead'] = $gen->create_and_enrol($course, 'editingteacher', ['firstname' => 'Lea', 'lastname' => 'Lead']);
        $this->u['m1'] = $gen->create_and_enrol($course, 'teacher', ['firstname' => 'Mia', 'lastname' => 'One']);
        $this->u['m2'] = $gen->create_and_enrol($course, 'teacher', ['firstname' => 'Max', 'lastname' => 'Two']);
        foreach (['a' => 'Ann', 'b' => 'Ben', 'c' => 'Cat', 'd' => 'Dan'] as $key => $first) {
            $this->u[$key] = $gen->create_and_enrol($course, 'student', ['firstname' => $first, 'lastname' => 'Student']);
        }
        $assign = $gen->create_module('assign', ['course' => $course->id, 'grade' => 100]);
        $this->setUser($this->u['lead']);
        $this->dm = double_marking::for_cmid((int) $assign->cmid);
        $this->dm->configure(5, reconciler::AVERAGE);
        foreach (['a', 'b', 'c'] as $key) {
            $this->dm->allocate((int) $this->u[$key]->id, (int) $this->u['m1']->id, (int) $this->u['m2']->id);
        }
        // Ann agrees (60, 62), Ben differs (40, 70), Cat has one mark, Dan has no markers.
        $this->mark('m1', ['a' => 60, 'b' => 40, 'c' => 55]);
        $this->mark('m2', ['a' => 62, 'b' => 70]);
        $this->setUser($this->u['lead']);
    }

    /**
     * Record marks as one marker.
     *
     * @param string $marker
     * @param array $marks student key => grade
     */
    private function mark(string $marker, array $marks): void {
        $this->setUser($this->u[$marker]);
        foreach ($marks as $key => $grade) {
            $this->dm->record_mark((int) $this->u[$key]->id, $grade, "note for {$key}");
        }
    }

    /**
     * Export a renderable as plain arrays.
     *
     * @param \core\output\templatable $widget
     * @return array
     */
    private function export(\core\output\templatable $widget): array {
        global $PAGE;
        return json_decode(json_encode($widget->export_for_template($PAGE->get_renderer('core'))), true);
    }

    public function test_overview_counts_and_rows(): void {
        $data = $this->export(new overview($this->dm, 'all'));
        $tiles = array_column($data['tiles'], 'count', 'name');
        $this->assertSame(['marking' => 2, 'reconcile' => 1, 'ready' => 1, 'released' => 0], $tiles);

        $rows = array_column($data['rows'], null, 'name');
        $ann = $rows['Ann Student'];
        $this->assertSame('Mia One', $ann['marker1']);
        $this->assertSame('60', $ann['mark1']);
        $this->assertSame('62', $ann['mark2']);
        $this->assertSame('2', $ann['gap']);
        $this->assertSame('61', $ann['agreed']);
        $this->assertSame('Agreed', $ann['statuslabel']);
        $this->assertStringContainsString('bg-success', $ann['badge']);

        $ben = $rows['Ben Student'];
        $this->assertSame('Marks differ', $ben['statuslabel']);
        $this->assertTrue($ben['needsyou']);
        $this->assertTrue($ben['wide'], 'A gap over the tolerance is highlighted');
        $this->assertStringContainsString('agree.php', $ben['agreeurl']);

        $cat = $rows['Cat Student'];
        $this->assertSame('', $cat['mark2']);
        $this->assertSame('', $cat['agreed']);
        $this->assertFalse($cat['canagree']);

        $this->assertSame('No markers yet', $rows['Dan Student']['statuslabel']);
        $this->assertSame(1, $data['releasecount']);
        $this->assertTrue($data['canrelease']);
    }

    public function test_overview_puts_who_needs_the_lead_first_and_says_what_is_next(): void {
        $data = $this->export(new overview($this->dm, 'all'));
        // Ben's marks differ, Ann agrees, Cat and Dan are still being marked.
        $this->assertSame('Ben Student', $data['rows'][0]['name']);
        $this->assertSame('Ann Student', $data['rows'][1]['name']);
        $stages = array_column($data['tiles'], null, 'name');
        $this->assertSame(['marking', 'reconcile', 'ready', 'released'], array_keys($stages));
        $this->assertSame(2, $stages['marking']['share']);
        $this->assertTrue($stages['reconcile']['needsyou']);
        $this->assertFalse($stages['ready']['needsyou']);
        // Something is ready, so the next step is releasing it.
        $this->assertSame(get_string('releasehelp', 'local_doublemark'), $data['next']);
    }

    public function test_overview_filters(): void {
        $data = $this->export(new overview($this->dm, 'reconcile'));
        $this->assertSame(['Ben Student'], array_column($data['rows'], 'name'));
        $active = array_values(array_filter($data['tiles'], fn($t) => $t['active']));
        $this->assertSame('reconcile', $active[0]['name']);

        $data = $this->export(new overview($this->dm, 'released'));
        $this->assertSame([], $data['rows']);
        $this->assertFalse($data['hasrows']);
        $this->assertSame('Nobody in this group.', $data['emptymessage']);
    }

    public function test_overview_flags_grades_typed_in_the_assignment(): void {
        $this->setAdminUser();
        $this->dm->assign()->save_grade((int) $this->u['a']->id, (object) ['grade' => 50, 'attemptnumber' => -1]);
        $this->setUser($this->u['lead']);
        $rows = array_column($this->export(new overview($this->dm, 'all'))['rows'], null, 'name');
        $this->assertTrue($rows['Ann Student']['gradedoutside']);
        $this->assertFalse($rows['Ben Student']['gradedoutside']);
    }

    public function test_marker_list_hides_the_other_mark_until_both_are_in(): void {
        $data = $this->export(new marker_list($this->dm, (int) $this->u['m2']->id));
        $rows = array_column($data['rows'], null, 'name');
        $this->assertSame(['Ann Student', 'Ben Student', 'Cat Student'], array_keys($rows));
        $this->assertSame('62', $rows['Ann Student']['own']);
        $this->assertSame('60', $rows['Ann Student']['other']);
        $this->assertSame('', $rows['Cat Student']['own']);
        $this->assertSame('', $rows['Cat Student']['other'], 'The first mark leaked to the second marker');
        $this->assertSame('To mark', $rows['Cat Student']['statuslabel']);
        $this->assertSame('Second marker', $rows['Cat Student']['role']);
        $this->assertStringContainsString('mark.php', $rows['Cat Student']['markurl']);
        $this->assertSame(1, $data['todo']);

        $first = array_column($this->export(new marker_list($this->dm, (int) $this->u['m1']->id))['rows'], null, 'name');
        $this->assertSame('Waiting for the other marker', $first['Cat Student']['statuslabel']);
        $this->assertSame('', $first['Cat Student']['other']);
    }

    public function test_marker_list_is_empty_for_someone_with_no_students(): void {
        $data = $this->export(new marker_list($this->dm, (int) $this->u['lead']->id));
        $this->assertFalse($data['hasrows']);
        $this->assertSame(0, $data['todo']);
    }

    public function test_allocation_marks_the_chosen_markers(): void {
        $data = $this->export(new allocation($this->dm));
        $rows = array_column($data['rows'], null, 'name');
        $ann = $rows['Ann Student'];
        $chosen1 = array_values(array_filter($ann['options1'], fn($o) => $o['selected']));
        $chosen2 = array_values(array_filter($ann['options2'], fn($o) => $o['selected']));
        $this->assertSame('Mia One', $chosen1[0]['name']);
        $this->assertSame('Max Two', $chosen2[0]['name']);
        $this->assertSame([], array_filter($rows['Dan Student']['options1'], fn($o) => $o['selected']));
        $this->assertTrue($rows['Dan Student']['missing']);
        $this->assertFalse($ann['missing']);
        // Marks already in lock the allocation of that student.
        $this->assertTrue($ann['locked']);
        $this->assertFalse($rows['Dan Student']['locked']);
        $this->assertSame(1, $data['unallocated']);
        $this->assertSame(['Lea Lead', 'Mia One', 'Max Two'], array_column($data['markers'], 'name'));
    }
}
