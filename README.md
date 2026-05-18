# CommandRoom (`mod_commandroom`)

**CommandRoom** is a Moodle activity module for building and running simple, teacher-authored systems models.

It is designed for educators who want learners to practise decision-making inside a safe simulation before they face the same kind of trade-off in the real world. The purpose is not to create perfect mathematical models. The purpose is to help teachers create **useful models** that reveal patterns, delays, trade-offs, feedback, and unintended consequences.

> All models are wrong, but some are useful. CommandRoom helps teachers build useful learning models.

---

## Current status

- **Component:** `mod_commandroom`
- **Working title in the UI:** Situation Room / CommandRoom
- **Current release stage:** alpha / release candidate preparation
- **Target audience:** teachers, instructional designers, trainers, and non-programmers
- **Primary authoring mode:** guided presets, Builder interface, and JSON import/export
- **Primary teaching mode:** students propose values, a leader chooses values, and the system advances over time

---

## What CommandRoom does

CommandRoom lets a teacher create a system made of:

- **nodes** — things that change or influence the system;
- **stocks** — values that carry forward over time;
- **flows** — values that add to or subtract from stocks;
- **computed values** — values calculated from other nodes;
- **student-controlled levers** — values learners can propose and discuss;
- **relationships** — visual cause-and-effect links between nodes;
- **presets** — ready-made archetypical systems teachers can adapt;
- **icons** — built-in SVG icons used to make nodes visually meaningful.

The teacher can start from a preset such as **Boom and Collapse**, **Hospital Patient Backlog**, **Tragedy of the Commons**, or **Online Course Engagement**, then refine the system in the Builder.

---

## Teacher workflow

A typical teacher workflow is:

1. Add a new CommandRoom activity in a Moodle course.
2. Choose a starter pattern from the question:

   > **When not managed, how would this system tend to behave over time?**

3. Review or edit the seeded system brief.
4. Save and display.
5. Open the Builder.
6. Move nodes, refine relationships, adjust calculations, and choose icons.
7. Publish and use the system.
8. Students propose values for student-controlled nodes.
9. The group leader chooses values.
10. The simulation advances one iteration at a time.
11. Learners interpret the War Room table and visual system.

---

## First-release preset archetypes

The first-release preset collection should be small enough not to overwhelm teachers, but rich enough to show CommandRoom’s range.

Recommended first-release presets:

| Preset key | Behaviour pattern | Teaching idea |
|---|---|---|
| `bank_balance_growth` | Growth / compounding accumulation | Interest and deposits grow a balance over time. |
| `optimum_point_trench` | Optimum point | More workers help up to a point, then coordination reduces productivity. |
| `boom_collapse_public_interest` | Boom and collapse | Public interest grows, hidden pressure builds, then interest collapses. |
| `oscillation_seasonal_interest` | Oscillation / seasonality | A system rises and falls repeatedly over time. |
| `hospital_patient_backlog` | Backlog build-up and recovery | Arrivals add to a backlog; capacity reduces it; training has short- and long-term effects. |
| `tragedy_of_the_commons` | Shared-resource depletion | Individual extraction can damage a shared resource and future benefit. |
| `success_to_the_successful` | Advantage compounds | Small early advantages can attract more resources and widen gaps. |
| `online_course_engagement` | Engagement drift and recovery | Interactivity, feedback, and social presence influence engagement and completion. |

Presets should live in:

```text
mod/commandroom/presets/
```

The preset index should live at:

```text
mod/commandroom/presets/presets.json
```

---

## Installation

For a manual Moodle install:

1. Copy the plugin folder to:

   ```text
   mod/commandroom
   ```

2. Visit:

   ```text
   Site administration → Notifications
   ```

3. Allow Moodle to install or upgrade the plugin.
4. Create or open a course.
5. Add a new **CommandRoom / Situation Room** activity.
6. Select a preset and save.

For development releases, always test:

- fresh install;
- upgrade from the previous version;
- creating a new activity;
- selecting a preset;
- opening Builder;
- saving Builder output;
- running a simulation;
- deleting an activity and confirming related data is cleaned up.

---

# CommandRoom System Writer Guide

This section defines the authoring specification for creating valid CommandRoom JSON systems.

It is intended for:

- teachers;
- instructional designers;
- non-programmers;
- AI assistants generating systems;
- future CommandRoom system libraries;
- developers maintaining presets;
- teachers who create useful CommandRoom models are encouraged to export, adapt, improve, and re-share their JSON systems, helping to build a shared commons of practical teaching simulations for other educators.
---

