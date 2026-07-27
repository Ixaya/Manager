# Spec campaigns — running a findings/fix initiative with agents

Generalized playbook for any review-then-fix campaign in this repo, distilled
from the 2026-07 auth/system campaign (`docs/design/01-auth-hardening/`,
`02-system-fixes/`). It covers the workspace structure, the
validation-before-fixing procedure, the fix-pass process, and how the operator
plans sessions (prompts, model, effort).

Status: battle-tested once. After another campaign or two, consider promoting
this to a shippable skill; until then it stays repo-local so an immature
methodology doesn't propagate to consuming projects.

## Lifecycle at a glance

```
review pass(es)  -> findings docs        (workspace/<section>/spec.md)
validation pass  -> quoted baselines     (workspace/<section>/handoff.md)
fix sessions     -> one item at a time   (spec entries deleted as fixed)
closing review   -> re-diff vs baselines (punch list or all-clear)
pending gate     -> open items disposed   (resolve / defer / hold)
distillation     -> skills + docs/design (workspace deleted)
```

The workspace (`docs/workspace/`, gitignored) is the working area; handoff
baselines carry state between sessions — never the conversation. Operator
commits; agents never do.

## Workspace anatomy

```
docs/workspace/
├── 00-shared/
│   ├── methodology.md   standing procedure, agent-facing — operator-owned,
│   │                    persists across campaigns (every session reads it FIRST)
│   ├── conventions.md   THIS campaign's scratchpad: campaign-wide rules and
│   │                    knots (read second; swept at distillation)
│   └── pending.md       indefinitely parked items — title + pointer only
└── NN-section/          one numbered directory per domain
    ├── spec.md          task log: the findings; fixed = entry DELETED
    ├── handoff.md       validated baselines + running applied-change record
    ├── review.md        provenance/historic record (how findings were made)
    └── prompts.md       OPERATOR-ONLY session runbook for this campaign
                         (agents write it when asked to plan sessions,
                         never load it as instructions)
```

Sectioning: split a big findings list by DOMAIN (one section per subsystem or
concern), not by size. Extract cross-section rules into
`00-shared/conventions.md` instead of repeating them. If two sections share an
item (a "knot"), name it in conventions and decide it ONCE.

A campaign large enough to run over weeks nests one level: a campaign
directory holding a numbered sub-directory per objective, each with its own
`spec.md` / `handoff.md` / `review.md`, and one `conventions.md` and
`prompts.md` for the campaign as a whole. Two sequencing rules come out of
running one that way:

- **An objective that churns identifiers runs LAST.** A rename restates the
  names every open finding quotes, so running it early stales finding text
  mid-campaign and forces a re-verification pass before anything can be
  fixed. Order the review-and-fix objectives first, the rename after them.
- **A closing-review item too large for a punch-list fix becomes its own
  objective**, not an oversized fix session. The signal is judgment per site
  rather than a pattern applied across sites — writing real content for ten
  files is a new objective; converting a phrasing at ten sites is a sweep.

`spec.md` is a task log, not documentation: fix an item, then delete its
entry. Durable conventions go to skills/AGENTS.md at distillation time, so
errors are never cemented into standing docs.

### Bootstrapping `00-shared/`

`methodology.md` is seeded ONCE, not per campaign. Open it with a short "where
state lives" block — the four per-campaign file roles (spec / handoff / review
/ prompts, the runbook flagged operator-only and never loaded as instructions)
and the shared files beside it, condensed from the Workspace anatomy above.
Then copy this playbook's "Fix-pass process" and "Live-testing" sections into
it, plus a condensed session-gates block — the task-list approval gate, a
generic look-for-applicable-skills line, the per-item restatement (the fourth
gate, baseline-mismatch = stop, is already in the fix-pass rules). Keep the
file lean: every session re-reads it, so every line is a recurring cost —
per-item machinery (skill pointers, traps, closing checks) stays in the
campaign prompts. From then on the file is the operator's. Personal process
adjustments (reporting language, summary format, extra gates) are direct edits
to the copy; there is no separate deviations list. This playbook stays the
canonical standard; `methodology.md` is the standard as this operator runs it.

`conventions.md` is seeded at each campaign start from this template:

