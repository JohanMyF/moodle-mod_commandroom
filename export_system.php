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
 * System export page for mod_commandroom.
 *
 * @package    mod_commandroom
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

/**
 * Decode JSON safely for export.
 *
 * @param string|null $json
 * @return array|null
 */
function mod_commandroom_export_decode_json(?string $json): ?array {
    if ($json === null || trim($json) === '') {
        return null;
    }

    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : null;
}

/**
 * Convert node ids in calculation config back to export refs.
 *
 * @param mixed $value
 * @param array $noderefs
 * @return mixed
 */
function mod_commandroom_export_calculation_refs($value, array $noderefs) {
    if (!is_array($value)) {
        return $value;
    }

    if (($value['kind'] ?? '') === 'node' && !empty($value['nodeid']) && isset($noderefs[(int)$value['nodeid']])) {
        $value['ref'] = $noderefs[(int)$value['nodeid']];
        unset($value['nodeid']);
        return $value;
    }

    foreach ($value as $key => $item) {
        $value[$key] = mod_commandroom_export_calculation_refs($item, $noderefs);
    }

    return $value;
}


$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('commandroom', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$commandroom = $DB->get_record('commandroom', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/commandroom:exportsystem', $context);

$nodes = $DB->get_records('commandroom_nodes', ['commandroomid' => $commandroom->id], 'displayorder ASC, id ASC');
$edges = $DB->get_records('commandroom_edges', ['commandroomid' => $commandroom->id], 'id ASC');
$shocks = $DB->get_records('commandroom_shocks', ['commandroomid' => $commandroom->id], 'iterationno ASC, id ASC');

$noderefs = [];
$counter = 1;
foreach ($nodes as $node) {
    $noderefs[$node->id] = 'n' . $counter;
    $counter++;
}

$exportnodes = [];
foreach ($nodes as $node) {
    $exportnode = [
        'ref' => $noderefs[$node->id],
        'name' => $node->name,
        'description' => $node->description ?? '',
        'unitlabel' => $node->unitlabel ?? '',
        'interpretation' => $node->interpretation ?? '',
        'nodetype' => $node->nodetype,
        'initialvalue' => (float)$node->initialvalue,
        'minvalue' => $node->minimumvalue !== null ? (float)$node->minimumvalue : null,
        'maxvalue' => $node->maximumvalue !== null ? (float)$node->maximumvalue : null,
        'studentcontrolled' => (int)$node->studentcontrolled,
        'visibletostudents' => (int)$node->visibletostudents,
        'displayorder' => (int)$node->displayorder,
    ];

    $visualconfig = mod_commandroom_export_decode_json($node->visualconfig ?? null);
    if ($visualconfig !== null) {
        $exportnode['visual'] = $visualconfig;
    }

    if (!empty($node->updateconfig)) {
        $decodedupdate = json_decode($node->updateconfig, true);
        if (is_array($decodedupdate)) {
            if (!empty($decodedupdate['rate']) && isset($noderefs[$decodedupdate['rate']])) {
                $decodedupdate['rate'] = $noderefs[$decodedupdate['rate']];
            }
            if (!empty($decodedupdate['inflows']) && is_array($decodedupdate['inflows'])) {
                foreach ($decodedupdate['inflows'] as $k => $inflowid) {
                    if (isset($noderefs[$inflowid])) {
                        $decodedupdate['inflows'][$k] = $noderefs[$inflowid];
                    }
                }
            }
            if (!empty($decodedupdate['outflows']) && is_array($decodedupdate['outflows'])) {
                foreach ($decodedupdate['outflows'] as $k => $outflowid) {
                    if (isset($noderefs[$outflowid])) {
                        $decodedupdate['outflows'][$k] = $noderefs[$outflowid];
                    }
                }
            }
            $exportnode['updateconfig'] = $decodedupdate;
        }
    }

    $calculationconfig = mod_commandroom_export_decode_json($node->calculationconfig ?? null);
    if ($calculationconfig !== null) {
        $exportnode['calculation'] = mod_commandroom_export_calculation_refs($calculationconfig, $noderefs);
    }

    $exportnodes[] = $exportnode;
}

$exportedges = [];
foreach ($edges as $edge) {
    if (!isset($noderefs[$edge->sourcenodeid]) || !isset($noderefs[$edge->targetnodeid])) {
        continue;
    }

    $functionconfig = '';
    if ($edge->functionconfig !== null && $edge->functionconfig !== '') {
        $decoded = json_decode($edge->functionconfig, true);
        $functionconfig = $decoded !== null ? $decoded : $edge->functionconfig;
    }

    $exportedges[] = [
        'source' => $noderefs[$edge->sourcenodeid],
        'target' => $noderefs[$edge->targetnodeid],
        'relationtype' => $edge->relationtype,
        'strength' => (float)$edge->strength,
        'delayiterations' => (int)$edge->delayiterations,
        'functionconfig' => $functionconfig,
        'polarity' => $edge->polarity ?? 'neutral',
        'label' => $edge->label ?? '',
        'loopgroup' => $edge->loopgroup ?? '',
        'curvature' => (int)($edge->curvature ?? 0),
        'visibletostudents' => (int)$edge->visibletostudents,
    ];
}

$exportshocks = [];
foreach ($shocks as $shock) {
    if (!isset($noderefs[$shock->nodeid])) {
        continue;
    }

    $exportshocks[] = [
        'node' => $noderefs[$shock->nodeid],
        'shocktype' => $shock->shocktype ?? 'scheduled',
        'iterationno' => (int)$shock->iterationno,
        'adjustment' => (float)$shock->adjustment,
        'minadjustment' => $shock->minadjustment !== null ? (float)$shock->minadjustment : null,
        'maxadjustment' => $shock->maxadjustment !== null ? (float)$shock->maxadjustment : null,
        'applyeveryiteration' => (int)($shock->applyeveryiteration ?? 0),
        'visibletostudents' => (int)$shock->visibletostudents,
        'description' => $shock->description ?? '',
    ];
}

$exportdata = [
    'metadata' => [
        'plugin' => 'mod_commandroom',
        'pluginname' => 'Situation Room',
        'version' => '0.1.4-alpha',
        'exportedat' => time(),
        'timesteplabel' => $commandroom->timesteplabel,
        'stepduration' => (int)$commandroom->stepduration,
        'stepdurationunit' => $commandroom->stepdurationunit,
        'totaliterations' => (int)$commandroom->totaliterations,
        'useshocks' => (int)$commandroom->useshocks,
    ],
    'nodes' => $exportnodes,
    'edges' => $exportedges,
    'shocks' => $exportshocks,
];

$json = json_encode($exportdata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    throw new moodle_exception('error:exportfailed', 'mod_commandroom');
}

$exportrecord = new stdClass();
$exportrecord->commandroomid = $commandroom->id;
$exportrecord->userid = $USER->id;
$exportrecord->name = clean_filename(format_string($commandroom->name, true, ['context' => $context]));
$exportrecord->jsonhash = hash('sha256', $json);
$exportrecord->timecreated = time();
$DB->insert_record('commandroom_exports', $exportrecord);

$filenamebase = clean_filename(format_string($commandroom->name, true, ['context' => $context]));
if ($filenamebase === '') {
    $filenamebase = 'situation-room';
}
$filename = $filenamebase . '.json';

send_file($json, $filename, 0, 0, true, true, 'application/json; charset=utf-8');
exit;
