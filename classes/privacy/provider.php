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

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_doublemark\local\double_marking;

/**
 * Privacy provider: marks and comments about students, and who gave them.
 *
 * Deleting a marker's data keeps the marks they gave, without their id, because removing
 * a mark would change a student's agreed grade.
 *
 * @package    local_doublemark
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** @var array Columns that point at a user, per table. */
    private const USERCOLUMNS = [
        'local_doublemark_config' => ['usermodified'],
        'local_doublemark_alloc' => ['userid', 'marker1', 'marker2'],
        'local_doublemark_mark' => ['userid', 'markerid'],
        'local_doublemark_agreed' => ['userid', 'agreedby', 'releasedby'],
    ];

    /**
     * Describe the stored data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_doublemark_alloc', [
            'userid' => 'privacy:metadata:alloc:userid',
            'marker1' => 'privacy:metadata:alloc:marker1',
            'marker2' => 'privacy:metadata:alloc:marker2',
        ], 'privacy:metadata:alloc');
        $collection->add_database_table('local_doublemark_mark', [
            'userid' => 'privacy:metadata:mark:userid',
            'markerid' => 'privacy:metadata:mark:markerid',
            'position' => 'privacy:metadata:mark:position',
            'grade' => 'privacy:metadata:mark:grade',
            'comment' => 'privacy:metadata:mark:comment',
            'timecreated' => 'privacy:metadata:mark:timecreated',
            'timemodified' => 'privacy:metadata:mark:timemodified',
        ], 'privacy:metadata:mark');
        $collection->add_database_table('local_doublemark_agreed', [
            'userid' => 'privacy:metadata:agreed:userid',
            'grade' => 'privacy:metadata:agreed:grade',
            'feedback' => 'privacy:metadata:agreed:feedback',
            'note' => 'privacy:metadata:agreed:note',
            'source' => 'privacy:metadata:agreed:source',
            'timeagreed' => 'privacy:metadata:agreed:timeagreed',
            'timereleased' => 'privacy:metadata:agreed:timereleased',
            'agreedby' => 'privacy:metadata:agreed:agreedby',
            'releasedby' => 'privacy:metadata:agreed:releasedby',
        ], 'privacy:metadata:agreed');
        $collection->add_database_table('local_doublemark_config', [
            'usermodified' => 'privacy:metadata:config:usermodified',
        ], 'privacy:metadata:config');
        return $collection;
    }

    /**
     * Module contexts where the user appears in any role.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        foreach (self::USERCOLUMNS as $table => $columns) {
            $where = implode(' OR ', array_map(fn($c) => "t.$c = :$c", $columns));
            $params = ['level' => CONTEXT_MODULE] + array_fill_keys($columns, $userid);
            $list->add_from_sql("SELECT ctx.id FROM {{$table}} t
                JOIN {context} ctx ON ctx.instanceid = t.cmid AND ctx.contextlevel = :level
                WHERE $where", $params);
        }
        return $list;
    }

    /**
     * Users with data in a module context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        foreach (self::USERCOLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $userlist->add_from_sql(
                    $column,
                    "SELECT $column FROM {{$table}} WHERE cmid = :cmid AND $column > 0",
                    ['cmid' => $context->instanceid]
                );
            }
        }
    }

    /**
     * Export marks about the user as a student, and marks they gave as a marker.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cmid = (int) $context->instanceid;
            $marks = array_values(array_map(fn($m) => [
                'position' => (int) $m->position,
                'grade' => (float) $m->grade,
                'comment' => (string) $m->comment,
                'timemodified' => transform::datetime($m->timemodified),
            ], $DB->get_records('local_doublemark_mark', ['cmid' => $cmid, 'userid' => $userid], 'position')));
            $given = array_values(array_map(fn($m) => [
                'grade' => (float) $m->grade,
                'comment' => (string) $m->comment,
                'timemodified' => transform::datetime($m->timemodified),
            ], $DB->get_records('local_doublemark_mark', ['cmid' => $cmid, 'markerid' => $userid], 'id')));
            $agreed = $DB->get_record('local_doublemark_agreed', ['cmid' => $cmid, 'userid' => $userid]);
            $asstudent = $DB->record_exists('local_doublemark_alloc', ['cmid' => $cmid, 'userid' => $userid]);
            $asmarker = $DB->count_records_select(
                'local_doublemark_alloc',
                'cmid = ? AND (marker1 = ? OR marker2 = ?)',
                [$cmid, $userid, $userid]
            );
            $agreedby = $DB->count_records('local_doublemark_agreed', ['cmid' => $cmid, 'agreedby' => $userid]);
            $releasedby = $DB->count_records('local_doublemark_agreed', ['cmid' => $cmid, 'releasedby' => $userid]);
            $settings = $DB->record_exists('local_doublemark_config', ['cmid' => $cmid, 'usermodified' => $userid]);
            $data = (object) [
                'allocatedmarkers' => transform::yesno($asstudent),
                'studentstomark' => $asmarker,
                'marksagreed' => $agreedby,
                'marksreleased' => $releasedby,
                'changedsettings' => transform::yesno($settings),
                'marks' => $marks,
                'marksgiven' => $given,
                'agreed' => $agreed ? [
                    'grade' => (float) $agreed->grade,
                    'feedback' => (string) $agreed->feedback,
                    'note' => (string) $agreed->note,
                    'timeagreed' => transform::datetime($agreed->timeagreed),
                    'timereleased' => $agreed->timereleased ? transform::datetime($agreed->timereleased) : '',
                ] : null,
            ];
            if ($marks || $given || $agreed || $asstudent || $asmarker || $agreedby || $releasedby || $settings) {
                writer::with_context($context)->export_data([get_string('pluginname', 'local_doublemark')], $data);
            }
        }
    }

    /**
     * Delete everything stored for a module context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        if ($context instanceof \context_module) {
            double_marking::delete_for_cmid((int) $context->instanceid);
        }
    }

    /**
     * Delete one user's data in the approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_module) {
                self::delete_user((int) $context->instanceid, $userid);
            }
        }
    }

    /**
     * Delete several users' data in one context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        foreach ($userlist->get_userids() as $userid) {
            self::delete_user((int) $context->instanceid, (int) $userid);
        }
    }

    /**
     * Remove a student's rows, and the user's id from rows where they were marker or lead.
     *
     * @param int $cmid
     * @param int $userid
     */
    private static function delete_user(int $cmid, int $userid): void {
        global $DB;
        foreach (['alloc', 'mark', 'agreed'] as $table) {
            $DB->delete_records("local_doublemark_$table", ['cmid' => $cmid, 'userid' => $userid]);
        }
        foreach (self::USERCOLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                if ($column !== 'userid') {
                    $DB->set_field($table, $column, 0, ['cmid' => $cmid, $column => $userid]);
                }
            }
        }
    }
}