```
# Campaign conventions — <campaign name>

> Standing procedure lives in methodology.md beside this file — this one
> carries only what is specific to THIS campaign. Agents read both, this
> file second, before any section files.

## Campaign-wide rules

Rules every section of this campaign shares — stated here once instead
of repeated per section.

## Knots

Items two or more sections share. Name the knot, decide it ONCE here;
section specs point at this entry instead of re-deciding.

- <knot>: <decision, or OPEN>
```

`pending.md` needs no seed: it is created the first time an item is parked,
and stays titles + pointers only.

## Validation before fixing

A findings doc authored by LLM review passes carries two risks until checked:
hallucinated references, and no baseline for later comparison. "Validate" does
NOT mean "check whether it's already fixed" — it means: does the cited code
exist, does it behave as claimed, and what does it look like right now.

When commissioning the review pass that authors a findings doc, ask for full
coverage and a flat, actionable list — one entry per finding with location,
claim, severity, and confidence — not analysis prose the findings must be dug
out of. Current models follow "only report important issues" literally and
silently drop the rest, and unstructured review output buries what it does
find. Filtering is validation's job, not the reviewer's.

Per finding:

1. **Existence check.** Open the cited file; line numbers drift, so search by
   symbol before concluding anything. Nothing found anywhere =
   `HALLUCINATED-REFERENCE` — say so; never substitute a plausible guess.
2. **Behavior check.** Read enough context to confirm or refute the SPECIFIC
   claim — this is what catches false positives.
3. **Quote the current code verbatim** (a few lines) as the baseline a
   post-fix pass will diff against.
4. **Run any "inspect first" grep the item ships, verbatim**, and record the
   raw output.
5. **Classify** into exactly one verdict: `VERIFIED` / `FALSE-POSITIVE` /
   `HALLUCINATED-REFERENCE` / `DRIFTED` / `ALREADY-FIXED` / `CANNOT-VERIFY` /
   `DECISION-ONLY`.
6. **Do not fix, do not decide.** Facts and baselines only; open DECISION
   items stay open.

Validate in read-only parallel batches mirroring the doc's own section
structure; each batch returns one evidence table plus a flag list.

