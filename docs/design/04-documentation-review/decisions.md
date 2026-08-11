# Documentation review — decisions

Design decisions with rationale. What was in scope is in `spec.md`, the
resulting state in `handoff.md`, how it was verified in `review.md`.

## Skill identifiers keep a prefix, and the prefix is `mgr-`

The question was twofold: rename the skills off the vendor name, and decide
whether a prefix is right at all or whether bare names (`auth`, `models`,
`migrations`) would match better semantically. The answer is `mgr-*`.

**The load-bearing fact is that a prefix costs nothing in selection.** An
agent chooses a skill from its `description` field; the name is an
identifier — what `/name` invocation and the skill listing key on, and what
other skills cross-reference. So "drop the prefix for better semantic
matching" trades away real benefits for a matching gain that does not exist,
because the description already carries the semantics.

What the prefix buys, in order of weight:

- **Namespace safety.** Skill names share one global namespace with the
  developer's personal and plugin skills. Bare generic names are exactly the
  collision-prone ones — `auth` and `cache` far more than `mgr-auth`.
- **Provenance.** In a consuming project's listing, `mgr-*` reads as "the
  framework's way," distinct from application-local skills.
- **Set cohesion.** The ten read as one family, matching how they
  cross-reference and how they all chain off `mgr-code-style`.

Bare names would be right in a closed single-project environment with no
plugins and no global skills. These ship through Composer into many projects
with their own skill sets, which is the namespace case a prefix exists for.

**Why `mgr-` and not `manager-`.** `manager-` was chosen first, applied, and
then superseded. It described the product but accepted a trade-off: `manager`
is a generic English word and therefore the more collision-prone token.
`mgr-` removes that trade-off and matches the namespace the framework already
owns everywhere else — `MGR_` classes, `mgr_*` helpers, `MGRPATH`,
`MgrFieldType`. A namespace prefix wants distinctiveness; descriptiveness is
the description's job. Its one cost — opacity to someone who has never opened
the framework — is bounded, because nobody meets these skills cold: they
arrive through the routing table in `AGENTS.md` and through descriptions that
name the framework outright.

**Titles stay legible.** The `SKILL.md` H1s are `# Manager Auth`,
`# Manager Models`, and so on. A title is documentation, not an identifier,
so it takes the readable form while `name:` takes the distinctive one.

**The rename is a soft breaking change** for anyone who typed `/ixaya-auth`.
No application code references a skill name, so nothing breaks at runtime;
the consumer action is recorded in `system/docs/upgrading/2.0.0.md`.

## A shipped contrast must be unconditional — the canon test

Two `Anti-patterns` blocks were written, reviewed, and then removed rather
than shipped. Both failed the same test, which is now the rule: **a WRONG /
RIGHT contrast in a shipped skill must be wrong in every project, not only in
ours.**

Skills ship to consuming projects. Where the framework deliberately delegates
a choice — response wording, delivery channel, anything behind an extension
seam — a contrast that picks a side teaches canon the framework cannot
enforce, and does it in the most authoritative register the skill has. If a
contrast is only wrong here, it is a default, and defaults belong in prose
that says so.

The corollary that stopped a section from being added to every skill: **a
section with no real material is worse than its absence.** Hunting for a third
example to fill a block is the signal that the section does not belong. Four
omissions across three skills were confirmed as correct rather than filled.

## Framework-mode content only where the mistake is expensive

Considered and decided against: adding a "what changes when the repository is
the framework" note to the eight skills that lacked one. Skills state paths in
project terms and the reader swaps the root; adding mode qualifiers everywhere
inverts the default reader for content that ships to projects.

Explicit framework-mode qualifiers survive in exactly two places, both where
the mistake is expensive rather than merely confusing: the `.dockerignore`
guard in `mgr-live-probes`, without which a build context ships the probes
module into production images, and the `BE_` fork warning in `mgr-auth`.

