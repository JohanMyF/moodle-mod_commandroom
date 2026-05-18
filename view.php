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
 * Main activity page for mod_commandroom.
 *
 * @package    mod_commandroom
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

/**
 * Safely decode a node update config JSON string.
 *
 * @param stdClass $node
 * @return array|null
 */

/**
 * Apply a node's configured minimum and maximum boundaries to a runtime value.
 *
 * A maximum of 0 is common in older CommandRoom data as an unset/default value,
 * so the upper clamp is only applied when maximumvalue is greater than minimumvalue.
 *
 * @param float $value
 * @param stdClass $node
 * @return float
 */
function mod_commandroom_view_clamp_node_value(float $value, stdClass $node): float {
    if (property_exists($node, 'minimumvalue') && $node->minimumvalue !== null) {
        $value = max((float)$node->minimumvalue, $value);
    }

    if (property_exists($node, 'maximumvalue') && $node->maximumvalue !== null) {
        $minimum = (property_exists($node, 'minimumvalue') && $node->minimumvalue !== null)
            ? (float)$node->minimumvalue
            : 0.0;
        $maximum = (float)$node->maximumvalue;

        if ($maximum > $minimum) {
            $value = min($maximum, $value);
        }
    }

    return $value;
}

function mod_commandroom_view_get_updateconfig(stdClass $node): ?array {
    if (!property_exists($node, 'updateconfig')) {
        return null;
    }

    if ($node->updateconfig === null || trim((string)$node->updateconfig) === '') {
        return null;
    }

    $decoded = json_decode((string)$node->updateconfig, true);
    if (!is_array($decoded)) {
        return null;
    }

    return $decoded;
}

/**
 * Return the value for a node to be used in current iteration calculations,
 * applying any shock adjustments already computed for the next iteration.
 *
 * @param int $nodeid
 * @param array $currentstate
 * @param array $nextstate
 * @param array $shockadjustmentsbynodeid
 * @return float
 */
function mod_commandroom_view_resolve_source_value(
    int $nodeid,
    array $currentstate,
    array $nextstate,
    array $shockadjustmentsbynodeid
): float {
    if (isset($nextstate[$nodeid])) {
        $value = (float)$nextstate[$nodeid]->nodevalue;
    } else if (isset($currentstate[$nodeid])) {
        $value = (float)$currentstate[$nodeid]->nodevalue;
    } else {
        $value = 0.0;
    }

    if (isset($shockadjustmentsbynodeid[$nodeid])) {
        $value += (float)$shockadjustmentsbynodeid[$nodeid];
    }

    return $value;
}


/**
 * Safely decode a node calculation config JSON string.
 *
 * @param stdClass $node
 * @return array|null
 */
function mod_commandroom_view_get_calculationconfig(stdClass $node): ?array {
    if (!property_exists($node, 'calculationconfig')) {
        return null;
    }

    if ($node->calculationconfig === null || trim((string)$node->calculationconfig) === '') {
        return null;
    }

    $decoded = json_decode((string)$node->calculationconfig, true);
    if (!is_array($decoded)) {
        return null;
    }

    return $decoded;
}

/**
 * Resolve a Calculation Layer v1 operand.
 *
 * @param array $operand
 * @param array $currentstate
 * @param array $nextstate
 * @param array $shockadjustmentsbynodeid
 * @return float
 */
function mod_commandroom_view_resolve_calculation_operand(
    array $operand,
    array $currentstate,
    array $nextstate,
    array $shockadjustmentsbynodeid
): float {
    $kind = $operand['kind'] ?? '';

    if ($kind === 'number') {
        return isset($operand['value']) ? (float)$operand['value'] : 0.0;
    }

    if ($kind === 'node' && isset($operand['nodeid'])) {
        return mod_commandroom_view_resolve_source_value(
            (int)$operand['nodeid'],
            $currentstate,
            $nextstate,
            $shockadjustmentsbynodeid
        );
    }

    return 0.0;
}

/**
 * Evaluate Calculation Layer v1 rules.
 *
 * @param array $calculationconfig
 * @param array $currentstate
 * @param array $nextstate
 * @param array $shockadjustmentsbynodeid
 * @return float|null
 */
