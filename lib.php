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

/**
 * Library of interface functions and constants for module commandroom.
 *
 * @package    mod_commandroom
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Returns the information on whether the module supports a feature.
 *
 * @param string $feature
 * @return mixed True if yes, null if unknown
 */
function commandroom_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}


/**
 * Ensure optional System Brief fields exist before inserting/updating the instance.
 *
 * Moodle form data usually includes these fields when present, but this helper
 * makes the add/update path explicit and safe when fields are empty.
 *
 * @param stdClass $data
 * @return stdClass
 */
function commandroom_prepare_system_brief_fields(stdClass $data): stdClass {
    $fields = [
        'systembrief',
        'studentdecision',
        'learninggoal',
        'riskychoice',
        'safechoice',
        'nodeinventory',
        'presetkey',
    ];

    foreach ($fields as $field) {
        if (!property_exists($data, $field) || $data->$field === null) {
            $data->$field = ($field === 'presetkey') ? 'custom' : '';
        } else if (is_string($data->$field)) {
            $data->$field = trim($data->$field);
        }
    }

    $data->presetkey = clean_param((string)$data->presetkey, PARAM_ALPHANUMEXT);
    if ($data->presetkey === '') {
        $data->presetkey = 'custom';
    }

    return $data;
}



/**
 * Return the first Moodle group id for a user in this activity context.
 *
 * CommandRoom deliberately uses Moodle's normal group system. When a learner
 * belongs to more than one applicable group, the first group returned by
 * Moodle's groups API is used consistently across the activity and AJAX calls.
 *
 * @param int $courseid
 * @param int $userid
 * @param int $groupingid
 * @return int
 */
function commandroom_get_user_groupid(int $courseid, int $userid, int $groupingid = 0): int {
    $groups = groups_get_all_groups($courseid, $userid, $groupingid, 'g.id');
    if (empty($groups)) {
        return 0;
    }

    $firstgroup = reset($groups);
    return !empty($firstgroup->id) ? (int)$firstgroup->id : 0;
}

/**
 * Get the configured group leader for an activity group.
 *
 * @param int $commandroomid
 * @param int $groupid
 * @return int
 */
function commandroom_get_group_leader(int $commandroomid, int $groupid): int {
    global $DB;

    if ($commandroomid <= 0 || $groupid <= 0) {
        return 0;
    }

    $leaderid = $DB->get_field('commandroom_group_leaders', 'leaderid', [
        'commandroomid' => $commandroomid,
        'groupid' => $groupid,
    ]);

    return $leaderid ? (int)$leaderid : 0;
}

/**
 * Save group leader assignments submitted from the activity settings form.
 *
 * The form uses element names like groupleader_12, where 12 is the Moodle
 * group id. Only leaders who are actual members of the relevant group are
 * saved. Existing active runs for those groups are also aligned to the selected
 * leader so old teacher-led draft runs do not linger.
 *
 * @param int $commandroomid
 * @param stdClass $data
 * @param int $courseid
 */
function commandroom_save_group_leaders(int $commandroomid, stdClass $data, int $courseid): void {
    global $DB;

    if ($commandroomid <= 0 || $courseid <= 0) {
        return;
    }

    $assignments = [];
    foreach ((array)$data as $key => $value) {
        if (preg_match('/^groupleader_(\d+)$/', (string)$key, $matches)) {
            $groupid = (int)$matches[1];
            $leaderid = (int)$value;
            if ($groupid > 0 && $leaderid > 0) {
                $assignments[$groupid] = $leaderid;
            }
        }
    }

    // If the form did not contain leader fields, leave existing assignments intact.
    $hasleaderfields = false;
    foreach (array_keys((array)$data) as $key) {
        if (preg_match('/^groupleader_\d+$/', (string)$key)) {
            $hasleaderfields = true;
            break;
        }
    }
    if (!$hasleaderfields) {
        return;
    }

    $validassignments = [];
    if (!empty($assignments)) {
        $groupids = array_keys($assignments);
        $leaderids = array_values(array_unique($assignments));
        list($groupinsql, $groupparams) = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED, 'grp');
        list($leaderinsql, $leaderparams) = $DB->get_in_or_equal($leaderids, SQL_PARAMS_NAMED, 'usr');

        $sql = "SELECT gm.id, gm.groupid, gm.userid
                  FROM {groups_members} gm
                  JOIN {groups} g ON g.id = gm.groupid
                 WHERE g.courseid = :courseid
                   AND gm.groupid $groupinsql
                   AND gm.userid $leaderinsql";
        $memberships = $DB->get_records_sql($sql, ['courseid' => $courseid] + $groupparams + $leaderparams);

        foreach ($memberships as $membership) {
            $groupid = (int)$membership->groupid;
            $leaderid = (int)$membership->userid;
            if (isset($assignments[$groupid]) && (int)$assignments[$groupid] === $leaderid) {
                $validassignments[$groupid] = $leaderid;
            }
        }
    }

    $transaction = $DB->start_delegated_transaction();

    $DB->delete_records('commandroom_group_leaders', ['commandroomid' => $commandroomid]);

    $time = time();
    $records = [];
    foreach ($validassignments as $groupid => $leaderid) {
        $record = new stdClass();
        $record->commandroomid = $commandroomid;
        $record->groupid = $groupid;
        $record->leaderid = $leaderid;
        $record->timecreated = $time;
        $record->timemodified = $time;
        $records[] = $record;
    }

    if (!empty($records)) {
        $DB->insert_records('commandroom_group_leaders', $records);
    }

    $transaction->allow_commit();
}

