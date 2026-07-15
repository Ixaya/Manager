# Spec campaigns — running a findings/fix initiative with agents

Generalized playbook for any review-then-fix campaign in this repo,
distilled from the 2026-07 auth/system campaign
(`docs/design/01-auth-hardening/`, `02-system-fixes/`). It covers the
workspace structure, the validation-before-fixing procedure, the fix-pass
process, and how the operator plans sessions (prompts, model, effort).

Status: battle-tested once. After another campaign or two, consider
promoting this to a shippable skill; until then it stays repo-local so an
immature methodology doesn't propagate to consuming projects.

## Lifecycle at a glance

```
review pass(es)  -> findings docs        (workspace/<section>/spec.md)
validation pass  -> quoted baselines     (workspace/<section>/handoff.md)
fix sessions     -> one item at a time   (spec entries deleted as fixed)
closing review   -> re-diff vs baselines (punch list or all-clear)
distillation     -> skills + docs/design (workspace deleted)
```

The workspace (`docs/workspace/`, gitignored) is the working area; handoff
baselines carry state between sessions — never the conversation. Operator
commits; agents never do.

## Workspace anatomy

```
docs/workspace/
├── 00-shared/
│   ├── conventions.md   process rules shared by every section (read FIRST)
│   └── prompts.md       OPERATOR-ONLY session runbook (agents never load it)
└── NN-section/          one numbered directory per domain
    ├── spec.md          task log: the findings; fixed = entry DELETED
    ├── handoff.md       validated baselines + running applied-change record
    └── review.md        provenance/historic record (how findings were made)
```

Sectioning: split a big findings list by DOMAIN (one section per subsystem
or concern), not by size. Extract cross-section rules into
`00-shared/conventions.md` instead of repeating them. If two sections share
an item (a "knot"), name it in conventions and decide it ONCE.

`spec.md` is a task log, not documentation: fix an item, then delete its
entry. Durable conventions go to skills/AGENTS.md at distillation time, so
errors are never cemented into standing docs.

## Validation before fixing

A findings doc authored by LLM review passes carries two risks until
checked: hallucinated references, and no baseline for later comparison.
"Validate" does NOT mean "check whether it's already fixed" — it means:
does the cited code exist, does it behave as claimed, and what does it look
like right now.

Per finding:

1. **Existence check.** Open the cited file; line numbers drift, so search
   by symbol before concluding anything. Nothing found anywhere =
   `HALLUCINATED-REFERENCE` — say so; never substitute a plausible guess.
2. **Behavior check.** Read enough context to confirm or refute the
   SPECIFIC claim — this is what catches false positives.
3. **Quote the current code verbatim** (a few lines) as the baseline a
   post-fix pass will diff against.
4. **Run any "inspect first" grep the item ships, verbatim**, and record
   the raw output.
5. **Classify** into exactly one verdict:
   `VERIFIED` / `FALSE-POSITIVE` / `HALLUCINATED-REFERENCE` / `DRIFTED` /
   `ALREADY-FIXED` / `CANNOT-VERIFY` / `DECISION-ONLY`.
6. **Do not fix, do not decide.** Facts and baselines only; open DECISION
   items stay open.

Validate in read-only parallel batches mirroring the doc's own section
structure; each batch returns one evidence table plus a flag list.

