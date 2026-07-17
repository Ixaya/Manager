# Documentation Guidelines — framework addendum

> Scope: the **ixaya/manager framework repository**. The canonical
> documentation standard lives in the shipped scaffold —
> `sample/docs/documentation.md` — and governs this repo too; read it
> first. This file carries only what is framework-specific. Nothing here
> duplicates the base standard: every shared principle lives there, once.

## Framework deltas to the base standard

- **Repository root** additionally carries `MIGRATION.md` (upgrade
  guidance for consuming projects — its home whenever the decision matrix
  would ask) and `SECURITY.md`.
- **`README.md` is the package's public face** and changes rarely,
  deliberately, and minimally: when installation steps actually change,
  when a major capability ships, or to fix an error. Keep diffs surgical —
  no restructuring, no session learnings, no operational detail (that
  belongs under `docs/` or the scaffold's docs). Any command shown must be
  verified against the current scaffold before committing.
- **`docs/workspace/` is already ignored** here via `docs/.gitignore` — no
  setup needed.

## Framework-only drift rules

These apply on top of the base standard's Drift Rules:

1. **References are one-way.** Files under `sample/` ship to consuming
   projects and must never reference this repo's `docs/` or
   `docs/workspace/` — those paths do not exist in a consuming project.
   Framework docs may deep-link into `sample/` freely. Check:
   `grep -rn 'docker-decisions' sample/` must stay empty — same for any
   other framework-side doc name.
2. **Claims about "this repo" must be true of this repo** — not of the
   project the content was first written in (VCS type, hosting,
   infrastructure).
3. **Nothing shipped carries workspace or campaign references — HARD
   rule.** Anything under `sample/` (docs, code comments, env templates,
   configs) must never cite this repo's workspace docs, handoff sections,
   decision logs, or campaign item/task numbers ("Item 5", "Phase 2",
   "HANDOFF §2.1") — a consuming project can never resolve them, and they
   leak internal history into the product. This has shipped before: an env
   template comment citing a handoff section survived into the scaffold.
   The lifecycle *vocabulary* (a doc explaining what a handoff is) is fine;
   citing a *specific* one is not. At distillation time, sweep for
   history citations and justify every hit:
   `grep -rniE '§|item [0-9]|phase [0-9]|workspace/[0-9]' sample/`
   (the only expected hit is the shipped standard quoting these examples).

## Maintaining the shipped standard

`sample/docs/documentation.md` is a **product**, not a mirror of this
file: every consuming project bootstraps its documentation culture from it,
the same way it bootstraps its code conventions from the sample PHP and the
skills. Editing it is a product change and rides the same review bar as
sample code (AGENTS.md hard rule: `sample/` is the canonical example
source) — edit it deliberately, never as a side effect of reorganizing this
repo's own docs. It must stay fully self-contained: a consuming project
sees only that file.
