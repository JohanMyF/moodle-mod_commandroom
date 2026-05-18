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

namespace mod_commandroom\privacy;

defined('MOODLE_INTERNAL') || die();

use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for mod_commandroom.
 *
 * @package    mod_commandroom
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Returns metadata about this plugin's stored data.
     *
     * @param collection $collection The metadata collection to add items to.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('commandroom_proposals', [
            'userid' => 'privacy:metadata:commandroom_proposals:userid',
            'nodeid' => 'privacy:metadata:commandroom_proposals:nodeid',
            'proposedvalue' => 'privacy:metadata:commandroom_proposals:proposedvalue',
            'rationale' => 'privacy:metadata:commandroom_proposals:rationale',
            'timecreated' => 'privacy:metadata:commandroom_proposals:timecreated',
            'timemodified' => 'privacy:metadata:commandroom_proposals:timemodified',
        ], 'privacy:metadata:commandroom_proposals');

        $collection->add_database_table('commandroom_runs', [
            'leaderid' => 'privacy:metadata:commandroom_runs:leaderid',
            'groupid' => 'privacy:metadata:commandroom_runs:groupid',
            'finalscore' => 'privacy:metadata:commandroom_runs:finalscore',
            'timecreated' => 'privacy:metadata:commandroom_runs:timecreated',
            'timemodified' => 'privacy:metadata:commandroom_runs:timemodified',
            'timecompleted' => 'privacy:metadata:commandroom_runs:timecompleted',
        ], 'privacy:metadata:commandroom_runs');

        $collection->add_database_table('commandroom_decisions', [
            'leaderid' => 'privacy:metadata:commandroom_decisions:leaderid',
            'nodeid' => 'privacy:metadata:commandroom_decisions:nodeid',
            'decisionmode' => 'privacy:metadata:commandroom_decisions:decisionmode',
            'selectedvalue' => 'privacy:metadata:commandroom_decisions:selectedvalue',
            'timecreated' => 'privacy:metadata:commandroom_decisions:timecreated',
        ], 'privacy:metadata:commandroom_decisions');

        $collection->add_database_table('commandroom_exports', [
            'userid' => 'privacy:metadata:commandroom_exports:userid',
            'name' => 'privacy:metadata:commandroom_exports:name',
            'jsonhash' => 'privacy:metadata:commandroom_exports:jsonhash',
            'timecreated' => 'privacy:metadata:commandroom_exports:timecreated',
        ], 'privacy:metadata:commandroom_exports');

        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:core_files');

        return $collection;
    }

    /**
     * Get the list of contexts containing user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm
                    ON cm.id = ctx.instanceid
                  JOIN {modules} m
                    ON m.id = cm.module
                  JOIN {commandroom} c
                    ON c.id = cm.instance
             LEFT JOIN {commandroom_proposals} p
                    ON p.runid IN (
                        SELECT r.id
                          FROM {commandroom_runs} r
                         WHERE r.commandroomid = c.id
                    )
                   AND p.userid = :proposaluserid
             LEFT JOIN {commandroom_runs} r2
                    ON r2.commandroomid = c.id
                   AND r2.leaderid = :leaderuserid
             LEFT JOIN {commandroom_decisions} d
                    ON d.runid = r2.id
                   AND d.leaderid = :decisionuserid
             LEFT JOIN {commandroom_exports} e
                    ON e.commandroomid = c.id
                   AND e.userid = :exportuserid
                 WHERE ctx.contextlevel = :contextlevel
                   AND m.name = :modname
                   AND (
                        p.id IS NOT NULL
                     OR r2.id IS NOT NULL
                     OR d.id IS NOT NULL
                     OR e.id IS NOT NULL
                   )";

        $params = [
            'proposaluserid' => $userid,
            'leaderuserid' => $userid,
            'decisionuserid' => $userid,
            'exportuserid' => $userid,
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'commandroom',
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Export all user data for the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if ($contextlist->count() === 0) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $contexts = [];
        $contextids = [];

        foreach ($contextlist as $context) {
            if ($context instanceof context_module) {
                $contexts[$context->id] = $context;
                $contextids[] = $context->id;
            }
        }

        if (empty($contextids)) {
            return;
        }

        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'contextid');
        $sql = "SELECT ctx.id AS contextid, cm.id AS cmid, c.id AS commandroomid, c.name
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  JOIN {modules} m ON m.id = cm.module
                  JOIN {commandroom} c ON c.id = cm.instance
                 WHERE ctx.contextlevel = :contextlevel
                   AND m.name = :modname
                   AND ctx.id $contextsql";

        $commandrooms = $DB->get_records_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'commandroom',
        ] + $contextparams);

        if (empty($commandrooms)) {
            return;
        }

        $commandroomids = [];
        foreach ($commandrooms as $commandroom) {
            $commandroomids[] = (int)$commandroom->commandroomid;
        }

        list($commandroomsql, $commandroomparams) =
            $DB->get_in_or_equal($commandroomids, SQL_PARAMS_NAMED, 'commandroomid');

        $runs = $DB->get_records_select(
            'commandroom_runs',
            "commandroomid $commandroomsql AND leaderid = :leaderid",
            $commandroomparams + ['leaderid' => $userid],
            'commandroomid ASC, id ASC'
        );
        $runsbycommandroom = [];
        foreach ($runs as $run) {
            $runsbycommandroom[(int)$run->commandroomid][] = $run;
        }

        $sql = "SELECT p.id, r.commandroomid, p.runid, p.nodeid, n.name AS nodename,
                       p.proposedvalue, p.rationale, p.timecreated, p.timemodified
                  FROM {commandroom_proposals} p
                  JOIN {commandroom_runs} r ON r.id = p.runid
                  JOIN {commandroom_nodes} n ON n.id = p.nodeid
                 WHERE r.commandroomid $commandroomsql
                   AND p.userid = :userid
              ORDER BY r.commandroomid ASC, p.id ASC";
        $proposals = $DB->get_records_sql($sql, $commandroomparams + ['userid' => $userid]);
        $proposalsbycommandroom = [];
        foreach ($proposals as $proposal) {
            $proposalsbycommandroom[(int)$proposal->commandroomid][] = $proposal;
        }

        $sql = "SELECT d.id, r.commandroomid, d.runid, d.nodeid, n.name AS nodename,
                       d.decisionmode, d.selectedvalue, d.timecreated
                  FROM {commandroom_decisions} d
                  JOIN {commandroom_runs} r ON r.id = d.runid
                  JOIN {commandroom_nodes} n ON n.id = d.nodeid
                 WHERE r.commandroomid $commandroomsql
                   AND d.leaderid = :userid
              ORDER BY r.commandroomid ASC, d.id ASC";
        $decisions = $DB->get_records_sql($sql, $commandroomparams + ['userid' => $userid]);
        $decisionsbycommandroom = [];
        foreach ($decisions as $decision) {
            $decisionsbycommandroom[(int)$decision->commandroomid][] = $decision;
        }

        $exports = $DB->get_records_select(
            'commandroom_exports',
            "commandroomid $commandroomsql AND userid = :userid",
            $commandroomparams + ['userid' => $userid],
            'commandroomid ASC, id ASC'
        );
        $exportsbycommandroom = [];
        foreach ($exports as $export) {
            $exportsbycommandroom[(int)$export->commandroomid][] = $export;
        }

        foreach ($contexts as $contextid => $context) {
            if (empty($commandrooms[$contextid])) {
                continue;
            }

            $commandroom = $commandrooms[$contextid];
            $commandroomid = (int)$commandroom->commandroomid;
            $data = new \stdClass();
            $data->activityname = $commandroom->name;

            if (!empty($runsbycommandroom[$commandroomid])) {
                $data->runs = array_values(array_map(function($run) {
                    return (object) [
                        'groupid' => $run->groupid,
                        'status' => $run->status,
                        'finalscore' => $run->finalscore,
                        'timecreated' => transform::datetime($run->timecreated),
                        'timemodified' => transform::datetime($run->timemodified),
                        'timecompleted' => $run->timecompleted ? transform::datetime($run->timecompleted) : null,
                    ];
                }, $runsbycommandroom[$commandroomid]));
            }

            if (!empty($proposalsbycommandroom[$commandroomid])) {
                $data->proposals = array_values(array_map(function($proposal) {
                    return (object) [
                        'runid' => $proposal->runid,
                        'nodeid' => $proposal->nodeid,
                        'nodename' => $proposal->nodename,
                        'proposedvalue' => $proposal->proposedvalue,
                        'rationale' => $proposal->rationale,
                        'timecreated' => transform::datetime($proposal->timecreated),
                        'timemodified' => transform::datetime($proposal->timemodified),
                    ];
                }, $proposalsbycommandroom[$commandroomid]));
            }

            if (!empty($decisionsbycommandroom[$commandroomid])) {
                $data->decisions = array_values(array_map(function($decision) {
                    return (object) [
                        'runid' => $decision->runid,
                        'nodeid' => $decision->nodeid,
                        'nodename' => $decision->nodename,
                        'decisionmode' => $decision->decisionmode,
                        'selectedvalue' => $decision->selectedvalue,
                        'timecreated' => transform::datetime($decision->timecreated),
                    ];
                }, $decisionsbycommandroom[$commandroomid]));
            }

            if (!empty($exportsbycommandroom[$commandroomid])) {
                $data->exports = array_values(array_map(function($export) {
                    return (object) [
                        'name' => $export->name,
                        'jsonhash' => $export->jsonhash,
                        'timecreated' => transform::datetime($export->timecreated),
                    ];
                }, $exportsbycommandroom[$commandroomid]));
            }

            writer::with_context($context)->export_data([], $data);
            helper::export_context_files($context, $userid, 'mod_commandroom', 'intro', 0);
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The context to delete data from.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('commandroom', $context->instanceid);
        if (!$cm) {
            return;
        }

        $commandroom = $DB->get_record('commandroom', ['id' => $cm->instance], 'id', IGNORE_MISSING);
        if (!$commandroom) {
            return;
        }

        $runs = $DB->get_records('commandroom_runs', ['commandroomid' => $commandroom->id], '', 'id');
        if ($runs) {
            $runids = array_keys($runs);
            list($insql, $params) = $DB->get_in_or_equal($runids, SQL_PARAMS_NAMED);

            $DB->delete_records_select('commandroom_proposals', "runid $insql", $params);
            $DB->delete_records_select('commandroom_decisions', "runid $insql", $params);
            $DB->delete_records_select('commandroom_results', "runid $insql", $params);
            $DB->delete_records_select('commandroom_runs', "id $insql", $params);
        }

        $DB->delete_records('commandroom_exports', ['commandroomid' => $commandroom->id]);
    }

    /**
     * Delete multiple users within a context.
     *
     * @param approved_contextlist $contextlist The approved contexts and users.
     */
    public static function delete_data_for_users(approved_contextlist $contextlist): void {
        global $DB;

        if ($contextlist->count() === 0) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $contextids = [];

        foreach ($contextlist as $context) {
            if ($context instanceof context_module) {
                $contextids[] = $context->id;
            }
        }

        if (empty($contextids)) {
            return;
        }

        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'contextid');
        $sql = "SELECT c.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  JOIN {modules} m ON m.id = cm.module
                  JOIN {commandroom} c ON c.id = cm.instance
                 WHERE ctx.contextlevel = :contextlevel
                   AND m.name = :modname
                   AND ctx.id $contextsql";

        $commandroomids = $DB->get_fieldset_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'commandroom',
        ] + $contextparams);

        if (empty($commandroomids)) {
            return;
        }

        list($commandroomsql, $commandroomparams) =
            $DB->get_in_or_equal($commandroomids, SQL_PARAMS_NAMED, 'commandroomid');

        $runsql = "SELECT id
                     FROM {commandroom_runs}
                    WHERE commandroomid $commandroomsql
                      AND leaderid = :leaderid";
        $runids = $DB->get_fieldset_sql($runsql, $commandroomparams + ['leaderid' => $userid]);

        if ($runids) {
            list($runinsql, $runparams) = $DB->get_in_or_equal($runids, SQL_PARAMS_NAMED, 'runid');
            $DB->delete_records_select('commandroom_decisions', "runid $runinsql", $runparams);
            $DB->delete_records_select('commandroom_results', "runid $runinsql", $runparams);
            $DB->delete_records_select('commandroom_proposals', "runid $runinsql", $runparams);
            $DB->delete_records_select('commandroom_runs', "id $runinsql", $runparams);
        }

        $sql = "SELECT p.id
                  FROM {commandroom_proposals} p
                  JOIN {commandroom_runs} r ON r.id = p.runid
                 WHERE r.commandroomid $commandroomsql
                   AND p.userid = :userid";
        $proposalids = $DB->get_fieldset_sql($sql, $commandroomparams + ['userid' => $userid]);

        if ($proposalids) {
            list($proposalinsql, $proposalparams) =
                $DB->get_in_or_equal($proposalids, SQL_PARAMS_NAMED, 'proposalid');
            $DB->delete_records_select('commandroom_proposals', "id $proposalinsql", $proposalparams);
        }

        $sql = "SELECT d.id
                  FROM {commandroom_decisions} d
                  JOIN {commandroom_runs} r ON r.id = d.runid
                 WHERE r.commandroomid $commandroomsql
                   AND d.leaderid = :userid";
        $decisionids = $DB->get_fieldset_sql($sql, $commandroomparams + ['userid' => $userid]);

        if ($decisionids) {
            list($decisioninsql, $decisionparams) =
                $DB->get_in_or_equal($decisionids, SQL_PARAMS_NAMED, 'decisionid');
            $DB->delete_records_select('commandroom_decisions', "id $decisioninsql", $decisionparams);
        }

        $DB->delete_records_select(
            'commandroom_exports',
            "commandroomid $commandroomsql AND userid = :userid",
            $commandroomparams + ['userid' => $userid]
        );
    }

    /**
     * Get users in the supplied context.
     *
     * @param userlist $userlist The userlist object.
     */
    public static function get_users_in_context(userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('commandroom', $context->instanceid);
        if (!$cm) {
            return;
        }

        $params = ['commandroomid' => $cm->instance];

        $sql = "SELECT p.userid
                  FROM {commandroom_proposals} p
                  JOIN {commandroom_runs} r
                    ON r.id = p.runid
                 WHERE r.commandroomid = :commandroomid
              GROUP BY p.userid";
        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT r.leaderid AS userid
                  FROM {commandroom_runs} r
                 WHERE r.commandroomid = :commandroomid
              GROUP BY r.leaderid";
        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT e.userid
                  FROM {commandroom_exports} e
                 WHERE e.commandroomid = :commandroomid
              GROUP BY e.userid";
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Delete data for users in the supplied context.
     *
     * @param approved_userlist $userlist The approved userlist to delete.
     */
    public static function delete_data_for_userlist(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('commandroom', $context->instanceid);
        if (!$cm) {
            return;
        }

        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }

        list($userinsql, $userparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        $runsql = "SELECT id
                     FROM {commandroom_runs}
                    WHERE commandroomid = :commandroomid
                      AND leaderid $userinsql";
        $runparams = ['commandroomid' => $cm->instance] + $userparams;
        $runids = $DB->get_fieldset_sql($runsql, $runparams);

        if ($runids) {
            list($runinsql, $runparams2) = $DB->get_in_or_equal($runids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('commandroom_decisions', "runid $runinsql", $runparams2);
            $DB->delete_records_select('commandroom_results', "runid $runinsql", $runparams2);
            $DB->delete_records_select('commandroom_proposals', "runid $runinsql", $runparams2);
            $DB->delete_records_select('commandroom_runs', "id $runinsql", $runparams2);
        }

        $sql = "SELECT p.id
                  FROM {commandroom_proposals} p
                  JOIN {commandroom_runs} r
                    ON r.id = p.runid
                 WHERE r.commandroomid = :commandroomid
                   AND p.userid $userinsql";
        $proposalids = $DB->get_fieldset_sql($sql, ['commandroomid' => $cm->instance] + $userparams);

        if ($proposalids) {
            list($proposalinsql, $proposalparams) = $DB->get_in_or_equal($proposalids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('commandroom_proposals', "id $proposalinsql", $proposalparams);
        }

        $DB->delete_records_select('commandroom_exports', "commandroomid = :commandroomid AND userid $userinsql",
            ['commandroomid' => $cm->instance] + $userparams);
    }
}