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

/**
 * External service for saving or updating a leader decision.
 *
 * @package    mod_commandroom
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_decision extends external_api {

    /**
     * Describe parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'runid' => new external_value(PARAM_INT, 'Run id'),
            'nodeid' => new external_value(PARAM_INT, 'Node id'),
            'decisiontype' => new external_value(PARAM_ALPHA, 'Leader decision type: min, max, or mean'),
        ]);
    }

    /**
     * Save or update a leader decision.
     *
     * @param int $cmid
     * @param int $runid
     * @param int $nodeid
     * @param string $decisiontype
     * @return array
     */
    public static function execute(int $cmid, int $runid, int $nodeid, string $decisiontype): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'runid' => $runid,
            'nodeid' => $nodeid,
            'decisiontype' => $decisiontype,
        ]);

        $cm = get_coursemodule_from_id('commandroom', $params['cmid'], 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        $commandroom = $DB->get_record('commandroom', ['id' => $cm->instance], '*', MUST_EXIST);

        require_login($course, true, $cm);

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        // Temporary capability gate. Leadership is enforced below via run leader check.
        require_capability('mod/commandroom:submitproposal', $context);

        $run = $DB->get_record('commandroom_runs', [
            'id' => $params['runid'],
            'commandroomid' => $commandroom->id,
        ], '*', MUST_EXIST);

        $node = $DB->get_record('commandroom_nodes', [
            'id' => $params['nodeid'],
            'commandroomid' => $commandroom->id,
        ], '*', MUST_EXIST);

        if (empty($node->studentcontrolled)) {
            throw new moodle_exception('error:nodenotstudentcontrolled', 'mod_commandroom');
        }

        if ((int)$run->leaderid !== (int)$USER->id) {
            throw new moodle_exception('error:notrunleader', 'mod_commandroom');
        }

        $decisiontype = strtolower(trim($params['decisiontype']));
        $allowedtypes = ['min', 'max', 'mean'];

        if (!in_array($decisiontype, $allowedtypes, true)) {
            throw new moodle_exception('error:invaliddecisiontype', 'mod_commandroom');
        }

        $sql = "SELECT
                    MIN(proposedvalue) AS minproposal,
                    MAX(proposedvalue) AS maxproposal,
                    AVG(proposedvalue) AS meanproposal,
                    COUNT(id) AS proposalcount
                  FROM {commandroom_proposals}
                 WHERE runid = :runid
                   AND nodeid = :nodeid";

        $aggregates = $DB->get_record_sql($sql, [
            'runid' => $run->id,
            'nodeid' => $node->id,
        ]);

        if (empty($aggregates) || empty($aggregates->proposalcount)) {
            throw new moodle_exception('error:noproposalsfordecision', 'mod_commandroom');
        }

        switch ($decisiontype) {
            case 'min':
                $selectedvalue = (float)$aggregates->minproposal;
                break;

            case 'max':
                $selectedvalue = (float)$aggregates->maxproposal;
                break;

            default:
                $selectedvalue = (float)$aggregates->meanproposal;
                break;
        }

        $time = time();

        $existing = $DB->get_record_sql(
            "SELECT *
               FROM {commandroom_decisions}
              WHERE runid = :runid
                AND nodeid = :nodeid
           ORDER BY timemodified DESC, id DESC",
            [
                'runid' => $run->id,
                'nodeid' => $node->id,
            ],
            IGNORE_MULTIPLE
        );

        if ($existing) {
            $existing->leaderid = $USER->id;
            $existing->decisiontype = $decisiontype;
            $existing->selectedvalue = $selectedvalue;
            $existing->timemodified = $time;
            $DB->update_record('commandroom_decisions', $existing);
            $decisionid = (int)$existing->id;
            $created = 0;
        } else {
            $decision = new \stdClass();
            $decision->runid = $run->id;
            $decision->nodeid = $node->id;
            $decision->leaderid = $USER->id;
            $decision->decisiontype = $decisiontype;
            $decision->selectedvalue = $selectedvalue;
            $decision->timecreated = $time;
            $decision->timemodified = $time;

            $decisionid = (int)$DB->insert_record('commandroom_decisions', $decision);
            $created = 1;
        }

        $runupdate = new \stdClass();
        $runupdate->id = $run->id;
        $runupdate->timemodified = $time;
        $DB->update_record('commandroom_runs', $runupdate);

        return [
            'status' => 'ok',
            'created' => $created,
            'decisionid' => $decisionid,
            'runid' => (int)$run->id,
            'nodeid' => (int)$node->id,
            'leaderid' => (int)$USER->id,
            'decisiontype' => $decisiontype,
            'selectedvalue' => $selectedvalue,
            'proposalcount' => (int)$aggregates->proposalcount,
            'message' => get_string('decisionsaved', 'mod_commandroom'),
        ];
    }

    /**
     * Describe return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'Result status'),
            'created' => new external_value(PARAM_INT, '1 if inserted, 0 if updated'),
            'decisionid' => new external_value(PARAM_INT, 'Decision id'),
            'runid' => new external_value(PARAM_INT, 'Run id'),
            'nodeid' => new external_value(PARAM_INT, 'Node id'),
            'leaderid' => new external_value(PARAM_INT, 'Leader user id'),
            'decisiontype' => new external_value(PARAM_ALPHA, 'Decision type'),
            'selectedvalue' => new external_value(PARAM_FLOAT, 'Selected numeric value'),
            'proposalcount' => new external_value(PARAM_INT, 'Number of proposals aggregated'),
            'message' => new external_value(PARAM_TEXT, 'User-facing status message'),
        ]);
    }
}