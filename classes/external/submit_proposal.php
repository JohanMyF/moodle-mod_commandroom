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
 * External service implementation for mod_commandroom.
 *
 * @package    mod_commandroom
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_commandroom\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/commandroom/lib.php');

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use moodle_exception;

class submit_proposal extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'nodeid' => new external_value(PARAM_INT, 'Node id'),
            'proposedvalue' => new external_value(PARAM_FLOAT, 'Proposed value'),
            'rationale' => new external_value(PARAM_TEXT, 'Proposal rationale'),
        ]);
    }

    public static function execute(int $cmid, int $nodeid, float $proposedvalue, string $rationale): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'nodeid' => $nodeid,
            'proposedvalue' => $proposedvalue,
            'rationale' => $rationale,
        ]);

        $cm = get_coursemodule_from_id('commandroom', $params['cmid'], 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        $commandroom = $DB->get_record('commandroom', ['id' => $cm->instance], '*', MUST_EXIST);

        require_login($course, true, $cm);

        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/commandroom:submitproposal', $context);

        $node = $DB->get_record('commandroom_nodes', [
            'id' => $params['nodeid'],
            'commandroomid' => $commandroom->id,
        ], '*', MUST_EXIST);

        if (empty($node->studentcontrolled)) {
            throw new moodle_exception('error:nodenotstudentcontrolled', 'mod_commandroom');
        }

        $rationale = trim($params['rationale']);
        if ($rationale === '') {
            throw new moodle_exception('error:rationalerequired', 'mod_commandroom');
        }

        if ((float)$params['proposedvalue'] < (float)$node->minimumvalue) {
            throw new moodle_exception('error:proposalbelowminimum', 'mod_commandroom');
        }

        if ((float)$params['proposedvalue'] > (float)$node->maximumvalue) {
            throw new moodle_exception('error:proposalabovemaximum', 'mod_commandroom');
        }

        $groupid = commandroom_get_user_groupid((int)$course->id, (int)$USER->id, $cm->groupingid ?: 0);
        $assignedleaderid = commandroom_get_group_leader((int)$commandroom->id, $groupid);

        // Prefer latest inprogress run, else latest draft run.
        $sql = "SELECT *
                  FROM {commandroom_runs}
                 WHERE commandroomid = ?
                   AND groupid = ?
                   AND status IN ('inprogress', 'draft')
              ORDER BY CASE status
                           WHEN 'inprogress' THEN 0
                           WHEN 'draft' THEN 1
                           ELSE 2
                       END,
                       id DESC";
        $run = $DB->get_record_sql($sql, [$commandroom->id, $groupid], IGNORE_MULTIPLE);

        if (!$run) {
            $run = new \stdClass();
            $run->commandroomid = $commandroom->id;
            $run->groupid = $groupid;
            $run->leaderid = $assignedleaderid;
            $run->status = 'draft';
            $run->currentiteration = 0;
            $run->totaliterations = (int)$commandroom->totaliterations;
            $run->scenarioversion = 1;
            $run->finalscore = 0;
            $run->timecreated = time();
            $run->timemodified = $run->timecreated;
            $run->timecompleted = null;
            $run->timesubmitted = null;
            $run->submittedby = null;
            $run->timeinvalidated = null;
            $run->invalidatedreason = null;

            $run->id = $DB->insert_record('commandroom_runs', $run);
        } else if ($assignedleaderid > 0 && (int)$run->leaderid !== (int)$assignedleaderid) {
            $runupdate = new \stdClass();
            $runupdate->id = $run->id;
            $runupdate->leaderid = $assignedleaderid;
            $runupdate->timemodified = time();
            $DB->update_record('commandroom_runs', $runupdate);

            $run->leaderid = $assignedleaderid;
        }

        $existing = $DB->get_record('commandroom_proposals', [
            'runid' => $run->id,
            'nodeid' => $node->id,
            'userid' => $USER->id,
        ]);

        $time = time();

        if ($existing) {
            $existing->proposedvalue = $params['proposedvalue'];
            $existing->rationale = $rationale;
            $existing->timemodified = $time;
            $DB->update_record('commandroom_proposals', $existing);
            $proposalid = (int)$existing->id;
            $created = 0;
        } else {
            $proposal = new \stdClass();
            $proposal->runid = $run->id;
            $proposal->nodeid = $node->id;
            $proposal->userid = $USER->id;
            $proposal->proposedvalue = $params['proposedvalue'];
            $proposal->rationale = $rationale;
            $proposal->timecreated = $time;
            $proposal->timemodified = $time;

            $proposalid = (int)$DB->insert_record('commandroom_proposals', $proposal);
            $created = 1;
        }

        $runupdate = new \stdClass();
        $runupdate->id = $run->id;
        $runupdate->timemodified = $time;
        $DB->update_record('commandroom_runs', $runupdate);

        return [
            'status' => 'ok',
            'created' => $created,
            'proposalid' => $proposalid,
            'runid' => (int)$run->id,
            'nodeid' => (int)$node->id,
            'userid' => (int)$USER->id,
            'message' => get_string('proposalsaved', 'mod_commandroom'),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'Result status'),
            'created' => new external_value(PARAM_INT, '1 if inserted, 0 if updated'),
            'proposalid' => new external_value(PARAM_INT, 'Proposal id'),
            'runid' => new external_value(PARAM_INT, 'Run id'),
            'nodeid' => new external_value(PARAM_INT, 'Node id'),
            'userid' => new external_value(PARAM_INT, 'User id'),
            'message' => new external_value(PARAM_TEXT, 'User-facing status message'),
        ]);
    }
}