## 1. High-level JSON structure

A valid CommandRoom JSON file uses this structure:

```json
{
  "metadata": {},
  "nodes": [],
  "edges": [],
  "shocks": []
}
```

| Section | Required | Purpose |
|---|---:|---|
| `metadata` | Yes | Simulation settings and preset identity |
| `nodes` | Yes | Stocks, flows, variables, and computed values |
| `edges` | Optional | Visible cause-and-effect relationships |
| `shocks` | Optional | Scheduled or random events |

---

## 2. Metadata schema

Example:

```json
{
  "metadata": {
    "plugin": "mod_commandroom",
    "pluginname": "Situation Room",
    "version": "first-release-archetype",
    "archetype": "backlog_reduction",
    "timesteplabel": "day",
    "stepduration": 1,
    "stepdurationunit": "day",
    "totaliterations": 30,
    "useshocks": false,
    "presetkey": "hospital_patient_backlog",
    "presettitle": "Backlog build-up and recovery",
    "firstrelease": true
  }
}
```

| Field | Required | Type | Meaning |
|---|---:|---|---|
| `plugin` | Recommended | string | Usually `mod_commandroom` |
| `pluginname` | Recommended | string | Human-readable name |
| `version` | Recommended | string | Preset/spec version |
| `archetype` | Recommended | string | Behaviour pattern |
| `timesteplabel` | Yes | string | Label for one iteration, e.g. `week` |
| `stepduration` | Yes | integer | Length of one step |
| `stepdurationunit` | Yes | string | `iteration`, `hour`, `day`, `week`, `month`, `quarter`, `year` |
| `totaliterations` | Yes | integer | Number of iterations to run |
| `useshocks` | Yes | boolean/integer | Whether shocks are used |
| `presetkey` | Recommended | string | Key used in `presets.json` |
| `presettitle` | Recommended | string | Teacher-facing title |

---

## 3. Node schema

Nodes represent values in the system.

Example:

```json
{
  "ref": "patient_backlog",
  "name": "Patient Backlog",
  "description": "Patients waiting to be processed by the clinic.",
  "unitlabel": "patients",
  "interpretation": "Backlog increases with arrivals and decreases when patients are processed.",
  "nodetype": "stock",
  "initialvalue": 80,
  "minvalue": 0,
  "maxvalue": 500,
  "studentcontrolled": false,
  "visibletostudents": true,
  "displayorder": 1,
  "visual": {
    "type": "scaling_icon",
    "icon": "default",
    "minvalue": 0,
    "maxvalue": 500,
    "minsize": 50,
    "maxsize": 150,
    "x": 6,
    "y": 5,
    "w": 4,
    "h": 3
  }
}
```

| Field | Required | Type | Meaning |
|---|---:|---|---|
| `ref` | Yes | string | Unique stable node reference |
| `name` | Yes | string | Display name |
| `description` | Recommended | string | Teacher/student explanation |
| `unitlabel` | Recommended | string | Unit, e.g. `patients/day` |
| `interpretation` | Recommended | string | Meaning of the value |
| `nodetype` | Yes | string | `stock`, `flow`, `computed`, or `variable` |
| `initialvalue` | Yes | number | Starting value |
| `minvalue` | Yes | number | Minimum allowed runtime value |
| `maxvalue` | Yes | number | Maximum allowed runtime value |
| `studentcontrolled` | Yes | boolean/integer | Whether learners propose this value |
| `visibletostudents` | Yes | boolean/integer | Whether learners see this node |
| `displayorder` | Yes | integer | Ordering in tables and authoring |
| `visual` | Recommended | object | Icon and layout settings |
| `updateconfig` | Conditional | object | Used mainly by stock nodes |
| `calculation` | Conditional | object | Used by computed nodes |

---

## 4. Node types

### `stock`

A stock carries value forward over time.

Examples:

- Patient Backlog
- Bank Balance
- Public Interest
- Shared Resource
- Learner Engagement
- Completion Progress

Stocks usually need an `updateconfig`.

### `flow`

A flow adds to or subtracts from a stock.

Examples:

- Patient Arrivals
- Patients Processed
- Deposits
- Expenses
- New Interest
- Interest Lost to Fatigue

### `variable`

A variable is usually a parameter or lever.

Examples:

- Interest Rate
- Staff Available
- Training Hours
- Extraction Effort
- Feedback Frequency

