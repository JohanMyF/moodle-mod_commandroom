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
 * Restore structure steps for mod_commandroom.
 *
 * @package    mod_commandroom
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Defines the CommandRoom restore structure.
 */
class restore_commandroom_activity_structure_step extends restore_activity_structure_step {

    /**
     * Define restore paths.
     *
     * @return restore_path_element[]
     */
    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('commandroom', '/activity/commandroom');
        $paths[] = new restore_path_element('commandroom_node', '/activity/commandroom/nodes/node');
        $paths[] = new restore_path_element('commandroom_edge', '/activity/commandroom/edges/edge');
        $paths[] = new restore_path_element('commandroom_shock', '/activity/commandroom/shocks/shock');

        if ($userinfo) {
            $paths[] = new restore_path_element('commandroom_run', '/activity/commandroom/runs/run');
            $paths[] = new restore_path_element('commandroom_proposal', '/activity/commandroom/runs/run/proposals/proposal');
            $paths[] = new restore_path_element('commandroom_decision', '/activity/commandroom/runs/run/decisions/decision');
            $paths[] = new restore_path_element('commandroom_result', '/activity/commandroom/runs/run/results/result');
            $paths[] = new restore_path_element('commandroom_submission', '/activity/commandroom/runs/run/submissions/submission');
            $paths[] = new restore_path_element('commandroom_export', '/activity/commandroom/exports/export');
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore main CommandRoom record.
     *
     * @param array|stdClass $data Restored data.
     */
    protected function process_commandroom($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->course = $this->get_courseid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('commandroom', $data);
        $this->apply_activity_instance($newitemid);
        $this->set_mapping('commandroom', $oldid, $newitemid, true);
    }

    /**
     * Restore CommandRoom node.
     *
     * @param array|stdClass $data Restored data.
     */
    protected function process_commandroom_node($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->commandroomid = $this->get_new_parentid('commandroom');

        $newitemid = $DB->insert_record('commandroom_nodes', $data);
        $this->set_mapping('commandroom_node', $oldid, $newitemid);
    }

    /**
     * Restore CommandRoom edge.
     *
     * @param array|stdClass $data Restored data.
     */
    protected function process_commandroom_edge($data) {
        global $DB;

        $data = (object)$data;
        $data->commandroomid = $this->get_new_parentid('commandroom');
        $data->sourcenodeid = $this->get_mappingid('commandroom_node', $data->sourcenodeid);
        $data->targetnodeid = $this->get_mappingid('commandroom_node', $data->targetnodeid);

        if (empty($data->sourcenodeid) || empty($data->targetnodeid)) {
            return;
        }

        $DB->insert_record('commandroom_edges', $data);
    }

    /**
     * Restore CommandRoom shock.
     *
     * @param array|stdClass $data Restored data.
     */
    protected function process_commandroom_shock($data) {
        global $DB;

        $data = (object)$data;
        $data->commandroomid = $this->get_new_parentid('commandroom');
        $data->nodeid = $this->get_mappingid('commandroom_node', $data->nodeid);

        if (empty($data->nodeid)) {
            return;
        }

        $DB->insert_record('commandroom_shocks', $data);
    }

    /**
     * Restore a simulation run.
     *
     * @param array|stdClass $data Restored data.
     */
    protected function process_commandroom_run($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->commandroomid = $this->get_new_parentid('commandroom');
        $data->groupid = $this->get_mappingid('group', $data->groupid, 0);
        $data->leaderid = $this->get_mappingid('user', $data->leaderid, 0);
        $data->submittedby = $this->get_mappingid('user', $data->submittedby, 0);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        if (!empty($data->timesubmitted)) {
            $data->timesubmitted = $this->apply_date_offset($data->timesubmitted);
        }
        if (!empty($data->timeinvalidated)) {
            $data->timeinvalidated = $this->apply_date_offset($data->timeinvalidated);
        }
        if (!empty($data->timecompleted)) {
            $data->timecompleted = $this->apply_date_offset($data->timecompleted);
        }

        $newitemid = $DB->insert_record('commandroom_runs', $data);
        $this->set_mapping('commandroom_run', $oldid, $newitemid);
    }

    /**
     * Restore a proposal.
     *
     * @param array|stdClass $data Restored data.
     */
    protected function process_commandroom_proposal($data) {
        global $DB;

        $data = (object)$data;
        $data->runid = $this->get_new_parentid('commandroom_run');
        $data->nodeid = $this->get_mappingid('commandroom_node', $data->nodeid);
        $data->userid = $this->get_mappingid('user', $data->userid, 0);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        if (empty($data->nodeid) || empty($data->runid)) {
            return;
        }

        $DB->insert_record('commandroom_proposals', $data);
    }

    /**
     * Restore a leader decision.
     *
     * @param array|stdClass $data Restored data.
     */
    protected function process_commandroom_decision($data) {
        global $DB;

        $data = (object)$data;
        $data->runid = $this->get_new_parentid('commandroom_run');
        $data->nodeid = $this->get_mappingid('commandroom_node', $data->nodeid);
        $data->leaderid = $this->get_mappingid('user', $data->leaderid, 0);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        if (empty($data->nodeid) || empty($data->runid)) {
            return;
        }

        $DB->insert_record('commandroom_decisions', $data);
    }

    /**
     * Restore a result row.
     *
     * @param array|stdClass $data Restored data.
     */
    protected function process_commandroom_result($data) {
        global $DB;

        $data = (object)$data;
        $data->runid = $this->get_new_parentid('commandroom_run');
        $data->nodeid = $this->get_mappingid('commandroom_node', $data->nodeid);
        $data->timecreated = $this->apply_date_offset($data->timecreated);

        if (empty($data->nodeid) || empty($data->runid)) {
            return;
        }

        $DB->insert_record('commandroom_results', $data);
    }

    /**
     * Restore a submitted snapshot.
     *
     * @param array|stdClass $data Restored data.
     */
    protected function process_commandroom_submission($data) {
        global $DB;

        $data = (object)$data;
        $data->commandroomid = $this->get_new_parentid('commandroom');
        $data->runid = $this->get_new_parentid('commandroom_run');
        $data->groupid = $this->get_mappingid('group', $data->groupid, 0);
        $data->submittedby = $this->get_mappingid('user', $data->submittedby, 0);
        $data->timecreated = $this->apply_date_offset($data->timecreated);

        if (empty($data->runid)) {
            return;
        }

        $DB->insert_record('commandroom_submissions', $data);
    }

    /**
     * Restore an export metadata row.
     *
     * @param array|stdClass $data Restored data.
     */
    protected function process_commandroom_export($data) {
        global $DB;

        $data = (object)$data;
        $data->commandroomid = $this->get_new_parentid('commandroom');
        $data->userid = $this->get_mappingid('user', $data->userid, 0);
        $data->timecreated = $this->apply_date_offset($data->timecreated);

        $DB->insert_record('commandroom_exports', $data);
    }

    /**
     * Final restore processing.
     */
    protected function after_execute() {
        $this->add_related_files('mod_commandroom', 'intro', null);
    }
}
