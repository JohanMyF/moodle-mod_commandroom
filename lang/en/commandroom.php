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
 * English language strings for mod_commandroom.
 *
 * @package    mod_commandroom
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Situation Room';
$string['modulename'] = 'Situation Room';
$string['modulenameplural'] = 'Situation Rooms';
$string['pluginadministration'] = 'Situation Room administration';
$string['pluginfieldset'] = 'Situation Room settings';
$string['commandroomname'] = 'Situation Room name';
$string['commandroomintro'] = 'Description';
$string['timesteplabel'] = 'Time step label';
$string['stepduration'] = 'Step duration';
$string['stepdurationunit'] = 'Step duration unit';
$string['totaliterations'] = 'Total iterations';
$string['useshocks'] = 'Use external shocks';
$string['node'] = 'Node';
$string['nodes'] = 'Nodes';
$string['nodename'] = 'Node name';
$string['nodetype'] = 'Node type';
$string['initialvalue'] = 'Initial value';
$string['currentvalue'] = 'Current value';
$string['minvalue'] = 'Minimum value';
$string['maxvalue'] = 'Maximum value';
$string['studentcontrolled'] = 'Student controlled';
$string['visibletostudents'] = 'Visible to students';
$string['displayorder'] = 'Display order';
$string['svgfile'] = 'SVG file';
$string['description'] = 'Description';
$string['stock'] = 'Stock';
$string['computed'] = 'Computed';
$string['flow'] = 'Flow';
$string['edge'] = 'Relationship';
$string['edges'] = 'Relationships';
$string['sourcenode'] = 'Source node';
$string['targetnode'] = 'Target node';
$string['relationtype'] = 'Relationship type';
$string['strength'] = 'Strength';
$string['delayiterations'] = 'Delay in iterations';
$string['functionconfig'] = 'Function configuration';
$string['linear'] = 'Linear';
$string['inverse'] = 'Inverse';
$string['nonlinear'] = 'Non-linear';
$string['shock'] = 'Shock';
$string['shocks'] = 'External shocks';
$string['shocktype'] = 'Shock type';
$string['iterationno'] = 'Iteration number';
$string['adjustment'] = 'Adjustment';
$string['minadjustment'] = 'Minimum adjustment';
$string['maxadjustment'] = 'Maximum adjustment';
$string['applyeveryiteration'] = 'Apply every iteration';
$string['scheduled'] = 'Scheduled';
$string['random_range'] = 'Random range';
$string['run'] = 'Run';
$string['runs'] = 'Runs';
$string['group'] = 'Group';
$string['leader'] = 'Leader';
$string['status'] = 'Status';
$string['draft'] = 'Draft';
$string['completed'] = 'Completed';
$string['finalscore'] = 'Final score';
$string['timecreated'] = 'Time created';
$string['timemodified'] = 'Time modified';
$string['timecompleted'] = 'Time completed';
$string['proposal'] = 'Proposal';
$string['proposals'] = 'Proposals';
$string['proposedvalue'] = 'Proposed value';
$string['rationale'] = 'Rationale';
$string['decision'] = 'Decision';
$string['decisions'] = 'Decisions';
$string['decisionmode'] = 'Decision mode';
$string['selectedvalue'] = 'Selected value';
$string['minimum'] = 'Minimum';
$string['maximum'] = 'Maximum';
$string['mean'] = 'Mean';
$string['result'] = 'Result';
$string['results'] = 'Results';
$string['nodevalue'] = 'Node value';
$string['valueorigin'] = 'Value origin';
$string['system'] = 'System';
$string['export'] = 'Export';
$string['exports'] = 'Exports';
$string['import'] = 'Import';
$string['exportsystem'] = 'Export system';
$string['importsystem'] = 'Import system';
$string['jsonfile'] = 'JSON file';
$string['jsonhash'] = 'JSON hash';
$string['warroom'] = 'War Room';
$string['governancepanel'] = 'Governance panel';
$string['feedbacklayer'] = 'Feedback layer';
$string['simulation'] = 'Simulation';
$string['submitproposal'] = 'Submit proposal';
$string['submitdecision'] = 'Submit decision';
$string['runsimulation'] = 'Run simulation';
$string['viewresults'] = 'View results';
$string['privacy:metadata'] = 'The Situation Room activity stores simulation, proposal, and decision data.';
$string['privacy:metadata:commandroom_proposals'] = 'Information about user proposals submitted in a Situation Room run.';
$string['privacy:metadata:commandroom_proposals:userid'] = 'The ID of the user submitting the proposal.';
$string['privacy:metadata:commandroom_proposals:nodeid'] = 'The node the proposal applies to.';
$string['privacy:metadata:commandroom_proposals:proposedvalue'] = 'The proposed value submitted by the user.';
$string['privacy:metadata:commandroom_proposals:rationale'] = 'The rationale submitted by the user.';
$string['privacy:metadata:commandroom_proposals:timecreated'] = 'The time the proposal was created.';
$string['privacy:metadata:commandroom_proposals:timemodified'] = 'The time the proposal was last modified.';
$string['privacy:metadata:commandroom_runs'] = 'Information about a Situation Room run involving a user as leader.';
$string['privacy:metadata:commandroom_runs:leaderid'] = 'The ID of the leader for the run.';
$string['privacy:metadata:commandroom_runs:groupid'] = 'The group involved in the run.';
$string['privacy:metadata:commandroom_runs:finalscore'] = 'The final score awarded for the run.';
$string['privacy:metadata:commandroom_runs:timecreated'] = 'The time the run was created.';
$string['privacy:metadata:commandroom_runs:timemodified'] = 'The time the run was last modified.';
$string['privacy:metadata:commandroom_runs:timecompleted'] = 'The time the run was completed.';
$string['privacy:metadata:commandroom_decisions'] = 'Information about leader decisions recorded in a Situation Room run.';
$string['privacy:metadata:commandroom_decisions:leaderid'] = 'The ID of the leader making the decision.';
$string['privacy:metadata:commandroom_decisions:nodeid'] = 'The node the decision applies to.';
$string['privacy:metadata:commandroom_decisions:decisionmode'] = 'The decision mode used.';
$string['privacy:metadata:commandroom_decisions:selectedvalue'] = 'The selected value recorded for the node.';
$string['privacy:metadata:commandroom_decisions:timecreated'] = 'The time the decision was recorded.';
$string['privacy:metadata:commandroom_exports'] = 'Information about exported Situation Room systems created by a user.';
$string['privacy:metadata:commandroom_exports:userid'] = 'The ID of the user who created the export.';
$string['privacy:metadata:commandroom_exports:name'] = 'The name given to the exported system.';
$string['privacy:metadata:commandroom_exports:jsonhash'] = 'A hash of the exported JSON content.';
$string['privacy:metadata:commandroom_exports:timecreated'] = 'The time the export was created.';
$string['privacy:metadata:core_files'] = 'Files stored by Moodle for the Situation Room activity.';
$string['mod/commandroom:addinstance'] = 'Add a new Situation Room activity';
$string['mod/commandroom:view'] = 'View Situation Room';
$string['mod/commandroom:submitproposal'] = 'Submit proposals in Situation Room';
$string['mod/commandroom:leaddecision'] = 'Lead decisions in Situation Room';
$string['mod/commandroom:manageruns'] = 'Manage Situation Room runs';
$string['mod/commandroom:exportsystem'] = 'Export Situation Room systems';
$string['mod/commandroom:importsystem'] = 'Import Situation Room systems';
$string['error:nodenameempty'] = 'Node name is required.';
$string['error:invalidnodetype'] = 'Invalid node type.';
$string['error:invalidrelationtype'] = 'Invalid relationship type.';
$string['error:invalidproposal'] = 'Invalid proposal value.';
$string['error:rationalerequired'] = 'A rationale is required.';
$string['error:runnotfound'] = 'Run not found.';
$string['error:nodenotfound'] = 'Node not found.';
$string['error:notenoughproposals'] = 'Not all required proposals have been submitted.';
$string['error:invaliddecisionmode'] = 'Invalid decision mode.';
$string['error:importfailed'] = 'The system import failed.';
$string['error:exportfailed'] = 'The system export failed.';
$string['error:invalidupdateconfig'] = 'Invalid update configuration for node: {$a}.';
$string['eventcoursemoduleviewed'] = 'Situation Room viewed';
$string['iterationunit'] = 'Iteration';
$string['hourunit'] = 'Hour';
$string['dayunit'] = 'Day';
$string['weekunit'] = 'Week';
$string['monthunit'] = 'Month';
$string['quarterunit'] = 'Quarter';
$string['yearunit'] = 'Year';
$string['timesteplabel_help'] = 'A label for one simulation step, such as month, quarter, year, or period.';
$string['error:stepdurationpositive'] = 'Step duration must be 1 or greater.';
$string['error:totaliterationspositive'] = 'Total iterations must be 1 or greater.';
$string['warroomcomingsoon'] = 'The Situation Room interface is not yet active. This page confirms that the activity is installed and loading correctly.';
$string['nocommandrooms'] = 'There are no Situation Rooms in this course.';
$string['nonodesdefined'] = 'No nodes have been defined yet.';
$string['relationshipcount'] = 'Relationships defined: {$a}';
$string['editsystem'] = 'Edit system';
$string['editsystemfor'] = 'Edit system for: {$a}';
$string['editsystemintro'] = 'Use this page to import and inspect the system definition for this Situation Room activity.';
$string['backtoactivity'] = 'Back to activity';
$string['jsonimporthelp'] = 'Upload a JSON file containing metadata, nodes, edges, and optional shocks. Importing a file replaces the current system definition for this activity.';
$string['importjsonfile'] = 'Import JSON file';
$string['noedgesdefined'] = 'No relationships have been defined yet.';
$string['noshocksdefined'] = 'No shocks have been defined yet.';
$string['jsonimportsuccess'] = 'The JSON system was imported successfully.';
$string['error:nojsonfileuploaded'] = 'No JSON file was uploaded.';
$string['error:jsonextensionrequired'] = 'Please upload a file with a .json extension.';
$string['error:emptyjsonfile'] = 'The uploaded JSON file is empty.';
$string['error:invalidjsonformat'] = 'The uploaded file does not contain valid JSON.';
$string['error:jsonnodesmissing'] = 'The JSON file must contain a nodes array.';
$string['error:jsonedgesmissing'] = 'The JSON file must contain an edges array.';
$string['error:invalidnodeentry'] = 'Node entry {$a} is invalid.';
$string['error:noderefmissing'] = 'Node entry {$a} is missing a ref value.';
$string['error:duplicatenoderef'] = 'Duplicate node ref found: {$a}.';
$string['error:invalidedgeentry'] = 'Relationship entry {$a} is invalid.';
$string['error:invalidedgesource'] = 'Relationship entry {$a} has an invalid source node ref.';
$string['error:invalidedgetarget'] = 'Relationship entry {$a} has an invalid target node ref.';
$string['error:invalidshockentry'] = 'Shock entry {$a} is invalid.';
$string['error:invalidshocknode'] = 'Shock entry {$a} has an invalid node ref.';
$string['exportsystemhelp'] = 'Download the current system definition as a JSON file for reuse in another Situation Room activity.';
$string['proposalsaved'] = 'Your proposal has been saved.';
$string['error:nodenotstudentcontrolled'] = 'This node is not available for student proposals.';
$string['error:proposalbelowminimum'] = 'The proposed value is below the minimum allowed for this node.';
$string['error:proposalabovemaximum'] = 'The proposed value is above the maximum allowed for this node.';
$string['proposalpanel'] = 'Your proposals';
$string['nostudentcontrollednodes'] = 'There are no student-controlled nodes available for proposals.';
$string['proposalrange'] = 'Allowed range: {$a->min} to {$a->max}';
$string['rationaleplaceholder'] = 'Explain why you chose this value.';
$string['saveproposal'] = 'Save proposal';
$string['peerproposals'] = 'Peer proposals';
$string['noproposalssubmitted'] = 'No proposals submitted yet.';
$string['decisionsaved'] = 'The leader decision has been saved.';
$string['simulationadvanced'] = 'Simulation advanced to the next iteration.';
$string['error:nobaselineresults'] = 'No baseline simulation state exists for this run.';
$string['error:nextiterationalreadyexists'] = 'The next iteration already exists. Refresh your screen.';
$string['error:missingcurrentstate'] = 'Current state is incomplete for one or more nodes.';
$string['error:missingdecisionfornode'] = 'Leader decision missing for node: {$a}.';
$string['error:unsupportedrelationtype'] = 'Unsupported relation type: {$a}.';
$string['runalreadysubmitted'] = 'This run has already been submitted.';
$string['runinvalidated'] = 'This run is no longer valid because the scenario was reloaded.';
$string['runalreadycompleted'] = 'This run has already reached the end of the scenario.';
$string['invalidtotaliterations'] = 'The scenario has an invalid total iteration setting.';
$string['endofscenariorun'] = 'End of scenario run';

