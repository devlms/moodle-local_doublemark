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

namespace local_doublemark\local;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_doublemark\local\reconciler::class)]
/**
 * Tests for the rule that turns two marks into a status and an agreed mark.
 *
 * @package    local_doublemark
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_doublemark\local\reconciler
 */
final class reconciler_test extends \basic_testcase {
    public function test_waits_until_both_marks_are_in(): void {
        $this->assertSame([reconciler::MARKING, null], reconciler::resolve(null, null, 5, reconciler::AVERAGE));
        $this->assertSame([reconciler::MARKING, null], reconciler::resolve(62.0, null, 5, reconciler::AVERAGE));
        $this->assertSame([reconciler::MARKING, null], reconciler::resolve(null, 62.0, 5, reconciler::AVERAGE));
    }

    public function test_marks_within_tolerance_agree_by_the_rule(): void {
        $this->assertSame([reconciler::AGREED, 64.5], reconciler::resolve(62.0, 67.0, 5, reconciler::AVERAGE));
        $this->assertSame([reconciler::AGREED, 67.0], reconciler::resolve(62.0, 67.0, 5, reconciler::HIGHER));
        $this->assertSame([reconciler::AGREED, 67.0], reconciler::resolve(67.0, 62.0, 5, reconciler::HIGHER));
        $this->assertSame([reconciler::AGREED, 62.0], reconciler::resolve(62.0, 67.0, 5, reconciler::FIRST));
    }

    public function test_tolerance_is_inclusive_and_exact(): void {
        // 0.1 + 0.2 style float error must not push a gap of exactly 5 over the line.
        $this->assertSame(reconciler::AGREED, reconciler::resolve(60.1, 65.1, 5, reconciler::AVERAGE)[0]);
        $this->assertSame(reconciler::RECONCILE, reconciler::resolve(60.0, 65.01, 5, reconciler::AVERAGE)[0]);
    }

    public function test_marks_outside_tolerance_need_the_lead(): void {
        $this->assertSame([reconciler::RECONCILE, null], reconciler::resolve(48.0, 66.0, 5, reconciler::AVERAGE));
    }

    public function test_the_lead_rule_always_asks(): void {
        $this->assertSame([reconciler::RECONCILE, null], reconciler::resolve(62.0, 62.0, 5, reconciler::LEAD));
    }

    public function test_average_is_rounded_to_two_places(): void {
        $this->assertSame([reconciler::AGREED, 63.67], reconciler::resolve(63.33, 64.0, 5, reconciler::AVERAGE));
    }

    public function test_zero_tolerance_needs_identical_marks(): void {
        $this->assertSame([reconciler::AGREED, 70.0], reconciler::resolve(70.0, 70.0, 0, reconciler::AVERAGE));
        $this->assertSame(reconciler::RECONCILE, reconciler::resolve(70.0, 70.5, 0, reconciler::AVERAGE)[0]);
    }

    public function test_unknown_rule_is_rejected(): void {
        $this->expectException(\coding_exception::class);
        reconciler::resolve(1.0, 2.0, 5, 'median');
    }

    public function test_rules_lists_every_rule(): void {
        $this->assertSame([reconciler::AVERAGE, reconciler::HIGHER, reconciler::FIRST, reconciler::LEAD], reconciler::RULES);
    }
}
