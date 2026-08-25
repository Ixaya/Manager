# Documentation review — validation record

How each claim in `spec.md`, `decisions.md` and `handoff.md` was verified, and
what the verification changed. The pattern worth carrying forward: every
objective was re-checked by a later pass, and three of those re-checks
overturned something the original pass was confident about.

## Method

Each objective recorded a verbatim baseline before editing and diffed against
it afterwards. A closing review then re-diffed every fixed item across the
first four objectives against its own recorded baseline, re-ran the
consolidated greps, and produced a punch list of what the objectives' scopes
had not reached. All four objectives were confirmed: no verdict was
overturned and no recorded fix had failed to land.

Because these were prose findings, the cited `file:line` was itself the
baseline — but the current text was quoted anyway, since a rewrite needs a
diff target exactly as a code fix does.

## What was checked, and the result

**Validation, nine checks over the full corpus plus the frozen `design/`
initiatives: zero actionable defects in the live and shipped corpus.** Every
path a live document claims resolves; no emoji anywhere; no shipped file cites
internal history; twin documents declare each other; no false imported facts.

Two observations were recorded rather than fixed, both in frozen history. The
`design/` records reference a probe module since renamed and three workspace
directories since deleted. The deletions were already self-labelled inline. The
module rename was not, and its four quote sites read as pointers to live,
reusable assets — so each gained a one-line bracketed note saying the module
was renamed and the files no longer exist at that path. The frozen narrative
was annotated, never rewritten.

**Homologation: 28 items, all closed and individually re-verified.**
Terminology unified across the corpus (product spellings, the `X-API-KEY`
header form, command-path forms), section numbering removed where it crossed
documents, altitude corrected where an entry point had absorbed content
belonging in a topic document, and first-person plural and version-specific
claims removed.

**Coverage: 20 gaps enumerated from the source tree, all disposed.** Ten
produced a document or a pointer; the remainder were confirmed as correct
absences. Enumerating from the tree rather than from the documents is what
surfaced them — a coverage read of the documents alone finds only what the
documents already mention.

**Rename: verified by state, not by report.** Ten `mgr-*` directories and
nothing else; ten `name: mgr-*` fields; zero surviving intermediates from the
superseded `manager-` pass; ten H1 titles in the `# Manager <Topic>` form. A
slug sweep returns four hits, all inside the `system/docs/upgrading/2.0.0.md` note that quotes
the old names on purpose.

**Discovery was verified live, in three pieces of evidence:**

1. After the content sweep had rewritten every `name:` but before any
   directory was renamed, the listing still advertised the old names while
   already showing the edited description text. The directory name is the
   identifier and the description is read from the file — which is why the
   directory rename was the load-bearing half.
2. All ten symlinks re-point and resolve, with no broken or leftover entries.
3. A skill was invoked by its new name and returned its full body.

## The three overturns

**Two `Anti-patterns` blocks were written, then removed.** One had been
applied before a re-check found it established canon the framework does not
enforce; the second's case rested partly on the first surviving. Both are the
origin of the canon test in `decisions.md`. The general lesson: flagging an
item for extra validation at authoring time worked — the flag is what caused
the re-read that caught it.

**A corpus-wide claim turned out to be a regex artifact.** A pass asserted the
`BASEPATH` guard appeared in one form with zero variants. Re-measured, the
real split is 95 one-liner against 16 block-form, the latter concentrated in
upstream-tracked `third_party/BE/` and older package files. The normalization
that followed stands on `mgr-code-style` and the style exemplar rather than on
unanimity — the recommendation survived, its stated evidence did not.

**A deferred item was decided against rather than executed.** Adding
framework-mode notes to eight skills was deferred to a dedicated session; that
session decided the notes should not exist, and fixed the one genuinely
dangerous instance instead. See `decisions.md`.

## Verified so it need not be re-derived

- **`sample/` is not `export-ignore`d**, so canonical-example paths of the
  form `vendor/ixaya/manager/sample/...` resolve inside a consuming project.
  `framework/docs/` and `extras/` are ignored, which is why skills correctly send
  readers elsewhere for content that lives there.
- **The scaffold ships `.php-cs-fixer.php`, `phpstan.neon` and
  `phpstan-bootstrap.php`**, so every tooling path named in `mgr-code-style`
  resolves in a project even though those files never ship in the package.
- **No blanket comment-placement rule is needed.** The standard already
  exempts fenced code from the wrap rule; a scan of all ten skills and their
  reference files found five multi-line comment runs, four of them
  legitimate.

## Accepted residuals

- **Three lines exceed 80 columns and will stay.** Each is a single backticked
  vendor path with no internal whitespace, in `mgr-rest-controller`,
  `mgr-migrations`, and
  `system/skills/mgr-rest-controller/references/full-example.md`.
  Wrapping them would break the token; the exemption matches the precedent
  already recorded for two such lines in `framework/docs/development/docker-decisions.md`.
- **Four `ixaya-*` occurrences remain**, all in the `system/docs/upgrading/2.0.0.md` rename note,
  where removing them would make the note useless.
- **`README.md` was not reflowed** — see the open items in `handoff.md`.

## Distillation validation

The conventions from the final skills pass had been written into
`framework/docs/development/skill-authoring.md` during the same session that produced
them, so distillation was a validation rather than an extraction: read the
document against the record and confirm nothing was lost, softened, or
invented in the compression.

Everything load-bearing survived — the canon test with its reason rather than
only its conclusion, the observation that prose a code block duplicates is not
redundant, the extraction rule with its never-extract-a-core-API corollary,
parameter-name verification as the first checklist item, and the deliberate
choice to point at the framework drift rules rather than restate them.

Two defects were found and fixed. The two-term repository naming rule
("framework repo" or "this codebase", never "this repo", which inverts when
read from a consuming project) had been settled but never written down, so an
author of a new skill had nothing to follow. And the omissions table was
introduced as covering four skills when it covers three — four is the count of
omitted sections. A sweep for the retired term then found one survivor in the
shipped corpus, in `mgr-live-probes`, now corrected.
