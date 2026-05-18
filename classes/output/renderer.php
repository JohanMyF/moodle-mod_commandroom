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

namespace mod_commandroom\output;

defined('MOODLE_INTERNAL') || die();

use html_table;
use html_writer;
use plugin_renderer_base;

/**
 * Renderer for mod_commandroom.
 *
 * @package    mod_commandroom
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    /**
     * Render the activity summary table.
     *
     * @param \stdClass $commandroom
     * @return string
     */
    public function render_activity_summary(\stdClass $commandroom): string {
        $table = new html_table();
        $table->attributes['class'] = 'generaltable commandroom-summary';

        $useshocks = $commandroom->useshocks ? get_string('yes') : get_string('no');

        $table->data = [
            [
                get_string('timesteplabel', 'mod_commandroom'),
                s($commandroom->timesteplabel),
            ],
            [
                get_string('stepduration', 'mod_commandroom'),
                format_float($commandroom->stepduration, 0) . ' ' . s($commandroom->stepdurationunit),
            ],
            [
                get_string('totaliterations', 'mod_commandroom'),
                format_float($commandroom->totaliterations, 0),
            ],
            [
                get_string('useshocks', 'mod_commandroom'),
                $useshocks,
            ],
        ];

        return html_writer::table($table);
    }

    /**
     * Render a system snapshot from nodes, edges, and optional current results.
     *
     * @param array $nodes
     * @param array $edges
     * @param array $resultsbynodeid Array keyed by node id.
     * @return string
     */
    public function render_system_snapshot(array $nodes, array $edges, array $resultsbynodeid = []): string {
        $output = html_writer::tag('h3', get_string('simulation', 'mod_commandroom'));

        if (empty($nodes)) {
            $output .= html_writer::div(
                get_string('nonodesdefined', 'mod_commandroom'),
                'alert alert-info'
            );
            return html_writer::div($output, 'generalbox commandroom-system-snapshot');
        }

        $headcells = [];
        $headcells[] = html_writer::tag('th', get_string('nodename', 'mod_commandroom'));
        $headcells[] = html_writer::tag('th', get_string('nodetype', 'mod_commandroom'));
        $headcells[] = html_writer::tag('th', get_string('initialvalue', 'mod_commandroom'));
        $headcells[] = html_writer::tag('th', get_string('currentvalue', 'mod_commandroom'));
        $headcells[] = html_writer::tag('th', get_string('valueorigin', 'mod_commandroom'));
        $headcells[] = html_writer::tag('th', get_string('studentcontrolled', 'mod_commandroom'));
        $headcells[] = html_writer::tag('th', get_string('visibletostudents', 'mod_commandroom'));

        $thead = html_writer::tag('thead', html_writer::tag('tr', implode('', $headcells)));

        $bodyrows = '';

        foreach ($nodes as $node) {
            $currentvalue = '-';
            $valueorigin = '-';

            if (!empty($resultsbynodeid[(int)$node->id])) {
                $resultrow = $resultsbynodeid[(int)$node->id];
                $currentvalue = format_float((float)$resultrow->nodevalue, 4);
                $valueorigin = s($resultrow->valueorigin);
            }

            $cells = [];
            $cells[] = html_writer::tag('td', s($node->name));
            $cells[] = html_writer::tag('td', s($node->nodetype));
            $cells[] = html_writer::tag('td', format_float((float)$node->initialvalue, 4));
            $cells[] = html_writer::tag('td', $currentvalue, ['class' => 'commandroom-current-value']);
            $cells[] = html_writer::tag('td', $valueorigin, ['class' => 'commandroom-value-origin']);
            $cells[] = html_writer::tag('td', !empty($node->studentcontrolled) ? get_string('yes') : get_string('no'));
            $cells[] = html_writer::tag('td', !empty($node->visibletostudents) ? get_string('yes') : get_string('no'));

            $bodyrows .= html_writer::tag(
                'tr',
                implode('', $cells),
                ['data-nodeid' => (int)$node->id]
            );
        }

        $tbody = html_writer::tag('tbody', $bodyrows);

        $table = html_writer::tag(
            'table',
            $thead . $tbody,
            ['class' => 'generaltable commandroom-nodes-table']
        );

        $output .= $table;

        $edgecount = count($edges);
        $output .= html_writer::div(
            get_string('relationshipcount', 'mod_commandroom', $edgecount),
            'commandroom-relationship-count'
        );

        return html_writer::div($output, 'generalbox commandroom-system-snapshot');
    }

    /**
     * Render proposal input cards for student-controlled nodes.
     *
     * @param int $cmid
     * @param array $nodes
     * @return string
     */
    public function render_proposal_panel(int $cmid, array $nodes): string {
        $studentnodes = array_filter($nodes, function($node) {
            return !empty($node->studentcontrolled);
        });

        $output = html_writer::tag('h3', get_string('proposalpanel', 'mod_commandroom'));

        if (empty($studentnodes)) {
            $output .= html_writer::div(
                get_string('nostudentcontrollednodes', 'mod_commandroom'),
                'alert alert-info'
            );

            return html_writer::div($output, 'generalbox commandroom-proposal-panel');
        }

        foreach ($studentnodes as $node) {
            $minimumvalue = isset($node->minimumvalue) ? (float)$node->minimumvalue : 0;
            $maximumvalue = isset($node->maximumvalue) ? (float)$node->maximumvalue : 0;
            $initialvalue = isset($node->initialvalue) ? (float)$node->initialvalue : 0;

            $title = html_writer::tag('h4', s($node->name), ['class' => 'commandroom-proposal-title']);

            $meta = html_writer::div(
                get_string('proposalrange', 'mod_commandroom',
                    (object)[
                        'min' => format_float($minimumvalue, 4),
                        'max' => format_float($maximumvalue, 4),
                    ]
                ),
                'commandroom-proposal-meta'
            );

            $valueinput = html_writer::empty_tag('input', [
                'type' => 'number',
                'step' => 'any',
                'class' => 'form-control commandroom-proposed-value',
                'value' => $initialvalue,
                'data-nodeid' => (int)$node->id,
                'data-cmid' => $cmid,
                'min' => $minimumvalue,
                'max' => $maximumvalue,
            ]);

            $valuelabel = html_writer::tag(
                'label',
                get_string('proposedvalue', 'mod_commandroom'),
                ['class' => 'commandroom-proposal-label']
            );

            $textarea = html_writer::tag('textarea', '', [
                'class' => 'form-control commandroom-proposal-rationale',
                'rows' => 4,
                'data-nodeid' => (int)$node->id,
                'data-cmid' => $cmid,
                'placeholder' => get_string('rationaleplaceholder', 'mod_commandroom'),
            ]);

            $rationalelabel = html_writer::tag(
                'label',
                get_string('rationale', 'mod_commandroom'),
                ['class' => 'commandroom-proposal-label']
            );

            $button = html_writer::tag('button',
                get_string('saveproposal', 'mod_commandroom'),
                [
                    'type' => 'button',
                    'class' => 'btn btn-primary commandroom-save-proposal',
                    'data-nodeid' => (int)$node->id,
                    'data-cmid' => $cmid,
                ]
            );

            $status = html_writer::div(
                '',
                'commandroom-proposal-status',
                [
                    'data-nodeid' => (int)$node->id,
                ]
            );

            $proposalstitle = html_writer::tag(
                'h5',
                get_string('peerproposals', 'mod_commandroom'),
                ['class' => 'commandroom-proposal-list-title']
            );

            $proposalslist = html_writer::div(
                get_string('noproposalssubmitted', 'mod_commandroom'),
                'commandroom-proposal-list',
                [
                    'data-nodeid' => (int)$node->id,
                ]
            );

            $body = html_writer::div($meta, 'commandroom-proposal-header');
            $body .= html_writer::div($valuelabel . $valueinput, 'commandroom-proposal-field');
            $body .= html_writer::div($rationalelabel . $textarea, 'commandroom-proposal-field');
            $body .= html_writer::div($button, 'commandroom-proposal-actions');
            $body .= $status;
            $body .= html_writer::div($proposalstitle . $proposalslist, 'commandroom-peer-proposals');

            $output .= html_writer::div(
                $title . $body,
                'generalbox commandroom-proposal-card'
            );
        }

        return html_writer::div($output, 'commandroom-proposal-panel');
    }

    /**
     * Render governance cards for student-controlled nodes.
     *
     * @param int $cmid
     * @param int $runid
     * @param array $nodes
     * @param array $decisions Array keyed by node id.
     * @param bool $readonly True to hide decision buttons.
     * @return string
     */
    public function render_governance_panel(
        int $cmid,
        int $runid,
        array $nodes,
        array $decisions = [],
        bool $readonly = false
    ): string {
        $studentnodes = array_filter($nodes, function($node) {
            return !empty($node->studentcontrolled);
        });

        $output = html_writer::tag('h3', get_string('governancepanel', 'mod_commandroom'));

        if (empty($studentnodes)) {
            $output .= html_writer::div(
                get_string('nostudentcontrollednodes', 'mod_commandroom'),
                'alert alert-info'
            );

            return html_writer::div($output, 'generalbox commandroom-governance-panel');
        }

        foreach ($studentnodes as $node) {
            $title = html_writer::tag('h4', s($node->name), ['class' => 'commandroom-decision-title']);

            $proposalstitle = html_writer::tag(
                'h5',
                get_string('peerproposals', 'mod_commandroom'),
                ['class' => 'commandroom-proposal-list-title']
            );

            $proposalslist = html_writer::div(
                get_string('noproposalssubmitted', 'mod_commandroom'),
                'commandroom-proposal-list',
                [
                    'data-nodeid' => (int)$node->id,
                ]
            );

            $currentdecisionhtml = html_writer::div(
                get_string('noleaderdecisionsaved', 'mod_commandroom'),
                'commandroom-current-decision',
                [
                    'data-nodeid' => (int)$node->id,
                ]
            );

            if (!empty($decisions[(int)$node->id])) {
                $decision = $decisions[(int)$node->id];
                $decisionlabel = s($decision->decisiontype);
                $decisionvalue = format_float((float)$decision->selectedvalue, 4);

                $currentdecisionhtml = html_writer::div(
                    html_writer::tag('strong', get_string('currentdecisionlabel', 'mod_commandroom')) .
                    s($decisionlabel) .
                    ' (' . $decisionvalue . ')',
                    'commandroom-current-decision',
                    [
                        'data-nodeid' => (int)$node->id,
                    ]
                );
            }

            $body = html_writer::div($proposalstitle . $proposalslist, 'commandroom-peer-proposals');
            $body .= $currentdecisionhtml;

            if (!$readonly) {
                $minbutton = html_writer::tag(
                    'button',
                    get_string('minimum', 'mod_commandroom'),
                    [
                        'type' => 'button',
                        'class' => 'btn btn-outline-secondary commandroom-save-decision',
                        'data-cmid' => $cmid,
                        'data-runid' => $runid,
                        'data-nodeid' => (int)$node->id,
                        'data-decisiontype' => 'min',
                    ]
                );

                $maxbutton = html_writer::tag(
                    'button',
                    get_string('maximum', 'mod_commandroom'),
                    [
                        'type' => 'button',
                        'class' => 'btn btn-outline-secondary commandroom-save-decision',
                        'data-cmid' => $cmid,
                        'data-runid' => $runid,
                        'data-nodeid' => (int)$node->id,
                        'data-decisiontype' => 'max',
                    ]
                );

                $meanbutton = html_writer::tag(
                    'button',
                    get_string('mean', 'mod_commandroom'),
                    [
                        'type' => 'button',
                        'class' => 'btn btn-primary commandroom-save-decision',
                        'data-cmid' => $cmid,
                        'data-runid' => $runid,
                        'data-nodeid' => (int)$node->id,
                        'data-decisiontype' => 'mean',
                    ]
                );

                $decisionactions = html_writer::div(
                    $minbutton . ' ' . $maxbutton . ' ' . $meanbutton,
                    'commandroom-decision-actions'
                );

                $status = html_writer::div(
                    '',
                    'commandroom-decision-status',
                    [
                        'data-nodeid' => (int)$node->id,
                    ]
                );

                $body .= $decisionactions;
                $body .= $status;
            }

            $output .= html_writer::div(
                $title . $body,
                'generalbox commandroom-decision-card'
            );
        }

        return html_writer::div($output, 'commandroom-governance-panel');
    }

    /**
     * Render simulation history table.
     *
     * @param array $historyrows
     * @param array $nodes
     * @return string
     */
    public function render_warroom_placeholder(array $historyrows = [], array $nodes = []): string {
        $title = html_writer::tag('h3', get_string('warroom', 'mod_commandroom'));

        if (empty($historyrows) || empty($nodes)) {
            $message = html_writer::div(
                get_string('warroomcomingsoon', 'mod_commandroom'),
                'commandroom-warroom-message'
            );

            return html_writer::div(
                $title . $message,
                'generalbox commandroom-warroom-placeholder'
            );
        }

        $table = new html_table();
        $table->attributes['class'] = 'generaltable commandroom-history-table';

        $headers = [];
        $headers[] = get_string('iterationno', 'mod_commandroom');

        foreach ($nodes as $node) {
            $headers[] = s($node->name);
        }

        $table->head = $headers;
        $table->data = [];

        ksort($historyrows);

        foreach ($historyrows as $iteration => $rowdata) {
            $row = [];
            $row[] = (int)$iteration;

            foreach ($nodes as $node) {
                $nodeid = (int)$node->id;

                if (isset($rowdata[$nodeid])) {
                    $row[] = format_float((float)$rowdata[$nodeid], 4);
                } else {
                    $row[] = '-';
                }
            }

            $table->data[] = $row;
        }

        $output = $title;
        $output .= html_writer::table($table);

        return html_writer::div(
            $output,
            'generalbox commandroom-warroom-history'
        );
    }
    /**
     * Decode node visual configuration safely.
     *
     * @param \stdClass $node
     * @return array|null
     */
    private function get_node_visual_config(\stdClass $node): ?array {
        if (!property_exists($node, 'visualconfig')) {
            return null;
        }

        if ($node->visualconfig === null || trim((string)$node->visualconfig) === '') {
            return null;
        }

        $decoded = json_decode((string)$node->visualconfig, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Return a safe built-in SVG icon URL from pix/icons/.
     *
     * The JSON should provide an icon name without extension, for example:
     * "infection_fever" maps to pix/icons/infection_fever.svg.
     *
     * @param string $iconname
     * @return string
     */
    private function get_builtin_icon_url(string $iconname): string {
        $iconname = trim($iconname);

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $iconname)) {
            $iconname = 'default';
        }

        return (new \moodle_url('/mod/commandroom/pix/icons/' . $iconname . '.svg'))->out(false);
    }

    /**
     * Render proposal controls inside a visual node card.
     *
     * @param int $cmid
     * @param \stdClass $node
     * @return string
     */
    private function render_visual_proposal_controls(int $cmid, \stdClass $node): string {
        $minimumvalue = isset($node->minimumvalue) ? (float)$node->minimumvalue : 0;
        $maximumvalue = isset($node->maximumvalue) ? (float)$node->maximumvalue : 0;
        $initialvalue = isset($node->initialvalue) ? (float)$node->initialvalue : 0;
        $nodeid = (int)$node->id;

        $meta = html_writer::div(
            get_string('proposalrange', 'mod_commandroom',
                (object)[
                    'min' => format_float($minimumvalue, 4),
                    'max' => format_float($maximumvalue, 4),
                ]
            ),
            'commandroom-proposal-meta'
        );

        $valueinput = html_writer::empty_tag('input', [
            'type' => 'number',
            'step' => 'any',
            'class' => 'form-control commandroom-proposed-value',
            'value' => $initialvalue,
            'data-nodeid' => $nodeid,
            'data-cmid' => $cmid,
            'min' => $minimumvalue,
            'max' => $maximumvalue,
        ]);

        $valuelabel = html_writer::tag(
            'label',
            get_string('proposedvalue', 'mod_commandroom'),
            ['class' => 'commandroom-proposal-label']
        );

        $textarea = html_writer::tag('textarea', '', [
            'class' => 'form-control commandroom-proposal-rationale',
            'rows' => 3,
            'data-nodeid' => $nodeid,
            'data-cmid' => $cmid,
            'placeholder' => get_string('rationaleplaceholder', 'mod_commandroom'),
        ]);

        $rationalelabel = html_writer::tag(
            'label',
            get_string('rationale', 'mod_commandroom'),
            ['class' => 'commandroom-proposal-label']
        );

        $button = html_writer::tag('button',
            get_string('saveproposal', 'mod_commandroom'),
            [
                'type' => 'button',
                'class' => 'btn btn-primary btn-sm commandroom-save-proposal',
                'data-nodeid' => $nodeid,
                'data-cmid' => $cmid,
            ]
        );

        $status = html_writer::div(
            '',
            'commandroom-proposal-status',
            [
                'data-nodeid' => $nodeid,
            ]
        );

        $closebutton = html_writer::tag(
            'button',
            '&times;',
            [
                'type' => 'button',
                'class' => 'btn btn-link commandroom-visual-panel-close',
                'data-nodeid' => $nodeid,
                'data-paneltype' => 'proposal',
                'aria-label' => 'Close panel',
            ]
        );

        $panelheader = html_writer::div(
            html_writer::tag('h5', get_string('proposalpanel', 'mod_commandroom'), ['class' => 'commandroom-visual-control-title']) .
            $closebutton,
            'commandroom-visual-overlay-header'
        );

        return html_writer::div(
            $panelheader .
            $meta .
            html_writer::div($valuelabel . $valueinput, 'commandroom-proposal-field') .
            html_writer::div($rationalelabel . $textarea, 'commandroom-proposal-field') .
            html_writer::div($button, 'commandroom-proposal-actions') .
            $status,
            'commandroom-visual-controls commandroom-visual-overlay-panel commandroom-visual-proposal-controls',
            [
                'data-nodeid' => $nodeid,
                'data-paneltype' => 'proposal',
                'hidden' => 'hidden',
            ]
        );
    }

    /**
     * Render leader governance controls inside a visual node card.
     *
     * @param int $cmid
     * @param int $runid
     * @param \stdClass $node
     * @param array $decisions
     * @param bool $readonly
     * @return string
     */
    private function render_visual_governance_controls(
        int $cmid,
        int $runid,
        \stdClass $node,
        array $decisions = [],
        bool $readonly = false
    ): string {
        $nodeid = (int)$node->id;

        $proposalstitle = html_writer::tag(
            'h5',
            get_string('peerproposals', 'mod_commandroom'),
            ['class' => 'commandroom-proposal-list-title']
        );

        $proposalslist = html_writer::div(
            get_string('noproposalssubmitted', 'mod_commandroom'),
            'commandroom-proposal-list',
            [
                'data-nodeid' => $nodeid,
            ]
        );

        $currentdecisionhtml = html_writer::div(
            get_string('noleaderdecisionsaved', 'mod_commandroom'),
            'commandroom-current-decision',
            [
                'data-nodeid' => $nodeid,
            ]
        );

        if (!empty($decisions[$nodeid])) {
            $decision = $decisions[$nodeid];
            $decisionlabel = s($decision->decisiontype);
            $decisionvalue = format_float((float)$decision->selectedvalue, 4);

            $currentdecisionhtml = html_writer::div(
                html_writer::tag('strong', get_string('currentdecisionlabel', 'mod_commandroom')) .
                s($decisionlabel) .
                ' (' . $decisionvalue . ')',
                'commandroom-current-decision',
                [
                    'data-nodeid' => $nodeid,
                ]
            );
        }

        $body = html_writer::div($proposalstitle . $proposalslist, 'commandroom-peer-proposals');
        $body .= $currentdecisionhtml;

        if (!$readonly) {
            $minbutton = html_writer::tag(
                'button',
                get_string('minimum', 'mod_commandroom'),
                [
                    'type' => 'button',
                    'class' => 'btn btn-outline-secondary btn-sm commandroom-save-decision',
                    'data-cmid' => $cmid,
                    'data-runid' => $runid,
                    'data-nodeid' => $nodeid,
                    'data-decisiontype' => 'min',
                ]
            );

            $maxbutton = html_writer::tag(
                'button',
                get_string('maximum', 'mod_commandroom'),
                [
                    'type' => 'button',
                    'class' => 'btn btn-outline-secondary btn-sm commandroom-save-decision',
                    'data-cmid' => $cmid,
                    'data-runid' => $runid,
                    'data-nodeid' => $nodeid,
                    'data-decisiontype' => 'max',
                ]
            );

            $meanbutton = html_writer::tag(
                'button',
                get_string('mean', 'mod_commandroom'),
                [
                    'type' => 'button',
                    'class' => 'btn btn-primary btn-sm commandroom-save-decision',
                    'data-cmid' => $cmid,
                    'data-runid' => $runid,
                    'data-nodeid' => $nodeid,
                    'data-decisiontype' => 'mean',
                ]
            );

            $body .= html_writer::div(
                $minbutton . ' ' . $maxbutton . ' ' . $meanbutton,
                'commandroom-decision-actions'
            );

            $body .= html_writer::div(
                '',
                'commandroom-decision-status',
                [
                    'data-nodeid' => $nodeid,
                ]
            );
        }

        $closebutton = html_writer::tag(
            'button',
            '&times;',
            [
                'type' => 'button',
                'class' => 'btn btn-link commandroom-visual-panel-close',
                'data-nodeid' => $nodeid,
                'data-paneltype' => 'governance',
                'aria-label' => 'Close panel',
            ]
        );

        $panelheader = html_writer::div(
            html_writer::tag('h5', get_string('governancepanel', 'mod_commandroom'), ['class' => 'commandroom-visual-control-title']) .
            $closebutton,
            'commandroom-visual-overlay-header'
        );

        return html_writer::div(
            $panelheader . $body,
            'commandroom-visual-controls commandroom-visual-overlay-panel commandroom-visual-governance-controls',
            [
                'data-nodeid' => $nodeid,
                'data-paneltype' => 'governance',
                'hidden' => 'hidden',
            ]
        );
    }
    /**
     * Render human meaning attached to a node.
     *
     * These fields explain what the numeric value means in the model.
     *
     * @param \stdClass $node
     * @return string
     */
    private function render_node_meaning(\stdClass $node): string {
        $items = '';

        if (!empty($node->description)) {
            $items .= html_writer::div(
                format_text($node->description, FORMAT_PLAIN),
                'commandroom-node-meaning-description'
            );
        }

        if (!empty($node->unitlabel)) {
            $items .= html_writer::div(
                s($node->unitlabel),
                'commandroom-node-meaning-unit'
            );
        }

        if (!empty($node->interpretation)) {
            $items .= html_writer::div(
                format_text($node->interpretation, FORMAT_PLAIN),
                'commandroom-node-meaning-interpretation'
            );
        }

        if ($items === '') {
            return '';
        }

        return html_writer::div(
            $items,
            'commandroom-node-meaning-panel',
            [
                'data-nodeid' => (int)$node->id,
                'hidden' => 'hidden',
            ]
        );
    }
    /**
     * Render visual system cards for nodes that define a visualconfig.
     *
     * Supported JSON examples:
     *
     * "visual": {
     *   "type": "repeated_icon",
     *   "icon": "infection_fever",
     *   "unitvalue": 10,
     *   "maxicons": 80,
     *   "iconsize": 36
     * }
     *
     * "visual": {
     *   "type": "scaling_icon",
     *   "icon": "energy",
     *   "minvalue": 0,
     *   "maxvalue": 10000,
     *   "minsize": 60,
     *   "maxsize": 220
     * }
     *
     * @param array $nodes
     * @param array $resultsbynodeid
     * @return string
     */
    public function render_visual_system_cards(
        array $nodes,
        array $resultsbynodeid = [],
        int $cmid = 0,
        ?int $runid = null,
        array $decisions = [],
        bool $canpropose = false,
        bool $showgovernance = false,
        bool $governancereadonly = true,
        array $edges = []
    ): string {
        $cards = '';
        $hasgridlayout = false;

        foreach ($nodes as $node) {
            if (empty($node->visibletostudents)) {
                continue;
            }

            $visual = $this->get_node_visual_config($node);
            if ($visual === null) {
                continue;
            }

            $type = isset($visual['type']) ? (string)$visual['type'] : 'repeated_icon';
            if (!in_array($type, ['repeated_icon', 'scaling_icon'], true)) {
                $type = 'repeated_icon';
            }

            $iconname = isset($visual['icon']) ? (string)$visual['icon'] : 'default';
            $iconurl = $this->get_builtin_icon_url($iconname);

            $nodeid = (int)$node->id;
            $currentvalue = isset($node->initialvalue) ? (float)$node->initialvalue : 0.0;

            if (!empty($resultsbynodeid[$nodeid])) {
                $currentvalue = (float)$resultsbynodeid[$nodeid]->nodevalue;
            }

            $minimumvalue = isset($visual['minvalue']) ? (float)$visual['minvalue'] :
                (isset($node->minimumvalue) ? (float)$node->minimumvalue : 0.0);
            $maximumvalue = isset($visual['maxvalue']) ? (float)$visual['maxvalue'] :
                (isset($node->maximumvalue) ? (float)$node->maximumvalue : max($currentvalue, 1.0));

            if ($maximumvalue <= $minimumvalue) {
                $maximumvalue = max($currentvalue, 1.0);
                $minimumvalue = 0.0;
            }

            $percentage = 0.0;
            if ($maximumvalue > $minimumvalue) {
                $percentage = (($currentvalue - $minimumvalue) / ($maximumvalue - $minimumvalue)) * 100.0;
            }
            $percentage = max(0.0, min(100.0, $percentage));

            $meaninghint = '';
            if (!empty($node->description)) {
                $meaninghint = s($node->description);
            } else if (!empty($node->unitlabel)) {
                $meaninghint = s($node->unitlabel);
            } else if (!empty($node->interpretation)) {
                $meaninghint = s($node->interpretation);
            }

            $infobutton = '';
            if ($meaninghint !== '') {
                $infobutton = html_writer::tag(
                    'button',
                    'ⓘ',
                    [
                        'type' => 'button',
                        'class' => 'commandroom-node-meaning-toggle',
                        'data-nodeid' => $nodeid,
                        'aria-expanded' => 'false',
                        'title' => $meaninghint,
                    ]
                );
            }

            $title = html_writer::tag(
                'h4',
                s($node->name) . $infobutton,
                ['class' => 'commandroom-visual-node-title']
            );

            $value = html_writer::span(
                html_writer::tag('strong', get_string('currentvalue', 'mod_commandroom') . ': ') .
                format_float($currentvalue, 2),
                'commandroom-visual-node-value'
            );

            $meaninghtml = $this->render_node_meaning($node);

            $unitvalue = null;
            $maxicons = null;
            $iconsize = null;
            $minsize = null;
            $maxsize = null;

            if ($type === 'scaling_icon') {
                $minsize = isset($visual['minsize']) ? max(10.0, (float)$visual['minsize']) : 60.0;
                $maxsize = isset($visual['maxsize']) ? max($minsize, (float)$visual['maxsize']) : 220.0;

                $scalepercentage = $percentage / 100.0;
                $sidepx = $minsize + (($maxsize - $minsize) * $scalepercentage);

                $meta = html_writer::div(
                    get_string('visualscalingiconmeta', 'mod_commandroom'),
                    'commandroom-visual-node-meta'
                );

                $visualhtml = html_writer::empty_tag('img', [
                    'src' => $iconurl,
                    'alt' => s($node->name),
                    'class' => 'commandroom-visual-scaling-image',
                    'style' => 'width: ' . format_float($sidepx, 2, true, true) .
                        'px; height: ' . format_float($sidepx, 2, true, true) . 'px;',
                ]);
            } else {
                $unitvalue = isset($visual['unitvalue']) ? max(0.000001, (float)$visual['unitvalue']) : 10.0;
                $maxicons = isset($visual['maxicons']) ? max(1, (int)$visual['maxicons']) : 80;
                $iconsize = isset($visual['iconsize']) ? max(8, (int)$visual['iconsize']) : 36;

                $iconcount = (int)round(max(0.0, $currentvalue) / $unitvalue);
                $iconcount = max(1, min($maxicons, $iconcount));

                $meta = html_writer::div(
                    get_string('visualrepeatediconmeta', 'mod_commandroom',
                        (object)[
                            'unit' => format_float($unitvalue, 2),
                            'name' => s($node->name),
                        ]
                    ),
                    'commandroom-visual-node-meta'
                );

                $visualhtml = '';
                for ($i = 0; $i < $iconcount; $i++) {
                    $seed = abs(crc32($nodeid . ':' . $i . ':' . $iconcount));
                    $left = 6 + ($seed % 82);
                    $top = 6 + ((int)floor($seed / 97) % 82);

                    $visualhtml .= html_writer::empty_tag('img', [
                        'src' => $iconurl,
                        'alt' => '',
                        'class' => 'commandroom-visual-repeated-image',
                        'style' => 'width: ' . $iconsize . 'px; height: ' . $iconsize . 'px; ' .
                            'left: ' . $left . '%; top: ' . $top . '%;',
                    ]);
                }
            }

            $iconarea = html_writer::div(
                $visualhtml,
                'commandroom-visual-icons',
                [
                    'aria-label' => s($node->name) . ' visual display',
                    'data-nodeid' => $nodeid,
                ]
            );

            $barfill = html_writer::div(
                '',
                'commandroom-visual-progress-fill',
                [
                    'style' => 'width: ' . format_float($percentage, 2, true, true) . '%;',
                ]
            );

            $bar = html_writer::div(
                $barfill,
                'commandroom-visual-progress-bar',
                [
                    'aria-label' => s($node->name) . ' progress',
                ]
            );

            $cardstyle = '';

            if (isset($visual['x']) && isset($visual['y']) && isset($visual['w']) && isset($visual['h'])) {
                $x = max(1, (int)$visual['x']);
                $y = max(1, (int)$visual['y']);
                $w = max(1, (int)$visual['w']);
                $h = max(1, (int)$visual['h']);

                $cardstyle = 'grid-column: ' . $x . ' / span ' . $w . '; ' .
                    'grid-row: ' . $y . ' / span ' . $h . ';';
                $hasgridlayout = true;
            }

            $controlshtml = '';
            $actionbuttons = '';
            if (!empty($node->studentcontrolled)) {
                if ($canpropose && $cmid > 0) {
                    $actionbuttons .= html_writer::tag(
                        'button',
                        '🔽',
                        [
                            'type' => 'button',
                            'class' => 'btn btn-outline-primary btn-sm commandroom-visual-panel-toggle commandroom-visual-panel-glyph',
                            'data-nodeid' => $nodeid,
                            'data-paneltype' => 'proposal',
                            'aria-expanded' => 'false',
                            'aria-label' => get_string('proposalpanel', 'mod_commandroom'),
                            'title' => get_string('proposalpanel', 'mod_commandroom'),
                        ]
                    );
                    $controlshtml .= $this->render_visual_proposal_controls($cmid, $node);
                }

                if ($showgovernance && $cmid > 0 && $runid !== null) {
                    $actionbuttons .= html_writer::tag(
                        'button',
                        '🔻',
                        [
                            'type' => 'button',
                            'class' => 'btn btn-outline-secondary btn-sm commandroom-visual-panel-toggle commandroom-visual-panel-glyph',
                            'data-nodeid' => $nodeid,
                            'data-paneltype' => 'governance',
                            'aria-expanded' => 'false',
                            'aria-label' => get_string('governancepanel', 'mod_commandroom'),
                            'title' => get_string('governancepanel', 'mod_commandroom'),
                        ]
                    );
                    $controlshtml .= $this->render_visual_governance_controls(
                        $cmid,
                        $runid,
                        $node,
                        $decisions,
                        $governancereadonly
                    );
                }
            }

            if ($actionbuttons !== '') {
                $actionbuttons = html_writer::div($actionbuttons, 'commandroom-visual-actionbar');
            }

            $cardattributes = [
                'data-nodeid' => $nodeid,
                'data-visualtype' => $type,
                'data-iconurl' => $iconurl,
                'data-unitvalue' => $unitvalue !== null ? format_float($unitvalue, 6, true, true) : '',
                'data-maxicons' => $maxicons !== null ? (int)$maxicons : '',
                'data-iconsize' => $iconsize !== null ? (int)$iconsize : '',
                'data-minvalue' => format_float($minimumvalue, 6, true, true),
                'data-maxvalue' => format_float($maximumvalue, 6, true, true),
                'data-minsize' => $minsize !== null ? format_float($minsize, 2, true, true) : '',
                'data-maxsize' => $maxsize !== null ? format_float($maxsize, 2, true, true) : '',
                'data-description' => !empty($node->description) ? s($node->description) : '',
                'data-unitlabel' => !empty($node->unitlabel) ? s($node->unitlabel) : '',
                'data-interpretation' => !empty($node->interpretation) ? s($node->interpretation) : '',
            ];

            if ($cardstyle !== '') {
                $cardattributes['style'] = $cardstyle;
            }

            $valuerow = html_writer::div(
                $value . $actionbuttons,
                'commandroom-visual-value-row'
            );

            $cards .= html_writer::div(
                $title . $valuerow . $meta . $meaninghtml . $iconarea . $bar . $controlshtml,
                'generalbox commandroom-visual-card',
                $cardattributes
            );
        }

        if ($cards === '') {
            return '';
        }

        $containerclass = $hasgridlayout ? 'commandroom-visual-system-grid' : 'commandroom-visual-system-stack';

        $edgemarkers = '';
        foreach ($edges as $edge) {
            $sourceid = isset($edge->sourcenodeid) ? (int)$edge->sourcenodeid : 0;
            $targetid = isset($edge->targetnodeid) ? (int)$edge->targetnodeid : 0;

            if ($sourceid < 1 || $targetid < 1) {
                continue;
            }

            $edgemarkers .= html_writer::empty_tag('span', [
                'class' => 'commandroom-visual-edge',
                'data-source' => $sourceid,
                'data-target' => $targetid,
                'data-strength' => isset($edge->strength) ? format_float((float)$edge->strength, 6, true, true) : '0',
                'data-relationtype' => isset($edge->relationtype) ? s($edge->relationtype) : 'linear',
                'data-polarity' => isset($edge->polarity) ? s($edge->polarity) : 'neutral',
                'data-label' => !empty($edge->label) ? s($edge->label) : '',
                'data-loopgroup' => !empty($edge->loopgroup) ? s($edge->loopgroup) : '',
                'data-curvature' => isset($edge->curvature) ? (int)$edge->curvature : 0,
            ]);
        }

        $toggle = html_writer::div(
            html_writer::tag('button', get_string('cardview', 'mod_commandroom'), [
                'type' => 'button',
                'class' => 'btn btn-outline-secondary btn-sm commandroom-visual-view-toggle',
                'data-view' => 'cards',
                'aria-pressed' => 'false',
            ]) . ' ' .
            html_writer::tag('button', get_string('systemsview', 'mod_commandroom'), [
                'type' => 'button',
                'class' => 'btn btn-secondary btn-sm commandroom-visual-view-toggle active',
                'data-view' => 'systems',
                'aria-pressed' => 'true',
            ]),
            'commandroom-visual-view-controls'
        );

        $loopgroups = [];
        foreach ($edges as $edge) {
            if (!empty($edge->loopgroup)) {
                $loopgroup = (string)$edge->loopgroup;
                if (!isset($loopgroups[$loopgroup])) {
                    $loopgroups[$loopgroup] = 0;
                }
                $loopgroups[$loopgroup]++;
            }
        }
        ksort($loopgroups);

        $loopcontrols = '';
        if (!empty($loopgroups)) {
            $options = html_writer::tag(
                'option',
                get_string('showallrelationships', 'mod_commandroom'),
                ['value' => '']
            );

            foreach ($loopgroups as $loopgroup => $edgecount) {
                $options .= html_writer::tag(
                    'option',
                    get_string('loopselectoroption', 'mod_commandroom',
                        (object)[
                            'loopgroup' => s($loopgroup),
                            'edgecount' => (int)$edgecount,
                        ]
                    ),
                    ['value' => s($loopgroup)]
                );
            }

            $loopselector = html_writer::tag(
                'select',
                $options,
                [
                    'id' => 'commandroom-loop-selector',
                    'class' => 'form-control commandroom-loop-selector',
                ]
            );

            $loopcontrols = html_writer::div(
                html_writer::tag(
                    'label',
                    get_string('showloop', 'mod_commandroom'),
                    [
                        'class' => 'commandroom-loop-label',
                        'for' => 'commandroom-loop-selector',
                    ]
                ) .
                $loopselector .
                html_writer::div(
                    get_string('allrelationshipsvisible', 'mod_commandroom'),
                    'commandroom-loop-summary',
                    ['aria-live' => 'polite']
                ),
                'commandroom-loop-controls'
            );
        }

        $arrowlayer = html_writer::tag('svg', '', [
            'class' => 'commandroom-visual-arrow-layer',
            'aria-hidden' => 'true',
            'focusable' => 'false',
        ]);

        $stage = html_writer::div(
            $arrowlayer . html_writer::div($cards, $containerclass) . $edgemarkers,
            'commandroom-visual-map-stage'
        );

        $heading = html_writer::tag('h3', get_string('visualsystemview', 'mod_commandroom'));

        return html_writer::div(
            $heading . $toggle . $loopcontrols . $stage,
            'commandroom-visual-system commandroom-view-systems'
        );
    }

    /**
     * Backwards-compatible wrapper for older view.php versions.
     *
     * @param array $nodes
     * @param array $resultsbynodeid
     * @return string
     */
    public function render_first_stock_visual_card(array $nodes, array $resultsbynodeid = []): string {
        return $this->render_visual_system_cards($nodes, $resultsbynodeid);
    }


}
