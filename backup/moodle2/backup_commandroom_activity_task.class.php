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
 * Backup activity task for mod_commandroom.
 *
 * @package    mod_commandroom
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/commandroom/backup/moodle2/backup_commandroom_stepslib.php');

/**
 * Defines backup task for CommandRoom activities.
 */
class backup_commandroom_activity_task extends backup_activity_task {

    /**
     * Define activity-specific backup settings.
     */
    protected function define_my_settings() {
        // The standard activity user-info setting is provided by Moodle.
    }

    /**
     * Define activity-specific backup steps.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_commandroom_activity_structure_step('commandroom_structure', 'commandroom.xml'));
    }

    /**
     * Encode links to CommandRoom activities in content.
     *
     * @param string $content Content to encode.
     * @return string Encoded content.
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot . '/mod/commandroom', '#');

        // Link to list of CommandRoom activities in a course.
        $search = '#(' . $base . '/index.php\?id=)([0-9]+)#';
        $content = preg_replace($search, '$@COMMANDROOMINDEX*$2@$', $content);

        // Link to a specific CommandRoom activity by course-module id.
        $search = '#(' . $base . '/view.php\?id=)([0-9]+)#';
        $content = preg_replace($search, '$@COMMANDROOMVIEWBYID*$2@$', $content);

        return $content;
    }
}
