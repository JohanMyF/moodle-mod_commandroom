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
 * System builder page for mod_commandroom.
 *
 * @package    mod_commandroom
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

/**
 * Decode a JSON field safely.
 *
 * @param string|null $json
 * @return array|null
 */
function mod_commandroom_builder_decode_json(?string $json): ?array {
    if ($json === null || trim($json) === '') {
        return null;
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return null;
    }

    return $decoded;
}

/**
 * Return the built-in icon list for the Builder icon selector.
 *
 * Teachers choose from SVG files shipped with the plugin in pix/icons/.
 * This deliberately excludes teacher uploads. The icon key stored in JSON is
 * the safe file basename without the .svg extension.
 *
 * @return array
 */
function mod_commandroom_builder_get_builtin_icons(): array {
    $iconpath = __DIR__ . '/pix/icons';
    $iconurlbase = '/mod/commandroom/pix/icons/';

    $icons = [];
    if (!is_dir($iconpath)) {
        return [[
            'key' => 'default',
            'label' => 'Default',
            'url' => (new moodle_url('/mod/commandroom/pix/icons/default.svg'))->out(false),
        ]];
    }

    $files = glob($iconpath . '/*.svg');
    if ($files === false) {
        $files = [];
    }

    sort($files, SORT_NATURAL | SORT_FLAG_CASE);

    foreach ($files as $file) {
        $basename = basename($file, '.svg');

        // Only expose simple, safe icon names. This prevents odd filenames from
        // becoming paths or HTML labels in the Builder.
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $basename)) {
            continue;
        }

        $label = str_replace(['_', '-'], ' ', $basename);
        $label = core_text::strtotitle($label);

        $icons[$basename] = [
            'key' => $basename,
            'label' => $label,
            'url' => (new moodle_url($iconurlbase . $basename . '.svg'))->out(false),
        ];
    }

    if (!isset($icons['default'])) {
        $icons = ['default' => [
            'key' => 'default',
            'label' => 'Default',
            'url' => (new moodle_url('/mod/commandroom/pix/icons/default.svg'))->out(false),
        ]] + $icons;
    } else {
        $default = $icons['default'];
        unset($icons['default']);
        $icons = ['default' => $default] + $icons;
    }

    return array_values($icons);
}


/**
 * Convert stored node IDs inside update config back to export refs.
 *
 * @param array $updateconfig
 * @param array $noderefs
 * @return array
 */
function mod_commandroom_builder_export_updateconfig(array $updateconfig, array $noderefs): array {
    if (!empty($updateconfig['rate']) && isset($noderefs[(int)$updateconfig['rate']])) {
        $updateconfig['rate'] = $noderefs[(int)$updateconfig['rate']];
    }

    if (!empty($updateconfig['inflows']) && is_array($updateconfig['inflows'])) {
        foreach ($updateconfig['inflows'] as $key => $nodeid) {
            if (isset($noderefs[(int)$nodeid])) {
                $updateconfig['inflows'][$key] = $noderefs[(int)$nodeid];
            }
        }
    }

    if (!empty($updateconfig['outflows']) && is_array($updateconfig['outflows'])) {
        foreach ($updateconfig['outflows'] as $key => $nodeid) {
            if (isset($noderefs[(int)$nodeid])) {
                $updateconfig['outflows'][$key] = $noderefs[(int)$nodeid];
            }
        }
    }

    return $updateconfig;
}

/**
 * Convert stored node IDs inside calculation config back to export refs.
 *
 * @param mixed $value
 * @param array $noderefs
 * @return mixed
 */
function mod_commandroom_builder_export_calculation_refs($value, array $noderefs) {
    if (!is_array($value)) {
        return $value;
    }

    if (($value['kind'] ?? '') === 'node' && !empty($value['nodeid']) && isset($noderefs[(int)$value['nodeid']])) {
        $value['ref'] = $noderefs[(int)$value['nodeid']];
        unset($value['nodeid']);
        return $value;
    }

    foreach ($value as $key => $item) {
        $value[$key] = mod_commandroom_builder_export_calculation_refs($item, $noderefs);
    }

    return $value;
}


/**
 * Parse the teacher's plain-English node inventory into temporary builder nodes.
 *
 * This creates a starter visual model for Builder only. It does not write nodes
 * into the database. The teacher can export the generated JSON and then import
 * it through the normal system import flow.
 *
 * @param string|null $nodeinventory
 * @param int $commandroomid
 * @return array
 */
