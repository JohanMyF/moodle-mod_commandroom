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
 * External service for initialising baseline run state.
 *
 * @package    mod_commandroom
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class initialise_run_state extends external_api {

    /**
     * Describe parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'runid' => new external_value(PARAM_INT, 'Run id'),
        ]);
    }

    /**
     * Initialise iteration 0 state for a run if it does not yet exist.
     *
     * @param int $cmid
     * @param int $runid
     * @return array
     */
    public static function execute(int $cmid, int $runid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'runid' => $runid,
        ]);

        $cm = get_coursemodule_from_id('commandroom', $params['cmid'], 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        $commandroom = $DB->get_record('commandroom', ['id' => $cm->instance], '*', MUST_EXIST);

        require_login($course, true, $cm);

        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/commandroom:submitproposal', $context);

        $run = $DB->get_record('commandroom_runs', [
            'id' => $params['runid'],
            'commandroomid' => $commandroom->id,
        ], '*', MUST_EXIST);

        if ((int)$run->leaderid !== (int)$USER->id) {
            throw new moodle_exception('error:notrunleader', 'mod_commandroom');
        }

        $iterationno = 0;

        $existingcount = $DB->count_records('commandroom_results', [
            'runid' => $run->id,
            'iterationno' => $iterationno,
        ]);

        if ($existingcount > 0) {
            return [
                'status' => 'ok',
                'initialised' => 0,
                'runid' => (int)$run->id,
                'iterationno' => $iterationno,
                'nodecount' => (int)$existingcount,
            ];
        }

        $nodes = $DB->get_records(
            'commandroom_nodes',
            ['commandroomid' => $commandroom->id],
            'displayorder ASC, id ASC'
        );

        $time = time();
        $transaction = $DB->start_delegated_transaction();

        foreach ($nodes as $node) {
            $record = new \stdClass();
            $record->runid = $run->id;
            $record->iterationno = $iterationno;
            $record->nodeid = (int)$node->id;
            $record->nodevalue = isset($node->initialvalue) ? (float)$node->initialvalue : 0;
            $record->valueorigin = 'initial';
            $record->timecreated = $time;

            $DB->insert_record('commandroom_results', $record);
        }

        $runupdate = new \stdClass();
        $runupdate->id = $run->id;
        $runupdate->timemodified = $time;
        $DB->update_record('commandroom_runs', $runupdate);

        $transaction->allow_commit();

        return [
            'status' => 'ok',
            'initialised' => 1,
            'runid' => (int)$run->id,
            'iterationno' => $iterationno,
            'nodecount' => count($nodes),
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
            'initialised' => new external_value(PARAM_INT, '1 if iteration 0 was created, 0 if it already existed'),
            'runid' => new external_value(PARAM_INT, 'Run id'),
            'iterationno' => new external_value(PARAM_INT, 'Initialised iteration number'),
            'nodecount' => new external_value(PARAM_INT, 'Number of state rows present for iteration 0'),
        ]);
    }
}