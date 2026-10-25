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

namespace mod_qrvote\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_qrvote\local\poll;
use mod_qrvote\local\results;

/**
 * Privacy provider for mod_qrvote.
 *
 * Personal data exists only for teachers who started rounds and for votes in activities that
 * require login. Public votes store no user id; their voter hash cannot be linked to a person.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describes the stored personal data.
     *
     * @param collection $collection Collection to add to.
     * @return collection The collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('qrvote_rounds', [
            'usermodified' => 'privacy:metadata:qrvote_rounds:usermodified',
            'timestarted' => 'privacy:metadata:qrvote_rounds:timestarted',
        ], 'privacy:metadata:qrvote_rounds');
        $collection->add_database_table('qrvote_votes', [
            'userid' => 'privacy:metadata:qrvote_votes:userid',
            'optionid' => 'privacy:metadata:qrvote_votes:optionid',
            'timecreated' => 'privacy:metadata:qrvote_votes:timecreated',
        ], 'privacy:metadata:qrvote_votes');
        return $collection;
    }

    /**
     * Returns the module contexts that hold data of a user.
     *
     * @param int $userid User id.
     * @return contextlist Contexts.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = 'SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {qrvote_rounds} r ON r.qrvoteid = cm.instance
             LEFT JOIN {qrvote_votes} v ON v.roundid = r.id AND v.userid = :voterid
                 WHERE r.usermodified = :starterid OR v.id IS NOT NULL';
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'qrvote',
            'voterid' => $userid,
            'starterid' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Returns the users with data in a context.
     *
     * @param userlist $userlist List to add users to.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $params = ['cmid' => $context->instanceid, 'modname' => 'qrvote'];
        $base = 'FROM {course_modules} cm
                 JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                 JOIN {qrvote_rounds} r ON r.qrvoteid = cm.instance';
        $userlist->add_from_sql('usermodified', "SELECT r.usermodified $base WHERE cm.id = :cmid AND r.usermodified > 0", $params);
        $userlist->add_from_sql(
            'userid',
            "SELECT v.userid $base JOIN {qrvote_votes} v ON v.roundid = r.id WHERE cm.id = :cmid AND v.userid IS NOT NULL",
            $params
        );
    }

    /**
     * Exports the data of a user.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $qrvoteid = self::get_instance_id($context);
            if ($qrvoteid === null) {
                continue;
            }

            $rounds = $DB->get_records('qrvote_rounds', ['qrvoteid' => $qrvoteid, 'usermodified' => $userid], 'timestarted ASC');
            $started = [];
            foreach ($rounds as $round) {
                $started[] = (object) ['timestarted' => transform::datetime($round->timestarted)];
            }

            $rating = (int) $DB->get_field('qrvote', 'votetype', ['id' => $qrvoteid]) === poll::TYPE_RATING;
            $sql = 'SELECT v.id, o.text, o.sortorder, v.timecreated
                      FROM {qrvote_votes} v
                      JOIN {qrvote_rounds} r ON r.id = v.roundid
                      JOIN {qrvote_options} o ON o.id = v.optionid
                     WHERE r.qrvoteid = :qrvoteid AND v.userid = :userid
                  ORDER BY v.timecreated ASC, v.id ASC';
            $votes = [];
            foreach ($DB->get_records_sql($sql, ['qrvoteid' => $qrvoteid, 'userid' => $userid]) as $vote) {
                $votes[] = (object) [
                    'answer' => $rating ? results::stars_label(poll::stars($vote)) : $vote->text,
                    'timecreated' => transform::datetime($vote->timecreated),
                ];
            }

            if (!$started && !$votes) {
                continue;
            }
            $data = (object) [
                get_string('privacy:roundsstarted', 'qrvote') => $started,
                get_string('privacy:votes', 'qrvote') => $votes,
            ];
            writer::with_context($context)->export_data([], $data);
        }
    }

    /**
     * Deletes the personal data of all users in a context.
     *
     * Votes of logged-in users are deleted; rounds stay with their public votes but lose the starter.
     *
     * @param \context $context Context.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }
        $qrvoteid = self::get_instance_id($context);
        if ($qrvoteid === null) {
            return;
        }
        $roundids = $DB->get_fieldset_select('qrvote_rounds', 'id', 'qrvoteid = ?', [$qrvoteid]);
        if (!$roundids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($roundids);
        $DB->delete_records_select('qrvote_votes', "userid IS NOT NULL AND roundid $insql", $params);
        $DB->set_field('qrvote_rounds', 'usermodified', 0, ['qrvoteid' => $qrvoteid]);
    }

    /**
     * Deletes the personal data of a user in the approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            self::delete_users_in_context($context, [$userid]);
        }
    }

    /**
     * Deletes the personal data of several users in one context.
     *
     * @param approved_userlist $userlist Approved users.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        self::delete_users_in_context($userlist->get_context(), $userlist->get_userids());
    }

    /**
     * Deletes votes and anonymises started rounds of the given users in a context.
     *
     * @param \context $context Context.
     * @param int[] $userids User ids.
     * @return void
     */
    private static function delete_users_in_context(\context $context, array $userids): void {
        global $DB;

        if (!$userids || !$context instanceof \context_module) {
            return;
        }
        $qrvoteid = self::get_instance_id($context);
        if ($qrvoteid === null) {
            return;
        }
        $roundids = $DB->get_fieldset_select('qrvote_rounds', 'id', 'qrvoteid = ?', [$qrvoteid]);
        if (!$roundids) {
            return;
        }
        [$roundsql, $roundparams] = $DB->get_in_or_equal($roundids, SQL_PARAMS_NAMED, 'round');
        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'user');
        $DB->delete_records_select(
            'qrvote_votes',
            "roundid $roundsql AND userid $usersql",
            array_merge($roundparams, $userparams)
        );
        $DB->set_field_select(
            'qrvote_rounds',
            'usermodified',
            0,
            "id $roundsql AND usermodified $usersql",
            array_merge($roundparams, $userparams)
        );
    }

    /**
     * Returns the instance id of a module context, or null if it is not a QR vote.
     *
     * @param \context_module $context Module context.
     * @return int|null Instance id.
     */
    private static function get_instance_id(\context_module $context): ?int {
        $cm = get_coursemodule_from_id('qrvote', $context->instanceid);
        return $cm ? (int) $cm->instance : null;
    }
}
