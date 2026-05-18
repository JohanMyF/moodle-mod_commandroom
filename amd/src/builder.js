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
 * Builder UI for mod_commandroom.
 *
 * @module     mod_commandroom/builder
 * @package    mod_commandroom
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/str'], function(Str) {
    var commandroomStrings = {};
    var commandroomStringKeys = [
        'polarity', 'label', 'builderjsonvalid', 'builderjsoninvalid', 'builderdragtitle', 'buildereditrelationship', 'builderaffectstext', 'builderrefinerelationship', 'builderclose', 'builderpositivepolarity', 'buildernegativepolarity', 'builderneutralpolarity', 'builderstrength', 'builderdelayiterations', 'builderloopgroup', 'builderloopgroupexample', 'builderapplytojsondraft', 'buildercancel', 'builderrelationshipupdated', 'buildersaveendpointmissing', 'buildersavingsystem', 'buildersavefailed', 'buildersystemsaved', 'buildercouldnotloadstrings', 'builderthisnode', 'builderpreviewstudent', 'builderpreviewincoming', 'builderpreviewpeaksat', 'builderpreviewwhen', 'builderpreviewis', 'builderpreviewbellcurve', 'builderpreviewisnear', 'builderpreviewspread', 'builderpreviewrandombetween', 'builderpreviewand', 'builderchoosedetermination', 'buildervisualappearance', 'buildericon', 'builderselectedicon', 'buildervisualmode', 'builderscalingicon', 'builderrepeatedicon', 'builderscalingvalue', 'builderminvalue', 'buildermaxvalue', 'builderminsize', 'buildermaxsize', 'builderrepeatedsettings', 'builderunitvalue', 'buildermaxicons', 'buildericonsize', 'builderlayout', 'buildergrid', 'builderrow', 'buildereditnode', 'buildernodetype', 'builderstock', 'builderflow', 'buildervariable', 'buildercomputed', 'builderhowupdated', 'builderstudentdecision', 'builderstockaccumulation', 'builderformula', 'builderincoming', 'builderstocksettings', 'builderbase', 'builderpriorself', 'builderzero', 'builderinflows', 'builderoutflows', 'builderoptionalrate', 'buildernone', 'builderformulatype', 'builderselectformula', 'buildermultiply', 'builderdivide', 'builderpercentage', 'buildersum', 'builderadd', 'builderlinear', 'builderdiminishing', 'builderoptimum', 'builderbell', 'builderrandom', 'builderleft', 'builderright', 'buildernumerator', 'builderdenominator', 'buildervalue', 'builderpercent', 'builderinputnode', 'builderslope', 'builderintercept', 'buildermaximumoutput', 'builderrisespeed', 'builderrisespeedhelp', 'builderbestinput', 'builderoptimumoutput', 'builderdropoff', 'builderdropoffhelp', 'builderfloor', 'buildercentre', 'builderpeakoutput', 'builderspread', 'builderaddnodes', 'buildersubtractnodes', 'builderminrandom', 'buildermaxrandom', 'builderinitialvalue', 'builderstudentsmayedit', 'buildervisibletostudents', 'builderdescription', 'builderinterpretation', 'buildernumberbelow', 'builderuntitlednode', 'buildercurrentvalue', 'buildernodeupdated', 'buildercouldnotupdateposition', 'buildercouldnotsyncmatrix', 'buildercouldnotupdaterelationships', 'buildercouldnotparsejson'
    ];

    function getString(key, replacement) {
        var value = commandroomStrings[key] || key;
        if (typeof replacement !== 'undefined') {
            value = value.replace('{$a}', replacement);
        }
        return value;
    }

    function loadLanguageStrings() {
        var requests = commandroomStringKeys.map(function(key) {
            return {key: key, component: 'mod_commandroom'};
        });

        return Str.get_strings(requests).then(function(values) {
            commandroomStringKeys.forEach(function(key, index) {
                commandroomStrings[key] = values[index];
            });
        });
    }
    var arrowRedrawTimer = null;
    var dragState = null;



    function clearElement(element) {
        if (!element) {
            return;
        }
        while (element.firstChild) {
            element.removeChild(element.firstChild);
        }
    }

    function replaceChildrenWithHtml(element, html) {
        if (!element) {
            return;
        }
        clearElement(element);
        var parser = new DOMParser();
        var documentFragment = parser.parseFromString('<div>' + html + '</div>', 'text/html');
        var wrapper = documentFragment.body.firstElementChild;
        if (!wrapper) {
            return;
        }
        while (wrapper.firstChild) {
            element.appendChild(document.importNode(wrapper.firstChild, true));
        }
    }

    function setSelectOptionsFromHtml(select, html) {
        if (!select) {
            return;
        }
        clearElement(select);
        var parser = new DOMParser();
        var documentFragment = parser.parseFromString('<select>' + html + '</select>', 'text/html');
        documentFragment.querySelectorAll('option').forEach(function(option) {
            select.appendChild(document.importNode(option, true));
        });
    }

    function getDownloadFilename() {
        var heading = document.querySelector('h1, h2');
        var raw = heading ? heading.textContent : 'commandroom-system';

        return raw.toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .substring(0, 80) + '.json';
    }

    function downloadJsonDraft(textarea) {
        var json = textarea.value || '{}';
        var blob = new Blob([json], {type: 'application/json;charset=utf-8'});
        var url = window.URL.createObjectURL(blob);
        var link = document.createElement('a');

        link.href = url;
        link.download = getDownloadFilename();

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        window.URL.revokeObjectURL(url);
    }

    function validateJsonDraft(textarea, status) {
        if (!status) {
            return;
        }

        try {
            JSON.parse(textarea.value || '{}');
            status.textContent = getString('builderjsonvalid');
            status.classList.remove('commandroom-builder-json-status-error');
            status.classList.add('commandroom-builder-json-status-ok');
        } catch (error) {
            status.textContent = getString('builderjsoninvalid', error.message);
            status.classList.remove('commandroom-builder-json-status-ok');
            status.classList.add('commandroom-builder-json-status-error');
        }
    }

    function registerJsonControls() {
        var textarea = document.querySelector('.commandroom-builder-json');
        var button = document.querySelector('.commandroom-builder-download-json');

        if (!textarea || !button) {
            return;
        }

        var status = document.querySelector('.commandroom-builder-json-status');
        if (!status) {
            status = document.createElement('div');
            status.className = 'commandroom-builder-json-status';
            textarea.parentNode.insertBefore(status, textarea.nextSibling);
        }

        validateJsonDraft(textarea, status);

        textarea.addEventListener('input', function() {
            validateJsonDraft(textarea, status);
        });

        button.addEventListener('click', function() {
            validateJsonDraft(textarea, status);
            downloadJsonDraft(textarea);
        });
    }

    function markBuilderCanvas() {
        var visualSystem = document.querySelector('.commandroom-visual-system');
        if (visualSystem) {
            visualSystem.classList.add('commandroom-builder-mode');
        }

        var cards = document.querySelectorAll('.commandroom-visual-system .commandroom-visual-card');
        cards.forEach(function(card) {
            card.setAttribute('title', getString('builderdragtitle'));
        });
    }

    function createSvgElement(name) {
        return document.createElementNS('http://www.w3.org/2000/svg', name);
    }

    function getCardRelativeRect(card, canvasRect, scrollLeft, scrollTop) {
        var rect = card.getBoundingClientRect();

        return {
            left: rect.left - canvasRect.left + scrollLeft,
            top: rect.top - canvasRect.top + scrollTop,
            width: rect.width,
            height: rect.height,
            cx: rect.left - canvasRect.left + scrollLeft + (rect.width / 2),
            cy: rect.top - canvasRect.top + scrollTop + (rect.height / 2)
        };
    }

    function getEdgePoint(fromRect, toRect, outwardOffset) {
        var dx = toRect.cx - fromRect.cx;
        var dy = toRect.cy - fromRect.cy;
        var halfWidth = Math.max(1, fromRect.width / 2);
        var halfHeight = Math.max(1, fromRect.height / 2);

        if (dx === 0 && dy === 0) {
            return {x: fromRect.cx, y: fromRect.cy};
        }

        var scaleX = dx === 0 ? Number.POSITIVE_INFINITY : Math.abs(halfWidth / dx);
        var scaleY = dy === 0 ? Number.POSITIVE_INFINITY : Math.abs(halfHeight / dy);
        var scale = Math.min(scaleX, scaleY);
        var length = Math.sqrt((dx * dx) + (dy * dy));
        var offset = Number(outwardOffset) || 0;

        return {
            x: fromRect.cx + (dx * scale) + ((dx / length) * offset),
            y: fromRect.cy + (dy * scale) + ((dy / length) * offset)
        };
    }


    function clamp(value, min, max) {
        return Math.max(min, Math.min(max, value));
    }

    function getPortPoint(rect, side, offset, outward) {
        var margin = Number(outward) || 0;
        var safeX = clamp(rect.cx + offset, rect.left + 16, rect.left + rect.width - 16);
        var safeY = clamp(rect.cy + offset, rect.top + 16, rect.top + rect.height - 16);

        if (side === 'right') {
            return {x: rect.left + rect.width + margin, y: safeY};
        }

        if (side === 'left') {
            return {x: rect.left - margin, y: safeY};
        }

        if (side === 'bottom') {
            return {x: safeX, y: rect.top + rect.height + margin};
        }

        return {x: safeX, y: rect.top - margin};
    }

    function getConnectionSides(sourceRect, targetRect) {
        var dx = targetRect.cx - sourceRect.cx;
        var dy = targetRect.cy - sourceRect.cy;

        if (Math.abs(dx) >= Math.abs(dy)) {
            return dx >= 0
                ? {source: 'right', target: 'left'}
                : {source: 'left', target: 'right'};
        }

        return dy >= 0
            ? {source: 'bottom', target: 'top'}
            : {source: 'top', target: 'bottom'};
    }

    function getPortBasedEdgePoints(sourceRect, targetRect, curvature) {
        var sides = getConnectionSides(sourceRect, targetRect);
        var offset = Math.max(-5, Math.min(5, Number(curvature) || 0)) * 36;

        return {
            source: getPortPoint(sourceRect, sides.source, offset, 16),
            target: getPortPoint(targetRect, sides.target, offset, 22)
        };
    }


    function getPathMidpoint(source, target) {
        return {
            x: source.x + ((target.x - source.x) / 2),
            y: source.y + ((target.y - source.y) / 2)
        };
    }

    function buildArrowPath(source, target, curvature) {
        var curve = Number(curvature) || 0;

        if (curve === 0) {
            return 'M ' + source.x + ' ' + source.y + ' L ' + target.x + ' ' + target.y;
        }

        var midpoint = getPathMidpoint(source, target);
        var dx = target.x - source.x;
        var dy = target.y - source.y;
        var length = Math.max(1, Math.sqrt((dx * dx) + (dy * dy)));
        var bend = Math.max(-4, Math.min(4, curve)) * 24;
        var controlX = midpoint.x + ((-dy / length) * bend);
        var controlY = midpoint.y + ((dx / length) * bend);

        return 'M ' + source.x + ' ' + source.y + ' Q ' + controlX + ' ' + controlY + ' ' + target.x + ' ' + target.y;
    }

    function getLabelPoint(source, target, curvature) {
        var midpoint = getPathMidpoint(source, target);
        var curve = Number(curvature) || 0;

        if (curve === 0) {
            return midpoint;
        }

        var dx = target.x - source.x;
        var dy = target.y - source.y;
        var length = Math.max(1, Math.sqrt((dx * dx) + (dy * dy)));
        var bend = Math.max(-4, Math.min(4, curve)) * 30;

        return {
            x: midpoint.x + ((-dy / length) * bend),
            y: midpoint.y + ((dx / length) * bend)
        };
    }

    function buildEdgeBuckets(edges) {
        var buckets = {};

        edges.forEach(function(edge) {
            var sourceid = edge.getAttribute('data-source') || '';
            var targetid = edge.getAttribute('data-target') || '';

            if (sourceid === '' || targetid === '') {
                return;
            }

            var key = sourceid < targetid ? sourceid + ':' + targetid : targetid + ':' + sourceid;

            if (!buckets[key]) {
                buckets[key] = [];
            }

            buckets[key].push(edge);
        });

        return buckets;
    }

    function getAutomaticCurvature(edge, buckets) {
        var sourceid = edge.getAttribute('data-source') || '';
        var targetid = edge.getAttribute('data-target') || '';

        if (sourceid === '' || targetid === '') {
            return 0;
        }

        var key = sourceid < targetid ? sourceid + ':' + targetid : targetid + ':' + sourceid;
        var bucket = buckets[key] || [];

        if (bucket.length <= 1) {
            return 0;
        }

        var index = bucket.indexOf(edge);
        var centre = (bucket.length - 1) / 2;
        var direction = sourceid < targetid ? 1 : -1;
        var offset = (index - centre) * 3.6;

        if (offset === 0 && bucket.length > 1) {
            offset = 1.8;
        }

        return offset * direction;
    }

    function getEffectiveCurvature(edge, buckets) {
        var configured = Number(edge.getAttribute('data-curvature')) || 0;
        var automatic = getAutomaticCurvature(edge, buckets);

        return configured + automatic;
    }

    function ensureMarkerDefs(svg) {
        var defs = createSvgElement('defs');

        ['positive', 'negative', 'neutral'].forEach(function(type) {
            var marker = createSvgElement('marker');
            marker.setAttribute('id', 'commandroom-builder-arrowhead-' + type);
            marker.setAttribute('viewBox', '0 0 10 10');
            marker.setAttribute('refX', '9');
            marker.setAttribute('refY', '5');
            marker.setAttribute('markerWidth', '5');
            marker.setAttribute('markerHeight', '5');
            marker.setAttribute('orient', 'auto-start-reverse');

            var markerpath = createSvgElement('path');
            markerpath.setAttribute('d', 'M 0 0 L 10 5 L 0 10 z');
            markerpath.setAttribute('class', 'commandroom-visual-arrow-marker commandroom-visual-arrow-marker-' + type);

            marker.appendChild(markerpath);
            defs.appendChild(marker);
        });

        svg.appendChild(defs);
    }

    function sizeSvgToCanvas(svg, stage, grid) {
        var width = Math.max(stage.scrollWidth, grid ? grid.scrollWidth : 0, stage.clientWidth, 1200);
        var height = Math.max(stage.scrollHeight, grid ? grid.scrollHeight : 0, stage.clientHeight, 800);

        svg.setAttribute('width', width);
        svg.setAttribute('height', height);
        svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);
        svg.style.width = width + 'px';
        svg.style.height = height + 'px';
    }

    function drawBuilderArrows() {
        var visualSystem = document.querySelector('.commandroom-visual-system');
        if (!visualSystem) {
            return;
        }

        var stage = visualSystem.querySelector('.commandroom-visual-map-stage');
        var svg = visualSystem.querySelector('.commandroom-visual-arrow-layer');
        var grid = visualSystem.querySelector('.commandroom-visual-system-grid, .commandroom-visual-system-stack');
        var edges = visualSystem.querySelectorAll('.commandroom-visual-edge');

        if (!stage || !svg || !edges.length) {
            return;
        }

        var edgeBuckets = buildEdgeBuckets(edges);

        sizeSvgToCanvas(svg, stage, grid);

        var stageRect = stage.getBoundingClientRect();
        var scrollLeft = stage.scrollLeft || 0;
        var scrollTop = stage.scrollTop || 0;

        clearElement(svg);
        ensureMarkerDefs(svg);

        edges.forEach(function(edge) {
            var sourceid = edge.getAttribute('data-source');
            var targetid = edge.getAttribute('data-target');

            var sourceCard = visualSystem.querySelector('.commandroom-visual-card[data-nodeid="' + sourceid + '"]');
            var targetCard = visualSystem.querySelector('.commandroom-visual-card[data-nodeid="' + targetid + '"]');

            if (!sourceCard || !targetCard) {
                return;
            }

            var sourceRect = getCardRelativeRect(sourceCard, stageRect, scrollLeft, scrollTop);
            var targetRect = getCardRelativeRect(targetCard, stageRect, scrollLeft, scrollTop);

            var configuredCurvature = Number(edge.getAttribute('data-curvature')) || 0;
            var curvature = getEffectiveCurvature(edge, edgeBuckets);
            var points = getPortBasedEdgePoints(sourceRect, targetRect, curvature);
            var polarity = edge.getAttribute('data-polarity') || 'neutral';

            if (['positive', 'negative', 'neutral'].indexOf(polarity) === -1) {
                polarity = 'neutral';
            }

            var pathclass = 'commandroom-visual-arrow-path commandroom-visual-arrow-path-' + polarity;
            if (curvature !== configuredCurvature) {
                pathclass += ' commandroom-visual-arrow-path-auto-separated';
            }
            if (edge.getAttribute('data-loopgroup')) {
                pathclass += ' commandroom-visual-arrow-path-looped';
            }

            var path = createSvgElement('path');
            path.setAttribute('d', buildArrowPath(points.source, points.target, curvature));
            path.setAttribute('class', pathclass + ' commandroom-builder-editable-edge');
            path.setAttribute('marker-end', 'url(#commandroom-builder-arrowhead-' + polarity + ')');
            path.setAttribute('fill', 'none');
            path.setAttribute('data-source-nodeid', sourceid);
            path.setAttribute('data-target-nodeid', targetid);
            path.setAttribute('data-polarity', polarity);
            path.setAttribute('data-label', edge.getAttribute('data-label') || '');
            path.setAttribute('data-loopgroup', edge.getAttribute('data-loopgroup') || '');
            path.setAttribute('data-curvature', edge.getAttribute('data-curvature') || 0);
            path.setAttribute('tabindex', '0');
            path.setAttribute('role', 'button');

            var title = createSvgElement('title');
            title.textContent = edge.getAttribute('data-label') || getString('buildereditrelationship');
            path.appendChild(title);

            path.addEventListener('click', function(event) {
                event.preventDefault();
                event.stopPropagation();
                openRelationshipEditor(path);
            });

            path.addEventListener('keydown', function(event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openRelationshipEditor(path);
                }
            });

            svg.appendChild(path);

            var label = edge.getAttribute('data-label') || '';
            if (label !== '') {
                var labelPoint = getLabelPoint(points.source, points.target, curvature);
                var text = createSvgElement('text');
                text.setAttribute('x', labelPoint.x);
                text.setAttribute('y', labelPoint.y);
                text.setAttribute('class', 'commandroom-visual-arrow-label commandroom-builder-editable-edge-label');
                text.setAttribute('text-anchor', 'middle');
                text.setAttribute('data-source-nodeid', sourceid);
                text.setAttribute('data-target-nodeid', targetid);
                text.textContent = label;
                text.addEventListener('click', function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    openRelationshipEditor(path);
                });
                svg.appendChild(text);
            }
        });
    }

    function scheduleBuilderArrowRedraw() {
        if (arrowRedrawTimer) {
            clearTimeout(arrowRedrawTimer);
        }

        arrowRedrawTimer = setTimeout(function() {
            drawBuilderArrows();
        }, 50);
    }

    function parseGridValue(value, fallback) {
        var parsed = parseInt(String(value).replace('span', ''), 10);
        return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
    }

    function getCardGridPosition(card) {
        var column = window.getComputedStyle(card).gridColumnStart;
        var row = window.getComputedStyle(card).gridRowStart;

        return {
            x: parseGridValue(column, 1),
            y: parseGridValue(row, 1)
        };
    }

    function getJsonNodeIndexForCard(card) {
        var cards = Array.prototype.slice.call(
            document.querySelectorAll('.commandroom-visual-system .commandroom-visual-card')
        );

        return cards.indexOf(card);
    }

    function updateJsonNodePosition(card, x, y) {
        var textarea = document.querySelector('.commandroom-builder-json');
        if (!textarea) {
            return;
        }

        var index = getJsonNodeIndexForCard(card);
        if (index < 0) {
            return;
        }

        try {
            var data = JSON.parse(textarea.value || '{}');
            if (!data.nodes || !data.nodes[index]) {
                return;
            }

            if (!data.nodes[index].visual) {
                data.nodes[index].visual = {};
            }

            data.nodes[index].visual.x = x;
            data.nodes[index].visual.y = y;
            data.nodes[index].visual.w = data.nodes[index].visual.w || 3;
            data.nodes[index].visual.h = data.nodes[index].visual.h || 3;

            textarea.value = JSON.stringify(data, null, 2);
            textarea.dispatchEvent(new Event('input', {bubbles: true}));
        } catch (error) {
            window.console.warn(getString('buildercouldnotupdateposition'), error);
        }
    }

    function getGridMetrics() {
        var rem = parseFloat(window.getComputedStyle(document.documentElement).fontSize) || 16;

        return {
            columnWidth: 7 * rem,
            rowHeight: 6 * rem
        };
    }

    function setCardGridPosition(card, x, y) {
        var style = window.getComputedStyle(card);
        var w = parseGridValue(style.gridColumnEnd, 3);
        var h = parseGridValue(style.gridRowEnd, 3);

        card.style.gridColumn = x + ' / span ' + w;
        card.style.gridRow = y + ' / span ' + h;
    }

    function clampGridPosition(x, y) {
        return {
            x: Math.max(1, Math.min(40, x)),
            y: Math.max(1, Math.min(30, y))
        };
    }

    function registerDragHandlers() {
        var visualSystem = document.querySelector('.commandroom-visual-system');
        if (!visualSystem) {
            return;
        }

        var stage = visualSystem.querySelector('.commandroom-visual-map-stage');
        var cards = visualSystem.querySelectorAll('.commandroom-visual-card');

        if (!stage || !cards.length) {
            return;
        }

        cards.forEach(function(card) {
            card.addEventListener('pointerdown', function(event) {
                if (event.button !== 0) {
                    return;
                }

                if (event.target.closest('button, input, textarea, select, a')) {
                    return;
                }

                var current = getCardGridPosition(card);
                var metrics = getGridMetrics();

                dragState = {
                    card: card,
                    startClientX: event.clientX,
                    startClientY: event.clientY,
                    startX: current.x,
                    startY: current.y,
                    metrics: metrics
                };

                card.classList.add('commandroom-builder-dragging');
                card.setPointerCapture(event.pointerId);
                event.preventDefault();
            });

            card.addEventListener('pointermove', function(event) {
                if (!dragState || dragState.card !== card) {
                    return;
                }

                var dx = event.clientX - dragState.startClientX;
                var dy = event.clientY - dragState.startClientY;

                var x = dragState.startX + Math.round(dx / dragState.metrics.columnWidth);
                var y = dragState.startY + Math.round(dy / dragState.metrics.rowHeight);
                var clamped = clampGridPosition(x, y);

                setCardGridPosition(card, clamped.x, clamped.y);
                scheduleBuilderArrowRedraw();
                event.preventDefault();
            });

            card.addEventListener('pointerup', function(event) {
                if (!dragState || dragState.card !== card) {
                    return;
                }

                var position = getCardGridPosition(card);
                card.classList.remove('commandroom-builder-dragging');
                updateJsonNodePosition(card, position.x, position.y);
                scheduleBuilderArrowRedraw();

                try {
                    card.releasePointerCapture(event.pointerId);
                } catch (ignore) {
                    // Pointer capture may already be released.
                }

                dragState = null;
                event.preventDefault();
            });

            card.addEventListener('pointercancel', function() {
                if (!dragState || dragState.card !== card) {
                    return;
                }

                card.classList.remove('commandroom-builder-dragging');
                dragState = null;
                scheduleBuilderArrowRedraw();
            });
        });
    }

    function registerBuilderCanvasEvents() {
        var visualSystem = document.querySelector('.commandroom-visual-system');
        var stage = visualSystem ? visualSystem.querySelector('.commandroom-visual-map-stage') : null;

        window.addEventListener('resize', scheduleBuilderArrowRedraw);

        if (stage) {
            stage.addEventListener('scroll', scheduleBuilderArrowRedraw);
        }

        scheduleBuilderArrowRedraw();
        setTimeout(scheduleBuilderArrowRedraw, 250);
        setTimeout(scheduleBuilderArrowRedraw, 800);
        setTimeout(scheduleBuilderArrowRedraw, 1500);
    }


    function getNodeRefMapFromJson(data) {
        var map = {};

        if (!data.nodes || !Array.isArray(data.nodes)) {
            return map;
        }

        var cards = Array.prototype.slice.call(
            document.querySelectorAll('.commandroom-visual-system .commandroom-visual-card')
        );

        cards.forEach(function(card, index) {
            if (data.nodes[index] && data.nodes[index].ref) {
                map[card.getAttribute('data-nodeid')] = data.nodes[index].ref;
            }
        });

        return map;
    }

    function getRelationshipKey(source, target) {
        return source + '->' + target;
    }

    function edgeExists(edges, source, target) {
        return edges.some(function(edge) {
            return edge.source === source && edge.target === target;
        });
    }

    function removeEdge(edges, source, target) {
        return edges.filter(function(edge) {
            return !(edge.source === source && edge.target === target);
        });
    }

    function syncRelationshipMatrixFromJson() {
        var textarea = document.querySelector('.commandroom-builder-json');
        if (!textarea) {
            return;
        }

        try {
            var data = JSON.parse(textarea.value || '{}');
            var refmap = getNodeRefMapFromJson(data);
            var edges = data.edges || [];

            document.querySelectorAll('.commandroom-relationship-checkbox').forEach(function(checkbox) {
                var sourceNodeid = checkbox.getAttribute('data-source-nodeid');
                var targetNodeid = checkbox.getAttribute('data-target-nodeid');
                var sourceref = refmap[sourceNodeid] || '';
                var targetref = refmap[targetNodeid] || '';

                checkbox.setAttribute('data-source-ref', sourceref);
                checkbox.setAttribute('data-target-ref', targetref);
                checkbox.checked = sourceref !== '' && targetref !== '' && edgeExists(edges, sourceref, targetref);

                var editbutton = document.querySelector(
                    '.commandroom-relationship-edit[data-source-nodeid="' + sourceNodeid + '"][data-target-nodeid="' + targetNodeid + '"]'
                );

                if (editbutton) {
                    if (checkbox.checked) {
                        editbutton.removeAttribute('hidden');
                        editbutton.disabled = false;
                    } else {
                        editbutton.setAttribute('hidden', 'hidden');
                        editbutton.disabled = true;
                    }
                }
            });
        } catch (error) {
            window.console.warn(getString('buildercouldnotsyncmatrix'), error);
        }
    }

    function addVisualEdgeMarker(edge) {
        var visualSystem = document.querySelector('.commandroom-visual-system');
        if (!visualSystem) {
            return;
        }

        var refmap = {};
        try {
            var data = JSON.parse((document.querySelector('.commandroom-builder-json') || {}).value || '{}');
            refmap = getNodeRefMapFromJson(data);
        } catch (ignore) {
            refmap = {};
        }

        var sourceNodeid = '';
        var targetNodeid = '';

        Object.keys(refmap).forEach(function(nodeid) {
            if (refmap[nodeid] === edge.source) {
                sourceNodeid = nodeid;
            }
            if (refmap[nodeid] === edge.target) {
                targetNodeid = nodeid;
            }
        });

        if (sourceNodeid === '' || targetNodeid === '') {
            return;
        }

        var existing = visualSystem.querySelector(
            '.commandroom-visual-edge[data-source="' + sourceNodeid + '"][data-target="' + targetNodeid + '"]'
        );

        if (existing) {
            return;
        }

        var marker = document.createElement('span');
        marker.className = 'commandroom-visual-edge';
        marker.setAttribute('hidden', 'hidden');
        marker.setAttribute('data-source', sourceNodeid);
        marker.setAttribute('data-target', targetNodeid);
        marker.setAttribute('data-polarity', edge.polarity || 'neutral');
        marker.setAttribute('data-label', edge.label || '');
        marker.setAttribute('data-loopgroup', edge.loopgroup || '');
        marker.setAttribute('data-curvature', edge.curvature || 0);

        visualSystem.appendChild(marker);
    }

    function removeVisualEdgeMarker(sourceRef, targetRef) {
        var visualSystem = document.querySelector('.commandroom-visual-system');
        if (!visualSystem) {
            return;
        }

        var refmap = {};
        try {
            var data = JSON.parse((document.querySelector('.commandroom-builder-json') || {}).value || '{}');
            refmap = getNodeRefMapFromJson(data);
        } catch (ignore) {
            refmap = {};
        }

        var sourceNodeid = '';
        var targetNodeid = '';

        Object.keys(refmap).forEach(function(nodeid) {
            if (refmap[nodeid] === sourceRef) {
                sourceNodeid = nodeid;
            }
            if (refmap[nodeid] === targetRef) {
                targetNodeid = nodeid;
            }
        });

        if (sourceNodeid === '' || targetNodeid === '') {
            return;
        }

        visualSystem.querySelectorAll(
            '.commandroom-visual-edge[data-source="' + sourceNodeid + '"][data-target="' + targetNodeid + '"]'
        ).forEach(function(marker) {
            marker.parentNode.removeChild(marker);
        });
    }

    function updateJsonEdgesFromCheckbox(checkbox) {
        var textarea = document.querySelector('.commandroom-builder-json');
        if (!textarea) {
            return;
        }

        try {
            var data = JSON.parse(textarea.value || '{}');
            if (!data.edges || !Array.isArray(data.edges)) {
                data.edges = [];
            }

            var refmap = getNodeRefMapFromJson(data);
            var sourceNodeid = checkbox.getAttribute('data-source-nodeid');
            var targetNodeid = checkbox.getAttribute('data-target-nodeid');
            var sourceref = refmap[sourceNodeid] || '';
            var targetref = refmap[targetNodeid] || '';

            if (sourceref === '' || targetref === '') {
                return;
            }

            if (checkbox.checked) {
                if (!edgeExists(data.edges, sourceref, targetref)) {
                    var edge = {
                        source: sourceref,
                        target: targetref,
                        relationtype: 'linear',
                        strength: 1,
                        delayiterations: 0,
                        polarity: 'positive',
                        label: checkbox.getAttribute('data-source-name') + ' ' + getString('builderaffectstext') + ' ' + checkbox.getAttribute('data-target-name'),
                        loopgroup: '',
                        curvature: 0,
                        visibletostudents: true
                    };

                    data.edges.push(edge);
                    addVisualEdgeMarker(edge);
                }

                window.setTimeout(function() {
                    openRelationshipEditorByNodeIds(sourceNodeid, targetNodeid);
                }, 120);
            } else {
                data.edges = removeEdge(data.edges, sourceref, targetref);
                removeVisualEdgeMarker(sourceref, targetref);
            }

            textarea.value = JSON.stringify(data, null, 2);
            textarea.dispatchEvent(new Event('input', {bubbles: true}));
            syncRelationshipMatrixFromJson();
            scheduleBuilderArrowRedraw();
        } catch (error) {
            window.console.warn(getString('buildercouldnotupdaterelationships'), error);
        }
    }

    function registerRelationshipMatrixHandlers() {
        var checkboxes = document.querySelectorAll('.commandroom-relationship-checkbox');

        if (!checkboxes.length) {
            return;
        }

        syncRelationshipMatrixFromJson();

        checkboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                updateJsonEdgesFromCheckbox(checkbox);
            });
        });

        document.querySelectorAll('.commandroom-relationship-edit').forEach(function(button) {
            button.addEventListener('click', function(event) {
                event.preventDefault();

                openRelationshipEditorByNodeIds(
                    button.getAttribute('data-source-nodeid') || '',
                    button.getAttribute('data-target-nodeid') || ''
                );
            });
        });

        var textarea = document.querySelector('.commandroom-builder-json');
        if (textarea) {
            textarea.addEventListener('input', syncRelationshipMatrixFromJson);
        }
    }


    function getJsonData() {
        var textarea = document.querySelector('.commandroom-builder-json');
        if (!textarea) {
            return null;
        }

        try {
            return JSON.parse(textarea.value || '{}');
        } catch (error) {
            window.console.warn(getString('buildercouldnotparsejson'), error);
            return null;
        }
    }

    function saveJsonData(data) {
        var textarea = document.querySelector('.commandroom-builder-json');
        if (!textarea || !data) {
            return;
        }

        textarea.value = JSON.stringify(data, null, 2);
        textarea.dispatchEvent(new Event('input', {bubbles: true}));
    }

    function getNodeRefMap(data) {
        var map = {};

        if (!data || !data.nodes || !Array.isArray(data.nodes)) {
            return map;
        }

        var cards = Array.prototype.slice.call(
            document.querySelectorAll('.commandroom-visual-system .commandroom-visual-card')
        );

        cards.forEach(function(card, index) {
            if (data.nodes[index] && data.nodes[index].ref) {
                map[card.getAttribute('data-nodeid')] = data.nodes[index].ref;
            }
        });

        return map;
    }

    function findEdgeInJson(data, sourceNodeid, targetNodeid) {
        var refmap = getNodeRefMap(data);
        var sourceref = refmap[sourceNodeid] || '';
        var targetref = refmap[targetNodeid] || '';

        if (!sourceref || !targetref || !data.edges || !Array.isArray(data.edges)) {
            return null;
        }

        for (var i = 0; i < data.edges.length; i++) {
            if (data.edges[i].source === sourceref && data.edges[i].target === targetref) {
                return data.edges[i];
            }
        }

        return null;
    }

    function findHiddenEdgeMarker(sourceNodeid, targetNodeid) {
        var visualSystem = document.querySelector('.commandroom-visual-system');
        if (!visualSystem) {
            return null;
        }

        return visualSystem.querySelector(
            '.commandroom-visual-edge[data-source="' + sourceNodeid + '"][data-target="' + targetNodeid + '"]'
        );
    }

    function ensureRelationshipEditor() {
        var editor = document.querySelector('.commandroom-relationship-editor');
        if (editor) {
            return editor;
        }

        editor = document.createElement('div');
        editor.className = 'commandroom-relationship-editor generalbox';
        editor.setAttribute('hidden', 'hidden');

        replaceChildrenWithHtml(editor,
            '<div class="commandroom-relationship-editor-header">' +
                '<h3 class="commandroom-relationship-editor-title">' + getString('builderrefinerelationship') + '</h3>' +
                '<button type="button" class="btn btn-link commandroom-relationship-editor-close" aria-label="' + getString('builderclose') + '">×</button>' +
            '</div>' +
            '<div class="commandroom-relationship-editor-summary"></div>' +
            '<div class="form-group">' +
                '<label>' + getString('label') + '</label>' +
                '<input type="text" class="form-control commandroom-relationship-editor-label">' +
            '</div>' +
            '<div class="form-group">' +
                '<label>' + getString('polarity') + '</label>' +
                '<select class="form-control commandroom-relationship-editor-polarity">' +
                    '<option value="positive">' + getString('builderpositivepolarity') + '</option>' +
                    '<option value="negative">' + getString('buildernegativepolarity') + '</option>' +
                    '<option value="neutral">' + getString('builderneutralpolarity') + '</option>' +
                '</select>' +
            '</div>' +
            '<div class="form-group">' +
                '<label>' + getString('builderstrength') + '</label>' +
                '<input type="number" step="0.1" class="form-control commandroom-relationship-editor-strength">' +
            '</div>' +
            '<div class="form-group">' +
                '<label>' + getString('builderdelayiterations') + '</label>' +
                '<input type="number" step="1" min="0" class="form-control commandroom-relationship-editor-delay">' +
            '</div>' +
            '<div class="form-group">' +
                '<label>' + getString('builderloopgroup') + '</label>' +
                '<input type="text" class="form-control commandroom-relationship-editor-loopgroup" placeholder="' + getString('builderloopgroupexample') + '">' +
            '</div>' +
            '<div class="commandroom-relationship-editor-actions">' +
                '<button type="button" class="btn btn-primary commandroom-relationship-editor-save">' + getString('builderapplytojsondraft') + '</button> ' +
                '<button type="button" class="btn btn-outline-secondary commandroom-relationship-editor-cancel">' + getString('buildercancel') + '</button>' +
            '</div>' +
            '<div class="commandroom-relationship-editor-status" aria-live="polite"></div>');

        var jsonPanel = document.querySelector('.commandroom-builder-json-panel');
        if (jsonPanel && jsonPanel.parentNode) {
            jsonPanel.parentNode.insertBefore(editor, jsonPanel);
        } else {
            document.body.appendChild(editor);
        }

        editor.querySelector('.commandroom-relationship-editor-close').addEventListener('click', closeRelationshipEditor);
        editor.querySelector('.commandroom-relationship-editor-cancel').addEventListener('click', closeRelationshipEditor);
        editor.querySelector('.commandroom-relationship-editor-save').addEventListener('click', saveRelationshipEditor);

        return editor;
    }

    function closeRelationshipEditor() {
        var editor = document.querySelector('.commandroom-relationship-editor');
        if (!editor) {
            return;
        }

        editor.setAttribute('hidden', 'hidden');
        editor.removeAttribute('data-source-nodeid');
        editor.removeAttribute('data-target-nodeid');
    }

    function openRelationshipEditorByNodeIds(sourceNodeid, targetNodeid) {
        var data = getJsonData();
        var edge = findEdgeInJson(data, sourceNodeid, targetNodeid);

        if (!edge) {
            return;
        }

        var sourceCard = document.querySelector('.commandroom-visual-card[data-nodeid="' + sourceNodeid + '"]');
        var targetCard = document.querySelector('.commandroom-visual-card[data-nodeid="' + targetNodeid + '"]');
        var sourceName = sourceCard ? sourceCard.querySelector('.commandroom-visual-node-title') : null;
        var targetName = targetCard ? targetCard.querySelector('.commandroom-visual-node-title') : null;

        var editor = ensureRelationshipEditor();
        editor.setAttribute('data-source-nodeid', sourceNodeid);
        editor.setAttribute('data-target-nodeid', targetNodeid);

        editor.querySelector('.commandroom-relationship-editor-summary').textContent =
            (sourceName ? sourceName.textContent : edge.source) + ' → ' +
            (targetName ? targetName.textContent : edge.target);

        editor.querySelector('.commandroom-relationship-editor-label').value = edge.label || '';
        editor.querySelector('.commandroom-relationship-editor-polarity').value = edge.polarity || 'neutral';
        editor.querySelector('.commandroom-relationship-editor-strength').value = Number(edge.strength || 0);
        editor.querySelector('.commandroom-relationship-editor-delay').value = Number(edge.delayiterations || 0);
        editor.querySelector('.commandroom-relationship-editor-loopgroup').value = edge.loopgroup || '';

        editor.querySelector('.commandroom-relationship-editor-status').textContent = '';
        editor.removeAttribute('hidden');
        editor.scrollIntoView({behavior: 'smooth', block: 'nearest'});
    }

    function openRelationshipEditor(path) {
        var sourceNodeid = path.getAttribute('data-source-nodeid') || '';
        var targetNodeid = path.getAttribute('data-target-nodeid') || '';

        openRelationshipEditorByNodeIds(sourceNodeid, targetNodeid);
    }

    function saveRelationshipEditor() {
        var editor = document.querySelector('.commandroom-relationship-editor');
        if (!editor) {
            return;
        }

        var sourceNodeid = editor.getAttribute('data-source-nodeid') || '';
        var targetNodeid = editor.getAttribute('data-target-nodeid') || '';
        var data = getJsonData();
        var edge = findEdgeInJson(data, sourceNodeid, targetNodeid);

        if (!edge) {
            return;
        }

        var label = editor.querySelector('.commandroom-relationship-editor-label').value.trim();
        var polarity = editor.querySelector('.commandroom-relationship-editor-polarity').value || 'neutral';
        var strength = Number(editor.querySelector('.commandroom-relationship-editor-strength').value);
        var delay = Number(editor.querySelector('.commandroom-relationship-editor-delay').value);
        var loopgroup = editor.querySelector('.commandroom-relationship-editor-loopgroup').value.trim();

        if (!Number.isFinite(strength)) {
            strength = 1;
        }

        if (!Number.isInteger(delay) || delay < 0) {
            delay = 0;
        }

        edge.label = label;
        edge.polarity = polarity;
        edge.strength = strength;
        edge.delayiterations = delay;
        edge.loopgroup = loopgroup;

        saveJsonData(data);

        var marker = findHiddenEdgeMarker(sourceNodeid, targetNodeid);
        if (marker) {
            marker.setAttribute('data-label', label);
            marker.setAttribute('data-polarity', polarity);
            marker.setAttribute('data-loopgroup', loopgroup);
        }

        syncRelationshipMatrixFromJson();
        editor.querySelector('.commandroom-relationship-editor-status').textContent = getString('builderrelationshipupdated');
        scheduleBuilderArrowRedraw();
    }


    function setBuilderSaveStatus(message, iserror) {
        var status = document.querySelector('.commandroom-builder-save-status');

        if (!status) {
            return;
        }

        status.textContent = message || '';
        status.classList.toggle('commandroom-builder-save-status-error', !!iserror);
        status.classList.toggle('commandroom-builder-save-status-ok', !iserror && message !== '');
    }

    function setSaveButtonsBusy(isbusy) {
        document.querySelectorAll('.commandroom-builder-save-system, .commandroom-builder-save-return').forEach(function(button) {
            button.disabled = !!isbusy;
        });
    }

    function encodeBase64Url(text) {
        var bytes;
        var binary = '';
        var i;

        if (window.TextEncoder) {
            bytes = new TextEncoder().encode(text);
            for (i = 0; i < bytes.length; i++) {
                binary += String.fromCharCode(bytes[i]);
            }
        } else {
            binary = window.unescape(window.encodeURIComponent(text));
        }

        return window.btoa(binary)
            .replace(/\+/g, '-')
            .replace(/\//g, '_')
            .replace(/=+$/g, '');
    }

    function saveBuilderJson(button) {
        var textarea = document.querySelector('.commandroom-builder-json');
        if (!textarea || !button) {
            return;
        }

        var saveurl = button.getAttribute('data-save-url') || '';
        var sesskey = button.getAttribute('data-sesskey') || '';
        var aftersave = button.getAttribute('data-after-save') || 'stay';
        var settingsurl = button.getAttribute('data-settings-url') || '';

        if (saveurl === '' || sesskey === '') {
            setBuilderSaveStatus(getString('buildersaveendpointmissing'), true);
            return;
        }

        try {
            JSON.parse(textarea.value || '{}');
        } catch (error) {
            setBuilderSaveStatus(getString('builderjsoninvalid', error.message), true);
            return;
        }

        setSaveButtonsBusy(true);
        setBuilderSaveStatus(getString('buildersavingsystem'), false);

        var formdata = new FormData();
        formdata.append('sesskey', sesskey);
        formdata.append('json64', encodeBase64Url(textarea.value || '{}'));

        fetch(saveurl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formdata
        }).then(function(response) {
            return response.json();
        }).then(function(data) {
            if (!data || data.status !== 'ok') {
                throw new Error(data && data.message ? data.message : getString('buildersavefailed'));
            }

            setBuilderSaveStatus(data.message || getString('buildersystemsaved'), false);

            if (aftersave === 'settings') {
                window.location.href = settingsurl || data.settingsurl || data.builderurl || window.location.href;
            }
        }).catch(function(error) {
            setBuilderSaveStatus(error.message || getString('buildersavefailed'), true);
        }).finally(function() {
            setSaveButtonsBusy(false);
        });
    }

    function registerBuilderSaveControls() {
        document.querySelectorAll('.commandroom-builder-save-system, .commandroom-builder-save-return').forEach(function(button) {
            button.addEventListener('click', function() {
                saveBuilderJson(button);
            });
        });
    }


    function findNodeIndexByNodeid(nodeid, data) {
        var cards = Array.prototype.slice.call(
            document.querySelectorAll('.commandroom-visual-system .commandroom-visual-card')
        );
        var card = document.querySelector('.commandroom-visual-card[data-nodeid="' + nodeid + '"]');

        if (!card || !data || !data.nodes || !Array.isArray(data.nodes)) {
            return -1;
        }

        var index = cards.indexOf(card);
        return data.nodes[index] ? index : -1;
    }

    function getJsonNodeRefs(data, excludeIndex) {
        var refs = [];

        if (!data || !data.nodes || !Array.isArray(data.nodes)) {
            return refs;
        }

        data.nodes.forEach(function(node, index) {
            if (index === excludeIndex) {
                return;
            }

            refs.push({
                ref: node.ref || '',
                name: node.name || node.ref || ''
            });
        });

        return refs;
    }

    function buildNodeOptions(nodes, selectedRefs) {
        var selected = {};
        (selectedRefs || []).forEach(function(ref) {
            selected[ref] = true;
        });

        return nodes.map(function(node) {
            var escapedRef = String(node.ref).replace(/"/g, '&quot;');
            var escapedName = String(node.name).replace(/</g, '&lt;').replace(/>/g, '&gt;');
            var selectedText = selected[node.ref] ? ' selected' : '';

            return '<option value="' + escapedRef + '"' + selectedText + '>' + escapedName + '</option>';
        }).join('');
    }


    function getArrayAlias(object, keys) {
        if (!object) {
            return [];
        }

        for (var i = 0; i < keys.length; i++) {
            if (Array.isArray(object[keys[i]])) {
                return object[keys[i]];
            }
        }

        return [];
    }

    function getIncomingEdgeRefsByPolarity(data, targetRef, polarity) {
        var refs = [];

        if (!data || !Array.isArray(data.edges) || !targetRef) {
            return refs;
        }

        data.edges.forEach(function(edge) {
            if (!edge || edge.target !== targetRef) {
                return;
            }

            if ((edge.polarity || 'neutral') !== polarity) {
                return;
            }

            if (edge.source && refs.indexOf(edge.source) === -1) {
                refs.push(edge.source);
            }
        });

        return refs;
    }

    function registerFriendlyMultiSelect(select, editor) {
        if (!select || select.getAttribute('data-commandroom-friendly-multiselect') === '1') {
            return;
        }

        select.setAttribute('data-commandroom-friendly-multiselect', '1');

        select.addEventListener('mousedown', function(event) {
            if (event.target && event.target.tagName === 'OPTION') {
                event.preventDefault();
                event.target.selected = !event.target.selected;
                select.focus();
                select.dispatchEvent(new Event('change', {bubbles: true}));
                if (editor) {
                    updateNodeEditorPreview(editor);
                }
            }
        });
    }

    function registerFriendlyMultiSelects(editor) {
        [
            '.commandroom-node-editor-inflows',
            '.commandroom-node-editor-outflows',
            '.commandroom-node-editor-sum-add',
            '.commandroom-node-editor-sum-subtract',
            '.commandroom-node-editor-add-items'
        ].forEach(function(selector) {
            registerFriendlyMultiSelect(editor.querySelector(selector), editor);
        });
    }

    function getOperandRef(operand) {
        if (!operand || typeof operand !== 'object') {
            return '';
        }
        return operand.kind === 'node' ? (operand.ref || '') : '';
    }

    function getOperandNumber(operand) {
        if (!operand || typeof operand !== 'object') {
            return '';
        }
        return operand.kind === 'number' && typeof operand.value !== 'undefined' ? Number(operand.value) : '';
    }

    function makeNodeOperand(ref) {
        return {kind: 'node', ref: ref};
    }

    function makeNumberOperand(value) {
        return {kind: 'number', value: Number.isFinite(value) ? value : 0};
    }

    function readNodeOrNumberOperand(select, input) {
        var ref = select ? (select.value || '') : '';
        if (ref !== '') {
            return makeNodeOperand(ref);
        }
        var number = input ? Number(input.value) : 0;
        return makeNumberOperand(Number.isFinite(number) ? number : 0);
    }

    function getNodeLabelByRef(ref) {
        var data = getJsonData();
        var nodes = data.nodes || [];
        var found = nodes.find(function(node) {
            return node.ref === ref;
        });
        return found ? (found.name || found.ref || ref) : ref;
    }

    function describeOperand(operand) {
        if (!operand || typeof operand !== 'object') {
            return '0';
        }
        if (operand.kind === 'node') {
            return getNodeLabelByRef(operand.ref || '');
        }
        if (operand.kind === 'number') {
            return String(Number(operand.value || 0));
        }
        return '0';
    }

    function updateNodeEditorPreview(editor) {
        var preview = editor.querySelector('.commandroom-node-editor-preview');
        if (!preview) {
            return;
        }

        var nodeName = editor.querySelector('.commandroom-node-editor-name').value.trim() || getString('builderthisnode');
        var mode = editor.querySelector('.commandroom-node-editor-updatemode').value;

        if (mode === 'student') {
            preview.textContent = nodeName + ' = ' + getString('builderpreviewstudent');
            return;
        }

        if (mode === 'incoming') {
            preview.textContent = nodeName + ' = ' + getString('builderpreviewincoming');
            return;
        }

        if (mode === 'stock_with_rate') {
            var base = editor.querySelector('.commandroom-node-editor-stock-base').value === 'zero' ? '0' : 'prior ' + nodeName;
            var pieces = [base];

            getSelectedOptions(editor.querySelector('.commandroom-node-editor-inflows')).forEach(function(ref) {
                pieces.push('+ ' + getNodeLabelByRef(ref));
            });

            getSelectedOptions(editor.querySelector('.commandroom-node-editor-outflows')).forEach(function(ref) {
                pieces.push('- ' + getNodeLabelByRef(ref));
            });

            var rate = editor.querySelector('.commandroom-node-editor-rate').value || '';
            if (rate !== '') {
                pieces.push('+ (' + base + ' × ' + getNodeLabelByRef(rate) + ')');
            }

            preview.textContent = nodeName + ' = ' + pieces.join(' ');
            return;
        }

        if (mode === 'formula') {
            var type = editor.querySelector('.commandroom-node-editor-calculation-type').value || '';
            if (type === 'multiply') {
                var left = readNodeOrNumberOperand(
                    editor.querySelector('.commandroom-node-editor-multiply-left'),
                    editor.querySelector('.commandroom-node-editor-multiply-left-number')
                );
                var right = readNodeOrNumberOperand(
                    editor.querySelector('.commandroom-node-editor-multiply-right'),
                    editor.querySelector('.commandroom-node-editor-multiply-right-number')
                );
                preview.textContent = nodeName + ' = ' + describeOperand(left) + ' × ' + describeOperand(right);
                return;
            }

            if (type === 'sum') {
                var sumPieces = [];
                getSelectedOptions(editor.querySelector('.commandroom-node-editor-sum-add')).forEach(function(ref) {
                    sumPieces.push(sumPieces.length === 0 ? getNodeLabelByRef(ref) : '+ ' + getNodeLabelByRef(ref));
                });
                getSelectedOptions(editor.querySelector('.commandroom-node-editor-sum-subtract')).forEach(function(ref) {
                    sumPieces.push('- ' + getNodeLabelByRef(ref));
                });
                preview.textContent = nodeName + ' = ' + (sumPieces.length ? sumPieces.join(' ') : '0');
                return;
            }

            if (type === 'add') {
                var addPieces = [];
                getSelectedOptions(editor.querySelector('.commandroom-node-editor-add-items')).forEach(function(ref) {
                    addPieces.push(addPieces.length === 0 ? getNodeLabelByRef(ref) : '+ ' + getNodeLabelByRef(ref));
                });
                preview.textContent = nodeName + ' = ' + (addPieces.length ? addPieces.join(' ') : '0');
                return;
            }

            if (type === 'divide') {
                var numerator = readNodeOrNumberOperand(
                    editor.querySelector('.commandroom-node-editor-divide-numerator'),
                    editor.querySelector('.commandroom-node-editor-divide-numerator-number')
                );
                var denominator = readNodeOrNumberOperand(
                    editor.querySelector('.commandroom-node-editor-divide-denominator'),
                    editor.querySelector('.commandroom-node-editor-divide-denominator-number')
                );
                preview.textContent = nodeName + ' = ' + describeOperand(numerator) + ' ÷ ' + describeOperand(denominator);
                return;
            }

            if (type === 'percentage') {
                var percentageValue = readNodeOrNumberOperand(
                    editor.querySelector('.commandroom-node-editor-percentage-value'),
                    editor.querySelector('.commandroom-node-editor-percentage-value-number')
                );
                var percentagePercent = readNodeOrNumberOperand(
                    editor.querySelector('.commandroom-node-editor-percentage-percent'),
                    editor.querySelector('.commandroom-node-editor-percentage-percent-number')
                );
                preview.textContent = nodeName + ' = ' + describeOperand(percentageValue) + ' × ' + describeOperand(percentagePercent) + '%';
                return;
            }

            if (type === 'linear') {
                var linearInput = editor.querySelector('.commandroom-node-editor-linear-input').value || '';
                var slope = editor.querySelector('.commandroom-node-editor-linear-slope').value || '1';
                var intercept = editor.querySelector('.commandroom-node-editor-linear-intercept').value || '0';
                preview.textContent = nodeName + ' = ' + intercept + ' + ' + slope + ' × ' + getNodeLabelByRef(linearInput);
                return;
            }

            if (type === 'diminishing_returns') {
                var diminishingInput = editor.querySelector('.commandroom-node-editor-diminishing-input').value || '';
                var maximum = editor.querySelector('.commandroom-node-editor-diminishing-maximum').value || '100';
                var rate = editor.querySelector('.commandroom-node-editor-diminishing-rate').value || '0.1';
                preview.textContent = nodeName + ' rises toward ' + maximum + ' from ' + getNodeLabelByRef(diminishingInput) + ' at rate ' + rate;
                return;
            }

            if (type === 'optimum_point') {
                var optimumInput = editor.querySelector('.commandroom-node-editor-optimum-input').value || '';
                var optimum = editor.querySelector('.commandroom-node-editor-optimum-optimum').value || '10';
                var optimumMax = editor.querySelector('.commandroom-node-editor-optimum-maximum').value || '100';
                preview.textContent = nodeName + ' ' + getString('builderpreviewpeaksat') + ' ' + optimumMax + ' ' + getString('builderpreviewwhen') + ' ' + getNodeLabelByRef(optimumInput) + ' ' + getString('builderpreviewis') + ' ' + optimum;
                return;
            }

            if (type === 'bell_curve') {
                var bellInput = editor.querySelector('.commandroom-node-editor-bell-input').value || '';
                var centre = editor.querySelector('.commandroom-node-editor-bell-centre').value || '10';
                var peak = editor.querySelector('.commandroom-node-editor-bell-maximum').value || '100';
                var spread = editor.querySelector('.commandroom-node-editor-bell-spread').value || '3';
                preview.textContent = nodeName + ' ' + getString('builderpreviewbellcurve') + ' ' + peak + ' ' + getString('builderpreviewwhen') + ' ' + getNodeLabelByRef(bellInput) + ' ' + getString('builderpreviewisnear') + ' ' + centre + ', ' + getString('builderpreviewspread') + ' ' + spread;
                return;
            }

            if (type === 'random_range') {
                var min = editor.querySelector('.commandroom-node-editor-random-min').value || '0';
                var max = editor.querySelector('.commandroom-node-editor-random-max').value || '0';
                preview.textContent = nodeName + ' = ' + getString('builderpreviewrandombetween') + ' ' + min + ' ' + getString('builderpreviewand') + ' ' + max;
                return;
            }
        }

        preview.textContent = getString('builderchoosedetermination');
    }

    function setFormulaTypePanels(editor) {
        var type = editor.querySelector('.commandroom-node-editor-calculation-type').value || '';
        ['add', 'sum', 'multiply', 'divide', 'percentage', 'linear', 'diminishing', 'optimum', 'bell', 'random'].forEach(function(name) {
            var panel = editor.querySelector('.commandroom-node-editor-' + name + '-panel');
            if (panel) {
                panel.hidden = true;
            }
        });

        if (type === 'add') {
            editor.querySelector('.commandroom-node-editor-add-panel').hidden = false;
        } else if (type === 'sum') {
            editor.querySelector('.commandroom-node-editor-sum-panel').hidden = false;
        } else if (type === 'multiply') {
            editor.querySelector('.commandroom-node-editor-multiply-panel').hidden = false;
        } else if (type === 'divide') {
            editor.querySelector('.commandroom-node-editor-divide-panel').hidden = false;
        } else if (type === 'percentage') {
            editor.querySelector('.commandroom-node-editor-percentage-panel').hidden = false;
        } else if (type === 'linear') {
            editor.querySelector('.commandroom-node-editor-linear-panel').hidden = false;
        } else if (type === 'diminishing_returns') {
            editor.querySelector('.commandroom-node-editor-diminishing-panel').hidden = false;
        } else if (type === 'optimum_point') {
            editor.querySelector('.commandroom-node-editor-optimum-panel').hidden = false;
        } else if (type === 'bell_curve') {
            editor.querySelector('.commandroom-node-editor-bell-panel').hidden = false;
        } else if (type === 'random_range') {
            editor.querySelector('.commandroom-node-editor-random-panel').hidden = false;
        }

        updateNodeEditorPreview(editor);
    }

    function setCalculationPanels(editor) {
        var mode = editor.querySelector('.commandroom-node-editor-updatemode').value;
        var stockPanel = editor.querySelector('.commandroom-node-editor-stock-panel');
        var formulaPanel = editor.querySelector('.commandroom-node-editor-formula-panel');
        var studentCheckbox = editor.querySelector('.commandroom-node-editor-studentcontrolled');
        var nodetype = editor.querySelector('.commandroom-node-editor-nodetype');

        if (stockPanel) {
            stockPanel.hidden = mode !== 'stock_with_rate';
        }

        if (formulaPanel) {
            formulaPanel.hidden = mode !== 'formula';
        }

        if (mode === 'student') {
            studentCheckbox.checked = true;
        } else if (mode === 'incoming' || mode === 'stock_with_rate' || mode === 'formula') {
            studentCheckbox.checked = false;
        }

        if (mode === 'stock_with_rate') {
            nodetype.value = 'stock';
        } else if (mode === 'incoming' || mode === 'formula') {
            nodetype.value = 'computed';
        }

        setFormulaTypePanels(editor);
        updateNodeEditorPreview(editor);
    }

    function inferUpdateMode(node) {
        if (node.studentcontrolled) {
            return 'student';
        }

        if (node.updateconfig && node.updateconfig.mode === 'stock_with_rate') {
            return 'stock_with_rate';
        }

        if (node.calculation && node.calculation.type) {
            return 'formula';
        }

        return 'incoming';
    }

    function ensureNodeEditor() {
        var editor = document.querySelector('.commandroom-node-editor');
        if (editor) {
            return editor;
        }

        editor = document.createElement('div');
        editor.className = 'commandroom-node-editor generalbox';
        editor.setAttribute('hidden', 'hidden');

        replaceChildrenWithHtml(editor,
            '<div class="commandroom-node-editor-header">' +
                '<h3 class="commandroom-node-editor-title">' + getString('buildereditnode') + '</h3>' +
                '<button type="button" class="btn btn-link commandroom-node-editor-close" aria-label="' + getString('builderclose') + '">×</button>' +
            '</div>' +
            '<div class="commandroom-node-editor-summary"></div>' +
            '<div class="form-group">' +
                '<label>Name</label>' +
                '<input type="text" class="form-control commandroom-node-editor-name">' +
            '</div>' +
            '<div class="form-group">' +
                '<label>' + getString('buildernodetype') + '</label>' +
                '<select class="form-control commandroom-node-editor-nodetype">' +
                    '<option value="stock">' + getString('builderstock') + '</option>' +
                    '<option value="flow">' + getString('builderflow') + '</option>' +
                    '<option value="computed">' + getString('buildercomputed') + '</option>' +
                    '<option value="variable">' + getString('buildervariable') + '</option>' +
                '</select>' +
            '</div>' +
            '<div class="form-group">' +
                '<label>How should this value change each iteration?</label>' +
                '<select class="form-control commandroom-node-editor-updatemode">' +
                    '<option value="student">Student/leader sets this value</option>' +
                    '<option value="incoming">Compute from incoming relationships</option>' +
                    '<option value="stock_with_rate">Accumulate as a stock</option>' +
                    '<option value="formula">Use a formula/configuration</option>' +
                '</select>' +
                '<small class="form-text text-muted">This writes updateconfig or calculation information into the JSON draft.</small>' +
            '</div>' +
            '<div class="generalbox commandroom-node-editor-stock-panel" hidden>' +
                '<h4>Stock accumulation</h4>' +
                '<p class="small">Use this when the value should remember its previous value, for example Population = old Population + Births - Deaths.</p>' +
                '<div class="form-group">' +
                    '<label>Base value</label>' +
                    '<select class="form-control commandroom-node-editor-stock-base">' +
                        '<option value="self">Use previous value of this node</option>' +
                        '<option value="zero">Start from zero each iteration</option>' +
                    '</select>' +
                '</div>' +
                '<div class="form-group">' +
                    '<label>Inflows: add these nodes each iteration</label>' +
                    '<select multiple class="form-control commandroom-node-editor-inflows"></select>' +
                '</div>' +
                '<div class="form-group">' +
                    '<label>Outflows: subtract these nodes each iteration</label>' +
                    '<select multiple class="form-control commandroom-node-editor-outflows"></select>' +
                    '<small class="form-text text-muted">Click an item to tick or untick it. Expenses should be selected here for a bank-balance example.</small>' +
                '</div>' +
                '<div class="form-group">' +
                    '<label>Growth/rate node (optional)</label>' +
                    '<select class="form-control commandroom-node-editor-rate">' +
                        '<option value="">' + getString('buildernone') + '</option>' +
                    '</select>' +
                    '<small class="form-text text-muted">Optional: adds previous value × selected rate node.</small>' +
                '</div>' +
            '</div>' +
            '<div class="generalbox commandroom-node-editor-formula-panel" hidden>' +
                '<h4>Formula builder</h4>' +
                '<p class="small">Use this when this node should be calculated from other nodes, for example Interest Earned = Balance × Interest Rate.</p>' +
                '<div class="form-group">' +
                    '<label>Calculation type</label>' +
                    '<select class="form-control commandroom-node-editor-calculation-type">' +
                        '<option value="">' + getString('buildernone') + '</option>' +
                        '<option value="multiply">' + getString('buildermultiply') + '</option>' +
                        '<option value="divide">' + getString('builderdivide') + '</option>' +
                        '<option value="percentage">' + getString('builderpercentage') + '</option>' +
                        '<option value="sum">' + getString('buildersum') + '</option>' +
                        '<option value="add">' + getString('builderadd') + '</option>' +
                        '<option value="linear">' + getString('builderlinear') + '</option>' +
                        '<option value="diminishing_returns">' + getString('builderdiminishing') + '</option>' +
                        '<option value="optimum_point">' + getString('builderoptimum') + '</option>' +
                        '<option value="bell_curve">' + getString('builderbell') + '</option>' +
                        '<option value="random_range">' + getString('builderrandom') + '</option>' +
                    '</select>' +
                '</div>' +
                '<div class="commandroom-node-editor-multiply-panel" hidden>' +
                    '<div class="form-group">' +
                        '<label>Left side</label>' +
                        '<select class="form-control commandroom-node-editor-multiply-left"></select>' +
                        '<small class="form-text text-muted">Choose a node, or leave blank and enter a number below.</small>' +
                        '<input type="number" step="any" class="form-control commandroom-node-editor-multiply-left-number" value="0">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>Right side</label>' +
                        '<select class="form-control commandroom-node-editor-multiply-right"></select>' +
                        '<small class="form-text text-muted">Choose a node, or leave blank and enter a number below.</small>' +
                        '<input type="number" step="any" class="form-control commandroom-node-editor-multiply-right-number" value="0">' +
                    '</div>' +
                '</div>' +
                '<div class="commandroom-node-editor-divide-panel" hidden>' +
                    '<div class="form-group">' +
                        '<label>Numerator / top value</label>' +
                        '<select class="form-control commandroom-node-editor-divide-numerator"></select>' +
                        '<small class="form-text text-muted">Choose a node, or leave blank and enter a number below.</small>' +
                        '<input type="number" step="any" class="form-control commandroom-node-editor-divide-numerator-number" value="0">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>Denominator / bottom value</label>' +
                        '<select class="form-control commandroom-node-editor-divide-denominator"></select>' +
                        '<small class="form-text text-muted">Choose a node, or leave blank and enter a number below.</small>' +
                        '<input type="number" step="any" class="form-control commandroom-node-editor-divide-denominator-number" value="1">' +
                    '</div>' +
                '</div>' +
                '<div class="commandroom-node-editor-percentage-panel" hidden>' +
                    '<div class="form-group">' +
                        '<label>' + getString('buildervalue') + '</label>' +
                        '<select class="form-control commandroom-node-editor-percentage-value"></select>' +
                        '<small class="form-text text-muted">Choose a node, or leave blank and enter a number below.</small>' +
                        '<input type="number" step="any" class="form-control commandroom-node-editor-percentage-value-number" value="0">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>Percentage</label>' +
                        '<select class="form-control commandroom-node-editor-percentage-percent"></select>' +
                        '<small class="form-text text-muted">Choose a rate node, or leave blank and enter a percentage such as 5.</small>' +
                        '<input type="number" step="any" class="form-control commandroom-node-editor-percentage-percent-number" value="5">' +
                    '</div>' +
                '</div>' +
                '<div class="commandroom-node-editor-linear-panel" hidden>' +
                    '<div class="form-group">' +
                        '<label>' + getString('builderinputnode') + '</label>' +
                        '<select class="form-control commandroom-node-editor-linear-input"></select>' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>' + getString('builderslope') + '</label>' +
                        '<input type="number" step="any" class="form-control commandroom-node-editor-linear-slope" value="1">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>' + getString('builderintercept') + '</label>' +
                        '<input type="number" step="any" class="form-control commandroom-node-editor-linear-intercept" value="0">' +
                    '</div>' +
                '</div>' +
                '<div class="commandroom-node-editor-diminishing-panel" hidden>' +
                    '<div class="form-group">' +
                        '<label>' + getString('builderinputnode') + '</label>' +
                        '<select class="form-control commandroom-node-editor-diminishing-input"></select>' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>' + getString('buildermaximumoutput') + '</label>' +
                        '<input type="number" step="any" class="form-control commandroom-node-editor-diminishing-maximum" value="100">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>' + getString('builderrisespeed') + '</label>' +
                        '<input type="number" step="any" class="form-control commandroom-node-editor-diminishing-rate" value="0.1">' +
                        '<small class="form-text text-muted">' + getString('builderrisespeedhelp') + '</small>' +
                    '</div>' +
                '</div>' +
                '<div class="commandroom-node-editor-optimum-panel" hidden>' +
                    '<div class="form-group">' +
                        '<label>' + getString('builderinputnode') + '</label>' +
                        '<select class="form-control commandroom-node-editor-optimum-input"></select>' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>' + getString('builderbestinput') + '</label>' +
                        '<input type="number" step="any" class="form-control commandroom-node-editor-optimum-optimum" value="10">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>' + getString('builderoptimumoutput') + '</label>' +
                        '<input type="number" step="any" class="form-control commandroom-node-editor-optimum-maximum" value="100">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>' + getString('builderdropoff') + '</label>' +
                        '<input type="number" step="any" class="form-control commandroom-node-editor-optimum-decline" value="1">' +
                        '<small class="form-text text-muted">' + getString('builderdropoffhelp') + '</small>' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>' + getString('builderfloor') + '</label>' +
                        '<input type="number" step="any" class="form-control commandroom-node-editor-optimum-floor" value="0">' +
                    '</div>' +
                '</div>' +
                '<div class="commandroom-node-editor-bell-panel" hidden>' +
                    '<div class="form-group">' +
                        '<label>' + getString('builderinputnode') + '</label>' +
                        '<select class="form-control commandroom-node-editor-bell-input"></select>' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>' + getString('buildercentre') + '</label>' +
                        '<input type="number" step="any" class="form-control commandroom-node-editor-bell-centre" value="10">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>' + getString('builderpeakoutput') + '</label>' +
                        '<input type="number" step="any" class="form-control commandroom-node-editor-bell-maximum" value="100">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>' + getString('builderspread') + '</label>' +
                        '<input type="number" step="any" class="form-control commandroom-node-editor-bell-spread" value="3">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>' + getString('builderfloor') + '</label>' +
                        '<input type="number" step="any" class="form-control commandroom-node-editor-bell-floor" value="0">' +
                    '</div>' +
                '</div>' +
                '<div class="commandroom-node-editor-sum-panel" hidden>' +
                    '<div class="form-group">' +
                        '<label>' + getString('builderaddnodes') + '</label>' +
                        '<select multiple class="form-control commandroom-node-editor-sum-add"></select>' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>' + getString('buildersubtractnodes') + '</label>' +
                        '<select multiple class="form-control commandroom-node-editor-sum-subtract"></select>' +
                    '</div>' +
                '</div>' +
                '<div class="commandroom-node-editor-add-panel" hidden>' +
                    '<div class="form-group">' +
                        '<label>' + getString('builderaddnodes') + '</label>' +
                        '<select multiple class="form-control commandroom-node-editor-add-items"></select>' +
                    '</div>' +
                '</div>' +
                '<div class="commandroom-node-editor-random-panel" hidden>' +
                    '<div class="form-group">' +
                        '<label>' + getString('builderminrandom') + '</label>' +
                        '<input type="number" step="any" class="form-control commandroom-node-editor-random-min" value="0">' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>' + getString('buildermaxrandom') + '</label>' +
                        '<input type="number" step="any" class="form-control commandroom-node-editor-random-max" value="100">' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="alert alert-info commandroom-node-editor-preview">' + getString('builderchoosedetermination') + '</div>' +
            '<div class="form-group">' +
                '<label>' + getString('builderinitialvalue') + '</label>' +
                '<input type="number" step="any" class="form-control commandroom-node-editor-initialvalue">' +
            '</div>' +
            '<div class="form-group">' +
                '<label>' + getString('builderminvalue') + '</label>' +
                '<input type="number" step="any" class="form-control commandroom-node-editor-minvalue">' +
            '</div>' +
            '<div class="form-group">' +
                '<label>' + getString('buildermaxvalue') + '</label>' +
                '<input type="number" step="any" class="form-control commandroom-node-editor-maxvalue">' +
            '</div>' +
            '<div class="form-check">' +
                '<input type="checkbox" class="form-check-input commandroom-node-editor-studentcontrolled" id="commandroom-node-editor-studentcontrolled">' +
                '<label class="form-check-label" for="commandroom-node-editor-studentcontrolled">' + getString('builderstudentsmayedit') + '</label>' +
            '</div>' +
            '<div class="form-check">' +
                '<input type="checkbox" class="form-check-input commandroom-node-editor-visible" id="commandroom-node-editor-visible">' +
                '<label class="form-check-label" for="commandroom-node-editor-visible">' + getString('buildervisibletostudents') + '</label>' +
            '</div>' +
            '<div class="form-group">' +
                '<label>' + getString('builderdescription') + '</label>' +
                '<textarea class="form-control commandroom-node-editor-description" rows="3"></textarea>' +
            '</div>' +
            '<div class="form-group">' +
                '<label>' + getString('builderinterpretation') + '</label>' +
                '<textarea class="form-control commandroom-node-editor-interpretation" rows="3"></textarea>' +
            '</div>' +
            '<div class="commandroom-node-editor-actions">' +
                '<button type="button" class="btn btn-primary commandroom-node-editor-save">' + getString('builderapplytojsondraft') + '</button> ' +
                '<button type="button" class="btn btn-outline-secondary commandroom-node-editor-cancel">' + getString('buildercancel') + '</button>' +
            '</div>' +
            '<div class="commandroom-node-editor-status" aria-live="polite"></div>');

        var relationshipPanel = document.querySelector('.commandroom-relationship-matrix-panel');
        if (relationshipPanel && relationshipPanel.parentNode) {
            relationshipPanel.parentNode.insertBefore(editor, relationshipPanel);
        } else {
            document.body.appendChild(editor);
        }

        editor.querySelector('.commandroom-node-editor-close').addEventListener('click', closeNodeEditor);
        editor.querySelector('.commandroom-node-editor-cancel').addEventListener('click', closeNodeEditor);
        editor.querySelector('.commandroom-node-editor-save').addEventListener('click', saveNodeEditor);
        editor.querySelector('.commandroom-node-editor-updatemode').addEventListener('change', function() {
            setCalculationPanels(editor);
        });
        editor.querySelector('.commandroom-node-editor-calculation-type').addEventListener('change', function() {
            setFormulaTypePanels(editor);
        });
        editor.addEventListener('input', function(event) {
            if (event.target.closest('.commandroom-node-editor')) {
                updateNodeEditorPreview(editor);
            }
        });
        editor.addEventListener('change', function(event) {
            if (event.target.closest('.commandroom-node-editor')) {
                updateNodeEditorPreview(editor);
            }
        });

        registerFriendlyMultiSelects(editor);

        return editor;
    }

    function closeNodeEditor() {
        var editor = document.querySelector('.commandroom-node-editor');
        if (!editor) {
            return;
        }

        editor.setAttribute('hidden', 'hidden');
        editor.removeAttribute('data-nodeid');
    }

    function getSelectedOptions(select) {
        return Array.prototype.slice.call(select.selectedOptions || []).map(function(option) {
            return option.value;
        }).filter(function(value) {
            return value !== '';
        });
    }

    function openNodeEditor(nodeid) {
        var data = getJsonData();
        var index = findNodeIndexByNodeid(nodeid, data);

        if (index < 0) {
            return;
        }

        var node = data.nodes[index];
        var editor = ensureNodeEditor();
        var availableNodes = getJsonNodeRefs(data, index);
        var updateconfig = node.updateconfig || {};
        var calculation = node.calculation || {};

        editor.setAttribute('data-nodeid', nodeid);

        editor.querySelector('.commandroom-node-editor-summary').textContent = node.name || node.ref || '';
        editor.querySelector('.commandroom-node-editor-name').value = node.name || '';
        editor.querySelector('.commandroom-node-editor-nodetype').value = node.nodetype || 'stock';
        editor.querySelector('.commandroom-node-editor-initialvalue').value = Number(node.initialvalue || 0);
        editor.querySelector('.commandroom-node-editor-minvalue').value =
            node.minvalue === null || typeof node.minvalue === 'undefined' ? '' : Number(node.minvalue);
        editor.querySelector('.commandroom-node-editor-maxvalue').value =
            node.maxvalue === null || typeof node.maxvalue === 'undefined' ? '' : Number(node.maxvalue);
        editor.querySelector('.commandroom-node-editor-studentcontrolled').checked = !!node.studentcontrolled;
        editor.querySelector('.commandroom-node-editor-visible').checked =
            typeof node.visibletostudents === 'undefined' ? true : !!node.visibletostudents;
        editor.querySelector('.commandroom-node-editor-description').value = node.description || '';
        editor.querySelector('.commandroom-node-editor-interpretation').value = node.interpretation || '';

        editor.querySelector('.commandroom-node-editor-updatemode').value = inferUpdateMode(node);
        editor.querySelector('.commandroom-node-editor-stock-base').value = updateconfig.base || 'self';

        var inflowRefs = getArrayAlias(updateconfig, ['inflows', 'adds']);
        var outflowRefs = getArrayAlias(updateconfig, ['outflows', 'subtracts']);

        // Safety net for teacher-created JSON: if a negative incoming relationship exists
        // but the stock outflow list is empty, use that relationship as the starter outflow.
        // This keeps CLD arrows and stock-flow configuration aligned instead of silently
        // ignoring a clearly negative flow such as Expenses -> Balance.
        if (inflowRefs.length === 0) {
            inflowRefs = getIncomingEdgeRefsByPolarity(data, node.ref || '', 'positive');
        }
        if (outflowRefs.length === 0) {
            outflowRefs = getIncomingEdgeRefsByPolarity(data, node.ref || '', 'negative');
        }

        setSelectOptionsFromHtml(editor.querySelector('.commandroom-node-editor-inflows'),
            buildNodeOptions(availableNodes, inflowRefs));
        setSelectOptionsFromHtml(editor.querySelector('.commandroom-node-editor-outflows'),
            buildNodeOptions(availableNodes, outflowRefs));

        var rateOptions = '<option value="">' + getString('buildernone') + '</option>' + buildNodeOptions(availableNodes, updateconfig.rate ? [updateconfig.rate] : []);
        setSelectOptionsFromHtml(editor.querySelector('.commandroom-node-editor-rate'), rateOptions);
        editor.querySelector('.commandroom-node-editor-rate').value = updateconfig.rate || '';
        editor.querySelector('.commandroom-node-editor-calculation-type').value = calculation.type || '';

        var blankNodeOptions = '<option value="">' + getString('buildernumberbelow') + '</option>' + buildNodeOptions(availableNodes, []);
        setSelectOptionsFromHtml(editor.querySelector('.commandroom-node-editor-multiply-left'), blankNodeOptions);
        setSelectOptionsFromHtml(editor.querySelector('.commandroom-node-editor-multiply-right'), blankNodeOptions);
        setSelectOptionsFromHtml(editor.querySelector('.commandroom-node-editor-divide-numerator'), blankNodeOptions);
        setSelectOptionsFromHtml(editor.querySelector('.commandroom-node-editor-divide-denominator'), blankNodeOptions);
        setSelectOptionsFromHtml(editor.querySelector('.commandroom-node-editor-percentage-value'), blankNodeOptions);
        setSelectOptionsFromHtml(editor.querySelector('.commandroom-node-editor-percentage-percent'), blankNodeOptions);
        setSelectOptionsFromHtml(editor.querySelector('.commandroom-node-editor-linear-input'), buildNodeOptions(availableNodes, []));
        setSelectOptionsFromHtml(editor.querySelector('.commandroom-node-editor-diminishing-input'), buildNodeOptions(availableNodes, []));
        setSelectOptionsFromHtml(editor.querySelector('.commandroom-node-editor-optimum-input'), buildNodeOptions(availableNodes, []));
        setSelectOptionsFromHtml(editor.querySelector('.commandroom-node-editor-bell-input'), buildNodeOptions(availableNodes, []));
        setSelectOptionsFromHtml(editor.querySelector('.commandroom-node-editor-add-items'), buildNodeOptions(availableNodes, calculation.items || []));
        setSelectOptionsFromHtml(editor.querySelector('.commandroom-node-editor-sum-add'), buildNodeOptions(availableNodes, []));
        setSelectOptionsFromHtml(editor.querySelector('.commandroom-node-editor-sum-subtract'), buildNodeOptions(availableNodes, []));

        if (calculation.type === 'multiply') {
            editor.querySelector('.commandroom-node-editor-multiply-left').value = getOperandRef(calculation.left);
            editor.querySelector('.commandroom-node-editor-multiply-right').value = getOperandRef(calculation.right);
            editor.querySelector('.commandroom-node-editor-multiply-left-number').value = getOperandNumber(calculation.left) || 0;
            editor.querySelector('.commandroom-node-editor-multiply-right-number').value = getOperandNumber(calculation.right) || 0;
        }

        if (calculation.type === 'divide') {
            editor.querySelector('.commandroom-node-editor-divide-numerator').value = getOperandRef(calculation.numerator);
            editor.querySelector('.commandroom-node-editor-divide-denominator').value = getOperandRef(calculation.denominator);
            editor.querySelector('.commandroom-node-editor-divide-numerator-number').value = getOperandNumber(calculation.numerator) || 0;
            editor.querySelector('.commandroom-node-editor-divide-denominator-number').value = getOperandNumber(calculation.denominator) || 1;
        }

        if (calculation.type === 'percentage') {
            editor.querySelector('.commandroom-node-editor-percentage-value').value = getOperandRef(calculation.value);
            editor.querySelector('.commandroom-node-editor-percentage-percent').value = getOperandRef(calculation.percent);
            editor.querySelector('.commandroom-node-editor-percentage-value-number').value = getOperandNumber(calculation.value) || 0;
            editor.querySelector('.commandroom-node-editor-percentage-percent-number').value = getOperandNumber(calculation.percent) || 5;
        }

        if (calculation.type === 'linear') {
            editor.querySelector('.commandroom-node-editor-linear-input').value = getOperandRef(calculation.input);
            editor.querySelector('.commandroom-node-editor-linear-slope').value = typeof calculation.slope === 'undefined' ? 1 : Number(calculation.slope);
            editor.querySelector('.commandroom-node-editor-linear-intercept').value = typeof calculation.intercept === 'undefined' ? 0 : Number(calculation.intercept);
        }

        if (calculation.type === 'diminishing_returns') {
            editor.querySelector('.commandroom-node-editor-diminishing-input').value = getOperandRef(calculation.input);
            editor.querySelector('.commandroom-node-editor-diminishing-maximum').value = typeof calculation.maximum === 'undefined' ? 100 : Number(calculation.maximum);
            editor.querySelector('.commandroom-node-editor-diminishing-rate').value = typeof calculation.rate === 'undefined' ? 0.1 : Number(calculation.rate);
        }

        if (calculation.type === 'optimum_point') {
            editor.querySelector('.commandroom-node-editor-optimum-input').value = getOperandRef(calculation.input);
            editor.querySelector('.commandroom-node-editor-optimum-optimum').value = typeof calculation.optimum === 'undefined' ? 10 : Number(calculation.optimum);
            editor.querySelector('.commandroom-node-editor-optimum-maximum').value = typeof calculation.maximum === 'undefined' ? 100 : Number(calculation.maximum);
            editor.querySelector('.commandroom-node-editor-optimum-decline').value = typeof calculation.decline === 'undefined' ? 1 : Number(calculation.decline);
            editor.querySelector('.commandroom-node-editor-optimum-floor').value = typeof calculation.floor === 'undefined' ? 0 : Number(calculation.floor);
        }

        if (calculation.type === 'bell_curve') {
            editor.querySelector('.commandroom-node-editor-bell-input').value = getOperandRef(calculation.input);
            editor.querySelector('.commandroom-node-editor-bell-centre').value = typeof calculation.centre === 'undefined' ? 10 : Number(calculation.centre);
            editor.querySelector('.commandroom-node-editor-bell-maximum').value = typeof calculation.maximum === 'undefined' ? 100 : Number(calculation.maximum);
            editor.querySelector('.commandroom-node-editor-bell-spread').value = typeof calculation.spread === 'undefined' ? 3 : Number(calculation.spread);
            editor.querySelector('.commandroom-node-editor-bell-floor').value = typeof calculation.floor === 'undefined' ? 0 : Number(calculation.floor);
        }

        if (calculation.type === 'sum' && calculation.items && Array.isArray(calculation.items)) {
            var plusrefs = [];
            var minusrefs = [];
            calculation.items.forEach(function(item) {
                if (!item || !item.operand) {
                    return;
                }
                var ref = getOperandRef(item.operand);
                if (ref === '') {
                    return;
                }
                if (Number(item.factor) < 0) {
                    minusrefs.push(ref);
                } else {
                    plusrefs.push(ref);
                }
            });
            setSelectOptionsFromHtml(editor.querySelector('.commandroom-node-editor-sum-add'), buildNodeOptions(availableNodes, plusrefs));
            setSelectOptionsFromHtml(editor.querySelector('.commandroom-node-editor-sum-subtract'), buildNodeOptions(availableNodes, minusrefs));
        }

        if (calculation.type === 'add' && calculation.items && Array.isArray(calculation.items)) {
            var addrefs = calculation.items.map(function(item) {
                return getOperandRef(item);
            }).filter(function(ref) {
                return ref !== '';
            });
            setSelectOptionsFromHtml(editor.querySelector('.commandroom-node-editor-add-items'), buildNodeOptions(availableNodes, addrefs));
        }

        if (calculation.type === 'random_range') {
            editor.querySelector('.commandroom-node-editor-random-min').value = typeof calculation.min === 'undefined' ? 0 : Number(calculation.min);
            editor.querySelector('.commandroom-node-editor-random-max').value = typeof calculation.max === 'undefined' ? 100 : Number(calculation.max);
        }

        editor.querySelector('.commandroom-node-editor-status').textContent = '';
        setCalculationPanels(editor);

        editor.removeAttribute('hidden');
        editor.scrollIntoView({behavior: 'smooth', block: 'nearest'});
    }

    function saveNodeEditor() {
        var editor = document.querySelector('.commandroom-node-editor');
        if (!editor) {
            return;
        }

        var nodeid = editor.getAttribute('data-nodeid') || '';
        var data = getJsonData();
        var index = findNodeIndexByNodeid(nodeid, data);

        if (index < 0) {
            return;
        }

        var node = data.nodes[index];
        var initialvalue = Number(editor.querySelector('.commandroom-node-editor-initialvalue').value);
        var minvalueRaw = editor.querySelector('.commandroom-node-editor-minvalue').value;
        var maxvalueRaw = editor.querySelector('.commandroom-node-editor-maxvalue').value;
        var updateMode = editor.querySelector('.commandroom-node-editor-updatemode').value;

        node.name = editor.querySelector('.commandroom-node-editor-name').value.trim() || node.name || getString('builderuntitlednode');
        node.nodetype = editor.querySelector('.commandroom-node-editor-nodetype').value || 'stock';
        node.initialvalue = Number.isFinite(initialvalue) ? initialvalue : 0;
        node.minvalue = minvalueRaw === '' ? null : Number(minvalueRaw);
        node.maxvalue = maxvalueRaw === '' ? null : Number(maxvalueRaw);
        node.studentcontrolled = editor.querySelector('.commandroom-node-editor-studentcontrolled').checked;
        node.visibletostudents = editor.querySelector('.commandroom-node-editor-visible').checked;
        node.description = editor.querySelector('.commandroom-node-editor-description').value.trim();
        node.interpretation = editor.querySelector('.commandroom-node-editor-interpretation').value.trim();

        delete node.updateconfig;
        delete node.calculation;

        if (updateMode === 'student') {
            node.studentcontrolled = true;
        } else if (updateMode === 'incoming') {
            node.studentcontrolled = false;
            node.nodetype = 'computed';
        } else if (updateMode === 'stock_with_rate') {
            node.studentcontrolled = false;
            node.nodetype = 'stock';
            var selectedInflows = getSelectedOptions(editor.querySelector('.commandroom-node-editor-inflows'));
            var selectedOutflows = getSelectedOptions(editor.querySelector('.commandroom-node-editor-outflows'));

            node.updateconfig = {
                mode: 'stock_with_rate',
                base: editor.querySelector('.commandroom-node-editor-stock-base').value || 'self',
                inflows: selectedInflows,
                outflows: selectedOutflows,
                adds: selectedInflows,
                subtracts: selectedOutflows
            };

            var rate = editor.querySelector('.commandroom-node-editor-rate').value || '';
            if (rate !== '') {
                node.updateconfig.rate = rate;
            }
        } else if (updateMode === 'formula') {
            node.studentcontrolled = false;
            node.nodetype = 'computed';

            var calculationType = editor.querySelector('.commandroom-node-editor-calculation-type').value || '';
            if (calculationType === 'multiply') {
                node.calculation = {
                    type: 'multiply',
                    left: readNodeOrNumberOperand(
                        editor.querySelector('.commandroom-node-editor-multiply-left'),
                        editor.querySelector('.commandroom-node-editor-multiply-left-number')
                    ),
                    right: readNodeOrNumberOperand(
                        editor.querySelector('.commandroom-node-editor-multiply-right'),
                        editor.querySelector('.commandroom-node-editor-multiply-right-number')
                    )
                };
            } else if (calculationType === 'divide') {
                node.calculation = {
                    type: 'divide',
                    numerator: readNodeOrNumberOperand(
                        editor.querySelector('.commandroom-node-editor-divide-numerator'),
                        editor.querySelector('.commandroom-node-editor-divide-numerator-number')
                    ),
                    denominator: readNodeOrNumberOperand(
                        editor.querySelector('.commandroom-node-editor-divide-denominator'),
                        editor.querySelector('.commandroom-node-editor-divide-denominator-number')
                    )
                };
            } else if (calculationType === 'percentage') {
                node.calculation = {
                    type: 'percentage',
                    value: readNodeOrNumberOperand(
                        editor.querySelector('.commandroom-node-editor-percentage-value'),
                        editor.querySelector('.commandroom-node-editor-percentage-value-number')
                    ),
                    percent: readNodeOrNumberOperand(
                        editor.querySelector('.commandroom-node-editor-percentage-percent'),
                        editor.querySelector('.commandroom-node-editor-percentage-percent-number')
                    )
                };
            } else if (calculationType === 'linear') {
                node.calculation = {
                    type: 'linear',
                    input: makeNodeOperand(editor.querySelector('.commandroom-node-editor-linear-input').value || ''),
                    slope: Number(editor.querySelector('.commandroom-node-editor-linear-slope').value) || 0,
                    intercept: Number(editor.querySelector('.commandroom-node-editor-linear-intercept').value) || 0
                };
            } else if (calculationType === 'diminishing_returns') {
                node.calculation = {
                    type: 'diminishing_returns',
                    input: makeNodeOperand(editor.querySelector('.commandroom-node-editor-diminishing-input').value || ''),
                    maximum: Number(editor.querySelector('.commandroom-node-editor-diminishing-maximum').value) || 0,
                    rate: Number(editor.querySelector('.commandroom-node-editor-diminishing-rate').value) || 0.1
                };
            } else if (calculationType === 'optimum_point') {
                node.calculation = {
                    type: 'optimum_point',
                    input: makeNodeOperand(editor.querySelector('.commandroom-node-editor-optimum-input').value || ''),
                    optimum: Number(editor.querySelector('.commandroom-node-editor-optimum-optimum').value) || 0,
                    maximum: Number(editor.querySelector('.commandroom-node-editor-optimum-maximum').value) || 0,
                    decline: Number(editor.querySelector('.commandroom-node-editor-optimum-decline').value) || 0,
                    floor: Number(editor.querySelector('.commandroom-node-editor-optimum-floor').value) || 0
                };
            } else if (calculationType === 'bell_curve') {
                node.calculation = {
                    type: 'bell_curve',
                    input: makeNodeOperand(editor.querySelector('.commandroom-node-editor-bell-input').value || ''),
                    centre: Number(editor.querySelector('.commandroom-node-editor-bell-centre').value) || 0,
                    maximum: Number(editor.querySelector('.commandroom-node-editor-bell-maximum').value) || 0,
                    spread: Number(editor.querySelector('.commandroom-node-editor-bell-spread').value) || 1,
                    floor: Number(editor.querySelector('.commandroom-node-editor-bell-floor').value) || 0
                };
            } else if (calculationType === 'sum') {
                var sumitems = [];
                getSelectedOptions(editor.querySelector('.commandroom-node-editor-sum-add')).forEach(function(ref) {
                    sumitems.push({operand: makeNodeOperand(ref), factor: 1});
                });
                getSelectedOptions(editor.querySelector('.commandroom-node-editor-sum-subtract')).forEach(function(ref) {
                    sumitems.push({operand: makeNodeOperand(ref), factor: -1});
                });
                node.calculation = {
                    type: 'sum',
                    items: sumitems
                };
            } else if (calculationType === 'add') {
                node.calculation = {
                    type: 'add',
                    items: getSelectedOptions(editor.querySelector('.commandroom-node-editor-add-items')).map(makeNodeOperand)
                };
            } else if (calculationType === 'random_range') {
                var randomMin = Number(editor.querySelector('.commandroom-node-editor-random-min').value);
                var randomMax = Number(editor.querySelector('.commandroom-node-editor-random-max').value);
                node.calculation = {
                    type: 'random_range',
                    min: Number.isFinite(randomMin) ? randomMin : 0,
                    max: Number.isFinite(randomMax) ? randomMax : 0
                };
            }
        }

        saveJsonData(data);
        updateNodeEditorPreview(editor);

        var card = document.querySelector('.commandroom-visual-card[data-nodeid="' + nodeid + '"]');
        if (card) {
            var title = card.querySelector('.commandroom-visual-node-title');
            if (title) {
                title.textContent = node.name;
            }
            var current = card.querySelector('.commandroom-visual-current-value');
            if (current) {
                current.textContent = getString('buildercurrentvalue', node.initialvalue.toFixed(2));
            }
        }

        editor.querySelector('.commandroom-node-editor-status').textContent = getString('buildernodeupdated');
        scheduleBuilderArrowRedraw();
    }

    function registerNodeEditorControls() {
        document.querySelectorAll('.commandroom-visual-system .commandroom-visual-card').forEach(function(card) {
            if (card.querySelector('.commandroom-node-edit')) {
                return;
            }

            var nodeid = card.getAttribute('data-nodeid') || '';
            if (nodeid === '') {
                return;
            }

            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-sm btn-outline-secondary commandroom-node-edit';
            button.textContent = getString('buildereditnode');
            button.addEventListener('click', function(event) {
                event.preventDefault();
                event.stopPropagation();
                openNodeEditor(nodeid);
            });

            card.appendChild(button);
        });
    }

    function init(cmid) {
        if (!cmid) {
            return;
        }

        loadLanguageStrings().then(function() {
            markBuilderCanvas();
            registerJsonControls();
            registerDragHandlers();
            registerRelationshipMatrixHandlers();
            registerNodeEditorControls();
            registerBuilderSaveControls();
            registerBuilderCanvasEvents();
        }).catch(function(error) {
            window.console.warn(getString('buildercouldnotloadstrings'), error);
            markBuilderCanvas();
            registerJsonControls();
            registerDragHandlers();
            registerRelationshipMatrixHandlers();
            registerNodeEditorControls();
            registerBuilderSaveControls();
            registerBuilderCanvasEvents();
        });
    }

    return {
        init: init
    };
});