/**
 * Return the packaged presets directory.
 *
 * @return string
 */
function commandroom_get_presets_directory(): string {
    global $CFG;

    return $CFG->dirroot . '/mod/commandroom/presets';
}

/**
 * Load the packaged preset index.
 *
 * @return array
 */
function commandroom_get_preset_index(): array {
    $indexfile = commandroom_get_presets_directory() . '/presets.json';

    if (!is_readable($indexfile)) {
        return [];
    }

    $rawjson = file_get_contents($indexfile);
    if ($rawjson === false || trim($rawjson) === '') {
        return [];
    }

    $decoded = json_decode($rawjson, true);
    if (!is_array($decoded)) {
        return [];
    }

    $presets = $decoded['presets'] ?? $decoded;
    return is_array($presets) ? $presets : [];
}

/**
 * Find a preset definition in presets.json.
 *
 * @param string $presetkey
 * @return array|null
 */
function commandroom_find_preset_definition(string $presetkey): ?array {
    $presetkey = clean_param($presetkey, PARAM_ALPHANUMEXT);

    if ($presetkey === '' || $presetkey === 'custom') {
        return null;
    }

    foreach (commandroom_get_preset_index() as $preset) {
        if (!is_array($preset)) {
            continue;
        }

        $key = isset($preset['key']) ? clean_param((string)$preset['key'], PARAM_ALPHANUMEXT) : '';
        if ($key === $presetkey) {
            return $preset;
        }
    }

    return null;
}

/**
 * Resolve a packaged preset JSON file safely.
 *
 * @param string $presetkey
 * @return string|null
 */
function commandroom_get_preset_file(string $presetkey): ?string {
    $preset = commandroom_find_preset_definition($presetkey);
    if ($preset === null || empty($preset['file'])) {
        return null;
    }

    $filename = clean_param((string)$preset['file'], PARAM_FILE);
    if ($filename === '') {
        return null;
    }

    $presetdir = realpath(commandroom_get_presets_directory());
    $candidate = realpath(commandroom_get_presets_directory() . '/' . $filename);

    if ($presetdir === false || $candidate === false) {
        return null;
    }

    if (strpos($candidate, $presetdir . DIRECTORY_SEPARATOR) !== 0) {
        return null;
    }

    if (!is_readable($candidate)) {
        return null;
    }

    return $candidate;
}

/**
 * Load a packaged preset system JSON definition.
 *
 * @param string $presetkey
 * @return array|null
 */
function commandroom_load_preset_data(string $presetkey): ?array {
    $presetfile = commandroom_get_preset_file($presetkey);
    if ($presetfile === null) {
        return null;
    }

    $rawjson = file_get_contents($presetfile);
    if ($rawjson === false || trim($rawjson) === '') {
        return null;
    }

    $decoded = json_decode($rawjson, true);
    return is_array($decoded) ? $decoded : null;
}

/**
 * Apply preset metadata to the activity record before first insert.
 *
 * @param stdClass $data
 * @param array|null $presetdata
 * @return stdClass
 */
function commandroom_apply_preset_metadata(stdClass $data, ?array $presetdata): stdClass {
    if ($presetdata === null) {
        return $data;
    }

    $metadata = $presetdata['metadata'] ?? [];
    if (!is_array($metadata)) {
        return $data;
    }

    if (isset($metadata['timesteplabel'])) {
        $data->timesteplabel = clean_param((string)$metadata['timesteplabel'], PARAM_TEXT);
    }
    if (isset($metadata['stepduration'])) {
        $data->stepduration = max(1, (int)$metadata['stepduration']);
    }
    if (isset($metadata['stepdurationunit'])) {
        $data->stepdurationunit = clean_param((string)$metadata['stepdurationunit'], PARAM_TEXT);
    }
    if (isset($metadata['totaliterations'])) {
        $data->totaliterations = max(1, (int)$metadata['totaliterations']);
    }
    if (isset($metadata['useshocks'])) {
        $data->useshocks = empty($metadata['useshocks']) ? 0 : 1;
    }

    return $data;
}