If a review compared against a source that is not in the repo (an upstream
original, a production tree pasted into the reviewing agent's context), state
that once per section: only the local half of such claims is re-verifiable.
Don't silently skip it, and don't let it taint the local half's verdict.

Validate the validation: campaign experience says a validation claim can
itself be stale (a "rename" that never landed). When a fix looks pointless,
re-check the claim before applying it.

### When the material is prose

A documentation campaign runs the same lifecycle, with three differences that
change the procedure rather than only the subject matter.

**Validation and the finding collapse.** "The cited path does not exist" is
simultaneously the finding and its own verified baseline — there is no
behavior to re-check behind it. Quote the current text verbatim anyway: a
rewrite needs a diff target exactly as a code fix does, and without one a
later reviewer cannot tell an applied edit from an unapplied one.

**Fix at the source of truth, once.** Where a rule lives in two documents —
a canonical one and an addendum, or a pair of twins written for different
audiences — decide which owns the rule and point the other at it. Applying
the same edit to both is how the two copies start drifting.

**The rubric must already contain the rule.** A campaign enforcing a written
standard may not invent house rules the standard lacks: enforcing an unwritten
rule produces findings the standard cannot back, and a reader who checks will
find nothing. Where a desired convention is absent, the choice is to add it
to the standard first — a product change, held to that artifact's own bar —
or to drop it. Both are legitimate; enforcing it silently is not.

## Fix-pass process

- **One item at a time, in the section's priority order.** Every item is a
  proposal, not a mandate — and that covers its prescribed shape, not just
  whether to do it: when a remedy prescribes the same edit in more than about
  three places, re-derive the structure it assumes before the first edit
  (fan-out is where a wrong shape multiplies). Do not start item N+1 until
  item N is closed. Items marked batchable (trivial, decision-free) may land
  together.
- **Approve-before-edit** on anything behavior-changing or carrying an open
  DECISION. Present the plan (files, signatures, back-compat), wait.
- **Baseline discipline:** before editing, confirm the code still matches the
  handoff quote — if it doesn't, STOP and report, don't improvise. After
  editing, diff against the quote and state in one line what changed.
- **Record deviations immediately** — any departure from the item as written,
  however small, goes into the handoff's fix-pass corrections WITH the
  operator rationale, before starting the next item.
- **Where fixes go:** prefer the subclass/config/extension seam; never edit
  upstream-tracked directories (`system/third_party/`) except as a deliberate,
  documented, one-commit exception recorded for re-verification after upstream
  merges (see `docs/development/auth-upstream.md` for the worked example).
- **Stop-and-flag on surprises.** A bug surfaced mid-item that isn't the item
  is its own finding — flag it for a decision, don't silently patch or ignore
  it.
- **No commits, ever.** The operator reviews and commits; batches meant to be
  one commit must be kept as one coherent changeset.

## Live-testing (runtime verification)

Reading a diff confirms it looks right, not that it executes right. Probe when
BEHAVIOR changed (comparison semantics, casts, auth/DB/session state,
cross-engine SQL); a grep fully confirms a string/doc fix. Timing: at the END
of a batch, operator-gated ("which items do you want live-tested?") — never
per-item mid-batch; the stack has real overhead.

Mechanics (probe controllers, authenticated-not-bypassed rule, Docker recipe,
the three log channels) live in the `mgr-live-probes` skill — follow it, don't
re-derive. Campaign-proven additions:

- One probe method per finding, so an item can be re-tested in isolation;
  probes stay afterwards (gitignored) for the next re-validation.
- Run the engine matrix (postgres, mysql, mariadb) before release when DB
  behavior changed — driver quirks proved decisive (a fix correct on one
  engine was wrong on another).
- Consider a durable CLI regression suite for what the throwaway probes
  proved, minus anything flaky by nature (timing asserts) or too white-box
  (builder-state reflection).

## Session planning (operator)

Each campaign keeps its own runbook, `<section>/prompts.md`, authored FOR the
operator by the campaign's planning/validation session — the strong model
writes the prompts the cheaper execution sessions will run — and appended to
when a later pass (a closing review, new findings) adds work. It opens with an
OPERATOR-ONLY header: agents touch it only when explicitly asked to plan
sessions, and never load it as instructions.

One prompt per session, each pasted into a CLEAN context (never chain two
sessions in one window), with the `/model` + `/effort` choice recorded above
the prompt. Mark sessions that worked or failed; the runbook doubles as the
campaign's execution log. The file always ENDS with the closing-review and
distillation prompts — seeded as skeletons when the runbook is written, filled
in as the campaign closes — so the exit is planned from day one. The runbook
dies with the campaign directory at distillation; a shared runbook that
outlives its campaigns only goes stale. Durable prompt lessons belong here, in
this playbook.

Model/effort selection — capability tier and reasoning effort are separate
dials: pick the tier from risk and session horizon, the effort from how deep
each individual judgment runs. The expensive reasoning already happened during
validation, so fix sessions run cheap; the passes that produce or re-check
judgments do not. Cheap is also safer there: surplus reasoning aimed at a
pre-decided item tends to come out as nitpicking and over-fixing — scope the
fix-pass rules already forbid — not as quality.

| Session class | Tier / effort | Why |
|---|---|---|
| Mechanical fixes against quoted baselines; batch pattern-application | Standard / medium (low for pure string/doc edits) | The reasoning is already done; the session only applies it. |
| Judgment/decision sessions with pre-digested options; design-sensitive fixes | Standard / high (xhigh for the hardest) | Short horizon, framed options — a smaller model at high effort covers it; escalate the tier only when options interact across the codebase. |
| Anything touching auth/session/crypto | Advanced / high | Prioritize correctness over speed, regardless of session length. |
| Validation pass (findings → verified items + baselines) | Advanced / high | This is where the expensive reasoning happens. |
| Closing review (re-diff every fix vs baselines) | Advanced / high | This is where the stronger model earns its cost. |
| Distillation / whole-workspace reorganization | Advanced / medium–high | Long horizon with the whole workspace in play; the tier buys coherence, not per-item depth. |

Tier mapping (Claude; re-verify whenever the vendor ships a new generation —
other vendors add their own mapping in their own change, without touching the
session-class table):

| Tier | Model | Notes |
|---|---|---|
| Fast | Haiku 4.5 | 200K context, no effort dial — isolated mechanical transforms only |
| Standard | Sonnet 5 | Same 1M window as Advanced; supports xhigh |
| Advanced | Opus 5 | medium approach previous-generation high — prefer dropping effort before dropping tier |
| Highest | Fable 5 | 2× Advanced price; hardest long-horizon work only |

Batch by section and let the hardest item set the tier — don't pay the
high-effort tax on ten renames because one spicy item is mixed in; pull it
into its own session. Keep sessions narrow: five items with the section's
handoff open beats thirty items across sections.

### Prompt skeleton

Every session prompt follows this shape (assembled from the campaign's
best-performing prompts):

```
<Role frame, one line — the session type and the state it inherits:
"You're picking up a validated findings list.">
Read docs/workspace/00-shared/methodology.md and conventions.md first,
then the spec entries for the items in scope (listed below) in
<section>/spec.md, with <section>/handoff.md open beside it — every item
has a verified baseline; before editing, confirm the code still matches
the quote. If it doesn't, stop and report.

After reading all the tasks, list them in a message — title/headline only —
and wait for the operator to approve the list — the approved list becomes
the scope. When an item carries an open DECISION, lay out the options with
a recommendation and wait: decisions get made in conversation, never
buried in a diff.

Before coding, always load the code-style skill, and look for any other
skill that applies to the modification you are working on. <Per-item
skill pointers when you know them: "mgr-models for #1".>

Scope: <items, in order; the priority item if one exists; what is
explicitly OUT of scope — including items that are the operator's alone>.
Process: one at a time, each prefixed "Item N of M". When you START an
item — after reading its code, handoff row, and spec text — open with a
one-line RESTATEMENT of the finding in plain language: translate the
technical headline into what it actually means (what breaks, for whom).
<Approve-before-edit if behavior-sensitive.> <Per-item notes and traps —
repeat anything load-bearing from the item text, agents skim.> Diff each
fix against the handoff quote, record deviations in handoff.md
immediately, delete finished entries from spec.md. <Closing check when
the batch has one: "verify after: <grep> — must return zero hits".>
No commits. Finish by listing what you fixed and what you skipped and
why.
```

The task-list approval gate, the skill-loading line, the per-item restatement,
and the stop-on-baseline-mismatch guardrail are mandatory boilerplate — they
are the lines that most improved session outcomes. Note the two list moments
are different: the APPROVAL list (titles only) is cheap and comes straight
from the docs, before any code is read — asking for reframes there would
produce guesses. The RESTATEMENT comes at the start of each item, once the
agent has actually analysed its code and baseline — that is when it can
genuinely translate the technical headline, and writing it forces the
digestion before the first edit. Per-item trap notes (what NOT to convert,
which sub-claim was disproven) belong in the prompt even though they're in the
docs: repetition there is cheap insurance.

That repetition is the only sanctioned one. Everything else in the skeleton is
pointers and process, never content — the state (findings, baselines, prior
deviations) lives in the workspace files. That is what makes the shape cheap
and repeatable: twenty lines fully brief a session at any model tier, and the
same prompt re-run a week later still binds to current reality, because the
files moved with it. Pasted code or findings are a second copy of state — they
drift, and they bill every turn. A prompt that seems to need a page of pasted
context is a symptom: the handoff is missing a baseline. The two read pointers
do the same job for prompts that skip the skeleton: a three-line prompt that
opens with methodology.md and conventions.md still runs a disciplined session,
because the standing process lives in the file, not in the prompt.

### Worked example

The skeleton filled in — a three-item fix batch on a fictional export section:

```
You're picking up a validated findings list. Read
docs/workspace/00-shared/methodology.md and conventions.md first, then
the spec entries for #1-#3 in 03-export-engine/spec.md, with
03-export-engine/handoff.md open beside it — every item has a verified
baseline; before editing, confirm the code still matches the quote. If
it doesn't, stop and report.

After reading all the tasks, list them in a message — title/headline
only — and wait for my approval; the approved list becomes the scope.

Before coding, always load the code-style skill, and look for any other
skill that applies — mgr-models for #2.

Scope: #1, #2, #3 in that order; #2 is the priority. OUT of scope: #4
(operator decision pending) and anything touching the queue workers.
Process: one at a time, each prefixed "Item N of 3". When you START an
item, open with a one-line RESTATEMENT: what breaks, for whom. #3 trap:
the date format lives in both the builder and the spreadsheet helper —
fix both or neither. Diff each fix against the handoff quote, record
deviations in handoff.md immediately, delete finished entries from
spec.md. Verify after: grep for the old format string — must return
zero hits. No commits. Finish by listing what you fixed and what you
skipped and why.
```

### The other session shapes

The skeleton is the fix-session shape, but its bones — role frame, context
files, approval gate, one-at-a-time process, exit report — carry every session
type; only the per-item process changes:

- **Judgment/decision session** (design-sensitive items, open DECISIONs): per
  item, restate the finding, check the handoff verdict, run or design the
  confirming test if it is unverified, then present the options with a
  recommendation and WAIT. Record every verdict inline in the doc as you go,
  the keep-as-is ones included — the record is the deliverable.
- **Closing review** ("You're the closing reviewer."): the per-item process is
  the one under Endgame below — re-diff against baselines, punch list, fix
  nothing.
- **Distillation**: clear the pending-task gate FIRST (Endgame step 2), then
  propose the distillation plan and wait for approval; then one deliverable at
  a time, per Endgame — and ask before deleting anything.

### Prompt anti-patterns

The failures new operators hit first — each is the negative of a rule above:

- **The kitchen-sink session.** Thirty items across sections; a narrow session
  beats it on quality and cost both.
- **The mixed-tier batch.** One design-sensitive item hidden among ten renames
  sets the whole session's tier — pull it into its own session.
- **Pasting code the handoff already quotes.** A second copy of state that
  drifts and bills every turn; point, don't paste.
- **No out-of-scope line.** Agents helpfully fix adjacent things — say what is
  NOT in scope, especially items that are the operator's alone.
- **Chaining a second task in the same window.** State lives in the files; a
  new task gets a clean context and a fresh prompt.
- **Asking for reframes at the approval gate.** Before the code is read, a
  reframe is a guess — the restatement comes later, per item.
- **The evidence-free exit report.** "Fixed" and "skipped" claims must point
  at this session's diff-vs-baseline or grep output — long runs are documented
  to fabricate status otherwise.
- **Delegating inside a session.** Current Advanced-tier models reach for
  subagents readily; a narrow session gains nothing from them — context
  re-established per agent, cost multiplied, baseline discipline split across
  contexts. Work solo.

## Endgame: closing review, pending gate, distillation

1. **Closing review** (separate session, strong model): for every item marked
   fixed, re-diff current code against the recorded baseline; verify any
   do-not-regress properties verbatim; re-run the campaign's consolidated
   greps; produce a per-section verdict table and a punch list (fix nothing in
   that session).
2. **Pending-task gate** (opens the distillation session, before its plan is
   proposed). Sweep the whole workspace for anything still open — `pending.md`,
   deferred verdicts in the objective records, follow-ons a late objective
   flagged, items an objective closed without saying so. List them explicitly
   and propose a disposition for each, then WAIT for a per-item ruling:
   - **resolve now** — small enough to close inside the distillation, or a
     record-keeping fix such as marking a deferred item as closed by a later
     objective that decided against it;
   - **defer to the next campaign** — real work, parked with a title and a
     pointer;
   - **hold** — stop the review/fix here and resume this campaign later; the
     workspace stays live and nothing is archived.

   Run this before proposing the plan, not inside it. A deferred item found
   halfway through distillation reopens decisions already made, and the gate is
   the last moment the campaign's own records are still readable. Two traps it
   exists to catch: a verdict that says DEFER while a later objective silently
   decided the question, and a parked item whose pointer aims into the
   workspace about to be archived — every surviving pointer must be repointed
   at something durable (a `docs/design/` record) or at a live campaign
   directory before the archive step runs.
3. **Distillation** (this campaign's worked example is
   `docs/design/01-auth-hardening/` + `02-system-fixes/`): durable conventions
   to skills; decisions + final state + validation record to
   `docs/design/<initiative>/` per the documentation standard
   (`sample/docs/documentation.md` + the framework addendum); operational
   runbooks to `docs/development/`; consumer traps to MIGRATION.md — one
   source of truth, pointers not duplicates. Sweep `00-shared/conventions.md`
   in the same pass: campaign rules that proved durable go to skills or this
   playbook, parked-but-alive items to `pending.md` (title + pointer), the
   rest dies with the workspace — `methodology.md` is the operator's and is
   never swept. Then list what remains, get operator approval, and archive:
   `tar cJf docs/workspace/archive/<n>-<name>.tar.xz -C docs/workspace
   <n>-<name>`, then delete the live folder. The archive keeps the campaign
   out of the active workspace while letting the operator uncompress it later
   for a detailed recount — `docs/workspace/archive` is gitignored, same as
   the rest of `docs/workspace/`.
