# Documentation Guidelines

## Purpose

This document defines how documentation is organized throughout the
project. Its goals are:

-   Keep documentation easy to find.
-   Keep the repository free of scattered Markdown files.
-   Give every document a single, obvious home.
-   Make documentation easy to maintain over the lifetime of the
    project.

## Core Principles

-   Documentation belongs where people expect to find it.
-   Every document should have a single owner.
-   Keep entry-point documents short.
-   Store detailed knowledge under `docs/`.
-   Prefer updating existing documents over creating new ones.
-   Separate generated documentation from hand-written documentation.
-   Separate permanent documentation from implementation history.

## Repository Root

The repository root should contain only standard entry-point files that
are expected by developers or common tooling.

Typical examples:

-   `README.md`
-   `LICENSE`
-   `CHANGELOG.md`
-   `SECURITY.md`
-   `MIGRATION.md`
-   `AGENTS.md` (or equivalent project entry point)

`README.md` is the public face of the project. It should remain stable
and should not accumulate implementation notes or working documentation.

Avoid adding design notes, reviews, handoffs, operational guides, or
feature documentation to the repository root.

## AGENTS.md

The project entry-point should act as an index, not a knowledge base.

Its responsibilities are to:

-   Describe the project.
-   Point readers to relevant documentation.
-   Reference reusable skills or tooling.
-   Define high-level project conventions.

Detailed documentation belongs under `docs/`.

## Documentation Layout

All project documentation should live under `docs/`.

Suggested layout:

``` text
docs/
├── architecture/
├── development/
├── design/
├── modules/
├── workspace/          (temporary, not committed)
└── generated/
```

Only create additional directories when they provide meaningful
organization.

## Decision Matrix

  If you're documenting...           Location
  ---------------------------------- ------------------------------------------
  Repository overview                `README.md`
  Upgrade guidance                   `MIGRATION.md`
  Project architecture               `docs/architecture/`
  Development or operations          `docs/development/`
  Engineering initiatives (complete) `docs/design/<initiative>/`
  Work-in-progress investigations    `docs/workspace/<task>/` (temporary)
  Long-lived module reference        `docs/modules/`
  Generated information              `docs/generated/`

## Documentation Categories

### architecture/

Long-lived documentation explaining how the project is structured.

Examples:

-   system architecture
-   application flow
-   extension points
-   major subsystems

### development/

Operational documentation for developers.

Examples:

-   local development
-   Docker
-   deployment
-   testing
-   debugging
-   release process

### design/

Documentation for engineering initiatives.

Each initiative should have its own directory.

Example:

``` text
docs/design/
└── 01-docker-stack/
    ├── spec.md
    ├── decisions.md
    ├── handoff.md
    └── review.md
```

Typical documents:

-   **spec.md** --- what should be built.
-   **decisions.md** --- important architectural decisions.
-   **handoff.md** --- current implementation status, remaining work,
    and context for continuing.
-   **review.md** --- findings, recommendations, and validation.

Avoid versioned documents such as:

-   `review2.md`
-   `handoff-final.md`
-   `review-v3.md`

Update existing documents instead.

### workspace/

Temporary working area for ongoing investigations and implementation work.

Each task gets its own numbered directory (e.g., `01-docker`, `03-auth-migration`).

Typical documents:

-   **spec.md** --- findings, analysis, and work scope while in progress.
-   **handoff.md** --- current state, blockers, and context for continuing work.
-   **review.md** --- intermediate reviews or validation results.

**Important:** The `workspace/` directory is not committed to the repository.
After a task is complete, its useful knowledge is distilled into permanent
documentation (design/, architecture/, development/, modules/) or deleted if
inconsequential.

This directory is a working area, not a historical record.

### modules/

Permanent documentation about project modules or major components.

This documents the current state of the project, not its implementation
history.

### generated/

Documentation produced automatically from code or tooling.

Examples:

-   route inventories
-   module indexes
-   service catalogs

Generated documentation should never be edited manually.

## Specifications

A specification describes what should be built.

It defines goals, requirements, and expected behavior.

It is not an execution log.

## Handoffs

A handoff captures:

-   current implementation state
-   completed work
-   remaining tasks
-   known issues
-   important context for future work

It exists to continue development efficiently.

## Reviews

Reviews evaluate the implementation against the specification and
architecture.

They record findings and recommendations rather than implementation
history.

## Documentation Lifecycle

Long-running initiatives generally evolve like this:

``` text
spec.md (in workspace/)
    ↓
implementation
    ↓
review.md (in workspace/)
    ↓
handoff.md (in workspace/) — current state recorded
    ↓
distillation (knowledge extracted from workspace)
    ↓
permanent documentation (design/, architecture/, development/, modules/)
or deletion (if inconsequential)
```

The `workspace/` directory holds temporary working files during development.

The `design/` directory preserves permanent project history (completed initiatives).

The other documentation directories describe the current system.

## Keeping Documentation Healthy

-   Prefer a few well-maintained documents over many small ones.
-   Use `workspace/` for active work — don't leave unfinished investigations in permanent docs.
-   After completing workspace tasks, distill findings into permanent docs or delete.
-   Consolidate temporary notes into permanent documentation.
-   Remove obsolete documentation instead of archiving multiple
    revisions.
-   Keep documentation synchronized with the codebase.
-   Generate documentation whenever it can be derived automatically.
-   Do not use emojis in any Markdown documentation. Some IDEs and
    terminals fail to render them correctly, and plain words (e.g. "Yes"/
    "No", status labels) are clearer and searchable.

## Documentation Hierarchy

1.  Code is the source of truth.
2.  Generated documentation reflects the current code.
3.  Hand-written documentation explains intent, architecture, and
    operation.
4.  Entry-point documents help readers discover information.

Documentation should improve discoverability without increasing
repository clutter.

## Repository Configuration

The `docs/workspace/` directory should be excluded from version control.

Add to `.gitignore`:

``` text
docs/workspace/
```

This keeps the repository free of temporary working files while allowing
developers to use workspace as a staging ground for active investigations
and work-in-progress documentation.
