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
 * Restore activity task for mod_commandroom.
 *
 * @package    mod_commandroom
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/commandroom/backup/moodle2/restore_commandroom_stepslib.php');

/**
 * Defines restore task for CommandRoom activities.
 */
class restore_commandroom_activity_task extends restore_activity_task {

    /**
     * Define activity-specific restore settings.
     */
    protected function define_my_settings() {
        // No activity-specific settings beyond Moodle's standard activity settings.
    }

    /**
     * Define activity-specific restore steps.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_commandroom_activity_structure_step('commandroom_structure', 'commandroom.xml'));
    }

    /**
     * Define content link decoding rules.
     *
     * @return restore_decode_rule[]
     */
    public static function define_decode_contents() {
        return [
            new restore_decode_content('commandroom', ['intro'], 'commandroom'),
        ];
    }

    /**
     * Define link decoding rules.
     *
     * @return restore_decode_rule[]
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('COMMANDROOMVIEWBYID', '/mod/commandroom/view.php?id=$1', 'course_module'),
            new restore_decode_rule('COMMANDROOMINDEX', '/mod/commandroom/index.php?id=$1', 'course'),
        ];
    }

    /**
     * Define restore log rules.
     *
     * @return array
     */
    public static function define_restore_log_rules() {
        return [
            new restore_log_rule('commandroom', 'view', 'view.php?id={course_module}', '{commandroom}'),
        ];
    }

    /**
     * Define restore log rules for course-level logs.
     *
     * @return array
     */
    public static function define_restore_log_rules_for_course() {
        return [
            new restore_log_rule('commandroom', 'view all', 'index.php?id={course}', null),
        ];
    }
}