function mod_commandroom_view_evaluate_calculationconfig(
    array $calculationconfig,
    array $currentstate,
    array $nextstate,
    array $shockadjustmentsbynodeid
): ?float {
    $type = $calculationconfig['type'] ?? '';

    $operandvalue = function($operand) use ($currentstate, $nextstate, $shockadjustmentsbynodeid): float {
        if (!is_array($operand)) {
            return is_numeric($operand) ? (float)$operand : 0.0;
        }
        return mod_commandroom_view_resolve_calculation_operand(
            $operand,
            $currentstate,
            $nextstate,
            $shockadjustmentsbynodeid
        );
    };

    if ($type === 'multiply') {
        if (empty($calculationconfig['left']) || empty($calculationconfig['right']) ||
                !is_array($calculationconfig['left']) || !is_array($calculationconfig['right'])) {
            return null;
        }
        return $operandvalue($calculationconfig['left']) * $operandvalue($calculationconfig['right']);
    }

    if ($type === 'divide') {
        if (empty($calculationconfig['numerator']) || empty($calculationconfig['denominator']) ||
                !is_array($calculationconfig['numerator']) || !is_array($calculationconfig['denominator'])) {
            return null;
        }
        $denominator = $operandvalue($calculationconfig['denominator']);
        if (abs($denominator) < 0.0000001) {
            return 0.0;
        }
        return $operandvalue($calculationconfig['numerator']) / $denominator;
    }

    if ($type === 'percentage') {
        if (empty($calculationconfig['value']) || empty($calculationconfig['percent']) ||
                !is_array($calculationconfig['value']) || !is_array($calculationconfig['percent'])) {
            return null;
        }
        return $operandvalue($calculationconfig['value']) * ($operandvalue($calculationconfig['percent']) / 100.0);
    }

    if ($type === 'add') {
        if (empty($calculationconfig['items']) || !is_array($calculationconfig['items'])) {
            return null;
        }
        $total = 0.0;
        foreach ($calculationconfig['items'] as $item) {
            if (!is_array($item)) {
                return null;
            }
            $total += $operandvalue($item);
        }
        return $total;
    }

    if ($type === 'sum') {
        if (empty($calculationconfig['items']) || !is_array($calculationconfig['items'])) {
            return null;
        }
        $total = 0.0;
        foreach ($calculationconfig['items'] as $item) {
            if (!is_array($item) || empty($item['operand']) || !is_array($item['operand'])) {
                return null;
            }
            $factor = isset($item['factor']) ? (float)$item['factor'] : 1.0;
            $total += $factor * $operandvalue($item['operand']);
        }
        return $total;
    }

    if ($type === 'linear') {
        if (empty($calculationconfig['input']) || !is_array($calculationconfig['input'])) {
            return null;
        }
        $input = $operandvalue($calculationconfig['input']);
        $slope = isset($calculationconfig['slope']) ? (float)$calculationconfig['slope'] : 1.0;
        $intercept = isset($calculationconfig['intercept']) ? (float)$calculationconfig['intercept'] : 0.0;
        return $intercept + ($slope * $input);
    }

    if ($type === 'diminishing_returns') {
        if (empty($calculationconfig['input']) || !is_array($calculationconfig['input'])) {
            return null;
        }
        $input = max(0.0, $operandvalue($calculationconfig['input']));
        $maximum = isset($calculationconfig['maximum']) ? (float)$calculationconfig['maximum'] : 100.0;
        $rate = isset($calculationconfig['rate']) ? max(0.000001, (float)$calculationconfig['rate']) : 0.1;
        return $maximum * (1 - exp(-$rate * $input));
    }

    if ($type === 'optimum_point') {
        if (empty($calculationconfig['input']) || !is_array($calculationconfig['input'])) {
            return null;
        }
        $input = $operandvalue($calculationconfig['input']);
        $optimum = isset($calculationconfig['optimum']) ? (float)$calculationconfig['optimum'] : 10.0;
        $maximum = isset($calculationconfig['maximum']) ? (float)$calculationconfig['maximum'] : 100.0;
        $decline = isset($calculationconfig['decline']) ? max(0.0, (float)$calculationconfig['decline']) : 1.0;
        $floor = isset($calculationconfig['floor']) ? (float)$calculationconfig['floor'] : 0.0;
        $value = $maximum - ($decline * pow($input - $optimum, 2));
        return max($floor, $value);
    }

    if ($type === 'bell_curve') {
        if (empty($calculationconfig['input']) || !is_array($calculationconfig['input'])) {
            return null;
        }
        $input = $operandvalue($calculationconfig['input']);
        $centre = isset($calculationconfig['centre']) ? (float)$calculationconfig['centre'] : 0.0;
        $maximum = isset($calculationconfig['maximum']) ? (float)$calculationconfig['maximum'] : 100.0;
        $spread = isset($calculationconfig['spread']) ? max(0.000001, (float)$calculationconfig['spread']) : 1.0;
        $floor = isset($calculationconfig['floor']) ? (float)$calculationconfig['floor'] : 0.0;
        $value = $maximum * exp(-pow($input - $centre, 2) / (2 * pow($spread, 2)));
        return max($floor, $value);
    }

    if ($type === 'random_range') {
        if (!array_key_exists('min', $calculationconfig) || !array_key_exists('max', $calculationconfig)) {
            return null;
        }
        $min = (float)$calculationconfig['min'];
        $max = (float)$calculationconfig['max'];
        if ($max < $min) {
            $tmp = $min;
            $min = $max;
            $max = $tmp;
        }
        return $min + (mt_rand() / mt_getrandmax()) * ($max - $min);
    }

    return null;
}