/**
 * Safely encode visual config for DB storage.
 *
 * @param array $visualconfig
 * @return string|null
 */
function commandroom_normalise_visualconfig(array $visualconfig): ?string {
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

    foreach ([
        'unitvalue', 'maxicons', 'iconsize', 'layout',
        'scaleby', 'minvalue', 'maxvalue', 'minsize', 'maxsize',
        'x', 'y', 'w', 'h',
    ] as $key) {
        if (array_key_exists($key, $visualconfig)) {
            if (is_numeric($visualconfig[$key])) {
                $normalised[$key] = $visualconfig[$key] + 0;
            } else {
                $normalised[$key] = clean_param((string)$visualconfig[$key], PARAM_TEXT);
            }
        }
    }

    return json_encode($normalised);
}

/**
 * Resolve a node reference from preset JSON to a database node id.
 *
 * @param mixed $value
 * @param array $nodemap
 * @return int|null
 */
function commandroom_resolve_preset_node_ref($value, array $nodemap): ?int {
    if (is_array($value)) {
        $ref = $value['ref'] ?? ($value['nodeid'] ?? null);
        return commandroom_resolve_preset_node_ref($ref, $nodemap);
    }

    if ($value === null || $value === '') {
        return null;
    }

    $ref = (string)$value;
    if (isset($nodemap[$ref])) {
        return (int)$nodemap[$ref];
    }

    if (is_numeric($ref)) {
        $intref = (int)$ref;
        return $intref > 0 ? $intref : null;
    }

    return null;
}

/**
 * Recursively add nodeid fields to calculation operands where a preset uses refs.
 *
 * @param mixed $value
 * @param array $nodemap
 * @return mixed
 */
function commandroom_normalise_calculation_refs($value, array $nodemap) {
    if (!is_array($value)) {
        return $value;
    }

    if (($value['kind'] ?? '') === 'node') {
        $nodeid = commandroom_resolve_preset_node_ref($value, $nodemap);
        if ($nodeid !== null) {
            $value['nodeid'] = $nodeid;
        }
        if (isset($value['ref'])) {
            $value['ref'] = clean_param((string)$value['ref'], PARAM_ALPHANUMEXT);
        }
        return $value;
    }

    foreach ($value as $key => $item) {
        $value[$key] = commandroom_normalise_calculation_refs($item, $nodemap);
    }

    return $value;
}

/**
 * Normalise update config from preset JSON.
 *
 * @param array $updateconfig
 * @param array $nodemap
 * @return string|null
 */
function commandroom_normalise_updateconfig(array $updateconfig, array $nodemap): ?string {
    if (empty($updateconfig['mode'])) {
        return null;
    }

    $normalised = [
        'mode' => clean_param((string)$updateconfig['mode'], PARAM_TEXT),
    ];

    if (isset($updateconfig['base'])) {
        $normalised['base'] = clean_param((string)$updateconfig['base'], PARAM_TEXT);
    }

    if (array_key_exists('rate', $updateconfig) && (string)$updateconfig['rate'] !== '') {
        $rateid = commandroom_resolve_preset_node_ref($updateconfig['rate'], $nodemap);
        if ($rateid !== null) {
            $normalised['rate'] = $rateid;
        }
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
            return null;
        }

        $normalised[$targetkey] = [];
        foreach ($rawitems as $item) {
            $nodeid = commandroom_resolve_preset_node_ref($item, $nodemap);
            if ($nodeid !== null) {
                $normalised[$targetkey][] = $nodeid;
            }
        }
    }

    return json_encode($normalised);
}

/**
 * Import preset system data into an already-created commandroom activity.
 *
 * This is deliberately scoped to first creation. Updating activity settings
 * should not silently replace a teacher's later Builder work.
 *
 * @param int $commandroomid
 * @param array $data
 * @return void
 */
