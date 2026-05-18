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
 * Course index page for mod_commandroom.
 *
 * @package    mod_commandroom
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$courseid = required_param('id', PARAM_INT);

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($course->id);
require_capability('mod/commandroom:view', $context);

$PAGE->set_url('/mod/commandroom/index.php', ['id' => $course->id]);
$PAGE->set_title(get_string('modulenameplural', 'mod_commandroom'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('modulenameplural', 'mod_commandroom'));

if (!$commandrooms = get_all_instances_in_course('commandroom', $course)) {
    notice(get_string('nocommandrooms', 'mod_commandroom'), new moodle_url('/course/view.php', ['id' => $course->id]));
    exit;
}

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

if ($course->format === 'weeks' || $course->format === 'topics') {
    $table->head = [
        get_string('sectionname', 'format_' . $course->format),
        get_string('name'),
        get_string('group', 'mod_commandroom'),
    ];
    $table->align = ['center', 'left', 'left'];
} else {
    $table->head = [
        get_string('name'),
        get_string('group', 'mod_commandroom'),
    ];
    $table->align = ['left', 'left'];
}

foreach ($commandrooms as $commandroom) {
    $link = new moodle_url('/mod/commandroom/view.php', ['id' => $commandroom->coursemodule]);
    $name = format_string($commandroom->name, true, ['context' => context_module::instance($commandroom->coursemodule)]);

    if (!$commandroom->visible) {
        $name = html_writer::link($link, $name, ['class' => 'dimmed']);
    } else {
        $name = html_writer::link($link, $name);
    }

    $grouptext = '-';

    if ($course->format === 'weeks' || $course->format === 'topics') {
        $table->data[] = [
            $commandroom->section,
            $name,
            $grouptext,
        ];
    } else {
        $table->data[] = [
            $name,
            $grouptext,
        ];
    }
}

echo html_writer::table($table);

echo $OUTPUT->footer();