$string['startnewrun'] = 'Start new run';
$string['newrunstarted'] = 'A fresh simulation run has been created for your group.';
$string['error:runmustbecompleted'] = 'The current run must be completed before starting a new run.';

$string['iterationstorun'] = 'Iterations to run';
$string['batchruninprogress'] = 'Batch run in progress...';
$string['batchrunstepstatus'] = 'Running iteration {$a->current} of {$a->total}...';
$string['batchruncompleted'] = 'Batch run completed.';
$string['batchrunstopped'] = 'Batch run stopped.';

$string['visualsystemview'] = 'Visual system view';
$string['visualcardunits'] = 'Each icon represents approximately {$a->unit} {$a->name}.';

$string['visualscalingiconmeta'] = 'This icon grows or shrinks as the value changes.';
$string['visualrepeatediconmeta'] = 'Each icon represents approximately {$a->unit} {$a->name}.';

$string['showloop'] = 'Show loop';
$string['showallrelationships'] = 'Show all relationships';
$string['allrelationshipsvisible'] = 'All relationships are visible.';
$string['loopselectoroption'] = '{$a->loopgroup} ({$a->edgecount} relationships)';

// ===== System Builder =====

$string['systembuilder'] = 'System Builder';
$string['systembuilderfor'] = 'System Builder: {$a}';

