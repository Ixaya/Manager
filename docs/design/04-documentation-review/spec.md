# Documentation review (2026-07) — scope

A quality initiative over the framework repository's entire Markdown corpus,
run in seven objectives between 2026-07-24 and 2026-07-27. Unlike the three
preceding initiatives, nothing here changed executable code: the material was
prose, and the rubric was the documentation standard itself.

The decisions with lasting force are in `decisions.md`, the resulting state of
the corpus in `handoff.md`, and how each claim was verified in `review.md`.

## Why it ran

The corpus had been written over a long period, by different authors and
models, under successive versions of the documentation rules. Individual
documents were sound; the set was not consistent, and no one had checked
whether the documents obeyed the standard the project ships to its own users.
Three specific pressures forced the work:

- **The standard is shipped.** `sample/docs/documentation.md` is copied into
  every consuming project at bootstrap. A rule the framework's own docs break
  is a rule no project will take seriously.
- **Skills are read by agents and validated by nothing.** A wrong parameter
  name in a skill survives indefinitely and fails at runtime in someone
  else's project. The corpus had never been audited against source.
- **The `ixaya-*` skill prefix predated the framework's own namespace.**
  Every other identifier in the package uses `MGR_` / `mgr_*` / `MGRPATH`.

## What was in scope

The corpus was fixed at the start and deliberately not widened:

- Root guides: `README.md`, `AGENTS.md`, `MIGRATION.md`, `SECURITY.md`,
  `SETUP.md`, `CLAUDE.md`.
- The framework's own `docs/` — the documentation addendum, the architecture
  document, and the six documents under `docs/development/`.
- Everything shipped in `sample/docs/`, plus `sample/AGENTS.md`.
- All ten skills under `system/skills/` and every file under their
  `references/`.

The three frozen `docs/design/` initiatives were **validate-only**: checked
for broken paths and false claims, never rewritten. Excluded entirely:
`extras/` (bundled legacy applications, not framework documentation),
`vendor/`, and the agent-local and operator-local trees.

## The seven objectives

1. **Validation** — does every document obey the drift rules? Does every
   named path exist? Do twins declare each other? Does anything shipped cite
   internal history?
2. **Homologation** — normalize quality and consistency across documents
   written at different times under different rule versions.
3. **Gaps** — coverage analysis, enumerated from the source tree rather than
   from the documents, then mapped back to find where a reader hits a wall.
4. **Skills naming** — settle the prefix question and execute the rename.
5. **Closing review** — re-diff every fixed item against its recorded
   baseline; the punch list of what the objectives' own scopes did not reach.
6. **Skill shape homologation** — bring all ten skills to the exemplar shape
   with real per-skill content, never templated sections.
7. **Skill uniformity** — the axes the shape work did not cover:
   project-centricity, skill-versus-reference register, and tone.

Objective 4 ran deliberately late: a rename churns the names that earlier
findings quote, and running it first would have staled every open finding.

## Two rules that governed the whole initiative

**The standard is the rubric, and the initiative does not invent house
rules.** Where a desired convention was absent from the standard, the choice
was to add it there first — a product change, held to the sample bar — or
drop it. Several conventions took that route; they are in `decisions.md`.

**For a prose finding, validation and the finding collapse.** "The cited path
does not exist" is simultaneously the finding and its own verified baseline.
The current text was still quoted verbatim before any rewrite, because a
homologation edit needs a diff target exactly as a code fix does.

## Where the knowledge lives now

- Writing or reviewing a skill: `docs/development/skill-authoring.md`.
- The documentation rules themselves: `sample/docs/documentation.md`
  (canonical, shipped) and `docs/documentation.md` (framework-only addendum).
- The development loop and where a change goes:
  `docs/development/framework-workflow.md`.
- How the framework attaches, boots, and resolves a class name:
  `docs/architecture/framework-wiring.md`.
- Running an initiative like this one: `docs/development/spec-campaigns.md`.