function mod_commandroom_builder_parse_node_inventory(?string $nodeinventory, int $commandroomid): array {
    if ($nodeinventory === null || trim($nodeinventory) === '') {
        return [];
    }

    $lines = preg_split('/\R/', $nodeinventory);
    if ($lines === false) {
        return [];
    }

    $nodes = [];
    $displayorder = 1;
    $columns = 4;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $name = $line;
        $rawtype = 'stock';

        if (preg_match('/^(.*?)\s*\((stock|flow|computed|shock)\)\s*$/i', $line, $matches)) {
            $name = trim($matches[1]);
            $rawtype = strtolower($matches[2]);
        }

        if ($name === '') {
            continue;
        }

        $nodetype = $rawtype;
        if ($rawtype === 'shock') {
            // Shocks are stored separately in CommandRoom. For the starter visual
            // model, represent a shock candidate as a computed node so it can
            // appear on the canvas and in the JSON draft.
            $nodetype = 'computed';
        }

        $column = ($displayorder - 1) % $columns;
        $row = intdiv($displayorder - 1, $columns);

        $visual = [
            'type' => 'scaling_icon',
            'icon' => 'default',
            'minvalue' => 0,
            'maxvalue' => 100,
            'minsize' => 50,
            'maxsize' => 150,
            'x' => 2 + ($column * 5),
            'y' => 2 + ($row * 4),
            'w' => 3,
            'h' => 3,
        ];

        $node = new stdClass();
        $node->id = 100000 + $displayorder;
        $node->commandroomid = $commandroomid;
        $node->name = $name;
        $node->description = $rawtype === 'shock'
            ? 'Starter node generated from inventory as a shock candidate.'
            : 'Starter node generated from inventory.';
        $node->unitlabel = '';
        $node->interpretation = '';
        $node->nodetype = $nodetype;
        $node->initialvalue = 0;
        $node->minimumvalue = 0;
        $node->maximumvalue = 100;
        $node->studentcontrolled = $rawtype === 'flow' ? 1 : 0;
        $node->visibletostudents = 1;
        $node->svgfileitemid = 0;
        $node->updateconfig = null;
        $node->visualconfig = json_encode($visual);
        $node->calculationconfig = null;
        $node->displayorder = $displayorder;

        $nodes[$node->id] = $node;
        $displayorder++;
    }

    return $nodes;
}


/**
 * Render a simple relationship matrix for the current builder nodes.
 *
 * @param array $nodes
 * @return string
 */
function mod_commandroom_builder_render_relationship_matrix(array $nodes): string {
    if (count($nodes) < 2) {
        return '';
    }

    $table = new html_table();
    $table->attributes['class'] = 'generaltable commandroom-relationship-matrix';
    $table->head = [get_string('relationshipmatrixsource', 'mod_commandroom')];

    foreach ($nodes as $targetnode) {
        $table->head[] = s($targetnode->name);
    }

    foreach ($nodes as $sourcenode) {
        $row = [s($sourcenode->name)];

        foreach ($nodes as $targetnode) {
            if ((int)$sourcenode->id === (int)$targetnode->id) {
                $row[] = html_writer::span('—', 'commandroom-relationship-self');
                continue;
            }

            $label = get_string(
                'relationshipmatrixcheckbox',
                'mod_commandroom',
                (object)[
                    'source' => $sourcenode->name,
                    'target' => $targetnode->name,
                ]
            );

            $checkbox = html_writer::empty_tag('input', [
                'type' => 'checkbox',
                'class' => 'commandroom-relationship-checkbox',
                'data-source-ref' => '',
                'data-target-ref' => '',
                'data-source-nodeid' => (int)$sourcenode->id,
                'data-target-nodeid' => (int)$targetnode->id,
                'data-source-name' => s($sourcenode->name),
                'data-target-name' => s($targetnode->name),
                'aria-label' => $label,
            ]);

            $editbutton = html_writer::tag('button', get_string('editrelationship', 'mod_commandroom'), [
                'type' => 'button',
                'class' => 'btn btn-sm btn-outline-secondary commandroom-relationship-edit',
                'data-source-nodeid' => (int)$sourcenode->id,
                'data-target-nodeid' => (int)$targetnode->id,
                'hidden' => 'hidden',
            ]);

            $row[] = html_writer::tag(
                'div',
                $checkbox . ' ' . $editbutton,
                ['class' => 'commandroom-relationship-cell']
            );
        }

        $table->data[] = $row;
    }

    return html_writer::div(
        html_writer::tag('h3', get_string('relationshipmatrix', 'mod_commandroom')) .
        html_writer::tag('p', get_string('relationshipmatrixhelp', 'mod_commandroom')) .
        html_writer::table($table),
        'generalbox commandroom-relationship-matrix-panel'
    );
}


/**
 * Build a reusable JSON system definition from the current DB state.
 *
 * @param stdClass $commandroom
 * @param array $nodes
 * @param array $edges
 * @param array $shocks
 * @return array
 */
