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
 * Runtime UI for mod_commandroom.
 *
 * @module     mod_commandroom/main
 * @package    mod_commandroom
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    var lastProposalHash = '';
    var lastDecisionHash = '';
    var lastResultsHash = '';
    var currentDecisions = {};
    var pollingActive = false;
    var pollDelayMs = 3000;
    var pollTimer = null;
    var initialResultsLoaded = false;
    var suppressReload = false;
    var batchRunning = false;
    var countdownTimer = null;
    var pulseTimer = null;
    var pulseVisible = true;
    var selectedLoopGroup = '';

    function updateStatus(message) {
        var container = document.querySelector('.commandroom-warroom-message');
        if (container) {
            container.textContent = message;
        }
    }

    function handlePollingUnavailable(error) {
        window.console.warn('CommandRoom background polling temporarily unavailable.', error);
        updateStatus('Background updates are temporarily unavailable. The page will keep trying automatically.');
    }

    function clearBatchIndicators() {
        if (countdownTimer) {
            clearInterval(countdownTimer);
            countdownTimer = null;
        }

        if (pulseTimer) {
            clearInterval(pulseTimer);
            pulseTimer = null;
        }

        pulseVisible = true;
    }

    function updateBatchStatus(message, isError) {
        var container = document.querySelector('.commandroom-batch-run-status');
        if (!container) {
            return;
        }

        container.textContent = message || '';
        container.classList.remove('commandroom-proposal-status-error');
        container.classList.remove('commandroom-proposal-status-success');

        if (message) {
            container.classList.add(isError ? 'commandroom-proposal-status-error' : 'commandroom-proposal-status-success');
        }

        container.style.opacity = '1';
    }

    function startRunningPulse(baseMessage) {
        var container = document.querySelector('.commandroom-batch-run-status');
        if (!container) {
            return;
        }

        if (pulseTimer) {
            clearInterval(pulseTimer);
        }

        pulseVisible = true;
        pulseTimer = setInterval(function() {
            pulseVisible = !pulseVisible;
            container.style.opacity = pulseVisible ? '1' : '0.45';

            if (baseMessage && !countdownTimer) {
                container.textContent = baseMessage;
            }
        }, 500);
    }

    function startStepCountdown(stepCurrent, stepTotal, secondsUntilNext) {
        var remaining = secondsUntilNext;
        var base = 'Running simulation. Iteration ' + stepCurrent + ' of ' + stepTotal;

        updateBatchStatus(base + ' — next step in ' + remaining + '...', false);
        startRunningPulse(base);

        if (countdownTimer) {
            clearInterval(countdownTimer);
        }

        countdownTimer = setInterval(function() {
            remaining -= 1;

            if (remaining <= 0) {
                clearInterval(countdownTimer);
                countdownTimer = null;
                updateBatchStatus(base + ' — launching next step...', false);
                return;
            }

            updateBatchStatus(base + ' — next step in ' + remaining + '...', false);
        }, 1000);
    }

    function setBatchControlsDisabled(disabled) {
        [
            '.commandroom-save-proposal',
            '.commandroom-save-decision',
            '.commandroom-proposed-value',
            '.commandroom-proposal-rationale',
            '.commandroom-batch-iterations',
            '.commandroom-run-simulation'
        ].forEach(function(selector) {
            document.querySelectorAll(selector).forEach(function(element) {
                element.disabled = disabled;
            });
        });
    }

    function updateProposalStatus(nodeid, message, isError) {
        var container = document.querySelector('.commandroom-proposal-status[data-nodeid="' + nodeid + '"]');
        if (!container) {
            return;
        }

        container.textContent = message;
        container.classList.remove('commandroom-proposal-status-error');
        container.classList.remove('commandroom-proposal-status-success');
        container.classList.add(isError ? 'commandroom-proposal-status-error' : 'commandroom-proposal-status-success');
    }

    function updateDecisionStatus(nodeid, message, isError) {
        var container = document.querySelector('.commandroom-decision-status[data-nodeid="' + nodeid + '"]');
        if (!container) {
            return;
        }

        container.textContent = message;
        container.classList.remove('commandroom-decision-status-error');
        container.classList.remove('commandroom-decision-status-success');
        container.classList.add(isError ? 'commandroom-decision-status-error' : 'commandroom-decision-status-success');
    }

    function clearElement(element) {
        while (element.firstChild) {
            element.removeChild(element.firstChild);
        }
    }

    function appendStrongText(container, label, text) {
        clearElement(container);

        var strong = document.createElement('strong');
        strong.textContent = label;

        container.appendChild(strong);
        container.appendChild(document.createTextNode(text));
    }

    function updateCurrentDecision(nodeid, decisiontype, selectedvalue) {
        var container = document.querySelector('.commandroom-current-decision[data-nodeid="' + nodeid + '"]');
        if (!container) {
            return;
        }

        appendStrongText(
            container,
            'Current decision: ',
            decisiontype + ' (' + Number(selectedvalue).toFixed(2) + ')'
        );
    }

    function stableIconPosition(nodeid, index, iconCount) {
        var seed = (Number(nodeid) || 0) * 1103515245 + index * 12345 + iconCount * 2654435761;
        seed = Math.abs(seed);

        var left = 6 + (seed % 82);
        var top = 6 + (Math.floor(seed / 97) % 82);

        return {
            left: left,
            top: top
        };
    }

    function updateVisualNodeCard(nodeid, nodevalue) {
        var card = document.querySelector('.commandroom-visual-card[data-nodeid="' + nodeid + '"]');
        if (!card) {
            return;
        }

        var numericValue = Number(nodevalue);
        if (!Number.isFinite(numericValue)) {
            return;
        }

        var valueContainer = card.querySelector('.commandroom-visual-node-value');
        if (valueContainer) {
            appendStrongText(valueContainer, 'Current value: ', numericValue.toFixed(2));
        }

        var iconArea = card.querySelector('.commandroom-visual-icons');
        if (!iconArea) {
            return;
        }

        var iconUrl = card.getAttribute('data-iconurl') || '';
        var visualType = card.getAttribute('data-visualtype') || 'repeated_icon';

        if (visualType === 'scaling_icon') {
            var minValue = Number(card.getAttribute('data-minvalue'));
            var maxValue = Number(card.getAttribute('data-maxvalue'));
            var minSize = Number(card.getAttribute('data-minsize'));
            var maxSize = Number(card.getAttribute('data-maxsize'));

            if (!Number.isFinite(minValue)) {
                minValue = 0;
            }
            if (!Number.isFinite(maxValue) || maxValue <= minValue) {
                maxValue = Math.max(numericValue, minValue + 1);
            }
            if (!Number.isFinite(minSize) || minSize <= 0) {
                minSize = 60;
            }
            if (!Number.isFinite(maxSize) || maxSize < minSize) {
                maxSize = 220;
            }

            var scalePercentage = ((numericValue - minValue) / (maxValue - minValue));
            scalePercentage = Math.max(0, Math.min(1, scalePercentage));
            var sidePx = minSize + ((maxSize - minSize) * scalePercentage);

            clearElement(iconArea);

            var scalingImage = document.createElement('img');
            scalingImage.src = iconUrl;
            scalingImage.alt = '';
            scalingImage.className = 'commandroom-visual-scaling-image';
            scalingImage.style.width = sidePx.toFixed(2) + 'px';
            scalingImage.style.height = sidePx.toFixed(2) + 'px';
            iconArea.appendChild(scalingImage);
        } else {
            var unitValue = Number(card.getAttribute('data-unitvalue'));
            var maxIcons = Number(card.getAttribute('data-maxicons'));
            var iconSize = Number(card.getAttribute('data-iconsize'));

            if (!Number.isFinite(unitValue) || unitValue <= 0) {
                unitValue = 10;
            }
            if (!Number.isFinite(maxIcons) || maxIcons < 1) {
                maxIcons = 80;
            }
            if (!Number.isFinite(iconSize) || iconSize < 8) {
                iconSize = 36;
            }

            var iconCount = Math.round(Math.max(0, numericValue) / unitValue);
            iconCount = Math.max(1, Math.min(maxIcons, iconCount));

            clearElement(iconArea);

            for (var i = 0; i < iconCount; i++) {
                var position = stableIconPosition(nodeid, i, iconCount);
                var repeatedImage = document.createElement('img');
                repeatedImage.src = iconUrl;
                repeatedImage.alt = '';
                repeatedImage.className = 'commandroom-visual-repeated-image';
                repeatedImage.style.width = iconSize + 'px';
                repeatedImage.style.height = iconSize + 'px';
                repeatedImage.style.left = position.left + '%';
                repeatedImage.style.top = position.top + '%';
                iconArea.appendChild(repeatedImage);
            }
        }

        var fill = card.querySelector('.commandroom-visual-progress-fill');
        if (fill) {
            var maxValueForBar = Number(card.getAttribute('data-maxvalue'));
            var minValueForBar = Number(card.getAttribute('data-minvalue'));

            if (!Number.isFinite(minValueForBar)) {
                minValueForBar = 0;
            }

            if (Number.isFinite(maxValueForBar) && maxValueForBar > minValueForBar) {
                var percentage = ((numericValue - minValueForBar) / (maxValueForBar - minValueForBar)) * 100;
                percentage = Math.max(0, Math.min(100, percentage));
                fill.style.width = percentage.toFixed(2) + '%';
            }
        }
    }

    function updateResultRow(nodeid, nodevalue, valueorigin) {
        var row = document.querySelector('tr[data-nodeid="' + nodeid + '"]');
        if (!row) {
            return;
        }

        var currentvaluecell = row.querySelector('.commandroom-current-value');
        var valueorigincell = row.querySelector('.commandroom-value-origin');

        if (currentvaluecell) {
            currentvaluecell.textContent = Number(nodevalue).toFixed(2);
        }

        if (valueorigincell) {
            valueorigincell.textContent = valueorigin;
        }
        updateVisualNodeCard(nodeid, nodevalue);
    }

    function renderDecisions(decisions) {
        currentDecisions = {};

        var containers = document.querySelectorAll('.commandroom-current-decision');

        containers.forEach(function(container) {
            container.textContent = 'No leader decision saved yet.';
        });

        if (!decisions || !decisions.length) {
            return;
        }

        decisions.forEach(function(decision) {
            currentDecisions[decision.nodeid] = decision;
            updateCurrentDecision(decision.nodeid, decision.decisiontype, decision.selectedvalue);
        });
    }

    function renderResults(results) {
        if (!results || !results.length) {
            return;
        }

        results.forEach(function(result) {
            var nodeid = result.nodeid;

            if (currentDecisions[nodeid]) {
                updateResultRow(
                    nodeid,
                    currentDecisions[nodeid].selectedvalue,
                    'decision'
                );
            } else {
                updateResultRow(
                    nodeid,
                    result.nodevalue,
                    result.valueorigin
                );
            }
        });

        scheduleArrowRedraw();
    }

    function getProposalListContainer(nodeid) {
        return document.querySelector('.commandroom-proposal-list[data-nodeid="' + nodeid + '"]');
    }

    function computeHash(items) {
        return JSON.stringify(items);
    }

    function renderProposals(proposals) {
        var containers = document.querySelectorAll('.commandroom-proposal-list');

        containers.forEach(function(container) {
            clearElement(container);
        });

        if (!proposals || !proposals.length) {
            containers.forEach(function(container) {
                container.textContent = 'No proposals submitted yet.';
            });
            return;
        }

        proposals.forEach(function(proposal) {
            var container = getProposalListContainer(proposal.nodeid);
            if (!container) {
                return;
            }

            var item = document.createElement('div');
            item.className = 'commandroom-proposal-list-item';

            var name = document.createElement('div');
            name.className = 'commandroom-proposal-list-name';
            name.textContent = proposal.userfullname + ': ' + proposal.proposedvalue;

            var rationale = document.createElement('div');
            rationale.className = 'commandroom-proposal-list-rationale';
            rationale.textContent = proposal.rationale;

            item.appendChild(name);
            item.appendChild(rationale);
            container.appendChild(item);
        });
    }

    function loadProposals(cmid, forceRender) {
        return Ajax.call([{
            methodname: 'mod_commandroom_get_proposals',
            args: {
                cmid: cmid
            }
        }])[0].then(function(result) {
            var newHash = computeHash(result.proposals);

            if (forceRender || newHash !== lastProposalHash) {
                lastProposalHash = newHash;
                renderProposals(result.proposals);
                window.console.log('Proposals updated');
            }

            return null;
        }).catch(function(error) {
            handlePollingUnavailable(error);
            return null;
        });
    }

    function loadDecisions(cmid, forceRender) {
        return Ajax.call([{
            methodname: 'mod_commandroom_get_decisions',
            args: {
                cmid: cmid
            }
        }])[0].then(function(result) {
            var newHash = computeHash(result.decisions);

            if (forceRender || newHash !== lastDecisionHash) {
                lastDecisionHash = newHash;
                renderDecisions(result.decisions);
                window.console.log('Decisions updated');
            }

            return null;
        }).catch(function(error) {
            handlePollingUnavailable(error);
            return null;
        });
    }

    function loadResults(cmid, forceRender) {
        return Ajax.call([{
            methodname: 'mod_commandroom_get_results',
            args: {
                cmid: cmid
            }
        }])[0].then(function(result) {
            var payloadForHash = {
                runid: result.runid,
                iterationno: result.iterationno,
                results: result.results
            };
            var newHash = computeHash(payloadForHash);

            if (forceRender || !initialResultsLoaded) {
                renderResults(result.results);
                lastResultsHash = newHash;
                initialResultsLoaded = true;
                window.console.log('Results initialised');
                return null;
            }

            if (newHash !== lastResultsHash) {
                lastResultsHash = newHash;

                if (suppressReload) {
                    renderResults(result.results);
                    window.console.log('Results changed during batch run');
                    return null;
                }

                window.console.log('Results changed - reloading page');
                window.location.reload();
                return null;
            }

            renderResults(result.results);
            return null;
        }).catch(function(error) {
            handlePollingUnavailable(error);
            return null;
        });
    }

    function scheduleNextPoll(cmid) {
        if (pollTimer) {
            clearTimeout(pollTimer);
        }

        pollTimer = setTimeout(function() {
            pollOnce(cmid);
        }, pollDelayMs);
    }

    function pollOnce(cmid) {
        if (pollingActive) {
            return;
        }

        pollingActive = true;

        Promise.all([
            loadProposals(cmid, false),
            loadDecisions(cmid, false),
            loadResults(cmid, false)
        ]).then(function() {
            pollingActive = false;
            scheduleNextPoll(cmid);
        }).catch(function() {
            pollingActive = false;
            scheduleNextPoll(cmid);
        });
    }

    function startPolling(cmid) {
        scheduleNextPoll(cmid);
    }

    function refreshNow(cmid) {
        if (pollTimer) {
            clearTimeout(pollTimer);
        }

        Promise.all([
            loadProposals(cmid, true),
            loadDecisions(cmid, true),
            loadResults(cmid, true)
        ]).then(function() {
            pollingActive = false;
            scheduleNextPoll(cmid);
        }).catch(function() {
            pollingActive = false;
            scheduleNextPoll(cmid);
        });
    }

    function registerVisibilityRefresh(cmid) {
        window.addEventListener('focus', function() {
            refreshNow(cmid);
        });

        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                refreshNow(cmid);
            }
        });
    }

    function loadActivityState(cmid) {
        updateStatus('Loading Situation Room data...');

        return Ajax.call([{
            methodname: 'mod_commandroom_get_activity_state',
            args: {
                cmid: cmid
            }
        }])[0].then(function(result) {
            var message = 'Situation Room data loaded. ' +
                'Nodes: ' + result.nodes.length + ', ' +
                'Relationships: ' + result.edges.length + ', ' +
                'Shocks: ' + result.shocks.length + '.';

            updateStatus(message);
            return null;
        }).catch(function(error) {
            handlePollingUnavailable(error);
            return null;
        });
    }

    function submitProposal(cmid, nodeid, proposedvalue, rationale) {
        return Ajax.call([{
            methodname: 'mod_commandroom_submit_proposal',
            args: {
                cmid: cmid,
                nodeid: nodeid,
                proposedvalue: proposedvalue,
                rationale: rationale
            }
        }])[0];
    }

    function saveDecision(cmid, runid, nodeid, decisiontype) {
        return Ajax.call([{
            methodname: 'mod_commandroom_save_decision',
            args: {
                cmid: cmid,
                runid: runid,
                nodeid: nodeid,
                decisiontype: decisiontype
            }
        }])[0];
    }

    function requestAdvance(advanceurl) {
        return fetch(advanceurl, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store'
        }).then(function(response) {
            if (!response.ok) {
                throw new Error('Advance request failed.');
            }
            return response.text();
        });
    }

    function runBatchIterations(cmid, advanceurl, totalsteps, currentstep) {
        if (currentstep > totalsteps) {
            batchRunning = false;
            suppressReload = false;
            clearBatchIndicators();
            updateBatchStatus('Batch run completed.', false);
            setBatchControlsDisabled(false);
            window.location.reload();
            return;
        }

        updateBatchStatus('Running simulation. Iteration ' + currentstep + ' of ' + totalsteps + '...', false);
        startRunningPulse('Running simulation. Iteration ' + currentstep + ' of ' + totalsteps + '...');

        requestAdvance(advanceurl + '&batchts=' + Date.now()).then(function() {
            return Promise.all([
                loadProposals(cmid, true),
                loadDecisions(cmid, true),
                loadResults(cmid, true)
            ]);
        }).then(function() {
            if (currentstep === totalsteps) {
                batchRunning = false;
                suppressReload = false;
                clearBatchIndicators();
                updateBatchStatus('Batch run completed.', false);
                setBatchControlsDisabled(false);
                window.location.reload();
                return;
            }

            startStepCountdown(currentstep, totalsteps, 3);

            setTimeout(function() {
                runBatchIterations(cmid, advanceurl, totalsteps, currentstep + 1);
            }, 3000);
        }).catch(function(error) {
            batchRunning = false;
            suppressReload = false;
            clearBatchIndicators();
            setBatchControlsDisabled(false);
            updateBatchStatus('Batch run stopped.', true);
            Notification.exception(error);
            window.location.reload();
        });
    }

    function handleRunSimulation(button) {
        if (batchRunning) {
            return;
        }

        var cmid = Number(button.getAttribute('data-cmid'));
        var advanceurl = button.getAttribute('data-advanceurl');
        var maxiterations = Number(button.getAttribute('data-maxiterations'));
        var input = document.querySelector('.commandroom-batch-iterations');

        if (!advanceurl || !input) {
            updateBatchStatus('Run controls could not be found.', true);
            return;
        }

        var requested = Number(input.value);

        if (!Number.isInteger(requested) || requested < 1) {
            updateBatchStatus('Please enter a whole number of iterations.', true);
            return;
        }

        if (requested > maxiterations) {
            updateBatchStatus('You cannot run more than the remaining iterations.', true);
            return;
        }

        batchRunning = true;
        suppressReload = true;
        setBatchControlsDisabled(true);
        updateBatchStatus('Running simulation...', false);
        startRunningPulse('Running simulation...');

        runBatchIterations(cmid, advanceurl, requested, 1);
    }

    function handleSaveProposal(button) {
        if (batchRunning) {
            updateBatchStatus('Batch run in progress...', true);
            return;
        }

        var cmid = Number(button.getAttribute('data-cmid'));
        var nodeid = Number(button.getAttribute('data-nodeid'));

        var valueInput = document.querySelector('.commandroom-proposed-value[data-nodeid="' + nodeid + '"]');
        var rationaleInput = document.querySelector('.commandroom-proposal-rationale[data-nodeid="' + nodeid + '"]');

        if (!valueInput || !rationaleInput) {
            updateProposalStatus(nodeid, 'Proposal inputs could not be found.', true);
            return;
        }

        var proposedvalue = Number(valueInput.value);
        var rationale = rationaleInput.value.trim();

        if (!Number.isFinite(proposedvalue)) {
            updateProposalStatus(nodeid, 'Please enter a valid number.', true);
            return;
        }

        if (!rationale) {
            updateProposalStatus(nodeid, 'Please provide a rationale.', true);
            return;
        }

        button.disabled = true;
        updateProposalStatus(nodeid, 'Saving proposal...', false);

        submitProposal(cmid, nodeid, proposedvalue, rationale).then(function(result) {
            updateProposalStatus(nodeid, result.message, false);
            button.disabled = false;
            refreshNow(cmid);
        }).catch(function(error) {
            button.disabled = false;
            updateProposalStatus(nodeid, 'Could not save proposal.', true);
            Notification.exception(error);
        });
    }

    function handleSaveDecision(button) {
        if (batchRunning) {
            updateBatchStatus('Batch run in progress...', true);
            return;
        }

        var cmid = Number(button.getAttribute('data-cmid'));
        var nodeid = Number(button.getAttribute('data-nodeid'));
        var runid = Number(button.getAttribute('data-runid'));
        var decisiontype = button.getAttribute('data-decisiontype');

        if (!decisiontype) {
            updateDecisionStatus(nodeid, 'Invalid decision type.', true);
            return;
        }

        button.disabled = true;
        updateDecisionStatus(nodeid, 'Saving decision...', false);

        saveDecision(cmid, runid, nodeid, decisiontype).then(function(result) {
            updateDecisionStatus(nodeid, result.message, false);
            updateCurrentDecision(nodeid, result.decisiontype, result.selectedvalue);
            button.disabled = false;
            refreshNow(cmid);
        }).catch(function(error) {
            button.disabled = false;
            updateDecisionStatus(nodeid, 'Could not save decision.', true);
            Notification.exception(error);
        });
    }

    function registerProposalHandlers() {
        var buttons = document.querySelectorAll('.commandroom-save-proposal');

        buttons.forEach(function(button) {
            button.addEventListener('click', function() {
                handleSaveProposal(button);
            });
        });
    }

    function registerDecisionHandlers() {
        var buttons = document.querySelectorAll('.commandroom-save-decision');

        buttons.forEach(function(button) {
            button.addEventListener('click', function() {
                handleSaveDecision(button);
            });
        });
    }

    function registerRunHandlers() {
        var buttons = document.querySelectorAll('.commandroom-run-simulation');

        buttons.forEach(function(button) {
            button.addEventListener('click', function() {
                handleRunSimulation(button);
            });
        });
    }

    function registerNodeMeaningHandlers() {
        var buttons = document.querySelectorAll('.commandroom-node-meaning-toggle');

        buttons.forEach(function(button) {
            button.addEventListener('click', function() {
                var nodeid = button.getAttribute('data-nodeid');
                var panel = document.querySelector('.commandroom-node-meaning-panel[data-nodeid="' + nodeid + '"]');

                if (!panel) {
                    return;
                }

                var isHidden = panel.hasAttribute('hidden');

                if (isHidden) {
                    panel.removeAttribute('hidden');
                    button.setAttribute('aria-expanded', 'true');
                } else {
                    panel.setAttribute('hidden', 'hidden');
                    button.setAttribute('aria-expanded', 'false');
                }
            });
        });
    }

    function getCardRelativeRect(card, stage) {
        var rect = card.getBoundingClientRect();
        var stageRect = stage.getBoundingClientRect();
        var scrollLeft = stage.scrollLeft || 0;
        var scrollTop = stage.scrollTop || 0;
        var left = rect.left - stageRect.left + scrollLeft;
        var top = rect.top - stageRect.top + scrollTop;

        return {
            left: left,
            top: top,
            width: rect.width,
            height: rect.height,
            cx: left + (rect.width / 2),
            cy: top + (rect.height / 2)
        };
    }

    function getEdgePoint(fromRect, toRect, outwardOffset) {
        var dx = toRect.cx - fromRect.cx;
        var dy = toRect.cy - fromRect.cy;
        var halfWidth = Math.max(1, fromRect.width / 2);
        var halfHeight = Math.max(1, fromRect.height / 2);

        if (dx === 0 && dy === 0) {
            return {
                x: fromRect.cx,
                y: fromRect.cy
            };
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
        var safeX = clamp(rect.cx + offset, rect.left + 18, rect.left + rect.width - 18);
        var safeY = clamp(rect.cy + offset, rect.top + 18, rect.top + rect.height - 18);

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
        var lane = Math.max(-5, Math.min(5, Number(curvature) || 0));
        var offset = lane * 42;

        return {
            source: getPortPoint(sourceRect, sides.source, offset, 18),
            target: getPortPoint(targetRect, sides.target, offset, 24)
        };
    }

    function getNodeConnectionPoints(sourceCard, targetCard, stage, curvature) {
        var sourceRect = getCardRelativeRect(sourceCard, stage);
        var targetRect = getCardRelativeRect(targetCard, stage);

        return getPortBasedEdgePoints(sourceRect, targetRect, curvature);
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

    function getPathMidpoint(source, target) {
        return {
            x: source.x + ((target.x - source.x) / 2),
            y: source.y + ((target.y - source.y) / 2)
        };
    }


    function getLoopGroups(system) {
        var groups = {};

        system.querySelectorAll('.commandroom-visual-edge').forEach(function(edge) {
            var loopgroup = edge.getAttribute('data-loopgroup') || '';
            var label = edge.getAttribute('data-label') || '';

            if (loopgroup !== '') {
                if (!groups[loopgroup]) {
                    groups[loopgroup] = {
                        id: loopgroup,
                        label: loopgroup,
                        edgecount: 0,
                        labels: []
                    };
                }

                groups[loopgroup].edgecount += 1;

                if (label !== '') {
                    groups[loopgroup].labels.push(label);
                }
            }
        });

        return Object.keys(groups).sort().map(function(key) {
            return groups[key];
        });
    }

    function ensureLoopControls(system) {
        var groups = getLoopGroups(system);
        var controls = system.querySelector('.commandroom-loop-controls');

        if (!groups.length) {
            if (controls) {
                controls.parentNode.removeChild(controls);
            }
            selectedLoopGroup = '';
            return;
        }

        if (!controls) {
            controls = document.createElement('div');
            controls.className = 'commandroom-loop-controls';

            var label = document.createElement('label');
            label.className = 'commandroom-loop-label';
            label.setAttribute('for', 'commandroom-loop-selector');
            label.textContent = 'Show loop';

            var select = document.createElement('select');
            select.className = 'form-control commandroom-loop-selector';
            select.setAttribute('id', 'commandroom-loop-selector');

            var summary = document.createElement('div');
            summary.className = 'commandroom-loop-summary';
            summary.setAttribute('aria-live', 'polite');

            controls.appendChild(label);
            controls.appendChild(select);
            controls.appendChild(summary);

            var viewControls = system.querySelector('.commandroom-visual-view-controls');
            if (viewControls && viewControls.parentNode) {
                viewControls.parentNode.insertBefore(controls, viewControls.nextSibling);
            } else {
                system.insertBefore(controls, system.firstChild);
            }

            select.addEventListener('change', function() {
                selectedLoopGroup = select.value || '';
                applyLoopHighlighting(system);
            });
        }

        var selector = controls.querySelector('.commandroom-loop-selector');
        var previous = selectedLoopGroup;
        clearElement(selector);

        var allOption = document.createElement('option');
        allOption.value = '';
        allOption.textContent = 'Show all relationships';
        selector.appendChild(allOption);

        groups.forEach(function(group) {
            var option = document.createElement('option');
            option.value = group.id;
            option.textContent = group.id + ' (' + group.edgecount + ' relationships)';
            selector.appendChild(option);
        });

        var stillExists = groups.some(function(group) {
            return group.id === previous;
        });

        selector.value = stillExists ? previous : '';
        selectedLoopGroup = selector.value || '';
        updateLoopSummary(system);
    }

    function updateLoopSummary(system) {
        var summary = system.querySelector('.commandroom-loop-summary');

        if (!summary) {
            return;
        }

        if (!selectedLoopGroup) {
            summary.textContent = 'All relationships are visible.';
            return;
        }

        var selectedEdges = system.querySelectorAll(
            '.commandroom-visual-edge[data-loopgroup="' + selectedLoopGroup + '"]'
        );

        var polarities = {
            positive: 0,
            negative: 0,
            neutral: 0
        };

        selectedEdges.forEach(function(edge) {
            var polarity = edge.getAttribute('data-polarity') || 'neutral';
            if (!polarities[polarity]) {
                polarities[polarity] = 0;
            }
            polarities[polarity] += 1;
        });

        var looptype = 'reinforcing or neutral';
        if (polarities.negative % 2 === 1) {
            looptype = 'balancing';
        } else if (polarities.negative > 0 && polarities.negative % 2 === 0) {
            looptype = 'reinforcing';
        }

        summary.textContent = 'Showing loop "' + selectedLoopGroup + '". Likely loop type: ' + looptype + '.';
    }

    function clearLoopHighlighting(system) {
        system.classList.remove('commandroom-loop-filter-active');

        system.querySelectorAll('.commandroom-visual-card').forEach(function(card) {
            card.classList.remove('commandroom-loop-card-active');
            card.classList.remove('commandroom-loop-card-dimmed');
        });

        system.querySelectorAll('.commandroom-visual-arrow-path, .commandroom-visual-arrow-label').forEach(function(item) {
            item.classList.remove('commandroom-loop-edge-active');
            item.classList.remove('commandroom-loop-edge-dimmed');
        });
    }

    function applyLoopHighlighting(system) {
        clearLoopHighlighting(system);

        if (!selectedLoopGroup) {
            updateLoopSummary(system);
            return;
        }

        var activeNodes = {};
        system.classList.add('commandroom-loop-filter-active');

        system.querySelectorAll('.commandroom-visual-edge').forEach(function(edge) {
            var loopgroup = edge.getAttribute('data-loopgroup') || '';
            if (loopgroup === selectedLoopGroup) {
                activeNodes[edge.getAttribute('data-source')] = true;
                activeNodes[edge.getAttribute('data-target')] = true;
            }
        });

        system.querySelectorAll('.commandroom-visual-card').forEach(function(card) {
            var nodeid = card.getAttribute('data-nodeid');

            if (activeNodes[nodeid]) {
                card.classList.add('commandroom-loop-card-active');
            } else {
                card.classList.add('commandroom-loop-card-dimmed');
            }
        });

        system.querySelectorAll('.commandroom-visual-arrow-path, .commandroom-visual-arrow-label').forEach(function(item) {
            var loopgroup = item.getAttribute('data-loopgroup') || '';

            if (loopgroup === selectedLoopGroup) {
                item.classList.add('commandroom-loop-edge-active');
            } else {
                item.classList.add('commandroom-loop-edge-dimmed');
            }
        });

        updateLoopSummary(system);
    }

    function initialiseLoopControls() {
        document.querySelectorAll('.commandroom-visual-system').forEach(function(system) {
            ensureLoopControls(system);
            applyLoopHighlighting(system);
        });
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
        var offset = (index - centre) * 3.8;

        if (offset === 0 && bucket.length > 1) {
            offset = 1.9;
        }

        return offset * direction;
    }

    function getEffectiveCurvature(edge, buckets) {
        var configured = Number(edge.getAttribute('data-curvature')) || 0;
        var automatic = getAutomaticCurvature(edge, buckets);

        return configured + automatic;
    }

    function getCurvedLabelPoint(source, target, curvature) {
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

    function drawCommandroomArrows() {
        var namespace = 'http://www.w3.org/2000/svg';

        document.querySelectorAll('.commandroom-visual-system').forEach(function(system) {
            var stage = system.querySelector('.commandroom-visual-map-stage');
            var svg = system.querySelector('.commandroom-visual-arrow-layer');
            var grid = system.querySelector('.commandroom-visual-system-grid, .commandroom-visual-system-stack');

            if (!stage || !svg || !grid) {
                return;
            }

            var stageRect = stage.getBoundingClientRect();
            if (stageRect.width <= 0 || stageRect.height <= 0) {
                return;
            }

            var canvasWidth = Math.max(stage.scrollWidth || 0, stage.clientWidth || 0, stageRect.width || 0);
            var canvasHeight = Math.max(stage.scrollHeight || 0, stage.clientHeight || 0, stageRect.height || 0);

            svg.setAttribute('viewBox', '0 0 ' + canvasWidth + ' ' + canvasHeight);
            svg.setAttribute('width', canvasWidth);
            svg.setAttribute('height', canvasHeight);
            svg.style.width = canvasWidth + 'px';
            svg.style.height = canvasHeight + 'px';

            while (svg.firstChild) {
                svg.removeChild(svg.firstChild);
            }

            var defs = document.createElementNS(namespace, 'defs');
            var markerid = 'commandroom-arrowhead-' + Math.random().toString(36).substring(2);
            var marker = document.createElementNS(namespace, 'marker');
            marker.setAttribute('id', markerid);
            marker.setAttribute('markerWidth', '9');
            marker.setAttribute('markerHeight', '7');
            marker.setAttribute('refX', '8');
            marker.setAttribute('refY', '3.5');
            marker.setAttribute('orient', 'auto');
            marker.setAttribute('markerUnits', 'userSpaceOnUse');

            var markerPath = document.createElementNS(namespace, 'path');
            markerPath.setAttribute('d', 'M 0 0 L 8 3.5 L 0 7 z');
            markerPath.setAttribute('class', 'commandroom-visual-arrow-marker');

            marker.appendChild(markerPath);
            defs.appendChild(marker);
            svg.appendChild(defs);

            var edges = Array.from(system.querySelectorAll('.commandroom-visual-edge'));
            var edgeBuckets = buildEdgeBuckets(edges);

            edges.forEach(function(edge) {
                var sourceId = edge.getAttribute('data-source');
                var targetId = edge.getAttribute('data-target');

                var sourceCard = grid.querySelector('.commandroom-visual-card[data-nodeid="' + sourceId + '"]');
                var targetCard = grid.querySelector('.commandroom-visual-card[data-nodeid="' + targetId + '"]');

                if (!sourceCard || !targetCard) {
                    return;
                }

                var polarity = edge.getAttribute('data-polarity') || 'neutral';
                var loopgroup = edge.getAttribute('data-loopgroup') || '';
                var label = edge.getAttribute('data-label') || '';
                var configuredCurvature = Number(edge.getAttribute('data-curvature')) || 0;
                var curvature = getEffectiveCurvature(edge, edgeBuckets);
                var points = getNodeConnectionPoints(sourceCard, targetCard, stage, curvature);

                var pathclass = 'commandroom-visual-arrow-path commandroom-visual-arrow-' + polarity;
                if (curvature !== configuredCurvature) {
                    pathclass += ' commandroom-visual-arrow-auto-separated';
                }

                var path = document.createElementNS(namespace, 'path');
                path.setAttribute('d', buildArrowPath(points.source, points.target, curvature));
                path.setAttribute('class', pathclass);
                path.setAttribute('marker-end', 'url(#' + markerid + ')');
                path.setAttribute('data-polarity', polarity);
                path.setAttribute('data-loopgroup', loopgroup);
                path.setAttribute('data-source', sourceId);
                path.setAttribute('data-target', targetId);

                if (loopgroup !== '') {
                    path.classList.add('commandroom-visual-arrow-looped');
                }

                svg.appendChild(path);

                if (label !== '') {
                    var midpoint = getCurvedLabelPoint(points.source, points.target, curvature);
                    var text = document.createElementNS(namespace, 'text');
                    text.setAttribute('x', midpoint.x);
                    text.setAttribute('y', midpoint.y - 6);
                    text.setAttribute('class', 'commandroom-visual-arrow-label commandroom-visual-arrow-label-' + polarity);
                    text.setAttribute('text-anchor', 'middle');
                    text.setAttribute('data-loopgroup', loopgroup);
                    text.setAttribute('data-source', sourceId);
                    text.setAttribute('data-target', targetId);
                    text.textContent = label;
                    svg.appendChild(text);
                }
            });

            applyLoopHighlighting(system);
        });
    }

    function getVisualViewStorageKey(cmid) {
        return 'mod_commandroom_visual_view_' + cmid;
    }

    function getSavedVisualView(cmid) {
        if (window.localStorage && cmid) {
            var saved = window.localStorage.getItem(getVisualViewStorageKey(cmid));
            if (saved === 'cards' || saved === 'systems') {
                return saved;
            }
        }
        return 'systems';
    }

    function setVisualView(system, view, cmid) {
        var isSystems = view === 'systems';
        system.classList.toggle('commandroom-view-cards', !isSystems);
        system.classList.toggle('commandroom-view-systems', isSystems);
        system.querySelectorAll('.commandroom-visual-view-toggle').forEach(function(button) {
            var isActive = button.getAttribute('data-view') === view;
            button.classList.toggle('active', isActive);
            button.classList.toggle('btn-secondary', isActive);
            button.classList.toggle('btn-outline-secondary', !isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
        if (window.localStorage && cmid) {
            window.localStorage.setItem(getVisualViewStorageKey(cmid), view);
        }
        scheduleArrowRedraw();
    }

    function scheduleArrowRedraw() {
        window.requestAnimationFrame(function() {
            window.requestAnimationFrame(drawCommandroomArrows);
        });
    }

    function registerVisualViewHandlers(cmid) {
        document.querySelectorAll('.commandroom-visual-system').forEach(function(system) {
            setVisualView(system, getSavedVisualView(cmid), cmid);
            system.querySelectorAll('.commandroom-visual-view-toggle').forEach(function(button) {
                button.addEventListener('click', function(event) {
                    event.preventDefault();
                    var view = button.getAttribute('data-view') || 'systems';
                    setVisualView(system, view, cmid);
                });
            });
        });
        document.querySelectorAll('.commandroom-visual-map-stage').forEach(function(stage) {
            stage.addEventListener('scroll', function() {
                window.requestAnimationFrame(function() {
                    applyLoopHighlighting(stage.closest('.commandroom-visual-system'));
                });
            });
        });

        window.addEventListener('resize', function() {
            scheduleArrowRedraw();
        });
    }

    function closeVisualPanel(nodeid, paneltype) {
        var selector = '.commandroom-visual-overlay-panel[data-nodeid="' + nodeid + '"]';
        if (paneltype) {
            selector += '[data-paneltype="' + paneltype + '"]';
        }

        document.querySelectorAll(selector).forEach(function(panel) {
            panel.setAttribute('hidden', 'hidden');
        });

        document.querySelectorAll('.commandroom-visual-panel-toggle[data-nodeid="' + nodeid + '"]').forEach(function(button) {
            if (!paneltype || button.getAttribute('data-paneltype') === paneltype) {
                button.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function openVisualPanel(nodeid, paneltype) {
        closeVisualPanel(nodeid, null);

        var panel = document.querySelector(
            '.commandroom-visual-overlay-panel[data-nodeid="' + nodeid + '"][data-paneltype="' + paneltype + '"]'
        );
        var button = document.querySelector(
            '.commandroom-visual-panel-toggle[data-nodeid="' + nodeid + '"][data-paneltype="' + paneltype + '"]'
        );

        if (!panel) {
            return;
        }

        panel.removeAttribute('hidden');

        if (button) {
            button.setAttribute('aria-expanded', 'true');
        }
    }

    function registerVisualPanelHandlers() {
        document.querySelectorAll('.commandroom-visual-panel-toggle').forEach(function(button) {
            button.addEventListener('click', function(event) {
                event.preventDefault();
                event.stopPropagation();

                var nodeid = button.getAttribute('data-nodeid');
                var paneltype = button.getAttribute('data-paneltype');
                var panel = document.querySelector(
                    '.commandroom-visual-overlay-panel[data-nodeid="' + nodeid + '"][data-paneltype="' + paneltype + '"]'
                );

                if (!panel) {
                    return;
                }

                if (panel.hasAttribute('hidden')) {
                    openVisualPanel(nodeid, paneltype);
                } else {
                    closeVisualPanel(nodeid, paneltype);
                }
            });
        });

        document.querySelectorAll('.commandroom-visual-panel-close').forEach(function(button) {
            button.addEventListener('click', function(event) {
                event.preventDefault();
                event.stopPropagation();

                closeVisualPanel(
                    button.getAttribute('data-nodeid'),
                    button.getAttribute('data-paneltype')
                );
            });
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('.commandroom-visual-overlay-panel').forEach(function(panel) {
                    panel.setAttribute('hidden', 'hidden');
                });
                document.querySelectorAll('.commandroom-visual-panel-toggle').forEach(function(button) {
                    button.setAttribute('aria-expanded', 'false');
                });
            }
        });
    }

    function init(cmid) {
        if (!cmid || Number(cmid) < 1) {
            updateStatus('Invalid course module id.');
            return;
        }

        cmid = Number(cmid);

        registerProposalHandlers();
        registerDecisionHandlers();
        registerRunHandlers();
        registerNodeMeaningHandlers();
        registerVisualPanelHandlers();
        registerVisualViewHandlers(cmid);
        initialiseLoopControls();
        registerVisibilityRefresh(cmid);
        scheduleArrowRedraw();

        loadActivityState(cmid).then(function() {
            return Promise.all([
                loadProposals(cmid, true),
                loadDecisions(cmid, true),
                loadResults(cmid, true)
            ]);
        }).then(function() {
            startPolling(cmid);
        });
    }

    return {
        init: init
    };
});