$string['systembuilderintro'] = 'Use this builder to view and refine your system layout. This tool reuses the same visual system used during teaching, ensuring consistency between design and delivery.';

$string['systemlayout'] = 'System Layout';
$string['systembuilderlayouthelp'] = 'This is a live preview of your system. In the next phase, you will be able to drag and reposition nodes directly on this canvas.';

$string['jsondraft'] = 'JSON Draft';
$string['jsondrafthelp'] = 'This is a live JSON representation of your system. You can export it, edit it, or reuse it in other CommandRoom activities.';

$string['downloadjsondraft'] = 'Download JSON Draft';

$string['backtoactivity'] = 'Back to activity';
$string['importsystem'] = 'Import system';
$string['exportsystem'] = 'Export system';

$string['nonodesdefined'] = 'No system has been defined yet. Please import or create a system first.';

// ===== System Brief =====
$string['systembriefheader'] = 'System brief';

$string['systembrief'] = 'What system are you modelling?';
$string['systembrief_help'] = 'Describe the real-world system in plain English. This is the teacher-facing design brief before the visual model is built.';
$string['systembriefplaceholder'] = 'Example: A game farm where lodge investment, hunting demand, game population, weather, revenue, and costs influence each other over several seasons.';

$string['studentdecision'] = 'What decision must students make?';
$string['studentdecision_help'] = 'Describe the main decision or trade-off students must manage during the simulation.';
$string['studentdecisionplaceholder'] = 'Example: Decide how many lodges to build, how strongly to market hunting, and when to reduce capacity.';