The one genuinely dangerous instance was fixed instead of generalized:
`system/skills/mgr-helpers-libraries/references/creating-a-library.md` taught
project-side placement as *the* placement, so an agent following it to add a
framework library would break the alias chain. It now opens with the three-file
framework shape and points at `docs/development/framework-workflow.md`.

## Rules added to the shipped standard

Each of these was a convention the corpus already followed in practice while
the standard was silent. Rather than enforce an unwritten rule, the rule was
written first, at the sample bar, and then enforced.

- **Hard-wrap prose at roughly 76-80 columns.** A reviewer diffing an
  unwrapped paragraph sees one changed line and must reread the whole thing.
  Tables, fenced code, and long URLs are exempt.
- **Compact table delimiter rows** (`|---|---|`). Width-matched rows render
  identically and churn the diff whenever a cell's width changes.
- **Spaces, not tabs, inside fenced code** — with the exception that carries
  it: a document whose purpose is to be pasted verbatim into a source file
  carries that language's real indentation. This is why `SKILL.md` snippets
  use spaces and `references/` files use tabs.
- **Point at directories, not files, unless the pointer is load-bearing.**
  The exception is narrow on purpose: name the file only when the reader
  needs one specific document by name for a specific task. An earlier draft
  also excused naming a file when the directory held several documents; that
  prong was dropped, because it makes the trigger a volatile fact the same
  document forbids depending on.
- **Cross-reference by section name, not number — above a threshold.** A
  procedure with more than 10 numbered steps or more than 5 self-references
  may number them; quoting full headings at that scale costs more than the
  drift risk it removes. A scale threshold was chosen over a categorical
  "setup versus recurring procedure" distinction as the simpler rule that
  decides the same cases.

## Rules added to the framework-only addendum

- **A guarded one-way reference is allowed.** Shipped files must not cite
  paths that exist only in the framework repository — unless the reference
  carries an explicit guard naming it as framework-only, which tells the
  reader the path will not exist in their checkout. The corpus had already
  settled on this idiom; the rule was narrower than the practice.
- **The Docker-only rule states its scope.** It binds `docs/development/` and
  `system/skills/`, where the reader is guaranteed the sample's stack. Root
  guides may adopt it and do, but a root document addressing an environment
  this repository does not control is judged on its own terms — which is why
  the `system/docs/upgrading/*.md` guides' bare host commands are correct
  as written. Rescoping the
  rule was preferred over naming a per-file exception.

## The frontmatter description follow-on: enforce literally, blanket

Closed by campaign `16-documentation-improvements` (see `handoff.md`'s Left
open section). Two decisions came out of it, both now folded into
`docs/development/skill-authoring.md`:

- **Enforce the "ending in `this codebase`" rule literally, across all ten
  skills, with no case-by-case exception.** Sentence-final position likely
  doesn't independently change firing probability on its own — trigger
  *vocabulary* is what matters, per the rule already stated above — but
  nothing else validates this field, so a mechanically greppable rule is the
  only drift resistance the format has. A violation is a corpus-consistency
  defect, not evidence the skill won't fire.
- **Phrase the replaces-clause as a literal `— instead of …`.** Three skills
  (`mgr-helpers-libraries`, `mgr-migrations`, `mgr-models`) stated what they
  replace in three different constructions ("so you don't reimplement it
  with…", "…is deprecated", "so you never write…") — all satisfied the
  written rule, but only the literal phrasing keeps a future audit
  greppable rather than a per-skill judgment call each time. Reworded to
  match the other seven; no anchor or fact was lost in any of the three —
  the migrations case looked hardest to homologate (a deprecation notice,
  not a hand-rolling anti-pattern) but the skill body already states the
  same fact as "Do NOT extend `CI_Migration` with hand-written dbforge
  arrays," so the reword was pure phrasing, not a new claim.

## One coverage gap was closed by cancelling the artifact

A `mgr-testing` skill had been parked as owed. It was cancelled outright
rather than deferred: testing is documentation, not a skill. Nothing about
authoring a test must arrive unbidden mid-task, which is the test a skill has
to pass; the shipped `sample/docs/development/testing.md` is the right home
and already exists.