$id = required_param('id', PARAM_INT);
$advancesimulation = optional_param('advancesimulation', 0, PARAM_BOOL);
$startrerun = optional_param('startrerun', 0, PARAM_BOOL);

// Load core records.
$cm = get_coursemodule_from_id('commandroom', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$commandroom = $DB->get_record('commandroom', ['id' => $cm->instance], '*', MUST_EXIST);

// Security.
require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/commandroom:view', $context);

// Trigger required event.
$event = \mod_commandroom\event\course_module_viewed::create([
    'objectid' => $commandroom->id,
    'context' => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('commandroom', $commandroom);
$event->trigger();

// Page setup.
$PAGE->set_url('/mod/commandroom/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($commandroom->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_activity_record($commandroom);
$PAGE->set_cm($cm);

// Initialise AMD.
$PAGE->requires->js_call_amd('mod_commandroom/main', 'init', [$cm->id]);

// Renderer.
$renderer = $PAGE->get_renderer('mod_commandroom');

// Bulk-load authoring data.
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

// Resolve the current user's Moodle group for this activity.
$groupid = commandroom_get_user_groupid((int)$course->id, (int)$USER->id, $cm->groupingid ?: 0);
$assignedleaderid = commandroom_get_group_leader((int)$commandroom->id, $groupid);
$fallbacklead = ($assignedleaderid === 0) && has_capability('mod/commandroom:leaddecision', $context);
$canlead = ($assignedleaderid > 0 && (int)$assignedleaderid === (int)$USER->id) || $fallbacklead;
$runleaderid = $assignedleaderid > 0 ? $assignedleaderid : ($fallbacklead ? (int)$USER->id : 0);

// Run resolution policy:
// 1. Prefer latest active run (inprogress first, then draft).
// 2. If none exists, fall back to the latest terminal run for display only.
// 3. Only create a run if no run exists at all AND a system has actually been imported.
$run = false;

$sql = "SELECT *
          FROM {commandroom_runs}
         WHERE commandroomid = ?
           AND groupid = ?
           AND status IN ('inprogress', 'draft')
      ORDER BY CASE status
                   WHEN 'inprogress' THEN 0
                   WHEN 'draft' THEN 1
                   ELSE 2
               END,
               id DESC";
$params = [$commandroom->id, $groupid];
$run = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE);

// If no active run exists, fall back to the latest terminal run for display.
if (!$run) {
    $sql = "SELECT *
              FROM {commandroom_runs}
             WHERE commandroomid = ?
               AND groupid = ?
               AND status IN ('completed', 'submitted', 'invalidated')
          ORDER BY id DESC";
    $params = [$commandroom->id, $groupid];
    $run = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE);
}

// Create or align a group run. The teacher-authored system is shared;
// the run, proposals, decisions and results are group-specific.
if (!$run && !empty($nodes)) {
    $run = new stdClass();
    $run->commandroomid = $commandroom->id;
    $run->groupid = $groupid;
    $run->leaderid = $runleaderid;
    $run->status = 'draft';
    $run->currentiteration = 0;
    $run->totaliterations = (int)$commandroom->totaliterations;
    $run->scenarioversion = 1;
    $run->finalscore = 0;
    $run->timecreated = time();
    $run->timemodified = $run->timecreated;
    $run->timecompleted = null;
    $run->timesubmitted = null;
    $run->submittedby = null;
    $run->timeinvalidated = null;
    $run->invalidatedreason = null;

    $run->id = $DB->insert_record('commandroom_runs', $run);
} else if ($run && $runleaderid > 0 && (int)$run->leaderid !== $runleaderid &&
        in_array($run->status, ['draft', 'inprogress'], true)) {
    $runupdate = new stdClass();
    $runupdate->id = $run->id;
    $runupdate->leaderid = $runleaderid;
    $runupdate->timemodified = time();
    $DB->update_record('commandroom_runs', $runupdate);

    $run->leaderid = $runleaderid;
    $run->timemodified = $runupdate->timemodified;
}

// Auto-initialise baseline iteration 0 state once for the run leader.
// Only do this for active runs.
if (!empty($run) &&
        in_array($run->status, ['draft', 'inprogress'], true) &&
        (int)$run->leaderid === (int)$USER->id) {
    $iterationzeroexists = $DB->record_exists('commandroom_results', [
        'runid' => $run->id,
        'iterationno' => 0,
    ]);

    if (!$iterationzeroexists) {
        $time = time();
        $transaction = $DB->start_delegated_transaction();

        foreach ($nodes as $node) {
            $result = new stdClass();
            $result->runid = $run->id;
            $result->iterationno = 0;
            $result->nodeid = (int)$node->id;
            $result->nodevalue = mod_commandroom_view_clamp_node_value(
                isset($node->initialvalue) ? (float)$node->initialvalue : 0.0,
                $node
            );
            $result->valueorigin = 'initial';
            $result->timecreated = $time;

            $DB->insert_record('commandroom_results', $result);
        }

        $runupdate = new stdClass();
        $runupdate->id = $run->id;
        $runupdate->currentiteration = 0;
        $runupdate->totaliterations = !empty($run->totaliterations)
            ? (int)$run->totaliterations
            : (int)$commandroom->totaliterations;
        $runupdate->timemodified = $time;
        $DB->update_record('commandroom_runs', $runupdate);

        $run->currentiteration = 0;
        $run->totaliterations = $runupdate->totaliterations;
        $run->timemodified = $time;

        $transaction->allow_commit();
    }
}

// Start new run handler.
if ($startrerun && confirm_sesskey()) {
    if (empty($run)) {
        throw new moodle_exception('error:runnotfound', 'mod_commandroom');
    }

    if (!$canlead || (int)$run->leaderid !== (int)$USER->id) {
        throw new moodle_exception('error:notrunleader', 'mod_commandroom');
    }

    if ($run->status !== 'completed') {
        throw new moodle_exception('error:runmustbecompleted', 'mod_commandroom');
    }

    $time = time();
    $transaction = $DB->start_delegated_transaction();

    $newrun = new stdClass();
    $newrun->commandroomid = $commandroom->id;
    $newrun->groupid = $groupid;
    $newrun->leaderid = $runleaderid;
    $newrun->status = 'draft';
    $newrun->currentiteration = 0;
    $newrun->totaliterations = (int)$commandroom->totaliterations;
    $newrun->scenarioversion = !empty($run->scenarioversion) ? ((int)$run->scenarioversion + 1) : 1;
    $newrun->finalscore = 0;
    $newrun->timecreated = $time;
    $newrun->timemodified = $time;
    $newrun->timecompleted = null;
    $newrun->timesubmitted = null;
    $newrun->submittedby = null;
    $newrun->timeinvalidated = null;
    $newrun->invalidatedreason = null;

    $newrunid = $DB->insert_record('commandroom_runs', $newrun);

    foreach ($nodes as $node) {
        $result = new stdClass();
        $result->runid = $newrunid;
        $result->iterationno = 0;
        $result->nodeid = (int)$node->id;
        $result->nodevalue = mod_commandroom_view_clamp_node_value(
                isset($node->initialvalue) ? (float)$node->initialvalue : 0.0,
                $node
            );
        $result->valueorigin = 'initial';
        $result->timecreated = $time;

        $DB->insert_record('commandroom_results', $result);
    }

    $transaction->allow_commit();

    redirect(
        new moodle_url('/mod/commandroom/view.php', ['id' => $cm->id]),
        get_string('newrunstarted', 'mod_commandroom'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Advance simulation handler.
if ($advancesimulation && confirm_sesskey()) {
    if (empty($run)) {
        throw new moodle_exception('error:runnotfound', 'mod_commandroom');
    }

    if (!$canlead || (int)$run->leaderid !== (int)$USER->id) {
        throw new moodle_exception('error:notrunleader', 'mod_commandroom');
    }

    if ($run->status === 'completed') {
        redirect(
            new moodle_url('/mod/commandroom/view.php', ['id' => $cm->id]),
            get_string('endofscenariorun', 'mod_commandroom'),
            null,
            \core\output\notification::NOTIFY_INFO
        );
    }

    if ($run->status === 'submitted') {
        throw new moodle_exception('runalreadysubmitted', 'mod_commandroom');
    }

    if ($run->status === 'invalidated') {
        throw new moodle_exception('runinvalidated', 'mod_commandroom');
    }

    $storedcurrentiteration = isset($run->currentiteration) ? (int)$run->currentiteration : 0;

    $resultsmaxiteration = $DB->get_field_sql(
        "SELECT MAX(iterationno)
           FROM {commandroom_results}
          WHERE runid = ?",
        [$run->id]
    );

    if ($resultsmaxiteration === false || $resultsmaxiteration === null) {
        throw new moodle_exception('error:nobaselineresults', 'mod_commandroom');
    }

    $resultsmaxiteration = (int)$resultsmaxiteration;
    $currentiteration = max($storedcurrentiteration, $resultsmaxiteration);
    $nextiteration = $currentiteration + 1;

    $totaliterations = !empty($run->totaliterations)
        ? (int)$run->totaliterations
        : (int)$commandroom->totaliterations;

    if ($totaliterations < 1) {
        throw new moodle_exception('invalidtotaliterations', 'mod_commandroom');
    }

    if ($currentiteration >= $totaliterations) {
        $time = time();

        $runupdate = new stdClass();
        $runupdate->id = $run->id;
        $runupdate->currentiteration = $currentiteration;
        $runupdate->totaliterations = $totaliterations;
        $runupdate->status = 'completed';
        $runupdate->timemodified = $time;

        if (empty($run->timecompleted)) {
            $runupdate->timecompleted = $time;
        }

        $DB->update_record('commandroom_runs', $runupdate);

        redirect(
            new moodle_url('/mod/commandroom/view.php', ['id' => $cm->id]),
            get_string('endofscenariorun', 'mod_commandroom'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    $nextexists = $DB->record_exists('commandroom_results', [
        'runid' => $run->id,
        'iterationno' => $nextiteration,
    ]);

    if ($nextexists) {
        redirect(
            new moodle_url('/mod/commandroom/view.php', ['id' => $cm->id]),
            get_string('error:nextiterationalreadyexists', 'mod_commandroom'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    $currentresults = $DB->get_records('commandroom_results', [
        'runid' => $run->id,
        'iterationno' => $currentiteration,
    ]);

    $currentstate = [];
    foreach ($currentresults as $row) {
        $currentstate[(int)$row->nodeid] = $row;
    }

    $decisionrecords = $DB->get_records('commandroom_decisions', ['runid' => $run->id]);
    $decisionsbynodeid = [];
    foreach ($decisionrecords as $decisionrecord) {
        $decisionsbynodeid[(int)$decisionrecord->nodeid] = $decisionrecord;
    }

    $incomingedgesbynode = [];
    foreach ($edges as $edge) {
        $targetnodeid = (int)$edge->targetnodeid;
        if (!isset($incomingedgesbynode[$targetnodeid])) {
            $incomingedgesbynode[$targetnodeid] = [];
        }
        $incomingedgesbynode[$targetnodeid][] = $edge;
    }

    $shockrecords = $DB->get_records(
        'commandroom_shocks',
        ['commandroomid' => $commandroom->id],
        'id ASC'
    );

    $shockadjustmentsbynodeid = [];
    foreach ($shockrecords as $shock) {
        $nodeid = (int)$shock->nodeid;
        if (!isset($shockadjustmentsbynodeid[$nodeid])) {
            $shockadjustmentsbynodeid[$nodeid] = 0.0;
        }

        $shocktype = $shock->shocktype ?? 'scheduled';

        if ($shocktype === 'scheduled') {
            if ((int)$shock->iterationno === $nextiteration) {
                $shockadjustmentsbynodeid[$nodeid] += (float)$shock->adjustment;
            }
        } else if ($shocktype === 'random_range') {
            if (!empty($shock->applyeveryiteration)) {
                $min = $shock->minadjustment !== null ? (float)$shock->minadjustment : 0.0;
                $max = $shock->maxadjustment !== null ? (float)$shock->maxadjustment : 0.0;

                if ($max < $min) {
                    $tmp = $min;
                    $min = $max;
                    $max = $tmp;
                }

                $random = $min + (mt_rand() / mt_getrandmax()) * ($max - $min);
                $shockadjustmentsbynodeid[$nodeid] += $random;
            }
        }
    }

    $nextstate = [];
    $time = time();
    $transaction = $DB->start_delegated_transaction();

    foreach ($nodes as $node) {
        $nodeid = (int)$node->id;

        if (!isset($currentstate[$nodeid])) {
            $transaction->rollback(new moodle_exception('error:missingcurrentstate', 'mod_commandroom'));
        }

        if (!empty($node->studentcontrolled)) {
            $selectedvalue = null;
            $valueorigin = 'decision';

            if (!empty($decisionsbynodeid[$nodeid])) {
                $decision = $decisionsbynodeid[$nodeid];
                $selectedvalue = (float)$decision->selectedvalue;
            } else if (!empty($currentstate[$nodeid])) {
                $selectedvalue = (float)$currentstate[$nodeid]->nodevalue;
                $valueorigin = 'carriedforward';
            } else {
                $selectedvalue = (float)$node->initialvalue;
                $valueorigin = 'initialfallback';
            }

            $result = new stdClass();
            $result->runid = $run->id;
            $result->iterationno = $nextiteration;
            $result->nodeid = $nodeid;
            $selectedvalue = mod_commandroom_view_clamp_node_value($selectedvalue, $node);

            $result->nodevalue = $selectedvalue;
            $result->valueorigin = $valueorigin;
            $result->timecreated = $time;

            $result->id = $DB->insert_record('commandroom_results', $result);
            $nextstate[$nodeid] = $result;
            continue;
        }

        $calculationconfig = mod_commandroom_view_get_calculationconfig($node);
        if (!empty($calculationconfig)) {
            $calculatedvalue = mod_commandroom_view_evaluate_calculationconfig(
                $calculationconfig,
                $currentstate,
                $nextstate,
                $shockadjustmentsbynodeid
            );

            if ($calculatedvalue !== null) {
                $result = new stdClass();
                $result->runid = $run->id;
                $result->iterationno = $nextiteration;
                $result->nodeid = $nodeid;
                $calculatedvalue = mod_commandroom_view_clamp_node_value($calculatedvalue, $node);

                $result->nodevalue = $calculatedvalue;
                $result->valueorigin = 'calculation';
                $result->timecreated = $time;

                $result->id = $DB->insert_record('commandroom_results', $result);
                $nextstate[$nodeid] = $result;
                continue;
            }
        }

        $updateconfig = mod_commandroom_view_get_updateconfig($node);
        if (!empty($updateconfig['mode']) && in_array($updateconfig['mode'], ['stock_with_rate', 'stock_accumulation'], true)) {
            $oldvalue = (float)$currentstate[$nodeid]->nodevalue;

            $base = $updateconfig['base'] ?? 'self';
            if (!in_array($base, ['self', 'zero'], true)) {
                $transaction->rollback(new moodle_exception(
                    'error:invalidupdateconfig',
                    'mod_commandroom',
                    '',
                    s($node->name)
                ));
            }

            $startingvalue = $base === 'zero' ? 0.0 : $oldvalue;

            $inflowtotal = 0.0;
            $inflows = $updateconfig['inflows'] ?? ($updateconfig['adds'] ?? []);
            if (!is_array($inflows)) {
                $transaction->rollback(new moodle_exception(
                    'error:invalidupdateconfig',
                    'mod_commandroom',
                    '',
                    s($node->name)
                ));
            }

            foreach ($inflows as $inflownodeid) {
                $inflowid = (int)$inflownodeid;
                $inflowtotal += mod_commandroom_view_resolve_source_value(
                    $inflowid,
                    $currentstate,
                    $nextstate,
                    $shockadjustmentsbynodeid
                );
            }

            $outflowtotal = 0.0;
            $outflows = $updateconfig['outflows'] ?? ($updateconfig['subtracts'] ?? []);
            if (!is_array($outflows)) {
                $transaction->rollback(new moodle_exception(
                    'error:invalidupdateconfig',
                    'mod_commandroom',
                    '',
                    s($node->name)
                ));
            }

            foreach ($outflows as $outflownodeid) {
                $outflowid = (int)$outflownodeid;
                $outflowtotal += mod_commandroom_view_resolve_source_value(
                    $outflowid,
                    $currentstate,
                    $nextstate,
                    $shockadjustmentsbynodeid
                );
            }

            $ratevalue = 0.0;
            if (!empty($updateconfig['rate'])) {
                $ratenodeid = (int)$updateconfig['rate'];
                $ratevalue = mod_commandroom_view_resolve_source_value(
                    $ratenodeid,
                    $currentstate,
                    $nextstate,
                    $shockadjustmentsbynodeid
                );
            }

            $newvalue = $startingvalue + $inflowtotal - $outflowtotal + ($startingvalue * $ratevalue);

            $result = new stdClass();
            $result->runid = $run->id;
            $result->iterationno = $nextiteration;
            $result->nodeid = $nodeid;
            $newvalue = mod_commandroom_view_clamp_node_value($newvalue, $node);

            $result->nodevalue = $newvalue;
            $result->valueorigin = 'computed';
            $result->timecreated = $time;

            $result->id = $DB->insert_record('commandroom_results', $result);
            $nextstate[$nodeid] = $result;
            continue;
        }

        $newvalue = isset($currentstate[$nodeid]) ? (float)$currentstate[$nodeid]->nodevalue : (float)$node->initialvalue;

        if (isset($shockadjustmentsbynodeid[$nodeid])) {
            $newvalue += (float)$shockadjustmentsbynodeid[$nodeid];
        }

        if (!empty($incomingedgesbynode[$nodeid])) {
            $computedvalue = 0.0;

            foreach ($incomingedgesbynode[$nodeid] as $edge) {
                $sourceid = (int)$edge->sourcenodeid;
                $strength = (float)$edge->strength;

                $sourcevalue = mod_commandroom_view_resolve_source_value(
                    $sourceid,
                    $currentstate,
                    $nextstate,
                    $shockadjustmentsbynodeid
                );

                if ($edge->relationtype === 'linear') {
                    $computedvalue += ($sourcevalue * $strength);
                } else {
                    $transaction->rollback(new moodle_exception(
                        'error:unsupportedrelationtype',
                        'mod_commandroom',
                        '',
                        s($edge->relationtype)
                    ));
                }
            }

            $newvalue = $computedvalue;
        }

        $result = new stdClass();
        $result->runid = $run->id;
        $result->iterationno = $nextiteration;
        $result->nodeid = $nodeid;
        $newvalue = mod_commandroom_view_clamp_node_value($newvalue, $node);

        $result->nodevalue = $newvalue;
        $result->valueorigin = 'computed';
        $result->timecreated = $time;

        $result->id = $DB->insert_record('commandroom_results', $result);
        $nextstate[$nodeid] = $result;
    }

    $runupdate = new stdClass();
    $runupdate->id = $run->id;
    $runupdate->currentiteration = $nextiteration;
    $runupdate->totaliterations = $totaliterations;
    $runupdate->timemodified = $time;

    if ($run->status === 'draft') {
        $runupdate->status = 'inprogress';
    }

    $successmessage = get_string('simulationadvanced', 'mod_commandroom');

    if ($nextiteration >= $totaliterations) {
        $runupdate->status = 'completed';
        $runupdate->timecompleted = $time;
        $successmessage = get_string('endofscenariorun', 'mod_commandroom');
    }

    $DB->update_record('commandroom_runs', $runupdate);

    $transaction->allow_commit();

    redirect(
        new moodle_url('/mod/commandroom/view.php', ['id' => $cm->id]),
        $successmessage,
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Reload run after any possible updates above.
if (!empty($run)) {
    $run = $DB->get_record('commandroom_runs', ['id' => $run->id], '*', MUST_EXIST);
}

// Load saved decisions for the current run, keyed by node id.
$decisions = [];
if (!empty($run)) {
    $decisionrecords = $DB->get_records('commandroom_decisions', ['runid' => $run->id]);

    foreach ($decisionrecords as $decision) {
        $decisions[(int)$decision->nodeid] = $decision;
    }
}

// Load the latest authoritative result rows for the current run, keyed by node id.
$resultsbynodeid = [];
$historyrows = [];
if (!empty($run)) {
    $latestiteration = $DB->get_field_sql(
        "SELECT MAX(iterationno)
           FROM {commandroom_results}
          WHERE runid = ?",
        [$run->id]
    );

    if ($latestiteration !== false && $latestiteration !== null) {
        $resultrecords = $DB->get_records('commandroom_results', [
            'runid' => $run->id,
            'iterationno' => (int)$latestiteration,
        ]);

        foreach ($resultrecords as $resultrecord) {
            $resultsbynodeid[(int)$resultrecord->nodeid] = $resultrecord;
        }
    }

    $allresults = $DB->get_records(
        'commandroom_results',
        ['runid' => $run->id],
        'iterationno ASC, nodeid ASC, id ASC'
    );

    foreach ($allresults as $result) {
        $iteration = (int)$result->iterationno;
        $nodeid = (int)$result->nodeid;

        if (!isset($historyrows[$iteration])) {
            $historyrows[$iteration] = [];
        }

        $historyrows[$iteration][$nodeid] = (float)$result->nodevalue;
    }
}

$remainingiterations = 1;
if (!empty($run) && in_array($run->status, ['draft', 'inprogress'], true)) {
    $remainingiterations = max(1, ((int)$run->totaliterations - (int)$run->currentiteration));
}

echo $OUTPUT->header();

echo $OUTPUT->heading(format_string($commandroom->name), 2);

if (!empty($commandroom->intro)) {
    echo $OUTPUT->box(
        format_module_intro('commandroom', $commandroom, $cm->id),
        'generalbox mod_introbox',
        'commandroomintro'
    );
}

// Teacher authoring links. Students do not see these controls.
$actions = [];

if (has_capability('mod/commandroom:manageruns', $context)) {
    $actions[] = html_writer::link(
        new moodle_url('/mod/commandroom/builder.php', ['id' => $cm->id]),
        get_string('openbuilder', 'mod_commandroom'),
        ['class' => 'btn btn-primary']
    );

    $actions[] = html_writer::link(
        new moodle_url('/course/modedit.php', ['update' => $cm->id, 'return' => 1]),
        get_string('activitysettings', 'mod_commandroom'),
        ['class' => 'btn btn-outline-secondary']
    );
}

if (has_capability('mod/commandroom:exportsystem', $context)) {
    $actions[] = html_writer::link(
        new moodle_url('/mod/commandroom/export_system.php', ['id' => $cm->id]),
        get_string('exportjson', 'mod_commandroom'),
        ['class' => 'btn btn-outline-secondary']
    );
}

if (!empty($actions)) {
    echo $OUTPUT->box(
        html_writer::tag('strong', get_string('teachertools', 'mod_commandroom')) .
            html_writer::div(implode(' ', $actions), 'commandroom-management-actions'),
        'generalbox commandroom-management-box'
    );
}

// Activity summary.
echo $renderer->render_activity_summary($commandroom);

if ($groupid === 0 && !has_capability('mod/commandroom:manageruns', $context)) {
    echo $OUTPUT->notification(
        get_string('notingroup', 'mod_commandroom'),
        \core\output\notification::NOTIFY_INFO
    );
} else if ($groupid > 0 && $assignedleaderid === 0 && !has_capability('mod/commandroom:manageruns', $context)) {
    echo $OUTPUT->notification(
        get_string('groupleadernotassigned', 'mod_commandroom'),
        \core\output\notification::NOTIFY_INFO
    );
}

// Proposal and governance controls are now embedded in student-controlled visual cards.
$canpropose = has_capability('mod/commandroom:submitproposal', $context);
$showgovernance = !empty($run);
$governancereadonly = true;

if (!empty($run) && $canlead && (int)$run->leaderid === (int)$USER->id) {
    $governancereadonly = false;
}

// Leader action buttons belong directly below the governance panel.
if (!empty($run) &&
        $canlead &&
        (int)$run->leaderid === (int)$USER->id &&
        in_array($run->status, ['draft', 'inprogress'], true)) {

    $advanceurl = new moodle_url('/mod/commandroom/view.php', [
        'id' => $cm->id,
        'advancesimulation' => 1,
        'sesskey' => sesskey(),
    ]);

    $batchlabel = html_writer::tag(
        'label',
        get_string('iterationstorun', 'mod_commandroom'),
        ['for' => 'commandroom-batch-iterations']
    );

    $batchinput = html_writer::empty_tag('input', [
        'type' => 'number',
        'id' => 'commandroom-batch-iterations',
        'class' => 'form-control commandroom-batch-iterations',
        'value' => 1,
        'min' => 1,
        'max' => $remainingiterations,
        'step' => 1,
        'style' => 'max-width: 120px; display: inline-block; margin-right: 12px;'
    ]);

    $advancebutton = html_writer::tag(
        'button',
        get_string('runsimulation', 'mod_commandroom'),
        [
            'type' => 'button',
            'class' => 'btn btn-warning commandroom-run-simulation',
            'data-advanceurl' => $advanceurl->out(false),
            'data-cmid' => $cm->id,
            'data-maxiterations' => $remainingiterations,
        ]
    );

    $statusbox = html_writer::div(
        '',
        'commandroom-batch-run-status',
        ['style' => 'margin-top: 8px;']
    );

    $controls = html_writer::div(
        $batchlabel . ' ' . $batchinput . ' ' . $advancebutton . $statusbox,
        'commandroom-simulation-actions'
    );

    echo $OUTPUT->box(
        $controls,
        'generalbox commandroom-simulation-box'
    );

} else if (!empty($run) && $run->status === 'completed') {
    echo $OUTPUT->notification(
        get_string('endofscenariorun', 'mod_commandroom'),
        \core\output\notification::NOTIFY_SUCCESS
    );

    if ($canlead && (int)$run->leaderid === (int)$USER->id) {
        $rerunurl = new moodle_url('/mod/commandroom/view.php', [
            'id' => $cm->id,
            'startrerun' => 1,
            'sesskey' => sesskey(),
        ]);

        $rerunbutton = html_writer::link(
            $rerunurl,
            get_string('startnewrun', 'mod_commandroom'),
            ['class' => 'btn btn-success']
        );

        echo $OUTPUT->box(
            html_writer::div($rerunbutton, 'commandroom-rerun-actions'),
            'generalbox commandroom-rerun-box'
        );
    }
}

// System snapshot using latest authoritative run state.
$displaystatebynodeid = $resultsbynodeid;

foreach ($nodes as $node) {
    $nodeid = (int)$node->id;

    if (!empty($node->studentcontrolled) && !empty($decisions[$nodeid])) {
        $decision = $decisions[$nodeid];

        $displayrow = new stdClass();
        $displayrow->nodeid = $nodeid;
        $displayrow->nodevalue = (float)$decision->selectedvalue;
        $displayrow->valueorigin = 'decision';

        $displaystatebynodeid[$nodeid] = $displayrow;
    }
}

// Visual system cards, with proposal and governance controls embedded where applicable.
echo $renderer->render_visual_system_cards(
    $nodes,
    $displaystatebynodeid,
    (int)$cm->id,
    !empty($run) ? (int)$run->id : null,
    $decisions,
    $canpropose,
    $showgovernance,
    $governancereadonly,
    $edges
);

echo $renderer->render_system_snapshot($nodes, $edges, $displaystatebynodeid);

// Simulation history ledger.
echo $renderer->render_warroom_placeholder($historyrows, $nodes);

echo $OUTPUT->footer();