A variable may be student-controlled or fixed.

### `computed`

A computed node is calculated from other nodes.

Examples:

- Service Capacity
- Patient Waiting Time
- Interest Earned
- Total Extraction
- Dropout Risk

---

## 5. Student-controlled nodes

Use:

```json
"studentcontrolled": true
```

A student-controlled node:

- appears in the proposal panel;
- allows learners to submit proposed values;
- allows a leader to choose a value;
- uses the leader decision in the next iteration.

Use student-controlled nodes for real learning choices, not for every parameter.

Good examples:

- Staff Available
- Training Hours
- Extraction Effort
- Conservation Effort
- Interactive Activities
- Feedback Frequency

---

## 6. Relationship layer vs calculation layer

CommandRoom has two related but different layers.

### Relationship layer

Relationships are stored in `edges`.

They answer:

> Which node influences which other node?

They are used for:

- the relationship matrix;
- visual arrows;
- causal discussion;
- conceptual clarity.

### Calculation layer

Calculations are stored in `updateconfig` or `calculation`.

They answer:

> How is this node’s value calculated?

A node may be used in a calculation even if it has few visible arrows in the relationship matrix.

For example, in a hospital backlog model:

```text
Base Capacity = Staff Available × Patients per Staff Member
Training Burden = Training Hours × Training Burden Rate
Service Capacity = Base Capacity + Training Benefit - Training Burden
Patient Waiting Time = Patient Backlog ÷ Service Capacity
```

Some nodes are helper values or parameters. They do not always need many visible relationships.

Recommended help text for teachers:

> The relationship matrix shows the main visible cause-and-effect links. Some nodes are helper values or calculation parameters and may not need visible arrows. A node can still be used in the calculation layer even if it has few or no relationship arrows.

---

## 7. Visual configuration

CommandRoom currently supports built-in preset icons only.

Icons should live in:

```text
mod/commandroom/pix/icons/
```

Only the icon name is stored in JSON.

Example:

```json
"icon": "patient"
```

The system loads:

```text
mod/commandroom/pix/icons/patient.svg
```

Do not store user-uploaded SVG files in `pix/icons/`. That folder belongs to the plugin package. Future versions may use Moodle’s File API for per-activity or per-node custom icons.

### Scaling icon

One icon grows or shrinks as the value changes.

```json
"visual": {
  "type": "scaling_icon",
  "icon": "patient",
  "minvalue": 0,
  "maxvalue": 500,
  "minsize": 50,
  "maxsize": 150,
  "x": 6,
  "y": 5,
  "w": 4,
  "h": 3
}
```

### Repeated icon

Multiple icons appear as the value increases.

```json
"visual": {
  "type": "repeated_icon",
  "icon": "patient",
  "unitvalue": 10,
  "maxicons": 80,
  "iconsize": 36,
  "layout": "grid",
  "x": 6,
  "y": 5,
  "w": 4,
  "h": 3
}
```

| Visual field | Meaning |
|---|---|
| `type` | `scaling_icon` or `repeated_icon` |
| `icon` | Built-in icon name without `.svg` |
| `minvalue` | Value mapped to smallest scaling icon |
| `maxvalue` | Value mapped to largest scaling icon |
| `minsize` | Smallest icon size |
| `maxsize` | Largest icon size |
| `unitvalue` | Units represented by one repeated icon |
| `maxicons` | Maximum repeated icons shown |
| `iconsize` | Size of repeated icons |
| `layout` | Usually `grid` |
| `x`, `y`, `w`, `h` | Builder layout coordinates |

---

## 8. Stock update rules

Stocks use `updateconfig`.

### `stock_with_rate`

This is the main stock accumulation mode.

General behaviour:

```text
new stock = base value + inflows - outflows + optional rate growth
```

Example:

```json
"updateconfig": {
  "mode": "stock_with_rate",
  "base": "self",
  "inflows": ["deposits", "interest_earned"],
  "outflows": ["expenses"],
  "adds": ["deposits", "interest_earned"],
  "subtracts": ["expenses"]
}
```

Teacher-facing interpretation:

```text
Balance = prior Balance + Deposits + Interest Earned - Expenses
```

| Field | Meaning |
|---|---|
| `mode` | Use `stock_with_rate` |
| `base` | `self` to use previous value, `zero` to restart from zero |
| `inflows` | Node refs added each iteration |
| `outflows` | Node refs subtracted each iteration |
| `rate` | Optional rate node; adds `base × rate` |
| `adds` | Friendly alias for `inflows` |
| `subtracts` | Friendly alias for `outflows` |

