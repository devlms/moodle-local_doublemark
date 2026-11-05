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

/**
 * Turns two independent marks into a status and, when the rule allows, an agreed mark.
 *
 * Pure logic, no database: the store and the pages build on it.
 *
 * @package    local_doublemark
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reconciler {
    /** One or both marks are still missing. */
    public const MARKING = 'marking';
    /** Both marks are in and the rule settled the agreed mark. */
    public const AGREED = 'agreed';
    /** Both marks are in and the lead has to decide. */
    public const RECONCILE = 'reconcile';

    /** Agreed mark is the mean of the two. */
    public const AVERAGE = 'average';
    /** Agreed mark is the higher of the two. */
    public const HIGHER = 'higher';
    /** Agreed mark is the first marker's. */
    public const FIRST = 'first';
    /** The lead always decides. */
    public const LEAD = 'lead';

    /** @var string[] Every rule, in the order the settings list them. */
    public const RULES = [self::AVERAGE, self::HIGHER, self::FIRST, self::LEAD];

    /**
     * Status and agreed mark for a pair of marks.
     *
     * @param float|null $first First marker's mark, null when missing.
     * @param float|null $second Second marker's mark, null when missing.
     * @param float $tolerance Largest gap that still counts as agreement, in grade points (inclusive).
     * @param string $rule One of RULES.
     * @return array [status, agreed mark or null]
     */
    public static function resolve(?float $first, ?float $second, float $tolerance, string $rule): array {
        if (!in_array($rule, self::RULES, true)) {
            throw new \coding_exception("Unknown agreement rule '$rule'");
        }
        if ($first === null || $second === null) {
            return [self::MARKING, null];
        }
        // Compare in hundredths so float noise cannot turn an exact gap into a disagreement.
        $gap = abs((int) round($first * 100) - (int) round($second * 100));
        if ($rule === self::LEAD || $gap > (int) round($tolerance * 100)) {
            return [self::RECONCILE, null];
        }
        $agreed = match ($rule) {
            self::AVERAGE => round(($first + $second) / 2, 2),
            self::HIGHER => max($first, $second),
            self::FIRST => $first,
        };
        return [self::AGREED, $agreed];
    }
}
