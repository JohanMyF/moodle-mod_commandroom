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
 * External service implementation for mod_commandroom.
 *
 * @package    mod_commandroom
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_commandroom\external;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/commandroom/lib.php');

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use moodle_exception;

class advance_simulation extends external_api {

    /**
     * Apply a node's configured minimum and maximum boundaries to a runtime value.
     *
     * A maximum of 0 is common in older CommandRoom data as an unset/default value,
     * so the upper clamp is only applied when maximumvalue is greater than minimumvalue.
     *
     * @param float $value
     * @param \stdClass $node
     * @return float
     */
    protected static function clamp_node_value(float $value, \stdClass $node): float {
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

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'runid' => new external_value(PARAM_INT, 'Run id'),
        ]);
    }

    protected static function get_updateconfig(\stdClass $node): ?array {
        if (!property_exists($node, 'updateconfig')) {
            return null;
        }
        if ($node->updateconfig === null || trim((string)$node->updateconfig) === '') {
            return null;
        }
        $decoded = json_decode((string)$node->updateconfig, true);
        return is_array($decoded) ? $decoded : null;
    }


    /**
     * Safely decode a node calculation config JSON string.
     *
     * @param \stdClass $node
     * @return array|null
     */
    protected static function get_calculationconfig(\stdClass $node): ?array {
        if (!property_exists($node, 'calculationconfig')) {
            return null;
        }
        if ($node->calculationconfig === null || trim((string)$node->calculationconfig) === '') {
            return null;
        }
        $decoded = json_decode((string)$node->calculationconfig, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Resolve a calculation operand.
     *
     * @param array $operand
     * @param array $nodemapbyref
     * @param array $currentstate
     * @param array $nextstate
     * @param array $shockadjustmentsbynodeid
     * @return float
     */
    protected static function resolve_calculation_operand(
        array $operand,
        array $nodemapbyref,
        array $currentstate,
        array $nextstate,
        array $shockadjustmentsbynodeid
    ): float {
        $kind = $operand['kind'] ?? '';

        if ($kind === 'number') {
            return isset($operand['value']) ? (float)$operand['value'] : 0.0;
        }

        if ($kind === 'node') {
            if (isset($operand['nodeid'])) {
                return self::resolve_source_value(
                    (int)$operand['nodeid'],
                    $currentstate,
                    $nextstate,
                    $shockadjustmentsbynodeid
                );
            }

            $ref = isset($operand['ref']) ? (string)$operand['ref'] : '';
            if ($ref !== '' && isset($nodemapbyref[$ref])) {
                return self::resolve_source_value(
                    (int)$nodemapbyref[$ref],
                    $currentstate,
                    $nextstate,
                    $shockadjustmentsbynodeid
                );
            }
        }

        return 0.0;
    }

    /**
     * Evaluate Calculation Layer v1 rules.
     *
     * Supported types:
     * - multiply
     * - add
     * - sum
     * - random_range
     *
     * @param array $calculationconfig
     * @param array $nodemapbyref
     * @param array $currentstate
     * @param array $nextstate
     * @param array $shockadjustmentsbynodeid
     * @return float|null
     */
    protected static function evaluate_calculationconfig(
        array $calculationconfig,
        array $nodemapbyref,
        array $currentstate,
        array $nextstate,
        array $shockadjustmentsbynodeid
    ): ?float {
        $type = $calculationconfig['type'] ?? '';

        $operandvalue = function($operand) use ($nodemapbyref, $currentstate, $nextstate, $shockadjustmentsbynodeid): float {
            if (!is_array($operand)) {
                return is_numeric($operand) ? (float)$operand : 0.0;
            }
            return self::resolve_calculation_operand(
                $operand,
                $nodemapbyref,
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

    protected static function resolve_source_value(
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
     * Resolve a node reference stored in update/calculation config.
     *
     * The Builder/import path normally stores database node ids, but older JSON
     * drafts and hand-authored systems may still contain refs or operand objects.
     *
     * @param mixed $reference
     * @param array $nodemapbyref
     * @return int
     */
    protected static function resolve_node_reference_id($reference, array $nodemapbyref): int {
        if (is_array($reference)) {
            if (isset($reference['nodeid']) && is_numeric($reference['nodeid'])) {
                return (int)$reference['nodeid'];
            }

            if (isset($reference['ref']) && isset($nodemapbyref[(string)$reference['ref']])) {
                return (int)$nodemapbyref[(string)$reference['ref']];
            }

            if (isset($reference['operand']) && is_array($reference['operand'])) {
                return self::resolve_node_reference_id($reference['operand'], $nodemapbyref);
            }

            return 0;
        }

        if (is_numeric($reference)) {
            return (int)$reference;
        }

        if (is_string($reference)) {
            $rawref = trim($reference);
            if ($rawref !== '' && isset($nodemapbyref[$rawref])) {
                return (int)$nodemapbyref[$rawref];
            }

            $normalisedref = self::normalise_reference_key($rawref);
            if ($normalisedref !== '' && isset($nodemapbyref[$normalisedref])) {
                return (int)$nodemapbyref[$normalisedref];
            }
        }

        return 0;
    }

    /**
     * Return the first available node-list entry from a config array.
     *
     * This keeps the runtime compatible with both the earlier technical names
     * (inflows/outflows) and the more teacher-friendly names (adds/subtracts).
     *
     * @param array $config
     * @param array $keys
     * @return array
     */
    protected static function get_config_node_list(array $config, array $keys): array {
        foreach ($keys as $key) {
            if (array_key_exists($key, $config)) {
                return is_array($config[$key]) ? $config[$key] : [];
            }
        }

        return [];
    }

    public static function execute(int $cmid, int $runid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid, 'runid' => $runid]);

        $cm = get_coursemodule_from_id('commandroom', $params['cmid'], 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        $commandroom = $DB->get_record('commandroom', ['id' => $cm->instance], '*', MUST_EXIST);

        require_login($course, true, $cm);

        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/commandroom:submitproposal', $context);

        $run = $DB->get_record('commandroom_runs', ['id' => $params['runid'], 'commandroomid' => $commandroom->id], '*', MUST_EXIST);

        if ((int)$run->leaderid !== (int)$USER->id) {
            throw new moodle_exception('error:notrunleader', 'mod_commandroom');
        }
        if ($run->status === 'submitted') {
            throw new moodle_exception('runalreadysubmitted', 'mod_commandroom');
        }
        if ($run->status === 'invalidated') {
            throw new moodle_exception('runinvalidated', 'mod_commandroom');
        }
        if ($run->status === 'completed') {
            throw new moodle_exception('runalreadycompleted', 'mod_commandroom');
        }

        $storedcurrentiteration = isset($run->currentiteration) ? (int)$run->currentiteration : 0;
        $resultsmaxiteration = $DB->get_field_sql(
            "SELECT MAX(iterationno) FROM {commandroom_results} WHERE runid = ?",
            [$run->id]
        );
        if ($resultsmaxiteration === false || $resultsmaxiteration === null) {
            throw new moodle_exception('error:nobaselineresults', 'mod_commandroom');
        }

        $resultsmaxiteration = (int)$resultsmaxiteration;
        $currentiteration = max($storedcurrentiteration, $resultsmaxiteration);
        $nextiteration = $currentiteration + 1;

        $totaliterations = !empty($run->totaliterations) ? (int)$run->totaliterations : (int)$commandroom->totaliterations;
        if ($totaliterations < 1) {
            throw new moodle_exception('error:invalidtotaliterations', 'mod_commandroom');
        }

        if ($currentiteration >= $totaliterations) {
            $time = time();
            $runupdate = new \stdClass();
            $runupdate->id = $run->id;
            $runupdate->currentiteration = $currentiteration;
            $runupdate->totaliterations = $totaliterations;
            $runupdate->status = 'completed';
            $runupdate->timemodified = $time;
            if (empty($run->timecompleted)) {
                $runupdate->timecompleted = $time;
            }
            $DB->update_record('commandroom_runs', $runupdate);

            return [
                'status' => 'completed',
                'runid' => (int)$run->id,
                'fromiteration' => $currentiteration,
                'toiteration' => $currentiteration,
                'nodecount' => 0,
                'currentiteration' => $currentiteration,
                'totaliterations' => $totaliterations,
                'iscompleted' => 1,
                'message' => get_string('endofscenariorun', 'mod_commandroom'),
            ];
        }

        if ($DB->record_exists('commandroom_results', ['runid' => $run->id, 'iterationno' => $nextiteration])) {
            throw new moodle_exception('error:nextiterationalreadyexists', 'mod_commandroom');
        }

        $nodes = $DB->get_records('commandroom_nodes', ['commandroomid' => $commandroom->id], 'displayorder ASC, id ASC');
        if (empty($nodes)) {
            throw new moodle_exception('error:nonodesdefined', 'mod_commandroom');
        }

        $nodemapbyref = [];
        foreach ($nodes as $node) {
            $nodeid = (int)$node->id;

            // Some imported/update configs are already normalised to database ids.
            $nodemapbyref[(string)$nodeid] = $nodeid;

            // Keep compatibility if a future schema stores a formal ref column.
            if (!empty($node->ref)) {
                $nodemapbyref[(string)$node->ref] = $nodeid;
                $nodemapbyref[self::normalise_reference_key((string)$node->ref)] = $nodeid;
            }

            // Current table has no ref column, so map common JSON refs back from node names.
            // This lets strings such as "expenses" and "Interest Earned" resolve safely.
            $nodename = isset($node->name) ? (string)$node->name : '';
            if ($nodename !== '') {
                $nodemapbyref[$nodename] = $nodeid;
                $nodemapbyref[strtolower($nodename)] = $nodeid;
                $nodemapbyref[self::normalise_reference_key($nodename)] = $nodeid;
            }
        }

        $currentresults = $DB->get_records('commandroom_results', ['runid' => $run->id, 'iterationno' => $currentiteration]);
        $currentstate = [];
        foreach ($currentresults as $row) {
            $currentstate[(int)$row->nodeid] = $row;
        }

        $decisions = $DB->get_records('commandroom_decisions', ['runid' => $run->id]);
        $decisionsbynodeid = [];
        foreach ($decisions as $decision) {
            $decisionsbynodeid[(int)$decision->nodeid] = $decision;
        }

        $edges = $DB->get_records('commandroom_edges', ['commandroomid' => $commandroom->id], 'id ASC');
        $incomingedgesbynode = [];
        foreach ($edges as $edge) {
            $targetnodeid = (int)$edge->targetnodeid;
            if (!isset($incomingedgesbynode[$targetnodeid])) {
                $incomingedgesbynode[$targetnodeid] = [];
            }
            $incomingedgesbynode[$targetnodeid][] = $edge;
        }

        $shockrecords = $DB->get_records('commandroom_shocks', ['commandroomid' => $commandroom->id], 'id ASC');
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
            } else if ($shocktype === 'random_range' && !empty($shock->applyeveryiteration)) {
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

        $nextstate = [];
        $time = time();
        $transaction = $DB->start_delegated_transaction();

        // PASS 1: student-controlled nodes first.
        foreach ($nodes as $node) {
            $nodeid = (int)$node->id;

            if (!isset($currentstate[$nodeid])) {
                $transaction->rollback(new moodle_exception('error:missingcurrentstate', 'mod_commandroom'));
            }

            if (!empty($node->studentcontrolled)) {
                if (empty($decisionsbynodeid[$nodeid])) {
                    $transaction->rollback(new moodle_exception(
                        'error:missingdecisionfornode',
                        'mod_commandroom',
                        '',
                        s($node->name)
                    ));
                }

                $decision = $decisionsbynodeid[$nodeid];

                $result = new \stdClass();
                $result->runid = $run->id;
                $result->iterationno = $nextiteration;
                $result->nodeid = $nodeid;
                $selectedvalue = self::clamp_node_value((float)$decision->selectedvalue, $node);

                $result->nodevalue = $selectedvalue;
                $result->valueorigin = 'decision';
                $result->timecreated = $time;
                $result->id = $DB->insert_record('commandroom_results', $result);

                $nextstate[$nodeid] = $result;
            }
        }

        // PASS 2: compute everything else after current decisions are available.
        foreach ($nodes as $node) {
            $nodeid = (int)$node->id;

            if (!isset($currentstate[$nodeid])) {
                $transaction->rollback(new moodle_exception('error:missingcurrentstate', 'mod_commandroom'));
            }

            if (!empty($node->studentcontrolled)) {
                continue;
            }

            $calculationconfig = self::get_calculationconfig($node);
            if (!empty($calculationconfig)) {
                $calculatedvalue = self::evaluate_calculationconfig(
                    $calculationconfig,
                    $nodemapbyref,
                    $currentstate,
                    $nextstate,
                    $shockadjustmentsbynodeid
                );

                if ($calculatedvalue !== null) {
                    $result = new \stdClass();
                    $result->runid = $run->id;
                    $result->iterationno = $nextiteration;
                    $result->nodeid = $nodeid;
                    $calculatedvalue = self::clamp_node_value($calculatedvalue, $node);

                    $result->nodevalue = $calculatedvalue;
                    $result->valueorigin = 'calculation';
                    $result->timecreated = $time;
                    $result->id = $DB->insert_record('commandroom_results', $result);

                    $nextstate[$nodeid] = $result;
                    continue;
                }
            }

            $updateconfig = self::get_updateconfig($node);
            $updatemode = is_array($updateconfig) && !empty($updateconfig['mode'])
                ? (string)$updateconfig['mode']
                : '';

            if (in_array($updatemode, ['stock_with_rate', 'stock_accumulation', 'accumulate'], true)) {
                $oldvalue = (float)$currentstate[$nodeid]->nodevalue;

                $base = $updateconfig['base'] ?? 'self';
                if (!in_array($base, ['self', 'zero'], true)) {
                    $transaction->rollback(new moodle_exception('error:invalidupdateconfig', 'mod_commandroom', '', s($node->name)));
                }

                $startingvalue = $base === 'zero' ? 0.0 : $oldvalue;

                $inflowtotal = 0.0;
                $inflows = self::get_config_node_list($updateconfig, ['inflows', 'adds']);
                foreach ($inflows as $inflownode) {
                    $inflowid = self::resolve_node_reference_id($inflownode, $nodemapbyref);
                    if ($inflowid > 0) {
                        $inflowtotal += self::resolve_source_value($inflowid, $currentstate, $nextstate, $shockadjustmentsbynodeid);
                    }
                }

                $outflowtotal = 0.0;
                $outflows = self::get_config_node_list($updateconfig, ['outflows', 'subtracts']);
                foreach ($outflows as $outflownode) {
                    $outflowid = self::resolve_node_reference_id($outflownode, $nodemapbyref);
                    if ($outflowid > 0) {
                        $outflowtotal += self::resolve_source_value($outflowid, $currentstate, $nextstate, $shockadjustmentsbynodeid);
                    }
                }

                $ratevalue = 0.0;
                if (!empty($updateconfig['rate'])) {
                    $ratenodeid = self::resolve_node_reference_id($updateconfig['rate'], $nodemapbyref);
                    if ($ratenodeid > 0) {
                        $ratevalue = self::resolve_source_value($ratenodeid, $currentstate, $nextstate, $shockadjustmentsbynodeid);
                    }
                }

                $newvalue = $startingvalue + $inflowtotal - $outflowtotal + ($startingvalue * $ratevalue);

                $result = new \stdClass();
                $result->runid = $run->id;
                $result->iterationno = $nextiteration;
                $result->nodeid = $nodeid;
                $newvalue = self::clamp_node_value($newvalue, $node);

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
                    $sourcevalue = self::resolve_source_value($sourceid, $currentstate, $nextstate, $shockadjustmentsbynodeid);

                    if ($edge->relationtype === 'linear') {
                        $computedvalue += ($sourcevalue * $strength);
                    } else {
                        $transaction->rollback(new moodle_exception('error:unsupportedrelationtype', 'mod_commandroom', '', s($edge->relationtype)));
                    }
                }
                $newvalue = $computedvalue;
            }

            $result = new \stdClass();
            $result->runid = $run->id;
            $result->iterationno = $nextiteration;
            $result->nodeid = $nodeid;
            $newvalue = self::clamp_node_value($newvalue, $node);

            $result->nodevalue = $newvalue;
            $result->valueorigin = 'computed';
            $result->timecreated = $time;
            $result->id = $DB->insert_record('commandroom_results', $result);

            $nextstate[$nodeid] = $result;
        }

        $runupdate = new \stdClass();
        $runupdate->id = $run->id;
        $runupdate->currentiteration = $nextiteration;
        $runupdate->totaliterations = $totaliterations;
        $runupdate->timemodified = $time;

        if ($run->status === 'draft') {
            $runupdate->status = 'inprogress';
        }

        $iscompleted = 0;
        $message = get_string('simulationadvanced', 'mod_commandroom');
        if ($nextiteration >= $totaliterations) {
            $runupdate->status = 'completed';
            $runupdate->timecompleted = $time;
            $iscompleted = 1;
            $message = get_string('endofscenariorun', 'mod_commandroom');
        }

        $DB->update_record('commandroom_runs', $runupdate);
        $transaction->allow_commit();

        return [
            'status' => $iscompleted ? 'completed' : 'ok',
            'runid' => (int)$run->id,
            'fromiteration' => $currentiteration,
            'toiteration' => $nextiteration,
            'nodecount' => count($nextstate),
            'currentiteration' => $nextiteration,
            'totaliterations' => $totaliterations,
            'iscompleted' => $iscompleted,
            'message' => $message,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'Result status'),
            'runid' => new external_value(PARAM_INT, 'Run id'),
            'fromiteration' => new external_value(PARAM_INT, 'Source iteration'),
            'toiteration' => new external_value(PARAM_INT, 'Created iteration'),
            'nodecount' => new external_value(PARAM_INT, 'Number of result rows written'),
            'currentiteration' => new external_value(PARAM_INT, 'Current run iteration after processing'),
            'totaliterations' => new external_value(PARAM_INT, 'Maximum iterations for this run'),
            'iscompleted' => new external_value(PARAM_INT, '1 if the run is now complete, otherwise 0'),
            'message' => new external_value(PARAM_TEXT, 'User-facing status message'),
        ]);
    }
}