Use both `inflows/outflows` and `adds/subtracts` in exported presets for compatibility and clarity.

---

## 9. Calculation rules

Computed nodes use `calculation`.

### 9.1 Multiply

```json
"calculation": {
  "type": "multiply",
  "left": {"kind": "node", "ref": "balance"},
  "right": {"kind": "node", "ref": "interest_rate"}
}
```

Teacher-facing interpretation:

```text
Interest Earned = Balance × Interest Rate
```

### 9.2 Divide

```json
"calculation": {
  "type": "divide",
  "numerator": {"kind": "node", "ref": "patient_backlog"},
  "denominator": {"kind": "node", "ref": "service_capacity"}
}
```

Teacher-facing interpretation:

```text
Patient Waiting Time = Patient Backlog ÷ Service Capacity
```

The engine should handle division by zero safely.

### 9.3 Add

```json
"calculation": {
  "type": "add",
  "items": [
    {"kind": "node", "ref": "a"},
    {"kind": "node", "ref": "b"}
  ]
}
```

### 9.4 Sum with positive and negative factors

This is the most flexible first-release formula type.

```json
"calculation": {
  "type": "sum",
  "items": [
    {
      "factor": 1,
      "operand": {"kind": "node", "ref": "base_capacity"}
    },
    {
      "factor": 1,
      "operand": {"kind": "node", "ref": "training_benefit"}
    },
    {
      "factor": -1,
      "operand": {"kind": "node", "ref": "training_burden"}
    }
  ]
}
```

Teacher-facing interpretation:

```text
Service Capacity = Base Capacity + Training Benefit - Training Burden
```

### 9.5 Percentage

```json
"calculation": {
  "type": "percentage",
  "value": {"kind": "node", "ref": "learner_engagement"},
  "rate": {"kind": "number", "value": 0.12}
}
```

Teacher-facing interpretation:

```text
Completion Gain = 12% of Learner Engagement
```

### 9.6 Linear

```json
"calculation": {
  "type": "linear",
  "input": {"kind": "node", "ref": "study_time"},
  "slope": 2,
  "intercept": 0
}
```

Teacher-facing interpretation:

```text
Output = slope × input + intercept
```

### 9.7 Diminishing returns

Use when more input helps, but each extra unit helps less.

```json
"calculation": {
  "type": "diminishing_returns",
  "input": {"kind": "node", "ref": "training_hours"},
  "maximum": 100,
  "rate": 0.15
}
```

Teacher-facing meaning:

```text
The result improves at first, then starts to level off.
```

### 9.8 Optimum point

Use when too little is bad and too much is also bad.

```json
"calculation": {
  "type": "optimum_point",
  "input": {"kind": "node", "ref": "workers"},
  "optimum": 10,
  "maximum": 100,
  "decline": 0.7
}
```

Teacher-facing meaning:

```text
Output is best near the optimum value and gets worse away from it.
```

Example:

```text
Daily Trench Progress is highest at around 10 workers.
```

### 9.9 Bell curve

Use when performance is highest near a centre point and falls away on both sides.

```json
"calculation": {
  "type": "bell_curve",
  "input": {"kind": "node", "ref": "training_hours"},
  "mean": 20,
  "amplitude": 100,
  "spread": 5
}
```

Teacher-facing meaning:

```text
The best result happens near the middle. Too little or too much reduces the result.
```

### 9.10 Random range

```json
"calculation": {
  "type": "random_range",
  "min": -2,
  "max": 3
}
```

Use sparingly. Randomness can be useful, but it can also make teacher testing harder.

---

## 10. Operand schema

Calculation operands can be numbers or nodes.

### Number operand

```json
{"kind": "number", "value": 0.12}
```

### Node operand

```json
{"kind": "node", "ref": "learner_engagement"}
```

The importer may normalise node refs into internal node IDs. Export should convert them back to refs.

---

## 11. Edge schema

Edges define visible cause-and-effect relationships.

Example:

```json
{
  "source": "staff_available",
  "target": "base_capacity",
  "relationtype": "linear",
  "strength": 1,
  "delayiterations": 0,
  "polarity": "positive",
  "label": "more staff process more patients",
  "loopgroup": "capacity",
  "curvature": 0,
  "visibletostudents": true
}
```

