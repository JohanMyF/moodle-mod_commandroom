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

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Moodle form for configuring mod_commandroom instances.
 *
 * @package    mod_commandroom
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_commandroom_mod_form extends moodleform_mod {

    /**
     * Load packaged preset metadata from presets/presets.json.
     *
     * The presets themselves are JSON system definitions. This method only reads
     * the lightweight index used to populate the teacher-facing starter selector.
     *
     * @return array
     */
    private function get_preset_index(): array {
        global $CFG;

        $presetfile = $CFG->dirroot . '/mod/commandroom/presets/presets.json';
        if (!file_exists($presetfile) || !is_readable($presetfile)) {
            return [];
        }

        $rawjson = file_get_contents($presetfile);
        if ($rawjson === false || trim($rawjson) === '') {
            return [];
        }

        $decoded = json_decode($rawjson, true);
        if (!is_array($decoded)) {
            return [];
        }

        // Support either a plain array of presets or an object containing a
        // "presets" array. This keeps the form tolerant while the index evolves.
        $presets = $decoded['presets'] ?? $decoded;
        if (!is_array($presets)) {
            return [];
        }

        $clean = [];
        foreach ($presets as $preset) {
            if (!is_array($preset)) {
                continue;
            }

            $key = isset($preset['key']) ? clean_param((string)$preset['key'], PARAM_ALPHANUMEXT) : '';
            $file = isset($preset['file']) ? clean_param((string)$preset['file'], PARAM_FILE) : '';

            if ($key === '' || $file === '') {
                continue;
            }

            $preset['key'] = $key;
            $preset['file'] = $file;
            $preset['order'] = isset($preset['order']) ? (int)$preset['order'] : 999;
            $preset['firstrelease'] = array_key_exists('firstrelease', $preset) ? !empty($preset['firstrelease']) : true;

            if ($preset['firstrelease']) {
                $clean[] = $preset;
            }
        }

        usort($clean, function(array $a, array $b): int {
            $ordercompare = ($a['order'] ?? 999) <=> ($b['order'] ?? 999);
            if ($ordercompare !== 0) {
                return $ordercompare;
            }

            return strcmp((string)($a['title'] ?? $a['key']), (string)($b['title'] ?? $b['key']));
        });

        return $clean;
    }

    /**
     * Build select options for the packaged starter-system presets.
     *
     * @return array
     */
    private function get_preset_options(): array {
        $options = [
            'custom' => get_string('presetoptioncustom', 'mod_commandroom'),
        ];

        foreach ($this->get_preset_index() as $preset) {
            $title = trim((string)($preset['cardtitle'] ?? $preset['title'] ?? $preset['key']));
            $shape = trim((string)($preset['graphdescription'] ?? ''));

            if ($shape !== '') {
                $options[$preset['key']] = $title . ' — ' . $shape;
            } else {
                $options[$preset['key']] = $title;
            }
        }

        return $options;
    }

    /**
     * Render a compact visual guide to the packaged starter-system presets.
     *
     * @return string
     */
    private function render_preset_cards(): string {
        $presets = $this->get_preset_index();

        $intro = html_writer::tag(
            'p',
            get_string('presetintro', 'mod_commandroom')
        );

        if (empty($presets)) {
            return $intro . html_writer::div(
                get_string('presetnotfound', 'mod_commandroom'),
                'alert alert-info'
            );
        }

        $cards = '';
        foreach ($presets as $preset) {
            $title = s((string)($preset['cardtitle'] ?? $preset['title'] ?? $preset['key']));
            $example = s((string)($preset['example'] ?? ''));
            $description = s((string)($preset['graphdescription'] ?? $preset['description'] ?? ''));

            $body = html_writer::tag('strong', $title, ['class' => 'commandroom-preset-card-title']);
            if ($description !== '') {
                $body .= html_writer::div($description, 'commandroom-preset-card-description');
            }
            if ($example !== '') {
                $body .= html_writer::div(get_string('presetexampleprefix', 'mod_commandroom', $example), 'commandroom-preset-card-example');
            }

            $cards .= html_writer::div($body, 'commandroom-preset-card');
        }

        $html = $intro;
        $html .= html_writer::div($cards, 'commandroom-preset-card-grid');
        $html .= html_writer::tag(
            'p',
            get_string('presettip', 'mod_commandroom'),
            ['class' => 'form-text text-muted']
        );

        return $html;
    }


    /**
     * Get existing group leader assignments for the current activity instance.
     *
     * @return array
     */
    private function get_existing_group_leaders(): array {
        global $DB;

        if (empty($this->current->instance)) {
            return [];
        }

        return $DB->get_records_menu('commandroom_group_leaders',
            ['commandroomid' => (int)$this->current->instance], '', 'groupid, leaderid');
    }

    /**
     * Build group and member data for leader selectors without per-group queries.
     *
     * @return array
     */
    private function get_group_leader_selector_data(): array {
        global $DB;

        $courseid = !empty($this->current->course) ? (int)$this->current->course : 0;
        if ($courseid <= 0) {
            return [];
        }

        $userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $sql = "SELECT gm.id AS membershipid, g.id AS groupid, g.name AS groupname, u.id AS userid, $userfields
                  FROM {groups} g
             LEFT JOIN {groups_members} gm ON gm.groupid = g.id
             LEFT JOIN {user} u ON u.id = gm.userid AND u.deleted = 0
                 WHERE g.courseid = :courseid
              ORDER BY g.name ASC, u.lastname ASC, u.firstname ASC";

        $records = $DB->get_records_sql($sql, ['courseid' => $courseid]);
        $groups = [];

        foreach ($records as $record) {
            $groupid = (int)$record->groupid;
            if (!isset($groups[$groupid])) {
                $groups[$groupid] = [
                    'id' => $groupid,
                    'name' => (string)$record->groupname,
                    'members' => [],
                ];
            }

            if (!empty($record->userid)) {
                $groups[$groupid]['members'][(int)$record->userid] = fullname($record);
            }
        }

        return $groups;
    }

    /**
     * Add group leader selectors to the form.
     *
     * @param MoodleQuickForm $mform
     */
    private function add_group_leader_elements(MoodleQuickForm $mform): void {
        $groups = $this->get_group_leader_selector_data();
        $existingleaders = $this->get_existing_group_leaders();

        $mform->addElement('header', 'groupleaderheader', get_string('groupleaderheader', 'mod_commandroom'));
        $mform->addElement(
            'static',
            'groupleaderintro',
            '',
            html_writer::tag('p', get_string('groupleaderintro', 'mod_commandroom'), ['class' => 'form-text text-muted'])
        );

        if (empty($groups)) {
            $mform->addElement(
                'static',
                'nogroupsavailable',
                '',
                html_writer::div(get_string('nogroupsavailable', 'mod_commandroom'), 'alert alert-info')
            );
            return;
        }

        foreach ($groups as $group) {
            $elementname = 'groupleader_' . (int)$group['id'];
            $options = [0 => get_string('groupleadernone', 'mod_commandroom')];
            foreach ($group['members'] as $userid => $fullname) {
                $options[(int)$userid] = $fullname;
            }

            $mform->addElement(
                'select',
                $elementname,
                get_string('groupleaderforgroup', 'mod_commandroom', format_string($group['name'])),
                $options
            );
            $mform->setType($elementname, PARAM_INT);

            if (isset($existingleaders[(int)$group['id']])) {
                $mform->setDefault($elementname, (int)$existingleaders[(int)$group['id']]);
            }
        }
    }


    /**
     * Defines the form.
     */
    public function definition() {
        $mform = $this->_form;

        // General section.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement(
            'text',
            'name',
            get_string('commandroomname', 'mod_commandroom'),
            ['size' => '64']
        );
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements(get_string('commandroomintro', 'mod_commandroom'));


        // Starter system / preset section.
        $mform->addElement('header', 'presetheader', get_string('presetheader', 'mod_commandroom'));

        $mform->addElement(
            'static',
            'presetoverview',
            '',
            $this->render_preset_cards()
        );

        $mform->addElement(
            'select',
            'presetkey',
            get_string('presetselectlabel', 'mod_commandroom'),
            $this->get_preset_options()
        );
        $mform->setType('presetkey', PARAM_ALPHANUMEXT);
        $mform->setDefault('presetkey', 'custom');

        // System brief section.
        $mform->addElement('header', 'systembriefheader', get_string('systembriefheader', 'mod_commandroom'));

        $mform->addElement(
            'textarea',
            'systembrief',
            get_string('systembrief', 'mod_commandroom'),
            [
                'rows' => 4,
                'cols' => 80,
                'placeholder' => get_string('systembriefplaceholder', 'mod_commandroom'),
            ]
        );
        $mform->setType('systembrief', PARAM_TEXT);
        $mform->addHelpButton('systembrief', 'systembrief', 'mod_commandroom');

        $mform->addElement(
            'textarea',
            'studentdecision',
            get_string('studentdecision', 'mod_commandroom'),
            [
                'rows' => 3,
                'cols' => 80,
                'placeholder' => get_string('studentdecisionplaceholder', 'mod_commandroom'),
            ]
        );
        $mform->setType('studentdecision', PARAM_TEXT);
        $mform->addHelpButton('studentdecision', 'studentdecision', 'mod_commandroom');

        $mform->addElement(
            'textarea',
            'learninggoal',
            get_string('learninggoal', 'mod_commandroom'),
            [
                'rows' => 3,
                'cols' => 80,
                'placeholder' => get_string('learninggoalplaceholder', 'mod_commandroom'),
            ]
        );
        $mform->setType('learninggoal', PARAM_TEXT);
        $mform->addHelpButton('learninggoal', 'learninggoal', 'mod_commandroom');

        $mform->addElement(
            'textarea',
            'riskychoice',
            get_string('riskychoice', 'mod_commandroom'),
            [
                'rows' => 3,
                'cols' => 80,
                'placeholder' => get_string('riskychoiceplaceholder', 'mod_commandroom'),
            ]
        );
        $mform->setType('riskychoice', PARAM_TEXT);

        $mform->addElement(
            'textarea',
            'safechoice',
            get_string('safechoice', 'mod_commandroom'),
            [
                'rows' => 3,
                'cols' => 80,
                'placeholder' => get_string('safechoiceplaceholder', 'mod_commandroom'),
            ]
        );
        $mform->setType('safechoice', PARAM_TEXT);

        $mform->addElement(
            'textarea',
            'nodeinventory',
            get_string('nodeinventory', 'mod_commandroom'),
            [
                'rows' => 8,
                'cols' => 80,
                'placeholder' => get_string('nodeinventoryplaceholder', 'mod_commandroom'),
            ]
        );
        $mform->setType('nodeinventory', PARAM_TEXT);
        $mform->addHelpButton('nodeinventory', 'nodeinventory', 'mod_commandroom');

        // Simulation settings.
        $mform->addElement('header', 'simulationsettings', get_string('simulation', 'mod_commandroom'));

        $mform->addElement(
            'text',
            'timesteplabel',
            get_string('timesteplabel', 'mod_commandroom'),
            ['size' => '32']
        );
        $mform->setType('timesteplabel', PARAM_TEXT);
        $mform->setDefault('timesteplabel', 'period');
        $mform->addHelpButton('timesteplabel', 'timesteplabel', 'mod_commandroom');

        $mform->addElement(
            'text',
            'stepduration',
            get_string('stepduration', 'mod_commandroom'),
            ['size' => '10']
        );
        $mform->setType('stepduration', PARAM_INT);
        $mform->setDefault('stepduration', 1);
        $mform->addRule('stepduration', null, 'required', null, 'client');
        $mform->addRule('stepduration', null, 'numeric', null, 'client');

        $durationunits = [
            'iteration' => get_string('iterationunit', 'mod_commandroom'),
            'hour' => get_string('hourunit', 'mod_commandroom'),
            'day' => get_string('dayunit', 'mod_commandroom'),
            'week' => get_string('weekunit', 'mod_commandroom'),
            'month' => get_string('monthunit', 'mod_commandroom'),
            'quarter' => get_string('quarterunit', 'mod_commandroom'),
            'year' => get_string('yearunit', 'mod_commandroom'),
        ];
        $mform->addElement(
            'select',
            'stepdurationunit',
            get_string('stepdurationunit', 'mod_commandroom'),
            $durationunits
        );
        $mform->setDefault('stepdurationunit', 'iteration');

        $mform->addElement(
            'text',
            'totaliterations',
            get_string('totaliterations', 'mod_commandroom'),
            ['size' => '10']
        );
        $mform->setType('totaliterations', PARAM_INT);
        $mform->setDefault('totaliterations', 1);
        $mform->addRule('totaliterations', null, 'required', null, 'client');
        $mform->addRule('totaliterations', null, 'numeric', null, 'client');

        $mform->addElement(
            'advcheckbox',
            'useshocks',
            get_string('useshocks', 'mod_commandroom')
        );
        $mform->setDefault('useshocks', 0);

        // System management section.
        $mform->addElement('header', 'builderlaunchheader', get_string('systemmanagement', 'mod_commandroom'));

        if (!empty($this->current->coursemodule)) {
            $cmid = (int)$this->current->coursemodule;
            $builderurl = new moodle_url('/mod/commandroom/builder.php', ['id' => $cmid]);
            $importurl = new moodle_url('/mod/commandroom/edit_system.php', ['id' => $cmid]);
            $exporturl = new moodle_url('/mod/commandroom/export_system.php', ['id' => $cmid]);

            $systemactions = html_writer::link(
                $builderurl,
                get_string('openbuilder', 'mod_commandroom'),
                ['class' => 'btn btn-primary']
            ) . ' ' . html_writer::link(
                $importurl,
                get_string('importjson', 'mod_commandroom'),
                ['class' => 'btn btn-outline-secondary']
            ) . ' ' . html_writer::link(
                $exporturl,
                get_string('exportjson', 'mod_commandroom'),
                ['class' => 'btn btn-outline-secondary']
            );

            $mform->addElement(
                'static',
                'builderlaunch',
                get_string('systemmanagementactions', 'mod_commandroom'),
                html_writer::div($systemactions, 'commandroom-system-management-actions') .
                    html_writer::tag('p', get_string('systemmanagementhelp', 'mod_commandroom'), ['class' => 'form-text text-muted'])
            );
        } else {
            $disabledactions = html_writer::tag(
                'button',
                get_string('openbuilder', 'mod_commandroom'),
                ['type' => 'button', 'class' => 'btn btn-primary', 'disabled' => 'disabled']
            ) . ' ' . html_writer::tag(
                'button',
                get_string('importjson', 'mod_commandroom'),
                ['type' => 'button', 'class' => 'btn btn-outline-secondary', 'disabled' => 'disabled']
            ) . ' ' . html_writer::tag(
                'button',
                get_string('exportjson', 'mod_commandroom'),
                ['type' => 'button', 'class' => 'btn btn-outline-secondary', 'disabled' => 'disabled']
            );

            $mform->addElement(
                'static',
                'builderlaunch',
                get_string('systemmanagementactions', 'mod_commandroom'),
                html_writer::div($disabledactions, 'commandroom-system-management-actions') .
                    html_writer::tag('p', get_string('systemmanagementsavefirst', 'mod_commandroom'), ['class' => 'form-text text-muted'])
            );
        }

        // Group leadership section.
        $this->add_group_leader_elements($mform);

        // Standard course module elements.
        $this->standard_coursemodule_elements();

        // Standard buttons.
        $this->add_action_buttons();
    }

    /**
     * Extra validation for the form.
     *
     * @param array $data Submitted data
     * @param array $files Submitted files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!isset($data['name']) || trim($data['name']) === '') {
            $errors['name'] = get_string('required');
        }

        if (!isset($data['timesteplabel']) || trim($data['timesteplabel']) === '') {
            $errors['timesteplabel'] = get_string('required');
        }

        if (!isset($data['stepduration']) || (int)$data['stepduration'] < 1) {
            $errors['stepduration'] = get_string('error:stepdurationpositive', 'mod_commandroom');
        }

        if (!isset($data['totaliterations']) || (int)$data['totaliterations'] < 1) {
            $errors['totaliterations'] = get_string('error:totaliterationspositive', 'mod_commandroom');
        }


        if (isset($data['presetkey']) && !preg_match('/^[a-zA-Z0-9_-]+$/', (string)$data['presetkey'])) {
            $errors['presetkey'] = get_string('invaliddata', 'error');
        }

        return $errors;
    }
}