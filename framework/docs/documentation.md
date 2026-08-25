# Documentation Guidelines — framework addendum

> Scope: the **ixaya/manager framework repository**. The canonical
> documentation standard lives in the shipped scaffold —
> `sample/docs/documentation.md` — and governs this repo too; read it
> first. This file carries only what is framework-specific. Nothing here
> duplicates the base standard: every shared principle lives there, once.

## Framework deltas to the base standard

- **Repository root** carries one document the base standard's clean-root
  rule would otherwise send under `framework/docs/`: `CLAUDE.md` — a
  one-line `@AGENTS.md` import, not content.
- **`.github/` is platform metadata, not documentation**, and sits outside
  this standard — its layout is GitHub's, not ours. It holds `SECURITY.md`
  (vulnerability reporting) and is `export-ignore`d.
- **`system/docs/`** is the shipped, **project-facing** reference tree —
  the material a consuming project actually reads, as opposed to this
  repo's own process docs under `framework/docs/`. It ships in full to every
  `vendor/ixaya/manager/`, so it stays small and curated:
  - `system/docs/setup.md` — the full first-install guide (formerly root
    `SETUP.md`). It could not live under this repo's own `framework/docs/` — a
    consuming project has no `docs/` until the scaffold copy step inside
    this guide has run — but `system/docs/` ships with `vendor/`
    regardless, so that blocker doesn't apply to it.
  - `system/docs/upgrading/` — upgrade guidance, split by release instead
    of one growing file: `<version>.md` documents what to do to move *to*
    that version, and `next.md` is an always-existing accumulator for
    unreleased upgrade-relevant work. A campaign/session writes its
    consumer trap to `next.md` the moment it closes, rather than waiting
    for a version number to exist. The release pass renames `next.md` →
    `<version>.md` when that release is cut, then recreates an empty
    `next.md` for the following cycle — so "where does unreleased work go"
    never depends on a tag existing yet.
- **`README.md` is the package's public face** and changes rarely,
  deliberately, and minimally: when installation steps actually change,
  when a major capability ships, or to fix an error. Keep diffs surgical —
  no restructuring, no session learnings, no operational detail (that
  belongs under `framework/docs/` or the scaffold's docs). Any command
  shown must be verified against the current scaffold before committing.
- **`framework/docs/workspace/` is already ignored** here via
  `framework/docs/workspace/.gitignore` — no setup needed.
- **`framework/docs/workspace/` carries two directories the base standard's
  numbered-task-dir layout does not name**, and both persist across
  campaigns: `00-shared/` (standing procedure, the current campaign's rules,
  parked items) and `00-proposals/` (self-contained write-ups for work found
  outside a campaign's scope and parked). The layout consequence is that this
  tree is not uniformly disposable — "a working area, not a historical
  record" holds per campaign directory, not per tree, since a campaign
  directory is archived and deleted at distillation while these are not. The
  mechanics belong to `framework/docs/development/spec-campaigns.md`.

## Framework-only drift rules

These apply on top of the base standard's Drift Rules:

1. **References are one-way.** Anything that ships to a consuming project —
   `sample/` and `system/skills/` — must never reference a path that exists
   only in this repo: `framework/docs/`, `framework/docs/workspace/`, or an
   `export-ignore`d tree such as `extras/` — unless the reference carries
   an explicit guard naming it as framework-only (e.g. "(this repo only,
   not shipped)"), which tells the reader the path will not exist in their
   own checkout. A skill is normally read from inside a consuming project,
   so its paths must resolve there (`vendor/ixaya/manager/system/…`, not a
   bare `system/…`). Framework docs may deep-link into `sample/` and
   `system/skills/` freely. Check:
   `grep -rn 'docker-decisions' sample/ system/skills/` must stay empty, or
   every hit must carry the guard — same for any other framework-side doc
   name.
2. **Nothing shipped carries workspace or campaign references — HARD
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