function commandroom_import_preset_system(int $commandroomid, array $data): void {
    global $DB;

    if (empty($data['nodes']) || !is_array($data['nodes'])) {
        return;
    }

    $transaction = $DB->start_delegated_transaction();

    $DB->delete_records('commandroom_shocks', ['commandroomid' => $commandroomid]);
    $DB->delete_records('commandroom_edges', ['commandroomid' => $commandroomid]);
    $DB->delete_records('commandroom_nodes', ['commandroomid' => $commandroomid]);

    $nodemap = [];
    foreach ($data['nodes'] as $node) {
        if (!is_array($node)) {
            continue;
        }

        $noderef = (string)($node['ref'] ?? ($node['id'] ?? ''));
        if ($noderef === '') {
            continue;
        }

        $record = new stdClass();
        $record->commandroomid = $commandroomid;
        $record->name = clean_param((string)($node['name'] ?? $noderef), PARAM_TEXT);
        $record->nodetype = clean_param((string)($node['nodetype'] ?? 'stock'), PARAM_TEXT);
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
            ? commandroom_normalise_visualconfig($node['visual'])
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
        if (!is_array($node)) {
            continue;
        }

        $noderef = (string)($node['ref'] ?? ($node['id'] ?? ''));
        if ($noderef === '' || empty($nodemap[$noderef])) {
            continue;
        }

        $update = new stdClass();
        $update->id = (int)$nodemap[$noderef];
        $changed = false;

        $updateconfig = $node['updateconfig'] ?? ($node['update'] ?? null);
        if (!empty($updateconfig) && is_array($updateconfig)) {
            $update->updateconfig = commandroom_normalise_updateconfig($updateconfig, $nodemap);
            $changed = true;
        }

        $calculationconfig = $node['calculation'] ?? null;
        if (!empty($calculationconfig) && is_array($calculationconfig)) {
            $update->calculationconfig = json_encode(commandroom_normalise_calculation_refs($calculationconfig, $nodemap));
            $changed = true;
        }

        if ($changed) {
            $DB->update_record('commandroom_nodes', $update);
        }
    }

    if (!empty($data['edges']) && is_array($data['edges'])) {
        foreach ($data['edges'] as $edge) {
            if (!is_array($edge)) {
                continue;
            }

            $sourceid = commandroom_resolve_preset_node_ref($edge['source'] ?? '', $nodemap);
            $targetid = commandroom_resolve_preset_node_ref($edge['target'] ?? '', $nodemap);
            if ($sourceid === null || $targetid === null) {
                continue;
            }

            $record = new stdClass();
            $record->commandroomid = $commandroomid;
            $record->sourcenodeid = $sourceid;
            $record->targetnodeid = $targetid;
            $record->relationtype = clean_param((string)($edge['relationtype'] ?? 'linear'), PARAM_TEXT);
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
    }

    if (!empty($data['shocks']) && is_array($data['shocks'])) {
        foreach ($data['shocks'] as $shock) {
            if (!is_array($shock)) {
                continue;
            }

            $nodeid = commandroom_resolve_preset_node_ref($shock['node'] ?? ($shock['nodeid'] ?? ''), $nodemap);
            if ($nodeid === null) {
                continue;
            }

            $record = new stdClass();
            $record->commandroomid = $commandroomid;
            $record->nodeid = $nodeid;
            $record->shocktype = clean_param((string)($shock['shocktype'] ?? 'scheduled'), PARAM_TEXT);
            $record->iterationno = max(1, (int)($shock['iterationno'] ?? 1));
            $record->adjustment = (float)($shock['adjustment'] ?? 0);
            $record->minadjustment = array_key_exists('minadjustment', $shock) && $shock['minadjustment'] !== null ? (float)$shock['minadjustment'] : null;
            $record->maxadjustment = array_key_exists('maxadjustment', $shock) && $shock['maxadjustment'] !== null ? (float)$shock['maxadjustment'] : null;
            $record->applyeveryiteration = !empty($shock['applyeveryiteration']) ? 1 : 0;
            $record->visibletostudents = array_key_exists('visibletostudents', $shock) ? (empty($shock['visibletostudents']) ? 0 : 1) : 0;
            $record->description = isset($shock['description']) ? clean_param((string)$shock['description'], PARAM_TEXT) : null;

            $DB->insert_record('commandroom_shocks', $record);
        }
    }

    $transaction->allow_commit();
}


/**
 * Saves a new instance of the commandroom into the database.
 *
 * @param stdClass $data
 * @param mod_commandroom_mod_form $mform
 * @return int The id of the newly inserted record
 */
function commandroom_add_instance($data, $mform = null) {
    global $DB;

    $data = commandroom_prepare_system_brief_fields($data);

    $presetdata = null;
    if (!empty($data->presetkey) && $data->presetkey !== 'custom') {
        $presetdata = commandroom_load_preset_data((string)$data->presetkey);
        $data = commandroom_apply_preset_metadata($data, $presetdata);
    }

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;

    // Insert the main record.
    $id = $DB->insert_record('commandroom', $data);

    if ($presetdata !== null) {
        commandroom_import_preset_system((int)$id, $presetdata);
    }

    commandroom_save_group_leaders((int)$id, $data, (int)$data->course);

    return $id;
}

/**
 * Updates an existing commandroom instance.
 *
 * @param stdClass $data
 * @param mod_commandroom_mod_form $mform
 * @return bool True on success
 */
function commandroom_update_instance($data, $mform = null) {
    global $DB;

    $data = commandroom_prepare_system_brief_fields($data);

    $data->timemodified = time();
    $data->id = $data->instance;

    $updated = $DB->update_record('commandroom', $data);
    if ($updated) {
        commandroom_save_group_leaders((int)$data->id, $data, (int)$data->course);
    }

    return $updated;
}

/**
 * Removes an instance of the commandroom from the database.
 *
 * @param int $id
 * @return bool True on success
 */
function commandroom_delete_instance($id) {
    global $DB;

    if (!$commandroom = $DB->get_record('commandroom', ['id' => $id])) {
        return false;
    }

    // Delete related records FIRST (avoids orphaned data and supports performance clarity).

    // Load runs once (avoid N+1).
    $runs = $DB->get_records('commandroom_runs', ['commandroomid' => $commandroom->id], '', 'id');

    if ($runs) {
        $runids = array_keys($runs);

        list($insql, $params) = $DB->get_in_or_equal($runids, SQL_PARAMS_NAMED);

        // Bulk deletes — no per-row loops.
        $DB->delete_records_select('commandroom_proposals', "runid $insql", $params);
        $DB->delete_records_select('commandroom_decisions', "runid $insql", $params);
        $DB->delete_records_select('commandroom_results', "runid $insql", $params);
        $DB->delete_records_select('commandroom_runs', "id $insql", $params);
    }

    // Delete authoring structures.
    $DB->delete_records('commandroom_shocks', ['commandroomid' => $commandroom->id]);
    $DB->delete_records('commandroom_edges', ['commandroomid' => $commandroom->id]);
    $DB->delete_records('commandroom_nodes', ['commandroomid' => $commandroom->id]);
    $DB->delete_records('commandroom_exports', ['commandroomid' => $commandroom->id]);
    $DB->delete_records('commandroom_group_leaders', ['commandroomid' => $commandroom->id]);

    // Finally delete the instance.
    $DB->delete_records('commandroom', ['id' => $commandroom->id]);

    return true;
}

/**
 * Returns a small object with summary information about what a user has done.
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param mod_commandroom $mod
 * @param stdClass $commandroom
 * @return stdClass|null
 */
function commandroom_user_outline($course, $user, $mod, $commandroom) {
    global $DB;

    $result = new stdClass();
    $result->time = 0;
    $result->info = '';

    // Count proposals in one query (no N+1).
    $count = $DB->count_records('commandroom_proposals', [
        'userid' => $user->id
    ]);

    $result->info = get_string('proposals', 'mod_commandroom') . ': ' . $count;

    return $result;
}

/**
 * Returns detailed information about what a user has done.
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param mod_commandroom $mod
 * @param stdClass $commandroom
 * @return stdClass|null
 */
function commandroom_user_complete($course, $user, $mod, $commandroom) {
    global $DB, $OUTPUT;

    $proposals = $DB->get_records('commandroom_proposals', ['userid' => $user->id]);

    if (!$proposals) {
        return null;
    }

    $output = '';

    foreach ($proposals as $proposal) {
        $output .= html_writer::div(
            get_string('proposedvalue', 'mod_commandroom') . ': ' . s($proposal->proposedvalue),
            'commandroom-proposal'
        );
    }

    return $output;
}

/**
 * Print recent activity (placeholder for now).
 */
function commandroom_print_recent_activity($course, $viewfullnames, $timestart) {
    return false;
}

/**
 * Update grades in gradebook.
 *
 * @param stdClass $commandroom
 * @param int $userid
 */
function commandroom_update_grades($commandroom, $userid = 0) {
    require_once($GLOBALS['CFG']->libdir . '/gradelib.php');

    $grades = [];

    // Placeholder — will be implemented once scoring logic is complete.

    grade_update(
        'mod/commandroom',
        $commandroom->course,
        'mod',
        'commandroom',
        $commandroom->id,
        0,
        $grades
    );
}