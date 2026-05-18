# COMMANDROOM SYSTEM WRITER GUIDE (v2)

This document defines the **official authoring specification** for creating valid JSON systems for `mod_commandroom`.

It is intended for:

- teachers
- instructional designers
- non-programmers
- AI assistants generating systems
- future CommandRoom system libraries

This guide explains:

1. what a CommandRoom system is
2. how JSON must be structured
3. how the engine interprets authored systems
4. what calculations are currently supported
5. what JSON can and cannot control

---

# 1. Core Concept

A CommandRoom system models a changing system over time.

Each system contains:

- **Nodes** (variables)
- **Iterations** (time steps)
- **Relationships** (influence between nodes)
- **Student decisions**
- **Optional shocks**
- **Update rules**

Each iteration produces a new system state.

A system is defined entirely by JSON.

The JSON does not perform calculations itself.

Instead:

> JSON describes a system, and the CommandRoom engine executes the system according to the rules defined here.

---

# 2. High-Level Structure

A valid CommandRoom JSON file must contain:

```json
{
  "metadata": { },
  "nodes": [ ],
  "edges": [ ],
  "shocks": [ ]
}
```

## Required Sections

| Section | Required | Purpose |
|--------|----------|----------|
| metadata | Yes | Simulation settings |
| nodes | Yes | Variables in the system |
| edges | Optional | Relationships between nodes |
| shocks | Optional | Random or scheduled events |

---

# 3. Metadata Schema

Metadata defines how the simulation behaves over time.

## Required Structure

```json
"metadata": {
  "timesteplabel": "month",
  "stepduration": 1,
  "stepdurationunit": "month",
  "totaliterations": 12,
  "useshocks": 1
}
```

## Metadata Fields

| Field | Required | Type | Meaning |
|-------|----------|------|---------|
| timesteplabel | Yes | string | Human-readable label |
| stepduration | Yes | integer | Length of one step |
| stepdurationunit | Yes | string | Unit of duration |
| totaliterations | Yes | integer | Number of iterations |
| useshocks | Yes | integer | 0 or 1 |

## Example

```json
"metadata": {
  "timesteplabel": "quarter",
  "stepduration": 3,
  "stepdurationunit": "month",
  "totaliterations": 8,
  "useshocks": 1
}
```

---

# 4. Node Schema

Nodes represent variables in the system.

Each node must have a unique reference ID.

## Required Structure

```json
{
  "ref": "n1",
  "name": "Balance",
  "nodetype": "stock",
  "initialvalue": 100,
  "minvalue": 0,
  "maxvalue": 100000,
  "studentcontrolled": 0,
  "visibletostudents": 1,
  "displayorder": 1
}
```

## Node Fields

| Field | Required | Type | Meaning |
|-------|----------|------|---------|
| ref | Yes | string | Unique node ID |
| name | Yes | string | Display name |
| nodetype | Yes | string | stock / flow / computed |
| initialvalue | Yes | number | Starting value |
| minvalue | Yes | number | Minimum allowed |
| maxvalue | Yes | number | Maximum allowed |
| studentcontrolled | Yes | integer | 0 or 1 |
| visibletostudents | Yes | integer | 0 or 1 |
| displayorder | Yes | integer | UI order |
| updateconfig | Conditional | object | Required for stocks |

---

# 5. Node Types

## stock

A stock persists across iterations.

Examples:

- money balance
- population
- energy reserve
- inventory
- debt

Stock nodes generally require an update rule.

---

## flow

A flow represents something entering or leaving a stock.

Examples:

- savings
- spending
- migration
- rainfall
- fuel usage

Flows are often student-controlled.

---

## computed

Computed nodes are automatically calculated.

Examples:

- inflation
- interest rate
- confidence score
- system pressure

Computed nodes are usually not student-controlled.

---

# 6. Student-Controlled Nodes

Student-controlled nodes allow learners to propose values.

Use:

```json
"studentcontrolled": 1
```

These values:

- are proposed by learners
- appear in governance
- are chosen by the leader
- affect the next iteration

---

# 7. Relationship Schema

Relationships connect nodes.

Relationships allow one node to influence another.

## Required Structure

```json
{
  "source": "n1",
  "target": "n5",
  "relationtype": "linear",
  "strength": 1.0,
  "delayiterations": 0,
  "functionconfig": "",
  "visibletostudents": 1
}
```

## Relationship Fields

| Field | Required | Type | Meaning |
|-------|----------|------|---------|
| source | Yes | string | Source node ref |
| target | Yes | string | Target node ref |
| relationtype | Yes | string | Type of relationship |
| strength | Yes | number | Influence amount |
| delayiterations | Yes | integer | Delay before effect |
| functionconfig | Yes | string | Optional config |
| visibletostudents | Yes | integer | 0 or 1 |

---

# 8. Supported Relationship Types

## linear