function mod_commandroom_builder_export_data(stdClass $commandroom, array $nodes, array $edges, array $shocks): array {
    $noderefs = [];
    $counter = 1;

    foreach ($nodes as $node) {
        $noderefs[(int)$node->id] = 'n' . $counter;
        $counter++;
    }

    $exportnodes = [];
    foreach ($nodes as $node) {
        $nodeid = (int)$node->id;

        $exportnode = [
            'ref' => $noderefs[$nodeid],
            'name' => $node->name,
            'description' => $node->description ?? '',
            'unitlabel' => $node->unitlabel ?? '',
            'interpretation' => $node->interpretation ?? '',
            'nodetype' => $node->nodetype,
            'initialvalue' => (float)$node->initialvalue,
            'minvalue' => $node->minimumvalue !== null ? (float)$node->minimumvalue : null,
            'maxvalue' => $node->maximumvalue !== null ? (float)$node->maximumvalue : null,
            'studentcontrolled' => (bool)$node->studentcontrolled,
            'visibletostudents' => (bool)$node->visibletostudents,
            'displayorder' => (int)$node->displayorder,
        ];

        $visualconfig = mod_commandroom_builder_decode_json($node->visualconfig ?? null);
        if ($visualconfig !== null) {
            $exportnode['visual'] = $visualconfig;
        }

        $updateconfig = mod_commandroom_builder_decode_json($node->updateconfig ?? null);
        if ($updateconfig !== null) {
            $exportnode['updateconfig'] = mod_commandroom_builder_export_updateconfig($updateconfig, $noderefs);
        }

        $calculationconfig = mod_commandroom_builder_decode_json($node->calculationconfig ?? null);
        if ($calculationconfig !== null) {
            $exportnode['calculation'] = mod_commandroom_builder_export_calculation_refs($calculationconfig, $noderefs);
        }

        $exportnodes[] = $exportnode;
    }

    $exportedges = [];
    foreach ($edges as $edge) {
        $sourceid = (int)$edge->sourcenodeid;
        $targetid = (int)$edge->targetnodeid;

        if (!isset($noderefs[$sourceid]) || !isset($noderefs[$targetid])) {
            continue;
        }

        $exportedge = [
            'source' => $noderefs[$sourceid],
            'target' => $noderefs[$targetid],
            'relationtype' => $edge->relationtype,
            'strength' => (float)$edge->strength,
            'delayiterations' => (int)$edge->delayiterations,
            'polarity' => $edge->polarity ?? 'neutral',
            'label' => $edge->label ?? '',
            'loopgroup' => $edge->loopgroup ?? '',
            'curvature' => (int)($edge->curvature ?? 0),
            'visibletostudents' => (bool)$edge->visibletostudents,
        ];

        if (!empty($edge->functionconfig)) {
            $decoded = json_decode($edge->functionconfig, true);
            $exportedge['functionconfig'] = $decoded !== null ? $decoded : $edge->functionconfig;
        }

        $exportedges[] = $exportedge;
    }

    $exportshocks = [];
    foreach ($shocks as $shock) {
        $nodeid = (int)$shock->nodeid;
        if (!isset($noderefs[$nodeid])) {
            continue;
        }

        $exportshocks[] = [
            'node' => $noderefs[$nodeid],
            'shocktype' => $shock->shocktype ?? 'scheduled',
            'iterationno' => (int)$shock->iterationno,
            'adjustment' => (float)$shock->adjustment,
            'minadjustment' => $shock->minadjustment !== null ? (float)$shock->minadjustment : null,
            'maxadjustment' => $shock->maxadjustment !== null ? (float)$shock->maxadjustment : null,
            'applyeveryiteration' => (bool)($shock->applyeveryiteration ?? 0),
            'visibletostudents' => (bool)$shock->visibletostudents,
            'description' => $shock->description ?? '',
        ];
    }

    return [
        'metadata' => [
            'plugin' => 'mod_commandroom',
            'pluginname' => 'Situation Room',
            'version' => 'builder-draft',
            'exportedat' => time(),
            'timesteplabel' => $commandroom->timesteplabel,
            'stepduration' => (int)$commandroom->stepduration,
            'stepdurationunit' => $commandroom->stepdurationunit,
            'totaliterations' => (int)$commandroom->totaliterations,
            'useshocks' => (bool)$commandroom->useshocks,
        ],
        'nodes' => $exportnodes,
        'edges' => $exportedges,
        'shocks' => $exportshocks,
    ];
}

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('commandroom', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$commandroom = $DB->get_record('commandroom', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/commandroom:manageruns', $context);

$PAGE->set_url('/mod/commandroom/builder.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('systembuilder', 'mod_commandroom'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_activity_record($commandroom);
$PAGE->set_cm($cm);

$builtinicons = mod_commandroom_builder_get_builtin_icons();
$PAGE->requires->js_call_amd('mod_commandroom/builder', 'init', [$cm->id]);

$renderer = $PAGE->get_renderer('mod_commandroom');

$nodes = $DB->get_records('commandroom_nodes', ['commandroomid' => $commandroom->id], 'displayorder ASC, id ASC');
$edges = $DB->get_records('commandroom_edges', ['commandroomid' => $commandroom->id], 'id ASC');
$shocks = $DB->get_records('commandroom_shocks', ['commandroomid' => $commandroom->id], 'iterationno ASC, id ASC');

$usingstarterinventory = false;
if (empty($nodes) && !empty($commandroom->nodeinventory)) {
    $nodes = mod_commandroom_builder_parse_node_inventory($commandroom->nodeinventory, (int)$commandroom->id);
    $edges = [];
    $shocks = [];
    $usingstarterinventory = !empty($nodes);
}

$exportdata = mod_commandroom_builder_export_data($commandroom, $nodes, $edges, $shocks);
$json = json_encode($exportdata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    throw new moodle_exception('error:exportfailed', 'mod_commandroom');
}

echo $OUTPUT->header();

$iconsjson = json_encode(
    $builtinicons,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
if ($iconsjson === false) {
    $iconsjson = '[]';
}
echo html_writer::tag('script', $iconsjson, [
    'type' => 'application/json',
    'id' => 'commandroom-builder-icons-json',
]);

echo $OUTPUT->heading(get_string('systembuilderfor', 'mod_commandroom', format_string($commandroom->name)), 2);

$settingsurl = new moodle_url('/course/modedit.php', ['update' => $cm->id, 'return' => 1]);
$viewurl = new moodle_url('/mod/commandroom/view.php', ['id' => $cm->id]);
$saveurl = new moodle_url('/mod/commandroom/save_builder.php', ['id' => $cm->id]);

$intro = html_writer::tag('p', get_string('systembuilderintro', 'mod_commandroom'));
if (!empty($usingstarterinventory)) {
    $intro .= html_writer::tag(
        'p',
        get_string('builderstartermodelnotice', 'mod_commandroom'),
        ['class' => 'commandroom-builder-starter-notice']
    );
}

$actions = html_writer::div(
    html_writer::tag(
        'button',
        get_string('saveandreturntosettings', 'mod_commandroom'),
        [
            'type' => 'button',
            'class' => 'btn btn-primary commandroom-builder-save-return',
            'data-save-url' => $saveurl->out(false),
            'data-sesskey' => sesskey(),
            'data-after-save' => 'settings',
            'data-settings-url' => $settingsurl->out(false),
        ]
    ) . ' ' .
    html_writer::tag(
        'button',
        get_string('publishanduse', 'mod_commandroom'),
        [
            'type' => 'button',
            'class' => 'btn btn-success commandroom-builder-save-system',
            'data-save-url' => $saveurl->out(false),
            'data-sesskey' => sesskey(),
            'data-after-save' => 'settings',
            'data-settings-url' => $viewurl->out(false),
        ]
    ),
    'commandroom-builder-actions commandroom-builder-actions-bar'
);

echo html_writer::div(
    $intro .
    $actions .
    html_writer::div('', 'commandroom-builder-save-status', ['aria-live' => 'polite']),
    'generalbox commandroom-builder-intro'
);

if (empty($nodes)) {
    echo $OUTPUT->notification(get_string('nonodesdefined', 'mod_commandroom'), \core\output\notification::NOTIFY_INFO);
} else {
    echo html_writer::div(
        html_writer::tag('h3', get_string('systemlayout', 'mod_commandroom')) .
        html_writer::tag('p', get_string('builderlayouthelp', 'mod_commandroom')) .
        $renderer->render_visual_system_cards($nodes, [], $cm->id, null, [], false, false, true, $edges),
        'commandroom-builder-canvas'
    );

    echo mod_commandroom_builder_render_relationship_matrix($nodes);
}

echo html_writer::div(
    html_writer::tag('h3', get_string('advancedjsoneditor', 'mod_commandroom')) .
    html_writer::tag('p', get_string('advancedjsoneditorhelp', 'mod_commandroom')) .
    html_writer::tag('textarea', s($json), [
        'class' => 'form-control commandroom-builder-json',
        'rows' => 18,
        'spellcheck' => 'false',
        'data-cmid' => $cm->id,
    ]),
    'generalbox commandroom-builder-json-panel'
);

echo $OUTPUT->footer();