If a review compared against a source that is not in the repo (an upstream
original, a production tree pasted into the reviewing agent's context),
state that once per section: only the local half of such claims is
re-verifiable. Don't silently skip it, and don't let it taint the local
half's verdict.

Validate the validation: campaign experience says a validation claim can
itself be stale (a "rename" that never landed). When a fix looks pointless,
re-check the claim before applying it.

## Fix-pass process

- **One item at a time, in the section's priority order.** Every item is a
  proposal, not a mandate. Do not start item N+1 until item N is closed.
  Items marked batchable (trivial, decision-free) may land together.
- **Approve-before-edit** on anything behavior-changing or carrying an open
  DECISION. Present the plan (files, signatures, back-compat), wait.
- **Baseline discipline:** before editing, confirm the code still matches
  the handoff quote — if it doesn't, STOP and report, don't improvise.
  After editing, diff against the quote and state in one line what changed.
- **Record deviations immediately** — any departure from the item as
  written, however small, goes into the handoff's fix-pass corrections WITH
  the operator rationale, before starting the next item.
- **Where fixes go:** prefer the subclass/config/extension seam; never edit
  upstream-tracked directories (`system/third_party/`) except as a
  deliberate, documented, one-commit exception recorded for re-verification
  after upstream merges (see `docs/development/02-auth/upstream.md` for the
  worked example).
- **Stop-and-flag on surprises.** A bug surfaced mid-item that isn't the
  item is its own finding — flag it for a decision, don't silently patch or
  ignore it.
- **No commits, ever.** The operator reviews and commits; batches meant to
  be one commit must be kept as one coherent changeset.

## Live-testing (runtime verification)

Reading a diff confirms it looks right, not that it executes right. Probe
when BEHAVIOR changed (comparison semantics, casts, auth/DB/session state,
cross-engine SQL); a grep fully confirms a string/doc fix. Timing: at the
END of a batch, operator-gated ("which items do you want live-tested?") —
never per-item mid-batch; the stack has real overhead.

Mechanics (probe controllers, authenticated-not-bypassed rule, Docker
recipe, the three log channels) live in the `ixaya-live-probes` skill —
follow it, don't re-derive. Campaign-proven additions:

- One probe method per finding, so an item can be re-tested in isolation;
  probes stay afterwards (gitignored) for the next re-validation.
- Run the engine matrix (postgres, mysql, mariadb) before release when DB
  behavior changed — driver quirks proved decisive (a fix correct on one
  engine was wrong on another).
- Consider a durable CLI regression suite for what the throwaway probes
  proved, minus anything flaky by nature (timing asserts) or too white-box
  (builder-state reflection).

## Session planning (operator)

Keep `00-shared/prompts.md` as the runbook: one prompt per session, each
pasted into a CLEAN context (never chain two sessions in one window), with
the `/model` + `/effort` choice recorded above the prompt. Mark sessions
that worked; the runbook doubles as the campaign's execution log.

Model/effort selection — the expensive reasoning already happened during
validation, so match the tier to the item class:

| Session class | Model / effort |
|---|---|
| Mechanical fixes against quoted baselines; batch pattern-application | Sonnet / medium (low for pure string/doc edits) |
| Design- or behavior-sensitive items; anything touching auth/session/crypto; DECISION walkthroughs | Opus or Fable / high |
| Final closing review (re-diff every fix vs baselines) | Opus or Fable / high — this is where the stronger model earns its cost |

Batch by section and let the hardest item set the tier — don't pay the
high-effort tax on ten renames because one spicy item is mixed in; pull it
into its own session. Keep sessions narrow: five items with the section's
handoff open beats thirty items across sections.

### Prompt skeleton

Every session prompt follows this shape (assembled from the campaign's
best-performing prompts):

```
Read docs/workspace/00-shared/conventions.md first, then <section>/spec.md
<the exact items/sections in scope>, with <section>/handoff.md open beside
it — every item has a verified baseline; before editing, confirm the code
still matches the quote. If it doesn't, stop and report.

After reading all the tasks, list them in a message — title/headline only —
and wait for the operator to approve the list. Accept the deviation if the
operator says to skip or defer any of them; annotate the decision.

Before coding, always load the code-style skill, and look for any other
skill that applies to the modification you are working on.

Scope: <items, in order; what is explicitly OUT of scope>.
Process: one at a time. When you START an item — after reading its code,
handoff row, and spec text — open with a one-line RESTATEMENT of the
finding in plain language: translate the technical headline into what it
actually means (what breaks, for whom). <Approve-before-edit if
behavior-sensitive.> <Per-item notes and traps — repeat anything
load-bearing from the item text, agents skim.> Diff each fix against the
handoff quote, record deviations in handoff.md immediately, delete
finished entries from spec.md. No commits. Finish by listing what you
fixed and what you skipped and why.
```

The task-list approval gate, the skill-loading line, the per-item
restatement, and the stop-on-baseline-mismatch guardrail are mandatory
boilerplate — they are the lines that most improved session outcomes. Note
the two list moments are different: the APPROVAL list (titles only) is
cheap and comes straight from the docs, before any code is read — asking
for reframes there would produce guesses. The RESTATEMENT comes at the
start of each item, once the agent has actually analysed its code and
baseline — that is when it can genuinely translate the technical headline,
and writing it forces the digestion before the first edit. Per-item trap notes
(what NOT to convert, which sub-claim was disproven) belong in the prompt
even though they're in the docs: repetition there is cheap insurance.

## Endgame: closing review, then distillation

1. **Closing review** (separate session, strong model): for every item
   marked fixed, re-diff current code against the recorded baseline; verify
   any do-not-regress properties verbatim; re-run the campaign's
   consolidated greps; produce a per-section verdict table and a punch list
   (fix nothing in that session).
2. **Distillation** (this campaign's worked example is
   `docs/design/01-auth-hardening/` + `02-system-fixes/`): durable
   conventions to skills; decisions + final state + validation record to
   `docs/design/<initiative>/` per `docs/documentation.md`; operational
   runbooks to `docs/development/`; consumer traps to MIGRATION.md — one
   source of truth, pointers not duplicates. Then list what remains, get
   operator approval, and delete the workspace sections.
