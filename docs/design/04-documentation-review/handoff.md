# Documentation review — final state

What the corpus looks like after the initiative, and what was deliberately
left open. Scope is in `spec.md`, rationale in `decisions.md`, verification in
`review.md`.

## The skill corpus

Ten skills under `system/skills/`, all `mgr-*`:

`mgr-auth`, `mgr-cache-websockets`, `mgr-cli-modules`, `mgr-code-style`,
`mgr-helpers-libraries`, `mgr-live-probes`, `mgr-migrations`, `mgr-models`,
`mgr-rest-controller`, `mgr-web-controllers`.

**Shape.** Frontmatter, `# Manager <Topic> (<governing class>)`, a
`> **Prerequisite:**` blockquote naming `mgr-code-style`, one orientation
paragraph, a `Source of truth` list, task sections, a closing
`## Anti-patterns`. `mgr-models`, `mgr-rest-controller` and
`mgr-web-controllers` are the reference implementations.

**Census, and why it is not ten out of ten.** `Source of truth` appears in
eight; `## Anti-patterns` in eight. The four omissions are correct and
recorded, not backlog:

| Skill | Omits | Because |
|---|---|---|
| `mgr-code-style` | Prerequisite, Source of truth, Anti-patterns | It is the baseline every other skill chains off; its `Hard rules` list is its own closing block and already states wrong-versus-right per line |
| `mgr-helpers-libraries` | Source of truth | Catalog-shaped — every table row carries its own vendor path |
| `mgr-auth` | Anti-patterns | `DO NOT REGRESS` is its closing block, in the register security invariants need |

The full reasoning, the equivalent-shape test that produces this table, and
every writing rule the corpus now follows are in
`docs/development/skill-authoring.md`. That document is the durable output of
objectives 6 and 7; this section is only the state it describes.

**Verified against source, so it need not be re-derived:** every method
signature in `mgr-models` and `mgr-rest-controller` was checked against the
framework. All exist. Three parameter names had rotted and were corrected —
`query(string $query, ?array $arguments)`,
`set_override_column(string $column_name)`, and `load_view()`'s third
parameter, `$layout_path`. That was the entire yield of the audit, which is
why parameter names lead the review checklist.

**Two behaviors were documented that had never been written down**, both
because they are invisible from any single call frame:

- The difference between `auth_override = 'none'` and `'allow'` — a
  by-reference flag three frames deep. Under `'allow'` a key is read and
  looked up but the request is permitted regardless, so `group_methods`
  gating applies only to identified callers.
- `Enum` columns are native `ENUM` on MySQL only; on the other three engines
  the builder emits a string type, so the constraint silently does not exist.

## Documents written or restructured

| Document | State |
|---|---|
| `docs/development/skill-authoring.md` | New. Writing and reviewing skills, including the review checklist for validating one written by a weaker model |
| `docs/development/framework-workflow.md` | New. The development loop, the quality gates, and where a change goes — including the three-file shape of a framework library |
| `sample/docs/development/upgrading.md` | New, shipped. Six-step upgrade procedure, pointed at from `README.md` and `SETUP.md` |
| `docs/architecture/framework-wiring.md` | Renamed from `mx-boot-wiring.md` and grown; now carries class resolution (`MGR_*` → package alias → `MY_`/`APP_`) as a section of its own |
| `system/skills/mgr-models/references/list-endpoint.md` | Extracted from the skill body — situational content, per the extraction rule |

Rule changes to `sample/docs/documentation.md` and `docs/documentation.md` are
listed in `decisions.md`.

## Consumer-facing change

`MIGRATION.md` carries the rename note: stale `.claude/skills/ixaya-*`
symlinks must be deleted by hand, because the loop that creates them only
writes the names it finds and will not remove the old ones. Re-run the loop,
then invoke `/mgr-*`. No application code references a skill name, so nothing
breaks at runtime.

The note quotes the old `ixaya-*` names deliberately, so a project migrating
from an older release recognizes what it has. Those are the only occurrences
of the old prefix left in the repository, and they should stay.

## Coverage decisions worth not re-opening

Twenty coverage gaps were enumerated from the source tree and disposed. Ten
produced documents or pointers; the rest were closed. Two closures are the
kind that get re-proposed by a later reader and are recorded here for that
reason:

- **No `application-structure.md`.** The proposal was a shipped document
  describing the application tree. Rejected in favor of placing each fact
  where the reader already is: the tree is self-describing, and a document
  enumerating it becomes an index that lies the moment a directory is added.
- **No release-process document.** The release process stays uncommitted and
  operator-owned. This is a permanent closure, not a parked item.

## Left open

Two follow-ons, both documentation, neither blocking:

- **The ten skill frontmatter `description` fields have never been audited.**
  The initiative rewrote skill bodies, shape, and names, and never looked at
  the one field that decides whether a skill is loaded at all. The rule
  prescribing their form exists in `docs/development/skill-authoring.md`, but
  it was written from reasoning rather than from a pass over the files. Selection runs off
  `description` and nothing validates a skill, so a description that never
  fires produces no error — only an agent that hand-writes what the skill
  would have taught.
- **`README.md` prose is not wrapped to the wrap rule.** Held out of scope
  twice, deliberately. The README is governed by a narrower rule —
  installation, major capability, and error fixes only, with minimal diffs —
  and a whole-file reflow is none of those. What has to be settled before any
  line moves is whether a pure-whitespace reflow is a change that rule is
  meant to restrict.