| Field | Required | Type | Meaning |
|---|---:|---|---|
| `source` | Yes | string | Source node ref |
| `target` | Yes | string | Target node ref |
| `relationtype` | Yes | string | Usually `linear` |
| `strength` | Yes | number | Influence amount / visual metadata |
| `delayiterations` | Yes | integer | Delay metadata |
| `polarity` | Recommended | string | `positive`, `negative`, or `neutral` |
| `label` | Recommended | string | Visible relationship label |
| `loopgroup` | Optional | string | Feedback-loop grouping |
| `curvature` | Optional | integer | Arrow curvature |
| `visibletostudents` | Yes | boolean/integer | Show/hide relationship |

### Edge polarity

| Polarity | Meaning |
|---|---|
| `positive` | Source and target move in the same direction |
| `negative` | Source and target move in opposite directions |
| `neutral` | Descriptive or uncertain link |

---

## 12. Shocks

Shocks modify node values during a run.

### Scheduled shock

```json
{
  "node": "demand",
  "shocktype": "scheduled",
  "iterationno": 6,
  "adjustment": 20,
  "visibletostudents": true,
  "description": "Unexpected demand increase"
}
```

### Random range shock

```json
{
  "node": "weather_effect",
  "shocktype": "random_range",
  "minadjustment": -5,
  "maxadjustment": 5,
  "applyeveryiteration": true,
  "visibletostudents": false,
  "description": "Random weekly fluctuation"
}
```

Use shocks when you want uncertainty, surprise, or environmental pressure.

---

## 13. Behaviour-over-time patterns

CommandRoom presets should be chosen because they produce a clear behaviour-over-time pattern.

The first form question should be:

> **When not managed, how would this system tend to behave over time?**

Good first-release patterns:

| Pattern | Simple graph behaviour | Example |
|---|---|---|
| Growth | Rises faster over time | Interest, reputation, demand |
| Depletion | Runs down | Cash, inventory, shared resource |
| Optimum point | Improves, then worsens | Team size, workload, training hours |
| Boom and collapse | Rises, peaks, then crashes | Public interest, over-expansion |
| Oscillation | Repeated rise and fall | Seasonal demand, workload cycles |
| Backlog | Accumulates unless capacity catches up | Hospital patients, support tickets |
| Success to the successful | Gaps widen | Markets, school streaming |
| Tragedy of the commons | Shared resource is overused | Grazing, fisheries, common funds |

---

## 14. Engine behaviour in practical terms

Each run begins with iteration 0, which records the baseline state.

Student or leader decisions affect later iterations.

At each advance, CommandRoom broadly does this:

1. Reads the current system state.
2. Applies student/leader decisions to student-controlled nodes.
3. Applies shocks if configured.
4. Computes calculated nodes and stock updates according to their configs.
5. Applies configured min/max boundaries.
6. Stores the next iteration.
7. Displays the War Room history.

Authoring advice:

- Test every preset in the War Room.
- Confirm the pattern appears within the chosen iteration count.
- Keep `initialvalue`, `minvalue`, and `maxvalue` realistic.
- Use boundaries to prevent impossible values such as negative patients or negative remaining work.
- Avoid overfitting. A preset should teach the pattern, not pretend to be a full scientific model.

---

## 15. Preset index: `presets.json`

The preset index lets `mod_form.php` show teacher-friendly archetype choices.

Recommended structure:

```json
[
  {
    "key": "hospital_patient_backlog",
    "title": "Backlog build-up and recovery",
    "cardtitle": "Backlog builds unless capacity keeps up",
    "question": "When not managed, how would this system tend to behave over time?",
    "graphshape": "backlog",
    "graphdescription": "A backlog grows when arrivals exceed processing capacity.",
    "example": "A clinic receives patients each day. Staff process patients, but training has a short-term cost and a longer-term benefit.",
    "file": "hospital_patient_backlog.json",
    "order": 5,
    "firstrelease": true,
    "primary_stock": "Patient Backlog",
    "timesteplabel": "day",
    "stepduration": 1,
    "stepdurationunit": "day",
    "totaliterations": 30,
    "sparkline_svg_path": "M 5 45 C 20 40, 30 35, 45 28 C 60 20, 75 18, 95 22",
    "seed_text": {
      "systembrief": "A clinic has patients arriving each day. Staff process patients. Training improves service rate, but takes staff away from service in the short term.",
      "studentdecision": "Students decide staff availability and training hours.",
      "learninggoal": "Students should learn that service systems involve trade-offs between immediate throughput and longer-term capacity.",
      "riskychoice": "Avoid training and focus only on today’s queue.",
      "safechoice": "Accept a short-term training burden to improve future service capacity."
    },
    "node_inventory": [
      "Patient Backlog (stock)",
      "Patient Arrivals (flow)",
      "Staff Available (variable)",
      "Training Hours (variable)",
      "Service Capacity (computed)",
      "Patients Processed (flow)",
      "Patient Waiting Time (computed)"
    ],
    "notes_for_teacher": "Use this preset to discuss capacity, queues, and delayed benefits."
  }
]
```