$string['learninggoal'] = 'What should students learn?';
$string['learninggoal_help'] = 'Describe the key insight students should gain from the system.';
$string['learninggoalplaceholder'] = 'Example: Short-term revenue can damage long-term ecological and financial sustainability.';

$string['riskychoice'] = 'What is the tempting but risky choice?';
$string['riskychoiceplaceholder'] = 'Example: Build many lodges and hunt aggressively to increase revenue quickly.';

$string['safechoice'] = 'What is the safer but costly choice?';
$string['safechoiceplaceholder'] = 'Example: Limit expansion, allow game stock to recover, and accept lower short-term revenue.';

$string['nodeinventory'] = 'Node inventory';
$string['nodeinventory_help'] = 'List likely system elements, one per line. You can include a type in brackets, such as stock, flow, computed, or shock.';
$string['nodeinventoryplaceholder'] = 'Cash Reserve (stock)
Lodge Capacity (stock)
Game Population (stock)
Build New Lodges (flow)
Hunting Intensity (flow)
Hunter Demand (computed)
Weather Shock (shock)';

$string['startermodelnotice'] = 'This is a starter model generated from the node inventory. It has not yet been saved as real system nodes. Download the JSON draft and import it to create the editable system.';

$string['builderlaunchheader'] = 'System Builder';
$string['builderlaunch'] = 'Open builder';
$string['openbuilder'] = 'Open System Builder';

