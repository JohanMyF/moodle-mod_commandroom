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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

class get_results extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
        ]);
    }

    public static function execute(int $cmid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);

        $cm = get_coursemodule_from_id('commandroom', $params['cmid'], 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        $commandroom = $DB->get_record('commandroom', ['id' => $cm->instance], '*', MUST_EXIST);

        require_login($course, true, $cm);

        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/commandroom:view', $context);

        $groupid = commandroom_get_user_groupid((int)$course->id, (int)$USER->id, $cm->groupingid ?: 0);

        $sql = "SELECT r.*
                  FROM {commandroom_runs} r
                 WHERE r.commandroomid = ?
                   AND r.groupid = ?
                   AND r.status IN ('inprogress', 'draft', 'completed')
                   AND EXISTS (
                       SELECT 1
                         FROM {commandroom_results} cr
                        WHERE cr.runid = r.id
                   )
              ORDER BY CASE r.status
                           WHEN 'inprogress' THEN 0
                           WHEN 'draft' THEN 1
                           WHEN 'completed' THEN 2
                           ELSE 3
                       END,
                       r.id DESC";
        $run = $DB->get_record_sql($sql, [$commandroom->id, $groupid], IGNORE_MULTIPLE);

        if (!$run) {
            return [
                'runid' => 0,
                'iterationno' => 0,
                'results' => [],
            ];
        }

        $latestiteration = $DB->get_field_sql(
            "SELECT MAX(iterationno)
               FROM {commandroom_results}
              WHERE runid = ?",
            [$run->id]
        );

        if ($latestiteration === false || $latestiteration === null) {
            return [
                'runid' => (int)$run->id,
                'iterationno' => 0,
                'results' => [],
            ];
        }

        $latestiteration = (int)$latestiteration;

        $records = $DB->get_records('commandroom_results', [
            'runid' => $run->id,
            'iterationno' => $latestiteration,
        ], 'nodeid ASC, id ASC');

        $results = [];
        foreach ($records as $record) {
            $results[] = [
                'id' => (int)$record->id,
                'runid' => (int)$record->runid,
                'iterationno' => (int)$record->iterationno,
                'nodeid' => (int)$record->nodeid,
                'nodevalue' => (float)$record->nodevalue,
                'valueorigin' => (string)$record->valueorigin,
                'timecreated' => (int)$record->timecreated,
            ];
        }

        return [
            'runid' => (int)$run->id,
            'iterationno' => $latestiteration,
            'results' => $results,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'runid' => new external_value(PARAM_INT, 'Active run id, or 0 if none exists'),
            'iterationno' => new external_value(PARAM_INT, 'Latest iteration number, or 0 if none exists'),
            'results' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Result id'),
                    'runid' => new external_value(PARAM_INT, 'Run id'),
                    'iterationno' => new external_value(PARAM_INT, 'Iteration number'),
                    'nodeid' => new external_value(PARAM_INT, 'Node id'),
                    'nodevalue' => new external_value(PARAM_FLOAT, 'Authoritative node value'),
                    'valueorigin' => new external_value(PARAM_TEXT, 'Origin of the value'),
                    'timecreated' => new external_value(PARAM_INT, 'Creation timestamp'),
                ])
            ),
        ]);
    }
}
