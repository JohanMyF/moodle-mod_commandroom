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

defined('MOODLE_INTERNAL') || die();

/**
 * External services for mod_commandroom.
 *
 * @package    mod_commandroom
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$functions = [
    'mod_commandroom_get_activity_state' => [
        'classname' => 'mod_commandroom\external\get_activity_state',
        'methodname' => 'execute',
        'classpath' => '',
        'description' => 'Return the current authoring state for a Situation Room activity.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/commandroom:view',
    ],

    'mod_commandroom_submit_proposal' => [
        'classname' => 'mod_commandroom\external\submit_proposal',
        'methodname' => 'execute',
        'classpath' => '',
        'description' => 'Submit or update a student proposal for a student-controlled node.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/commandroom:submitproposal',
    ],

    'mod_commandroom_get_proposals' => [
        'classname' => 'mod_commandroom\external\get_proposals',
        'methodname' => 'execute',
        'classpath' => '',
        'description' => 'Return proposals for the current draft run for the user group.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/commandroom:view',
    ],

    'mod_commandroom_get_decisions' => [
        'classname' => 'mod_commandroom\external\get_decisions',
        'methodname' => 'execute',
        'classpath' => '',
        'description' => 'Return saved leader decisions for the current draft run for the user group.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/commandroom:view',
    ],

    'mod_commandroom_initialise_run_state' => [
        'classname' => 'mod_commandroom\external\initialise_run_state',
        'methodname' => 'execute',
        'classpath' => '',
        'description' => 'Initialise baseline iteration 0 state for a run.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/commandroom:leaddecision',
    ],

    'mod_commandroom_advance_simulation' => [
        'classname' => 'mod_commandroom\external\advance_simulation',
        'methodname' => 'execute',
        'classpath' => '',
        'description' => 'Advance the simulation from iteration i to iteration i+1.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/commandroom:leaddecision',
    ],

    'mod_commandroom_save_decision' => [
        'classname' => 'mod_commandroom\external\save_decision',
        'methodname' => 'execute',
        'classpath' => '',
        'description' => 'Save or update a leader decision for a controllable node.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/commandroom:submitproposal',
    ],
    
    'mod_commandroom_get_results' => [
    'classname' => 'mod_commandroom\external\get_results',
    'methodname' => 'execute',
    'classpath' => '',
    'description' => 'Return latest simulation results for the current draft run.',
    'type' => 'read',
    'ajax' => true,
    'capabilities' => 'mod/commandroom:view',
     ],
    
];

$services = [];