$string['relationshipmatrix'] = 'Relationship matrix';
$string['relationshipmatrixhelp'] = 'Tick a box when the source node on the left affects the target node at the top. This updates the JSON draft only; it does not write relationships to the database yet.';
$string['relationshipmatrixsource'] = 'Source affects target';
$string['relationshipmatrixcheckbox'] = '{$a->source} affects {$a->target}';

$string['editrelationship'] = 'Edit';

$string['savesystem'] = 'Save system';
$string['saveandreturn'] = 'Save and return';
$string['savesystemcomingsoon'] = 'Saving from Builder will be added in the next step.';
$string['importjson'] = 'Import JSON';
$string['exportjson'] = 'Export JSON';
$string['previewsimulation'] = 'Preview simulation';

$string['buildersavesuccess'] = 'System saved successfully.';

$string['systemmanagement'] = 'System management';
$string['systemmanagementactions'] = 'System actions';
$string['systemmanagementhelp'] = 'Use these tools to build, import, or export the system model for this activity.';
$string['systemmanagementsavefirst'] = 'Save the activity first. Then you can open Builder, import JSON, or export JSON.';
$string['saveandreturntosettings'] = 'Save and return to settings';
$string['saveanduse'] = 'Save and use';
$string['builderstartermodelnotice'] = 'This starter model was generated from the node inventory. Use Builder to refine it, then save it to this activity.';
$string['advancedjsoneditor'] = 'Advanced JSON editor';
$string['advancedjsoneditorhelp'] = 'This is the system model behind the visual builder. Advanced users may inspect or edit it directly.';
$string['builderlayouthelp'] = 'Drag nodes to arrange the system. Use the relationship matrix and edit controls to refine the model.';
$string['importsystemfor'] = 'Import system JSON: {$a}';
$string['returntosettings'] = 'Return to settings';
$string['viewactivity'] = 'View activity';
$string['importsystemintro'] = 'Import a CommandRoom JSON system, then continue refining it in System Builder.';
$string['importthenbuilderhelp'] = 'After import, you will be taken directly to System Builder.';
$string['activitysettings'] = 'Activity settings';
$string['teachertools'] = 'Teacher tools';

