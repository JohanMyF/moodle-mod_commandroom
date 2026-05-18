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

class get_decisions extends external_api {

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

        $sql = "SELECT *
                  FROM {commandroom_runs}
                 WHERE commandroomid = ?
                   AND groupid = ?
                   AND status IN ('inprogress', 'draft', 'completed')
              ORDER BY CASE status
                           WHEN 'inprogress' THEN 0
                           WHEN 'draft' THEN 1
                           WHEN 'completed' THEN 2
                           ELSE 3
                       END,
                       id DESC";
        $run = $DB->get_record_sql($sql, [$commandroom->id, $groupid], IGNORE_MULTIPLE);

        if (!$run) {
            return [
                'runid' => 0,
                'decisions' => [],
            ];
        }

        $records = $DB->get_records('commandroom_decisions', ['runid' => $run->id], 'nodeid ASC, id ASC');

        $decisions = [];
        foreach ($records as $record) {
            $decisions[] = [
                'id' => (int)$record->id,
                'runid' => (int)$record->runid,
                'nodeid' => (int)$record->nodeid,
                'leaderid' => (int)$record->leaderid,
                'decisiontype' => (string)$record->decisiontype,
                'selectedvalue' => (float)$record->selectedvalue,
                'timecreated' => (int)$record->timecreated,
                'timemodified' => (int)$record->timemodified,
            ];
        }

        return [
            'runid' => (int)$run->id,
            'decisions' => $decisions,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'runid' => new external_value(PARAM_INT, 'Active run id, or 0 if none exists'),
            'decisions' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Decision id'),
                    'runid' => new external_value(PARAM_INT, 'Run id'),
                    'nodeid' => new external_value(PARAM_INT, 'Node id'),
                    'leaderid' => new external_value(PARAM_INT, 'Leader user id'),
                    'decisiontype' => new external_value(PARAM_ALPHA, 'Decision type'),
                    'selectedvalue' => new external_value(PARAM_FLOAT, 'Selected numeric value'),
                    'timecreated' => new external_value(PARAM_INT, 'Creation timestamp'),
                    'timemodified' => new external_value(PARAM_INT, 'Modification timestamp'),
                ])
            ),
        ]);
    }
}