Keep the first-release list manageable. Eight well-chosen choices are better than twelve confusing ones.

---

## 16. Good preset design

A good preset should:

- show a recognisable behaviour-over-time pattern;
- contain a small number of meaningful student decisions;
- use clear node names;
- use simple calculations;
- include useful descriptions and interpretations;
- include visible relationships that support discussion;
- avoid too many helper nodes unless they are pedagogically useful;
- run successfully from a fresh activity instance;
- produce visible change in the War Room within the planned number of iterations.

---

## 17. What not to do

Avoid:

- pretending that the model is mathematically perfect;
- creating large systems with too many nodes for a first-time teacher;
- making every node student-controlled;
- using relationship arrows as a substitute for calculation rules;
- using hidden helper nodes without descriptions;
- allowing impossible values when `minvalue`/`maxvalue` could prevent them;
- overusing random shocks in first-release presets;
- shipping too many archetypes in the first screen.

---

## 18. AI prompt for generating a preset

Use this prompt with an AI assistant:

> Create a CommandRoom JSON system for `mod_commandroom`.
>
> The system should model the behaviour-over-time pattern: **[INSERT PATTERN]**.
>
> Use the current CommandRoom schema:
>
> - metadata
> - nodes
> - edges
> - shocks
> - visual config using built-in icons
> - stock update rules using `stock_with_rate`
> - calculations using supported types only
> - student-controlled variables where useful
>
> The model should be simple, teachable, and useful rather than mathematically perfect.
>
> Include clear node names, descriptions, interpretations, min/max values, and a visible behaviour in the War Room within the selected number of iterations.

---

## 19. Validation checklist for JSON systems

Before shipping or importing a preset, confirm:

- `metadata` exists;
- `nodes` exists and is an array;
- `edges` exists and is an array, even if empty;
- `shocks` exists and is an array, even if empty;
- every node has a unique `ref`;
- every edge source/target refers to a valid node ref;
- every shock refers to a valid node ref;
- every calculation operand refers to a valid node ref or number;
- every stock with accumulation has `updateconfig`;
- rates are decimals where appropriate;
- `minvalue` and `maxvalue` are sensible;
- `studentcontrolled` and `visibletostudents` are boolean-like;
- visual config uses a safe built-in icon name;
- the War Room output shows the intended behaviour;
- the preset still works after save, Builder edit, and re-save.

---

## 20. Moodle compliance notes

Before public release, review:

- no hard-coded user-facing strings in PHP or JavaScript;
- all user-facing text should move to `lang/en/commandroom.php`;
- AMD source and minified files should both be present;
- no runtime artefacts such as `error_log`, `.bak`, or temporary ZIP files;
- `db/install.xml` must validate and must not contain duplicate table definitions;
- `db/upgrade.php` steps must match `version.php`;
- privacy provider must cover stored user data;
- external services must use Moodle External API patterns;
- all PHP, JS, Mustache, and other files should have Moodle GPL headers where required;
- repository name should follow Moodle convention: `moodle-mod_commandroom`.

---

## 21. Core philosophy

CommandRoom is not a spreadsheet and not a professional systems-dynamics engine.

It is a Moodle learning activity that helps teachers create useful, discussable system models.

Its purpose is to let learners:

- make decisions;
- see consequences;
- notice patterns;
- discuss trade-offs;
- experience safe failure;
- improve judgment before acting in the real world.

---

## 22. Short README summary

CommandRoom is a Moodle activity module for teacher-authored systems simulations. Teachers start from behaviour-over-time presets, adapt the model in a visual Builder, select icons for nodes, and let learners propose decisions. A group leader selects values, the system advances over iterations, and the War Room shows how the system changes over time.

CommandRoom is designed for practical education and training, not perfect mathematical modelling. It helps learners practise decisions safely before they fail in real organisations, classrooms, clinics, communities, or ecosystems.

---

END OF DOCUMENT
