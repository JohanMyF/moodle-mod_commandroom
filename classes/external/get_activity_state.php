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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * External service for returning the basic activity state.
 *
 * @package    mod_commandroom
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_activity_state extends external_api {

    /**
     * Define parameters for the service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
        ]);
    }

    /**
     * Return the current activity state.
     *
     * @param int $cmid
     * @return array
     */
    public static function execute(int $cmid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
        ]);

        $cm = get_coursemodule_from_id('commandroom', $params['cmid'], 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        $commandroom = $DB->get_record('commandroom', ['id' => $cm->instance], '*', MUST_EXIST);

        require_login($course, true, $cm);

        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/commandroom:view', $context);

        // Bulk-load authoring data. No N+1 queries.
        $nodes = $DB->get_records(
            'commandroom_nodes',
            ['commandroomid' => $commandroom->id],
            'displayorder ASC, id ASC'
        );

        $edges = $DB->get_records(
            'commandroom_edges',
            ['commandroomid' => $commandroom->id],
            'id ASC'
        );

        $shocks = $DB->get_records(
            'commandroom_shocks',
            ['commandroomid' => $commandroom->id],
            'iterationno ASC, id ASC'
        );

        $nodedata = [];
        foreach ($nodes as $node) {
            $nodedata[] = [
                'id' => (int)$node->id,
                'name' => (string)$node->name,
                'nodetype' => (string)$node->nodetype,
                'initialvalue' => (float)$node->initialvalue,
                'minvalue' => $node->minvalue !== null ? (float)$node->minvalue : null,
                'maxvalue' => $node->maxvalue !== null ? (float)$node->maxvalue : null,
                'studentcontrolled' => (int)$node->studentcontrolled,
                'visibletostudents' => (int)$node->visibletostudents,
                'svgfileitemid' => (int)$node->svgfileitemid,
                'displayorder' => (int)$node->displayorder,
            ];
        }

        $edgedata = [];
        foreach ($edges as $edge) {
            $edgedata[] = [
                'id' => (int)$edge->id,
                'sourcenodeid' => (int)$edge->sourcenodeid,
                'targetnodeid' => (int)$edge->targetnodeid,
                'relationtype' => (string)$edge->relationtype,
                'strength' => (float)$edge->strength,
                'delayiterations' => (int)$edge->delayiterations,
                'functionconfig' => $edge->functionconfig !== null ? (string)$edge->functionconfig : '',
                'visibletostudents' => (int)$edge->visibletostudents,
            ];
        }

        $shockdata = [];
        foreach ($shocks as $shock) {
            $shockdata[] = [
                'id' => (int)$shock->id,
                'nodeid' => (int)$shock->nodeid,
                'iterationno' => (int)$shock->iterationno,
                'adjustment' => (float)$shock->adjustment,
                'visibletostudents' => (int)$shock->visibletostudents,
                'description' => $shock->description !== null ? (string)$shock->description : '',
            ];
        }

        return [
            'commandroomid' => (int)$commandroom->id,
            'cmid' => (int)$cm->id,
            'name' => (string)format_string($commandroom->name, true, ['context' => $context]),
            'intro' => (string)format_module_intro('commandroom', $commandroom, $cm->id),
            'timesteplabel' => (string)$commandroom->timesteplabel,
            'stepduration' => (int)$commandroom->stepduration,
            'stepdurationunit' => (string)$commandroom->stepdurationunit,
            'totaliterations' => (int)$commandroom->totaliterations,
            'useshocks' => (int)$commandroom->useshocks,
            'nodes' => $nodedata,
            'edges' => $edgedata,
            'shocks' => $shockdata,
        ];
    }

    /**
     * Define return values for the service.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'commandroomid' => new external_value(PARAM_INT, 'Activity instance id'),
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'name' => new external_value(PARAM_TEXT, 'Formatted activity name'),
            'intro' => new external_value(PARAM_RAW, 'Formatted introduction'),
            'timesteplabel' => new external_value(PARAM_TEXT, 'Time step label'),
            'stepduration' => new external_value(PARAM_INT, 'Step duration'),
            'stepdurationunit' => new external_value(PARAM_TEXT, 'Step duration unit'),
            'totaliterations' => new external_value(PARAM_INT, 'Total iterations'),
            'useshocks' => new external_value(PARAM_INT, 'Whether shocks are enabled'),
            'nodes' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Node id'),
                    'name' => new external_value(PARAM_TEXT, 'Node name'),
                    'nodetype' => new external_value(PARAM_TEXT, 'Node type'),
                    'initialvalue' => new external_value(PARAM_FLOAT, 'Initial value'),
                    'minvalue' => new external_value(PARAM_FLOAT, 'Minimum value', VALUE_OPTIONAL),
                    'maxvalue' => new external_value(PARAM_FLOAT, 'Maximum value', VALUE_OPTIONAL),
                    'studentcontrolled' => new external_value(PARAM_INT, 'Whether students control the node'),
                    'visibletostudents' => new external_value(PARAM_INT, 'Whether visible to students'),
                    'svgfileitemid' => new external_value(PARAM_INT, 'SVG file item id'),
                    'displayorder' => new external_value(PARAM_INT, 'Display order'),
                ])
            ),
            'edges' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Edge id'),
                    'sourcenodeid' => new external_value(PARAM_INT, 'Source node id'),
                    'targetnodeid' => new external_value(PARAM_INT, 'Target node id'),
                    'relationtype' => new external_value(PARAM_TEXT, 'Relationship type'),
                    'strength' => new external_value(PARAM_FLOAT, 'Relationship strength'),
                    'delayiterations' => new external_value(PARAM_INT, 'Delay in iterations'),
                    'functionconfig' => new external_value(PARAM_RAW, 'Function configuration', VALUE_OPTIONAL),
                    'visibletostudents' => new external_value(PARAM_INT, 'Whether visible to students'),
                ])
            ),
            'shocks' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Shock id'),
                    'nodeid' => new external_value(PARAM_INT, 'Affected node id'),
                    'iterationno' => new external_value(PARAM_INT, 'Iteration number'),
                    'adjustment' => new external_value(PARAM_FLOAT, 'Adjustment value'),
                    'visibletostudents' => new external_value(PARAM_INT, 'Whether visible to students'),
                    'description' => new external_value(PARAM_RAW, 'Shock description', VALUE_OPTIONAL),
                ])
            ),
        ]);
    }
}