Direct proportional influence.

Example:

- more savings → higher balance
- more inflation → lower spending power

---

## inverse

Opposite influence.

Example:

- higher temperature → lower heating demand

---

## nonlinear

Nonlinear influence.

Used for future expansion.

May require `functionconfig`.

---

# 9. Update Rules

Update rules define how stocks evolve over time.

Stock nodes should define:

```json
"updateconfig": { }
```

---

# 10. Supported Update Modes (v2)

## stock_with_rate

Primary accumulation rule.

Formula:

```text
new = old + inflows + (old × rate)
```

Example:

```json
"updateconfig": {
  "mode": "stock_with_rate",
  "base": "self",
  "inflows": ["n3"],
  "rate": "n2"
}
```

### Fields

| Field | Meaning |
|-------|----------|
| mode | Calculation type |
| base | Starting stock |
| inflows | Flow node refs |
| rate | Rate node ref |

---

# 11. Planned Update Modes

These are future-supported modes.

Not yet guaranteed.

## sum

```text
new = n1 + n2 + n3
```

## difference

```text
new = n1 − n2
```

## product

```text
new = n1 × n2
```

## ratio

```text
new = n1 ÷ n2
```

## power

```text
new = n1 ^ n2
```

## weighted_sum

```text
new = (n1 × weight1) + (n2 × weight2)
```

---

# 12. Shock Schema

Shocks modify values before calculations occur.

Shocks may be:

- scheduled
- random

---

## Scheduled Shock

```json
{
  "node": "n2",
  "iterationno": 3,
  "adjustment": 0.01
}
```

### Meaning

At iteration 3:

```text
node value += adjustment
```

---

## Random Range Shock

```json
{
  "node": "n2",
  "shocktype": "random_range",
  "minadjustment": -0.005,
  "maxadjustment": 0.01,
  "applyeveryiteration": 1,
  "visibletostudents": 0,
  "description": "Random fluctuation"
}
```

### Meaning

Each iteration:

```text
random value between minadjustment and maxadjustment
```

is added to the node.

---

# 13. Engine Interpretation Order

This is extremely important.

CommandRoom processes each iteration in the following order.

## Iteration Lifecycle

For each iteration:

### Step 1
Load previous iteration values.

### Step 2
Apply shocks.

### Step 3
Apply leader decisions.

### Step 4
Update stock nodes using update rules.

### Step 5
Apply relationships.

### Step 6
Compute computed nodes.

### Step 7
Store results.

### Step 8
Advance iteration counter.

---

# 14. Units and Meaning

Rates must be expressed as decimals.

Correct:

- 0.01 = 1%
- 0.05 = 5%
- -0.02 = -2%

Incorrect:

- 1
- 5
- -2

---

# 15. Design Principles

A good CommandRoom system should:

- be understandable
- contain at least one decision variable
- produce visible change over time
- encourage team discussion
- demonstrate trade-offs
- reveal patterns across iterations

---

# 16. What NOT to Do

Avoid:

- using stocks without update rules
- using flows to represent long-term storage
- using whole numbers for percentages
- creating systems with no decision variables
- relying on relationships for complex formulas

---

# 17. JSON Capability Boundaries

JSON can currently define:

- metadata
- nodes
- relationships
- shocks
- update modes
- student decisions
- system structure

JSON cannot yet directly define:

- arbitrary formulas
- nested logic
- loops
- conditional rules
- dynamic node creation
- user-written code

These may appear in future versions.

---

# 18. Example Pattern

Investment system:

- Balance (stock)
- Interest Rate (computed)
- Saving (flow)

Goal:

Grow wealth through team decisions.

---

# 19. AI Prompt Template

Use this prompt with AI:

> Create a CommandRoom JSON system using the v2 schema.
>
> Include:
>
> - valid metadata
> - stock, flow, and computed nodes
> - student-controlled variables
> - valid relationships
> - shocks if appropriate
> - updateconfig using supported modes
> - realistic iteration behaviour
>
> Follow COMMANDROOM SYSTEM WRITER GUIDE v2 exactly.

---

# 20. Validation Checklist

Before importing a JSON system, confirm:

- metadata exists
- all nodes have unique refs
- stock nodes define updateconfig
- edges reference valid node refs
- shocks reference valid node refs
- rates use decimals
- displayorder is unique
- studentcontrolled values are 0 or 1
- visibletostudents values are 0 or 1

---

# 21. Future Extensions

Possible future capabilities:

- conditional rules
- thresholds
- piecewise functions
- reusable functions
- formula parser
- scenario branching
- event-triggered state changes
- policy interventions
- multi-layer systems

---

# 22. Core Philosophy

CommandRoom is designed to allow:

> non-programmers to author interactive systems using structured JSON.

The goal is not to replace coding.

The goal is to make systems modelling accessible.

---

END OF DOCUMENT

