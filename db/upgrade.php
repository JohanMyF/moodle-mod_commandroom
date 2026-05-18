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
 * Upgrade steps for mod_commandroom.
 *
 * @package    mod_commandroom
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute mod_commandroom upgrade steps.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_commandroom_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026042206) {
        $table = new xmldb_table('commandroom_edges');

        $field = new xmldb_field('polarity', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'neutral', 'functionconfig');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('label', XMLDB_TYPE_TEXT, null, null, null, null, null, 'polarity');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('loopgroup', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'label');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('curvature', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'loopgroup');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026042206, 'commandroom');
    }

    if ($oldversion < 2026042207) {
        $table = new xmldb_table('commandroom');

        $field = new xmldb_field('systembrief', XMLDB_TYPE_TEXT, null, null, null, null, null, 'useshocks');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('studentdecision', XMLDB_TYPE_TEXT, null, null, null, null, null, 'systembrief');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('learninggoal', XMLDB_TYPE_TEXT, null, null, null, null, null, 'studentdecision');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('riskychoice', XMLDB_TYPE_TEXT, null, null, null, null, null, 'learninggoal');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('safechoice', XMLDB_TYPE_TEXT, null, null, null, null, null, 'riskychoice');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('nodeinventory', XMLDB_TYPE_TEXT, null, null, null, null, null, 'safechoice');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026042207, 'commandroom');
    }


    if ($oldversion < 2026042208) {
        $table = new xmldb_table('commandroom');

        $field = new xmldb_field('presetkey', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, 'custom', 'nodeinventory');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026042208, 'commandroom');
    }


    if ($oldversion < 2026042209) {
        $table = new xmldb_table('commandroom_group_leaders');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('commandroomid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('leaderid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            $table->add_index('commandroomidx', XMLDB_INDEX_NOTUNIQUE, ['commandroomid']);
            $table->add_index('groupidx', XMLDB_INDEX_NOTUNIQUE, ['groupid']);
            $table->add_index('leaderidx', XMLDB_INDEX_NOTUNIQUE, ['leaderid']);
            $table->add_index('commandroomgroupuidx', XMLDB_INDEX_UNIQUE, ['commandroomid', 'groupid']);

            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026042209, 'commandroom');
    }

    return true;
}
