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
 * Backup structure steps for mod_commandroom.
 *
 * @package    mod_commandroom
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Defines the CommandRoom backup structure.
 */
class backup_commandroom_activity_structure_step extends backup_activity_structure_step {

    /**
     * Define the complete backup structure for a CommandRoom activity.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $commandroom = new backup_nested_element('commandroom', ['id'], [
            'name', 'intro', 'introformat', 'timesteplabel', 'stepduration',
            'stepdurationunit', 'totaliterations', 'useshocks', 'systembrief',
            'studentdecision', 'learninggoal', 'riskychoice', 'safechoice',
            'nodeinventory', 'presetkey', 'timecreated', 'timemodified',
        ]);

        $nodes = new backup_nested_element('nodes');
        $node = new backup_nested_element('node', ['id'], [
            'name', 'nodetype', 'initialvalue', 'minimumvalue', 'maximumvalue',
            'studentcontrolled', 'visibletostudents', 'svgfileitemid',
            'updateconfig', 'visualconfig', 'calculationconfig', 'description',
            'unitlabel', 'interpretation', 'displayorder',
        ]);

        $edges = new backup_nested_element('edges');
        $edge = new backup_nested_element('edge', ['id'], [
            'sourcenodeid', 'targetnodeid', 'relationtype', 'strength',
            'delayiterations', 'functionconfig', 'polarity', 'label',
            'loopgroup', 'curvature', 'visibletostudents',
        ]);

        $shocks = new backup_nested_element('shocks');
        $shock = new backup_nested_element('shock', ['id'], [
            'nodeid', 'iterationno', 'adjustment', 'shocktype', 'minadjustment',
            'maxadjustment', 'applyeveryiteration', 'visibletostudents', 'description',
        ]);

        $runs = new backup_nested_element('runs');
        $run = new backup_nested_element('run', ['id'], [
            'groupid', 'leaderid', 'status', 'currentiteration', 'totaliterations',
            'scenarioversion', 'timesubmitted', 'submittedby', 'timeinvalidated',
            'invalidatedreason', 'finalscore', 'timecreated', 'timemodified', 'timecompleted',
        ]);

        $proposals = new backup_nested_element('proposals');
        $proposal = new backup_nested_element('proposal', ['id'], [
            'nodeid', 'userid', 'proposedvalue', 'rationale', 'timecreated', 'timemodified',
        ]);

        $decisions = new backup_nested_element('decisions');
        $decision = new backup_nested_element('decision', ['id'], [
            'nodeid', 'leaderid', 'decisiontype', 'selectedvalue', 'timecreated', 'timemodified',
        ]);

        $results = new backup_nested_element('results');
        $result = new backup_nested_element('result', ['id'], [
            'iterationno', 'nodeid', 'nodevalue', 'valueorigin', 'timecreated',
        ]);

        $submissions = new backup_nested_element('submissions');
        $submission = new backup_nested_element('submission', ['id'], [
            'groupid', 'scenarioversion', 'submittedby', 'timecreated',
            'snapshotjson', 'finaliteration',
        ]);

        $exports = new backup_nested_element('exports');
        $export = new backup_nested_element('export', ['id'], [
            'userid', 'name', 'jsonhash', 'timecreated',
        ]);

        $commandroom->add_child($nodes);
        $nodes->add_child($node);

        $commandroom->add_child($edges);
        $edges->add_child($edge);

        $commandroom->add_child($shocks);
        $shocks->add_child($shock);

        $commandroom->add_child($runs);
        $runs->add_child($run);
        $run->add_child($proposals);
        $proposals->add_child($proposal);
        $run->add_child($decisions);
        $decisions->add_child($decision);
        $run->add_child($results);
        $results->add_child($result);
        $run->add_child($submissions);
        $submissions->add_child($submission);

        $commandroom->add_child($exports);
        $exports->add_child($export);

        $commandroom->set_source_table('commandroom', ['id' => backup::VAR_ACTIVITYID]);
        $node->set_source_table('commandroom_nodes', ['commandroomid' => backup::VAR_PARENTID], 'displayorder ASC, id ASC');
        $edge->set_source_table('commandroom_edges', ['commandroomid' => backup::VAR_PARENTID], 'id ASC');
        $shock->set_source_table('commandroom_shocks', ['commandroomid' => backup::VAR_PARENTID], 'iterationno ASC, id ASC');

        if ($userinfo) {
            $run->set_source_table('commandroom_runs', ['commandroomid' => backup::VAR_PARENTID], 'id ASC');
            $proposal->set_source_table('commandroom_proposals', ['runid' => backup::VAR_PARENTID], 'id ASC');
            $decision->set_source_table('commandroom_decisions', ['runid' => backup::VAR_PARENTID], 'id ASC');
            $result->set_source_table('commandroom_results', ['runid' => backup::VAR_PARENTID], 'iterationno ASC, id ASC');
            $submission->set_source_table('commandroom_submissions', ['runid' => backup::VAR_PARENTID], 'id ASC');
            $export->set_source_table('commandroom_exports', ['commandroomid' => backup::VAR_PARENTID], 'id ASC');
        }

        $run->annotate_ids('group', 'groupid');
        $run->annotate_ids('user', 'leaderid');
        $run->annotate_ids('user', 'submittedby');
        $proposal->annotate_ids('user', 'userid');
        $decision->annotate_ids('user', 'leaderid');
        $submission->annotate_ids('group', 'groupid');
        $submission->annotate_ids('user', 'submittedby');
        $export->annotate_ids('user', 'userid');

        $commandroom->annotate_files('mod_commandroom', 'intro', null);

        return $this->prepare_activity_structure($commandroom);
    }
}
