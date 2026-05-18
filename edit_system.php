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
 * System import and inspection page for mod_commandroom.
 *
 * @package    mod_commandroom
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);
$importjson = optional_param('importjson', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('commandroom', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$commandroom = $DB->get_record('commandroom', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/commandroom:manageruns', $context);

$PAGE->set_url('/mod/commandroom/edit_system.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('editsystem', 'mod_commandroom'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_activity_record($commandroom);
$PAGE->set_cm($cm);

$notifications = [];

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

    // Optional growth/rate node. Empty means "None" and should not fail import.
    if (array_key_exists('rate', $updateconfig) && (string)$updateconfig['rate'] !== '') {
        $rateref = (string)$updateconfig['rate'];
        if (!isset($nodemap[$rateref])) {
            throw new moodle_exception('error:invalidedgesource', 'mod_commandroom', '', $rateref);
        }
        $normalised['rate'] = (int)$nodemap[$rateref];
    }

    // Builder currently writes inflows/outflows. Keep adds/subtracts as friendly aliases
    // so older or hand-written JSON can still be imported safely.
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
        $rawupdateconfig = null;
        if (!empty($node['updateconfig']) && is_array($node['updateconfig'])) {
            $rawupdateconfig = $node['updateconfig'];
        } else if (!empty($node['update']) && is_array($node['update'])) {
            // Friendly alias for future/hand-written JSON.
            $rawupdateconfig = $node['update'];
        }

        if ($rawupdateconfig !== null) {
            $updaterecord = new stdClass();
            $noderef = (string)($node['ref'] ?? ($node['id'] ?? ''));
            $updaterecord->id = $nodemap[$noderef];
            $updaterecord->updateconfig = mod_commandroom_normalise_updateconfig($rawupdateconfig, $nodemap);
            $DB->update_record('commandroom_nodes', $updaterecord);
        }
    }

    foreach ($data['nodes'] as $node) {
        $rawcalculationconfig = null;
        if (!empty($node['calculation']) && is_array($node['calculation'])) {
            $rawcalculationconfig = $node['calculation'];
        } else if (!empty($node['calculationconfig']) && is_array($node['calculationconfig'])) {
            // Friendly alias for exported or hand-written JSON.
            $rawcalculationconfig = $node['calculationconfig'];
        }

        if ($rawcalculationconfig !== null) {
            $updaterecord = new stdClass();
            $noderef = (string)($node['ref'] ?? ($node['id'] ?? ''));
            $updaterecord->id = $nodemap[$noderef];
            $updaterecord->calculationconfig = mod_commandroom_normalise_calculationconfig($rawcalculationconfig, $nodemap);
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

if ($importjson && confirm_sesskey()) {
    require_capability('mod/commandroom:importsystem', $context);

    if (empty($_FILES['jsonfile']) || empty($_FILES['jsonfile']['tmp_name'])) {
        $notifications[] = $OUTPUT->notification(get_string('error:nojsonfileuploaded', 'mod_commandroom'), \core\output\notification::NOTIFY_ERROR);
    } else {
        $filename = $_FILES['jsonfile']['name'];
        $tmpname = $_FILES['jsonfile']['tmp_name'];
        $extension = core_text::strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ($extension !== 'json') {
            $notifications[] = $OUTPUT->notification(get_string('error:jsonextensionrequired', 'mod_commandroom'), \core\output\notification::NOTIFY_ERROR);
        } else {
            $rawjson = file_get_contents($tmpname);
            if ($rawjson === false || trim($rawjson) === '') {
                $notifications[] = $OUTPUT->notification(get_string('error:emptyjsonfile', 'mod_commandroom'), \core\output\notification::NOTIFY_ERROR);
            } else {
                $data = json_decode($rawjson, true);
                if (!is_array($data)) {
                    $notifications[] = $OUTPUT->notification(get_string('error:invalidjsonformat', 'mod_commandroom'), \core\output\notification::NOTIFY_ERROR);
                } else {
                    $errors = mod_commandroom_validate_import_data($data);
                    if ($errors) {
                        foreach ($errors as $error) {
                            $notifications[] = $OUTPUT->notification($error, \core\output\notification::NOTIFY_ERROR);
                        }
                    } else {
                        mod_commandroom_import_system($commandroom, $data);
                        redirect(new moodle_url('/mod/commandroom/builder.php', ['id' => $cm->id]), get_string('jsonimportsuccess', 'mod_commandroom'), null, \core\output\notification::NOTIFY_SUCCESS);
                    }
                }
            }
        }
    }
}

$nodes = $DB->get_records('commandroom_nodes', ['commandroomid' => $commandroom->id], 'displayorder ASC, id ASC');
$edges = $DB->get_records('commandroom_edges', ['commandroomid' => $commandroom->id], 'id ASC');
$shocks = $DB->get_records('commandroom_shocks', ['commandroomid' => $commandroom->id], 'iterationno ASC, id ASC');

$nodenames = [];
foreach ($nodes as $node) {
    $nodenames[$node->id] = $node->name;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('importsystemfor', 'mod_commandroom', format_string($commandroom->name)), 2);

$settingsurl = new moodle_url('/course/modedit.php', ['update' => $cm->id, 'return' => 1]);
$builderurl = new moodle_url('/mod/commandroom/builder.php', ['id' => $cm->id]);
$viewurl = new moodle_url('/mod/commandroom/view.php', ['id' => $cm->id]);
$exporturl = new moodle_url('/mod/commandroom/export_system.php', ['id' => $cm->id]);

$managementactions = html_writer::link(
    $settingsurl,
    get_string('returntosettings', 'mod_commandroom'),
    ['class' => 'btn btn-secondary']
) . ' ' . html_writer::link(
    $builderurl,
    get_string('openbuilder', 'mod_commandroom'),
    ['class' => 'btn btn-primary']
) . ' ' . html_writer::link(
    $viewurl,
    get_string('viewactivity', 'mod_commandroom'),
    ['class' => 'btn btn-outline-secondary']
) . ' ' . html_writer::link(
    $exporturl,
    get_string('exportjson', 'mod_commandroom'),
    ['class' => 'btn btn-outline-secondary']
);

echo $OUTPUT->box(
    html_writer::tag('p', get_string('importsystemintro', 'mod_commandroom')) .
        html_writer::div($managementactions, 'commandroom-system-management-actions'),
    'generalbox'
);

foreach ($notifications as $notification) {
    echo $notification;
}

if (has_capability('mod/commandroom:importsystem', $context)) {
    $importurl = new moodle_url('/mod/commandroom/edit_system.php', ['id' => $cm->id, 'importjson' => 1]);
    echo html_writer::start_div('generalbox');
    echo html_writer::tag('h3', get_string('importjson', 'mod_commandroom'));
    echo html_writer::tag('p', get_string('jsonimporthelp', 'mod_commandroom'));
    echo html_writer::start_tag('form', ['action' => $importurl, 'method' => 'post', 'enctype' => 'multipart/form-data']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'file', 'name' => 'jsonfile', 'accept' => '.json,application/json']);
    echo html_writer::empty_tag('br');
    echo html_writer::empty_tag('br');
    echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-primary', 'value' => get_string('importjsonfile', 'mod_commandroom')]);
    echo html_writer::end_tag('form');
    echo html_writer::tag('p', get_string('importthenbuilderhelp', 'mod_commandroom'), ['class' => 'form-text text-muted']);
    echo html_writer::end_div();
}

echo html_writer::start_div('generalbox');
echo html_writer::tag('h3', get_string('nodes', 'mod_commandroom'));
if (!$nodes) {
    echo $OUTPUT->notification(get_string('nonodesdefined', 'mod_commandroom'), \core\output\notification::NOTIFY_INFO);
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    $table->head = [get_string('nodename', 'mod_commandroom'), get_string('nodetype', 'mod_commandroom'), get_string('initialvalue', 'mod_commandroom'), get_string('studentcontrolled', 'mod_commandroom'), get_string('visibletostudents', 'mod_commandroom'), get_string('visualconfig', 'mod_commandroom'), get_string('updateconfig', 'mod_commandroom'), get_string('calculationconfig', 'mod_commandroom'), get_string('displayorder', 'mod_commandroom')];
    foreach ($nodes as $node) {
        $visualsummary = '-';
        if (!empty($node->visualconfig)) {
            $visualdecoded = json_decode((string)$node->visualconfig, true);
            if (is_array($visualdecoded)) {
                $visualsummary = s(($visualdecoded['type'] ?? '') . ' / ' . ($visualdecoded['icon'] ?? ''));
            }
        }
        $updatesummary = '-';
        if (!empty($node->updateconfig)) {
            $updatedecoded = json_decode((string)$node->updateconfig, true);
            if (is_array($updatedecoded)) {
                $updateparts = [];
                if (!empty($updatedecoded['mode'])) {
                    $updateparts[] = get_string('updatesummarymode', 'mod_commandroom', $updatedecoded['mode']);
                }
                if (!empty($updatedecoded['inflows']) && is_array($updatedecoded['inflows'])) {
                    $inflowlabels = [];
                    foreach ($updatedecoded['inflows'] as $inflowid) {
                        $inflowlabels[] = $nodenames[(int)$inflowid] ?? (string)$inflowid;
                    }
                    $updateparts[] = get_string('updatesummaryinflows', 'mod_commandroom', implode(', ', $inflowlabels));
                }
                if (!empty($updatedecoded['outflows']) && is_array($updatedecoded['outflows'])) {
                    $outflowlabels = [];
                    foreach ($updatedecoded['outflows'] as $outflowid) {
                        $outflowlabels[] = $nodenames[(int)$outflowid] ?? (string)$outflowid;
                    }
                    $updateparts[] = get_string('updatesummaryoutflows', 'mod_commandroom', implode(', ', $outflowlabels));
                }
                $updatesummary = s(implode(' | ', $updateparts));
            }
        }

        $calculationsummary = '-';
        if (!empty($node->calculationconfig)) {
            $calculationdecoded = json_decode((string)$node->calculationconfig, true);
            if (is_array($calculationdecoded)) {
                $calculationsummary = s($calculationdecoded['type'] ?? '');
            }
        }
        $table->data[] = [s($node->name), s($node->nodetype), format_float((float)$node->initialvalue, 2), $node->studentcontrolled ? get_string('yes') : get_string('no'), $node->visibletostudents ? get_string('yes') : get_string('no'), $visualsummary, $updatesummary, $calculationsummary, (int)$node->displayorder];
    }
    echo html_writer::table($table);
}
echo html_writer::end_div();

echo html_writer::start_div('generalbox');
echo html_writer::tag('h3', get_string('edges', 'mod_commandroom'));
if (!$edges) {
    echo $OUTPUT->notification(get_string('noedgesdefined', 'mod_commandroom'), \core\output\notification::NOTIFY_INFO);
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    $table->head = [get_string('sourcenode', 'mod_commandroom'), get_string('targetnode', 'mod_commandroom'), get_string('relationtype', 'mod_commandroom'), get_string('strength', 'mod_commandroom'), get_string('polarity', 'mod_commandroom'), get_string('label', 'mod_commandroom'), get_string('loopgroup', 'mod_commandroom'), get_string('curvature', 'mod_commandroom'), get_string('delayiterations', 'mod_commandroom'), get_string('visibletostudents', 'mod_commandroom')];
    foreach ($edges as $edge) {
        $table->data[] = [s($nodenames[$edge->sourcenodeid] ?? ''), s($nodenames[$edge->targetnodeid] ?? ''), s($edge->relationtype), format_float((float)$edge->strength, 3), s($edge->polarity ?? 'neutral'), s($edge->label ?? ''), s($edge->loopgroup ?? ''), (int)($edge->curvature ?? 0), (int)$edge->delayiterations, $edge->visibletostudents ? get_string('yes') : get_string('no')];
    }
    echo html_writer::table($table);
}
echo html_writer::end_div();

echo html_writer::start_div('generalbox');
echo html_writer::tag('h3', get_string('shocks', 'mod_commandroom'));
if (!$shocks) {
    echo $OUTPUT->notification(get_string('noshocksdefined', 'mod_commandroom'), \core\output\notification::NOTIFY_INFO);
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    $table->head = [get_string('node', 'mod_commandroom'), get_string('shocktype', 'mod_commandroom'), get_string('iterationno', 'mod_commandroom'), get_string('adjustment', 'mod_commandroom'), get_string('minadjustment', 'mod_commandroom'), get_string('maxadjustment', 'mod_commandroom'), get_string('applyeveryiteration', 'mod_commandroom'), get_string('visibletostudents', 'mod_commandroom'), get_string('description', 'mod_commandroom')];
    foreach ($shocks as $shock) {
        $table->data[] = [s($nodenames[$shock->nodeid] ?? ''), s($shock->shocktype ?? 'scheduled'), (int)$shock->iterationno, format_float((float)$shock->adjustment, 3), $shock->minadjustment !== null ? format_float((float)$shock->minadjustment, 3) : '-', $shock->maxadjustment !== null ? format_float((float)$shock->maxadjustment, 3) : '-', !empty($shock->applyeveryiteration) ? get_string('yes') : get_string('no'), $shock->visibletostudents ? get_string('yes') : get_string('no'), s((string)$shock->description)];
    }
    echo html_writer::table($table);
}
echo html_writer::end_div();

echo $OUTPUT->footer();