$string['publishanduse'] = 'Publish and use';
$string['builderpublishsuccess'] = 'System published successfully. Existing simulation runs were reset.';
$string['presetoptioncustom'] = 'I will start blank, import JSON, or build my own system';
$string['presetintro'] = 'Choose a starter system if you want CommandRoom to generate a useful first draft. You can still edit the brief, node inventory, relationships, calculations, and layout in the Builder.';
$string['presetnotfound'] = 'No packaged presets were found. Add presets.json and the preset JSON files to /mod/commandroom/presets/.';
$string['presetexampleprefix'] = 'Example: {$a}';
$string['presettip'] = 'Tip: if you choose a starter system now, CommandRoom will import the matching packaged JSON when the activity is saved for the first time.';
$string['presetheader'] = 'Starter system';
$string['presetselectlabel'] = 'When not managed, how would this system tend to behave over time?';
$string['visualconfig'] = 'Visual config';
$string['updateconfig'] = 'Update config';
$string['calculationconfig'] = 'Calculation config';
$string['polarity'] = 'Polarity';
$string['label'] = 'Label';
$string['loopgroup'] = 'Loop group';
$string['curvature'] = 'Curvature';
$string['updatesummarymode'] = 'mode={$a}';
$string['updatesummaryinflows'] = 'inflows: {$a}';
$string['updatesummaryoutflows'] = 'outflows: {$a}';
$string['noleaderdecisionsaved'] = 'No leader decision saved yet.';
$string['currentdecisionlabel'] = 'Current decision: ';
$string['cardview'] = 'Card view';
$string['systemsview'] = 'Systems view';
$string['builderjsonvalid'] = 'JSON draft is valid.';
$string['builderjsoninvalid'] = 'JSON draft is not valid yet: {$a}';
$string['builderdragtitle'] = 'Drag this node to reposition it. The JSON draft updates automatically.';
$string['buildereditrelationship'] = 'Edit relationship';
$string['builderaffectstext'] = 'affects';
$string['builderrefinerelationship'] = 'Refine relationship';
$string['builderclose'] = 'Close';
$string['builderpositivepolarity'] = 'Positive: source increases target';
$string['buildernegativepolarity'] = 'Negative: source reduces target';
$string['builderneutralpolarity'] = 'Neutral or descriptive';
$string['builderstrength'] = 'Strength';
$string['builderdelayiterations'] = 'Delay iterations';
$string['builderloopgroup'] = 'Loop group';
$string['builderloopgroupexample'] = 'Example: congestion_loop';
$string['builderapplytojsondraft'] = 'Apply to JSON draft';
$string['buildercancel'] = 'Cancel';
$string['builderrelationshipupdated'] = 'Relationship updated in JSON draft.';
$string['buildersaveendpointmissing'] = 'Save endpoint is not available.';
$string['buildersavingsystem'] = 'Saving system...';
$string['buildersavefailed'] = 'Save failed.';
$string['buildersystemsaved'] = 'System saved.';
$string['buildercouldnotloadstrings'] = 'CommandRoom builder could not load language strings.';
$string['builderthisnode'] = 'This node';
$string['builderpreviewstudent'] = 'leader decision chosen from student proposals';
$string['builderpreviewincoming'] = 'calculated from incoming relationship arrows';
$string['builderpreviewpeaksat'] = 'peaks at';
$string['builderpreviewwhen'] = 'when';
$string['builderpreviewis'] = 'is';
$string['builderpreviewbellcurve'] = 'follows a bell curve: peak';
$string['builderpreviewisnear'] = 'is near';
$string['builderpreviewspread'] = 'spread';
$string['builderpreviewrandombetween'] = 'random value between';
$string['builderpreviewand'] = 'and';
$string['builderchoosedetermination'] = 'Choose how this node is determined.';
$string['buildervisualappearance'] = 'Visual appearance';
$string['buildericon'] = 'Icon';
$string['builderselectedicon'] = 'Selected icon:';
$string['buildervisualmode'] = 'Visual mode';
$string['builderscalingicon'] = 'Scaling icon';
$string['builderrepeatedicon'] = 'Repeated icon';
$string['builderscalingvalue'] = 'Scaling value range';
$string['builderminvalue'] = 'Minimum value';
$string['buildermaxvalue'] = 'Maximum value';
$string['builderminsize'] = 'Minimum size';
$string['buildermaxsize'] = 'Maximum size';
$string['builderrepeatedsettings'] = 'Repeated icon settings';
$string['builderunitvalue'] = 'One icon represents this value';
$string['buildermaxicons'] = 'Maximum icons';
$string['buildericonsize'] = 'Icon size';
$string['builderlayout'] = 'Layout';
$string['buildergrid'] = 'Grid';
$string['builderrow'] = 'Row';
$string['buildereditnode'] = 'Edit node';
$string['buildernodetype'] = 'Node type';
$string['builderstock'] = 'Stock';
$string['builderflow'] = 'Flow';
$string['buildervariable'] = 'Variable';
$string['buildercomputed'] = 'Computed';
$string['builderhowupdated'] = 'How is this node updated?';
$string['builderstudentdecision'] = 'Student / leader decision';
$string['builderstockaccumulation'] = 'Stock accumulation';
$string['builderformula'] = 'Formula / curve';
$string['builderincoming'] = 'Incoming relationships only';
$string['builderstocksettings'] = 'Stock accumulation settings';
$string['builderbase'] = 'Base';
$string['builderpriorself'] = 'Prior value of this node';
$string['builderzero'] = 'Zero';
$string['builderinflows'] = 'Inflows / additions';
$string['builderoutflows'] = 'Outflows / subtractions';
$string['builderoptionalrate'] = 'Optional growth rate node';
$string['buildernone'] = 'None';
$string['builderformulatype'] = 'Formula type';
$string['builderselectformula'] = 'Select a formula';
$string['buildermultiply'] = 'Multiply two values';
$string['builderdivide'] = 'Divide one value by another';
$string['builderpercentage'] = 'Percentage of a value';
$string['buildersum'] = 'Add and subtract selected nodes';
$string['builderadd'] = 'Add selected nodes only';
$string['builderlinear'] = 'Linear relationship';
$string['builderdiminishing'] = 'Diminishing returns';
$string['builderoptimum'] = 'Optimum point curve';
$string['builderbell'] = 'Bell curve';
$string['builderrandom'] = 'Random range';
$string['builderleft'] = 'Left value';
$string['builderright'] = 'Right value';
$string['buildernumerator'] = 'Numerator';
$string['builderdenominator'] = 'Denominator';
$string['buildervalue'] = 'Value';
$string['builderpercent'] = 'Percent / rate';
$string['builderinputnode'] = 'Input node';
$string['builderslope'] = 'Slope: how much output changes per input unit';
$string['builderintercept'] = 'Starting value / intercept';
$string['buildermaximumoutput'] = 'Maximum possible output';
$string['builderrisespeed'] = 'Rise speed';
$string['builderrisespeedhelp'] = 'Higher values rise faster toward the maximum.';
$string['builderbestinput'] = 'Best / optimum input value';
$string['builderoptimumoutput'] = 'Output at the optimum point';
$string['builderdropoff'] = 'Drop-off strength';
$string['builderdropoffhelp'] = 'Higher values punish being far from the optimum more strongly.';
$string['builderfloor'] = 'Minimum output floor';
$string['buildercentre'] = 'Centre / best input value';
$string['builderpeakoutput'] = 'Peak output';
$string['builderspread'] = 'Spread / tolerance';
$string['builderaddnodes'] = 'Add these nodes';
$string['buildersubtractnodes'] = 'Subtract these nodes';
$string['builderminrandom'] = 'Minimum random value';
$string['buildermaxrandom'] = 'Maximum random value';
$string['builderinitialvalue'] = 'Initial value';
$string['builderstudentsmayedit'] = 'Students may edit/propose this value';
$string['buildervisibletostudents'] = 'Visible to students';
$string['builderdescription'] = 'Description';
$string['builderinterpretation'] = 'Interpretation';
$string['buildernumberbelow'] = 'Use number below';
$string['builderuntitlednode'] = 'Untitled node';
$string['buildercurrentvalue'] = 'Current value: {$a}';
$string['buildernodeupdated'] = 'Node updated in JSON draft.';
$string['buildercouldnotupdateposition'] = 'CommandRoom builder could not update JSON position.';
$string['buildercouldnotsyncmatrix'] = 'CommandRoom builder could not sync relationship matrix.';
$string['buildercouldnotupdaterelationships'] = 'CommandRoom builder could not update relationships.';
$string['buildercouldnotparsejson'] = 'CommandRoom builder could not parse JSON draft.';
// ===== Group leadership =====
$string['groupleaderheader'] = 'Group leadership';
$string['groupleaderintro'] = 'Use Moodle course groups for this activity. Choose one enrolled group member to act as leader for each group. Group members can propose values; the selected leader chooses the group decision.';
$string['groupleaderforgroup'] = 'Leader for {$a}';
$string['groupleadernone'] = 'No leader selected yet';
$string['nogroupsavailable'] = 'No Moodle groups were found in this course. Create course groups first, then return here to choose group leaders.';
$string['notingroup'] = 'You are not currently in a Moodle group for this activity.';
$string['groupleadernotassigned'] = 'No group leader has been assigned for your group yet.';
$string['assignedgroupleader'] = 'Assigned group leader: {$a}';
$string['privacy:metadata:commandroom_group_leaders'] = 'Information about the selected leader for each Moodle group in a Situation Room activity.';
$string['privacy:metadata:commandroom_group_leaders:leaderid'] = 'The ID of the user assigned as group leader.';
$string['privacy:metadata:commandroom_group_leaders:groupid'] = 'The Moodle group for which the leader is assigned.';
$string['privacy:metadata:commandroom_group_leaders:timecreated'] = 'The time the group leader assignment was created.';
$string['privacy:metadata:commandroom_group_leaders:timemodified'] = 'The time the group leader assignment was last modified.';
