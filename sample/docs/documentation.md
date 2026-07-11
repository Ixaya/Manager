# Documentation Guidelines

## Purpose

This document defines how documentation should be organized within the
project.

The goals are to:

-   Keep documentation easy to find.
-   Keep the repository free of scattered Markdown files.
-   Give every document a single, obvious home.
-   Keep documentation maintainable as the project grows.

## Core Principles

-   Documentation belongs where people expect to find it.
-   Keep entry-point documents short.
-   Store detailed knowledge under `docs/`.
-   Prefer updating existing documents over creating new ones.
-   Separate generated documentation from hand-written documentation.
-   Separate permanent documentation from implementation history.

## Repository Root

The repository root should remain clean.

Typical root documents are:

-   `README.md` (optional, project overview)
-   `AGENTS.md` (project entry point)

Avoid adding feature notes, implementation details, reviews, handoffs,
or operational guides to the repository root.

## AGENTS.md

`AGENTS.md` is the entry point for project guidance.

It should:

-   Describe the project at a high level.
-   Point to relevant documentation.
-   Define project-wide conventions.
-   Direct readers to the appropriate documents.

It should not become a large knowledge base.

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
  Project overview                   `README.md` (optional)
  Project architecture               `docs/architecture/`
  Development or operations          `docs/development/`
  Completed feature or initiative    `docs/design/<initiative>/`
  Work-in-progress investigations    `docs/workspace/<task>/` (temporary)
  Long-lived module reference        `docs/modules/`
  Generated information              `docs/generated/`

## Documentation Categories

### architecture/

Long-lived documentation explaining how the application is organized.

### development/

Developer-facing operational documentation.

Examples:

-   local development
-   Docker
-   deployment
-   testing
-   debugging

### design/

Documentation for a feature or engineering initiative.

Example:

``` text
docs/design/
└── user-authentication/
    ├── spec.md
    ├── decisions.md
    ├── handoff.md
    └── review.md
```

Typical documents:

-   **spec.md** --- what should be built.
-   **decisions.md** --- important design decisions.
-   **handoff.md** --- current state, remaining work and context.
-   **review.md** --- findings and recommendations.

Update existing documents instead of creating versioned copies.

### workspace/

Temporary working area for active investigations and feature development.

Each task gets its own numbered directory (e.g., `01-auth-overhaul`, `02-caching-layer`).

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

Permanent documentation describing the current behavior of modules or
major components.

### generated/

Documentation produced automatically from tooling.

Do not edit generated files manually.

## Documentation Lifecycle

Most initiatives naturally evolve like this:

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

The `design/` directory preserves permanent implementation history.

The remaining documentation describes the current project.

## Keeping Documentation Healthy

-   Prefer a few well-maintained documents over many small ones.
-   Use `workspace/` for active work — don't leave unfinished investigations in permanent docs.
-   After completing workspace tasks, distill findings into permanent docs or delete.
-   Consolidate temporary notes into permanent documentation.
-   Remove obsolete documentation.
-   Keep documentation synchronized with the project.
-   Generate documentation whenever possible.
-   Do not use emojis in any Markdown documentation. Some IDEs and
    terminals fail to render them correctly, and plain words (e.g. "Yes"/
    "No", status labels) are clearer and searchable.

## Documentation Hierarchy

1.  The project is the source of truth.
2.  Generated documentation reflects the current state.
3.  Hand-written documentation explains intent and operation.
4.  Entry-point documents help readers find information.

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
