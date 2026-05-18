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
 * System builder save endpoint for mod_commandroom.
 *
 * @package    mod_commandroom
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);
$jsonencoded = required_param('json64', PARAM_ALPHANUMEXT);

require_sesskey();

if ($jsonencoded === '') {
    throw new moodle_exception('error:emptyjsonfile', 'mod_commandroom');
}

// The Builder sends JSON as base64url so Moodle can validate the request
// parameter without PARAM_RAW while preserving the JSON text exactly.
$base64 = strtr($jsonencoded, '-_', '+/');
$padding = strlen($base64) % 4;
if ($padding > 0) {
    $base64 .= str_repeat('=', 4 - $padding);
}

$json = base64_decode($base64, true);
if ($json === false || $json === '') {
    throw new moodle_exception('error:invalidjson', 'mod_commandroom');
}

if (core_text::strlen($json) > 1048576) {
    throw new moodle_exception('error:jsonpayloadtoolarge', 'mod_commandroom');
}

$cm = get_coursemodule_from_id('commandroom', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$commandroom = $DB->get_record('commandroom', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/commandroom:manageruns', $context);

function mod_commandroom_validate_import_data(array $data): array {
    $errors = [];
    if (!array_key_exists('nodes', $data) || !is_array($data['nodes'])) {
        $errors[] = get_string('error:jsonnodesmissing', 'mod_commandroom');
    }
    if (!array_key_exists('edges', $data) || !is_array($data['edges'])) {
        $errors[] = get_string('error:jsonedgesmissing', 'mod_commandroom');
    }
    if (!array_key_exists('shocks', $data)) {
        $data['shocks'] = [];
    }

    if (isset($data['nodes']) && is_array($data['nodes'])) {
        $refs = [];
        foreach ($data['nodes'] as $index => $node) {
            $rownum = $index + 1;
            if (!is_array($node)) {
                $errors[] = get_string('error:invalidnodeentry', 'mod_commandroom', $rownum);
                continue;
            }
            $noderef = $node['ref'] ?? ($node['id'] ?? null);
            if ($noderef === null || $noderef === '') {
                $errors[] = get_string('error:noderefmissing', 'mod_commandroom', $rownum);
            } else {
                $noderef = (string)$noderef;
                if (isset($refs[$noderef])) {
                    $errors[] = get_string('error:duplicatenoderef', 'mod_commandroom', s($noderef));
                } else {
                    $refs[$noderef] = true;
                }
            }
            if (empty($node['name'])) {
                $errors[] = get_string('error:nodenameempty', 'mod_commandroom');
            }
            if (empty($node['nodetype']) || !in_array($node['nodetype'], ['stock', 'computed', 'flow', 'variable'], true)) {
                $errors[] = get_string('error:invalidnodetype', 'mod_commandroom');
            }
            if (isset($node['updateconfig']) && !is_array($node['updateconfig'])) {
                $errors[] = get_string('error:invalidnodeentry', 'mod_commandroom', $rownum);
            }
            if (isset($node['visual']) && !is_array($node['visual'])) {
                $errors[] = get_string('error:invalidnodeentry', 'mod_commandroom', $rownum);
            }
            if (isset($node['calculation']) && !is_array($node['calculation'])) {
                $errors[] = get_string('error:invalidnodeentry', 'mod_commandroom', $rownum);
            }
        }

        if (isset($data['edges']) && is_array($data['edges'])) {
            foreach ($data['edges'] as $index => $edge) {
                $rownum = $index + 1;
                if (!is_array($edge)) {
                    $errors[] = get_string('error:invalidedgeentry', 'mod_commandroom', $rownum);
                    continue;
                }
                $sourceref = isset($edge['source']) ? (string)$edge['source'] : '';
                $targetref = isset($edge['target']) ? (string)$edge['target'] : '';

                if ($sourceref === '' || !isset($refs[$sourceref])) {
                    $errors[] = get_string('error:invalidedgesource', 'mod_commandroom', $rownum);
                }
                if ($targetref === '' || !isset($refs[$targetref])) {
                    $errors[] = get_string('error:invalidedgetarget', 'mod_commandroom', $rownum);
                }
                if (empty($edge['relationtype']) || !in_array($edge['relationtype'], ['linear', 'inverse', 'nonlinear'], true)) {
                    $errors[] = get_string('error:invalidrelationtype', 'mod_commandroom');
                }
                if (isset($edge['polarity']) && $edge['polarity'] !== '' &&
                        !in_array($edge['polarity'], ['positive', 'negative', 'neutral'], true)) {
                    $errors[] = get_string('error:invalidedgeentry', 'mod_commandroom', $rownum);
                }
                if (isset($edge['loopgroup']) && !is_scalar($edge['loopgroup'])) {
                    $errors[] = get_string('error:invalidedgeentry', 'mod_commandroom', $rownum);
                }
                if (isset($edge['label']) && !is_scalar($edge['label'])) {
                    $errors[] = get_string('error:invalidedgeentry', 'mod_commandroom', $rownum);
                }
                if (isset($edge['curvature']) && !is_numeric($edge['curvature'])) {
                    $errors[] = get_string('error:invalidedgeentry', 'mod_commandroom', $rownum);
                }
            }
        }

        if (isset($data['shocks']) && is_array($data['shocks'])) {
            foreach ($data['shocks'] as $index => $shock) {
                $rownum = $index + 1;
                if (!is_array($shock)) {
                    $errors[] = get_string('error:invalidshockentry', 'mod_commandroom', $rownum);
                    continue;
                }
                $shockref = $shock['node'] ?? ($shock['nodeid'] ?? null);
                $shockref = $shockref === null ? '' : (string)$shockref;
                if ($shockref === '' || !isset($refs[$shockref])) {
                    $errors[] = get_string('error:invalidshocknode', 'mod_commandroom', $rownum);
                }
                $shocktype = $shock['shocktype'] ?? 'scheduled';
                if (!in_array($shocktype, ['scheduled', 'random_range'], true)) {
                    $errors[] = get_string('error:invalidshockentry', 'mod_commandroom', $rownum);
                }
            }
        }
    }

    return $errors;
}

function mod_commandroom_normalise_updateconfig(array $updateconfig, array $nodemap): ?string {
    if (empty($updateconfig['mode'])) {
        return null;
    }

    $normalised = ['mode' => clean_param((string)$updateconfig['mode'], PARAM_TEXT)];

    if (isset($updateconfig['base'])) {
        $normalised['base'] = clean_param((string)$updateconfig['base'], PARAM_TEXT);
    }

    if (array_key_exists('rate', $updateconfig) && (string)$updateconfig['rate'] !== '') {
        $rateref = (string)$updateconfig['rate'];
        if (!isset($nodemap[$rateref])) {
            throw new moodle_exception('error:invalidedgesource', 'mod_commandroom', '', $rateref);
        }
        $normalised['rate'] = (int)$nodemap[$rateref];
    }

    $listmap = [
        'inflows' => ['inflows', 'adds'],
        'outflows' => ['outflows', 'subtracts'],
    ];

    foreach ($listmap as $targetkey => $sourcekeys) {
        $rawitems = null;
        foreach ($sourcekeys as $sourcekey) {
            if (array_key_exists($sourcekey, $updateconfig)) {
                $rawitems = $updateconfig[$sourcekey];
                break;
            }
        }

        if ($rawitems === null) {
            continue;
        }

        if (!is_array($rawitems)) {
            throw new moodle_exception('error:invalidnodeentry', 'mod_commandroom', '', 'updateconfig.' . $targetkey);
        }

        $normalised[$targetkey] = [];
        foreach ($rawitems as $noderef) {
            $noderef = (string)$noderef;
            if ($noderef === '') {
                continue;
            }
            if (!isset($nodemap[$noderef])) {
                throw new moodle_exception('error:invalidedgesource', 'mod_commandroom', '', $noderef);
            }
            $normalised[$targetkey][] = (int)$nodemap[$noderef];
        }
    }

    return json_encode($normalised);
}

function mod_commandroom_normalise_visualconfig(array $visualconfig): ?string {
    if (empty($visualconfig['type'])) {
        return null;
    }

    $type = clean_param((string)$visualconfig['type'], PARAM_TEXT);
    if (!in_array($type, ['repeated_icon', 'scaling_icon'], true)) {
        return null;
    }

    $normalised = [];
    $normalised['type'] = $type;

    $icon = isset($visualconfig['icon'])
        ? clean_param((string)$visualconfig['icon'], PARAM_ALPHANUMEXT)
        : 'default';

    if ($icon === '') {
        $icon = 'default';
    }

    $normalised['icon'] = $icon;

    if ($type === 'repeated_icon') {
        $normalised['unitvalue'] = isset($visualconfig['unitvalue'])
            ? max(0.000001, (float)$visualconfig['unitvalue'])
            : 10.0;

        $normalised['maxicons'] = isset($visualconfig['maxicons'])
            ? max(1, (int)$visualconfig['maxicons'])
            : 80;

        $normalised['iconsize'] = isset($visualconfig['iconsize'])
            ? max(8, (int)$visualconfig['iconsize'])
            : 36;

        $normalised['layout'] = isset($visualconfig['layout'])
            ? clean_param((string)$visualconfig['layout'], PARAM_TEXT)
            : 'grid';
    }

    if ($type === 'scaling_icon') {
        $normalised['scaleby'] = isset($visualconfig['scaleby'])
            ? clean_param((string)$visualconfig['scaleby'], PARAM_TEXT)
            : 'currentvalue';

        $normalised['minvalue'] = array_key_exists('minvalue', $visualconfig)
            ? (float)$visualconfig['minvalue']
            : 0.0;

        $normalised['maxvalue'] = array_key_exists('maxvalue', $visualconfig)
            ? (float)$visualconfig['maxvalue']
            : 100.0;

        $normalised['minsize'] = isset($visualconfig['minsize'])
            ? max(8, (int)$visualconfig['minsize'])
            : 60;

        $normalised['maxsize'] = isset($visualconfig['maxsize'])
            ? max($normalised['minsize'], (int)$visualconfig['maxsize'])
            : 220;
    }

    foreach (['x', 'y', 'w', 'h'] as $gridkey) {
        if (isset($visualconfig[$gridkey])) {
            $normalised[$gridkey] = max(1, (int)$visualconfig[$gridkey]);
        }
    }

    return json_encode($normalised);
}

function mod_commandroom_normalise_calculationconfig(array $calculationconfig, array $nodemap): ?string {
    if (empty($calculationconfig['type'])) {
        return null;
    }

    $type = clean_param((string)$calculationconfig['type'], PARAM_TEXT);
    $allowedtypes = [
        'multiply',
        'divide',
        'percentage',
        'add',
        'sum',
        'linear',
        'diminishing_returns',
        'optimum_point',
        'bell_curve',
        'random_range',
    ];

    if (!in_array($type, $allowedtypes, true)) {
        return null;
    }

    $normalised = ['type' => $type];

    $normaliseoperand = function($operand) use ($nodemap) {
        if (is_array($operand)) {
            $kind = isset($operand['kind']) ? (string)$operand['kind'] : '';

            if ($kind === 'number' && array_key_exists('value', $operand) && is_numeric($operand['value'])) {
                return [
                    'kind' => 'number',
                    'value' => (float)$operand['value'],
                ];
            }

            if ($kind === 'node') {
                $ref = $operand['ref'] ?? ($operand['nodeid'] ?? null);
                $ref = $ref === null ? '' : (string)$ref;

                if ($ref !== '' && isset($nodemap[$ref])) {
                    return [
                        'kind' => 'node',
                        'ref' => clean_param($ref, PARAM_ALPHANUMEXT),
                        'nodeid' => (int)$nodemap[$ref],
                    ];
                }
            }

            return null;
        }

        if (is_string($operand) && isset($nodemap[$operand])) {
            return [
                'kind' => 'node',
                'ref' => clean_param($operand, PARAM_ALPHANUMEXT),
                'nodeid' => (int)$nodemap[$operand],
            ];
        }

        if (is_numeric($operand)) {
            return [
                'kind' => 'number',
                'value' => (float)$operand,
            ];
        }

        return null;
    };

    $normaliseoperandfield = function(string $fieldname) use ($calculationconfig, $normaliseoperand) {
        if (!array_key_exists($fieldname, $calculationconfig)) {
            return null;
        }
        return $normaliseoperand($calculationconfig[$fieldname]);
    };

    $normalisenumber = function(string $fieldname, float $default = 0.0) use ($calculationconfig): float {
        if (!array_key_exists($fieldname, $calculationconfig) || !is_numeric($calculationconfig[$fieldname])) {
            return $default;
        }
        return (float)$calculationconfig[$fieldname];
    };

    if ($type === 'multiply') {
        $left = $normaliseoperandfield('left');
        $right = $normaliseoperandfield('right');
        if ($left === null || $right === null) {
            return null;
        }
        $normalised['left'] = $left;
        $normalised['right'] = $right;
    }

    if ($type === 'divide') {
        $numerator = $normaliseoperandfield('numerator');
        $denominator = $normaliseoperandfield('denominator');
        if ($numerator === null || $denominator === null) {
            return null;
        }
        $normalised['numerator'] = $numerator;
        $normalised['denominator'] = $denominator;
    }

    if ($type === 'percentage') {
        $value = $normaliseoperandfield('value');
        $percent = $normaliseoperandfield('percent');
        if ($value === null || $percent === null) {
            return null;
        }
        $normalised['value'] = $value;
        $normalised['percent'] = $percent;
    }

    if ($type === 'add') {
        if (empty($calculationconfig['items']) || !is_array($calculationconfig['items'])) {
            return null;
        }

        $items = [];
        foreach ($calculationconfig['items'] as $item) {
            $normaliseditem = $normaliseoperand($item);
            if ($normaliseditem === null) {
                return null;
            }
            $items[] = $normaliseditem;
        }

        $normalised['items'] = $items;
    }

    if ($type === 'sum') {
        if (empty($calculationconfig['items']) || !is_array($calculationconfig['items'])) {
            return null;
        }

        $items = [];
        foreach ($calculationconfig['items'] as $item) {
            if (!is_array($item) || empty($item['operand']) || !is_array($item['operand'])) {
                return null;
            }

            $normalisedoperand = $normaliseoperand($item['operand']);
            if ($normalisedoperand === null) {
                return null;
            }

            $items[] = [
                'factor' => isset($item['factor']) ? (float)$item['factor'] : 1.0,
                'operand' => $normalisedoperand,
            ];
        }

        $normalised['items'] = $items;
    }

    if ($type === 'linear') {
        $input = $normaliseoperandfield('input');
        if ($input === null) {
            return null;
        }
        $normalised['input'] = $input;
        $normalised['slope'] = $normalisenumber('slope', 1.0);
        $normalised['intercept'] = $normalisenumber('intercept', 0.0);
    }

    if ($type === 'diminishing_returns') {
        $input = $normaliseoperandfield('input');
        if ($input === null) {
            return null;
        }
        $normalised['input'] = $input;
        $normalised['maximum'] = $normalisenumber('maximum', 100.0);
        $normalised['rate'] = max(0.000001, $normalisenumber('rate', 0.1));
    }

    if ($type === 'optimum_point') {
        $input = $normaliseoperandfield('input');
        if ($input === null) {
            return null;
        }
        $normalised['input'] = $input;
        $normalised['optimum'] = $normalisenumber('optimum', 10.0);
        $normalised['maximum'] = $normalisenumber('maximum', 100.0);
        $normalised['decline'] = max(0.0, $normalisenumber('decline', 1.0));
        $normalised['floor'] = $normalisenumber('floor', 0.0);
    }

    if ($type === 'bell_curve') {
        $input = $normaliseoperandfield('input');
        if ($input === null) {
            return null;
        }
        $normalised['input'] = $input;
        $normalised['centre'] = $normalisenumber('centre', 0.0);
        $normalised['maximum'] = $normalisenumber('maximum', 100.0);
        $normalised['spread'] = max(0.000001, $normalisenumber('spread', 1.0));
        $normalised['floor'] = $normalisenumber('floor', 0.0);
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

        $normalised['min'] = $min;
        $normalised['max'] = $max;
    }

    return json_encode($normalised);
}

function mod_commandroom_import_system(stdClass $commandroom, array $data): void {
    global $DB;

    $transaction = $DB->start_delegated_transaction();
    $metadata = $data['metadata'] ?? [];

    $commandroomupdate = new stdClass();
    $commandroomupdate->id = $commandroom->id;
    $commandroomupdate->timemodified = time();

    if (isset($metadata['timesteplabel'])) {
        $commandroomupdate->timesteplabel = clean_param($metadata['timesteplabel'], PARAM_TEXT);
    }
    if (isset($metadata['stepduration'])) {
        $commandroomupdate->stepduration = max(1, (int)$metadata['stepduration']);
    }
    if (isset($metadata['stepdurationunit'])) {
        $commandroomupdate->stepdurationunit = clean_param($metadata['stepdurationunit'], PARAM_TEXT);
    }
    if (isset($metadata['totaliterations'])) {
        $commandroomupdate->totaliterations = max(1, (int)$metadata['totaliterations']);
    }
    if (isset($metadata['useshocks'])) {
        $commandroomupdate->useshocks = empty($metadata['useshocks']) ? 0 : 1;
    }
    $DB->update_record('commandroom', $commandroomupdate);

    $DB->delete_records('commandroom_shocks', ['commandroomid' => $commandroom->id]);
    $DB->delete_records('commandroom_edges', ['commandroomid' => $commandroom->id]);
    $DB->delete_records('commandroom_nodes', ['commandroomid' => $commandroom->id]);

    $nodemap = [];
    foreach ($data['nodes'] as $node) {
        $noderef = (string)($node['ref'] ?? ($node['id'] ?? ''));
        $record = new stdClass();
        $record->commandroomid = $commandroom->id;
        $record->name = clean_param($node['name'], PARAM_TEXT);
        $record->nodetype = clean_param($node['nodetype'], PARAM_TEXT);
        $record->initialvalue = (float)($node['initialvalue'] ?? 0);
        $record->minimumvalue = array_key_exists('minimumvalue', $node) && $node['minimumvalue'] !== null
            ? (float)$node['minimumvalue']
            : (array_key_exists('minvalue', $node) && $node['minvalue'] !== null ? (float)$node['minvalue'] : 0);
        $record->maximumvalue = array_key_exists('maximumvalue', $node) && $node['maximumvalue'] !== null
            ? (float)$node['maximumvalue']
            : (array_key_exists('maxvalue', $node) && $node['maxvalue'] !== null ? (float)$node['maxvalue'] : 0);
        $record->studentcontrolled = empty($node['studentcontrolled']) ? 0 : 1;
        $record->visibletostudents = array_key_exists('visibletostudents', $node) ? (empty($node['visibletostudents']) ? 0 : 1) : 1;
        $record->svgfileitemid = 0;
        $record->updateconfig = null;
        $record->visualconfig = (!empty($node['visual']) && is_array($node['visual']))
            ? mod_commandroom_normalise_visualconfig($node['visual'])
            : null;
        $record->calculationconfig = null;
        $record->description = isset($node['description']) ? clean_param((string)$node['description'], PARAM_TEXT) : null;
        $record->unitlabel = isset($node['unitlabel']) ? clean_param((string)$node['unitlabel'], PARAM_TEXT) : null;
        $record->interpretation = isset($node['interpretation']) ? clean_param((string)$node['interpretation'], PARAM_TEXT) : null;
        $record->displayorder = isset($node['displayorder']) ? (int)$node['displayorder'] : 0;
        $newnodeid = $DB->insert_record('commandroom_nodes', $record);
        $nodemap[$noderef] = $newnodeid;
    }

    foreach ($data['nodes'] as $node) {
        if (!empty($node['updateconfig']) && is_array($node['updateconfig'])) {
            $updaterecord = new stdClass();
            $noderef = (string)($node['ref'] ?? ($node['id'] ?? ''));
            $updaterecord->id = $nodemap[$noderef];
            $updaterecord->updateconfig = mod_commandroom_normalise_updateconfig($node['updateconfig'], $nodemap);
            $DB->update_record('commandroom_nodes', $updaterecord);
        }
    }

    foreach ($data['nodes'] as $node) {
        if (!empty($node['calculation']) && is_array($node['calculation'])) {
            $updaterecord = new stdClass();
            $noderef = (string)($node['ref'] ?? ($node['id'] ?? ''));
            $updaterecord->id = $nodemap[$noderef];
            $updaterecord->calculationconfig = mod_commandroom_normalise_calculationconfig($node['calculation'], $nodemap);
            $DB->update_record('commandroom_nodes', $updaterecord);
        }
    }

    foreach ($data['edges'] as $edge) {
        $record = new stdClass();
        $record->commandroomid = $commandroom->id;
        $record->sourcenodeid = $nodemap[(string)$edge['source']];
        $record->targetnodeid = $nodemap[(string)$edge['target']];
        $record->relationtype = clean_param($edge['relationtype'], PARAM_TEXT);
        $record->strength = (float)($edge['strength'] ?? 0);
        $record->delayiterations = isset($edge['delayiterations']) ? max(0, (int)$edge['delayiterations']) : 0;
        $record->functionconfig = isset($edge['functionconfig']) ? json_encode($edge['functionconfig']) : null;

        $polarity = isset($edge['polarity']) ? clean_param((string)$edge['polarity'], PARAM_ALPHANUMEXT) : 'neutral';
        if (!in_array($polarity, ['positive', 'negative', 'neutral'], true)) {
            $polarity = 'neutral';
        }
        $record->polarity = $polarity;
        $record->label = isset($edge['label']) ? clean_param((string)$edge['label'], PARAM_TEXT) : null;
        $record->loopgroup = isset($edge['loopgroup']) ? clean_param((string)$edge['loopgroup'], PARAM_ALPHANUMEXT) : null;
        $record->curvature = isset($edge['curvature']) ? (int)$edge['curvature'] : 0;

        $record->visibletostudents = array_key_exists('visibletostudents', $edge) ? (empty($edge['visibletostudents']) ? 0 : 1) : 1;
        $DB->insert_record('commandroom_edges', $record);
    }

    if (!empty($data['shocks']) && is_array($data['shocks'])) {
        foreach ($data['shocks'] as $shock) {
            $record = new stdClass();
            $record->commandroomid = $commandroom->id;
            $record->nodeid = $nodemap[(string)($shock['node'] ?? $shock['nodeid'])];
            $record->shocktype = clean_param($shock['shocktype'] ?? 'scheduled', PARAM_TEXT);
            $record->iterationno = max(1, (int)($shock['iterationno'] ?? 1));
            $record->adjustment = (float)($shock['adjustment'] ?? 0);
            $record->minadjustment = array_key_exists('minadjustment', $shock) && $shock['minadjustment'] !== null ? (float)$shock['minadjustment'] : null;
            $record->maxadjustment = array_key_exists('maxadjustment', $shock) && $shock['maxadjustment'] !== null ? (float)$shock['maxadjustment'] : null;
            $record->applyeveryiteration = !empty($shock['applyeveryiteration']) ? 1 : 0;
            $record->visibletostudents = array_key_exists('visibletostudents', $shock) ? (empty($shock['visibletostudents']) ? 0 : 1) : 0;
            $record->description = isset($shock['description']) ? clean_param($shock['description'], PARAM_TEXT) : null;
            $DB->insert_record('commandroom_shocks', $record);
        }
    }

    $transaction->allow_commit();
}




/**
 * Reset existing simulation runs after the system structure has changed.
 *
 * Publishing a Builder JSON draft replaces nodes and edges. Existing run/results
 * rows may point to the previous node set, so they must be removed before the
 * activity is used again.
 *
 * @param int $commandroomid
 * @return void
 */
function mod_commandroom_builder_reset_runs(int $commandroomid): void {
    global $DB;

    $runs = $DB->get_records('commandroom_runs', ['commandroomid' => $commandroomid], '', 'id');

    if (empty($runs)) {
        return;
    }

    $runids = array_keys($runs);
    list($insql, $params) = $DB->get_in_or_equal($runids, SQL_PARAMS_NAMED, 'runid');

    if ($DB->get_manager()->table_exists('commandroom_results')) {
        $DB->delete_records_select('commandroom_results', "runid $insql", $params);
    }

    if ($DB->get_manager()->table_exists('commandroom_decisions')) {
        $columns = $DB->get_columns('commandroom_decisions');
        if (array_key_exists('runid', $columns)) {
            $DB->delete_records_select('commandroom_decisions', "runid $insql", $params);
        } else if (array_key_exists('commandroomid', $columns)) {
            $DB->delete_records('commandroom_decisions', ['commandroomid' => $commandroomid]);
        }
    }

    if ($DB->get_manager()->table_exists('commandroom_proposals')) {
        $columns = $DB->get_columns('commandroom_proposals');
        if (array_key_exists('runid', $columns)) {
            $DB->delete_records_select('commandroom_proposals', "runid $insql", $params);
        } else if (array_key_exists('commandroomid', $columns)) {
            $DB->delete_records('commandroom_proposals', ['commandroomid' => $commandroomid]);
        }
    }

    $DB->delete_records('commandroom_runs', ['commandroomid' => $commandroomid]);
}



@header('Content-Type: application/json; charset=utf-8');

try {
    $data = json_decode($json, true);

    if (!is_array($data)) {
        throw new moodle_exception('error:invalidjson', 'mod_commandroom');
    }

    if (!isset($data['shocks'])) {
        $data['shocks'] = [];
    }

    $errors = mod_commandroom_validate_import_data($data);
    if (!empty($errors)) {
        echo json_encode([
            'status' => 'error',
            'message' => implode("\n", $errors),
        ]);
        die();
    }

    mod_commandroom_import_system($commandroom, $data);
    mod_commandroom_builder_reset_runs((int)$commandroom->id);

    echo json_encode([
        'status' => 'ok',
        'message' => get_string('builderpublishsuccess', 'mod_commandroom'),
        'builderurl' => (new moodle_url('/mod/commandroom/builder.php', ['id' => $cm->id]))->out(false),
        'settingsurl' => (new moodle_url('/course/modedit.php', ['update' => $cm->id, 'return' => 1]))->out(false),
        'viewurl' => (new moodle_url('/mod/commandroom/view.php', ['id' => $cm->id]))->out(false),
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}
