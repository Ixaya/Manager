# Documentation Guidelines

> Scope: governs **this project's** documentation. This file ships with
> the ixaya/manager scaffold and is the canonical documentation standard —
> the framework repository follows it too, layering only a small
> framework-only addendum on top (maintained there). If you are editing
> this file inside the framework repo, you are editing a shipped product:
> every consuming project bootstraps its documentation culture from it.

## Purpose

This document defines how documentation is organized in this project, for
both the people and the coding agents working on it. Its goals:

- Keep documentation easy to find — for a person browsing, and for an agent
  listing directories and grepping.
- Keep the repository free of scattered Markdown files.
- Give every document a single, obvious home.
- Keep documentation maintainable as the project grows.

## Core Principles

- Documentation belongs where people expect to find it.
- Keep entry-point documents short.
- Store detailed knowledge under `docs/`.
- Prefer updating existing documents over creating new ones.
- Separate generated documentation from hand-written documentation.
- Separate permanent documentation from implementation history.

## Repository Root

The repository root should remain clean. Typical root documents:

- `README.md` (optional, project overview)
- `AGENTS.md` (project entry point)

`README.md` changes rarely, deliberately, and minimally. Avoid adding
feature notes, implementation details, reviews, handoffs, or operational
guides to the repository root.

## AGENTS.md

`AGENTS.md` is the entry point for project guidance. It should:

- Describe the project at a high level.
- Point to relevant documentation and skills.
- Define project-wide conventions.
- Direct readers to the appropriate documents.

It is an index, not a knowledge base — detailed documentation belongs
under `docs/`.

An entry point is the one document whose errors are believed instead of
checked: it is auto-loaded rather than sought out, so a reader meets it
before knowing what the task is and cannot judge which claims matter; it
arrives framed as instructions, so its contents read as fact rather than as
claims to verify; and nobody opens it *to answer a question*, which is the
moment a wrong answer would be caught. A document under `docs/` is opened by
a reader with a specific need, who notices a mismatch immediately. Three
rules follow from that:

1. **Admit content by the durable-vs-volatile test.** Ask "if this goes
   stale, does the reader find out?" — not whether the content is prose or a
   list. DURABLE, so admissible: rules, invariants, scope statements,
   prohibitions, and pointers. VOLATILE, so not: commands, concrete paths,
   counts, examples, line numbers.
2. **Point at directories, not files, unless the pointer is load-bearing.**
   Name a directory and its purpose (`docs/development/` — the development
   loop; `docs/architecture/` — boot and structure), never an individual
   document filename — unless the reader needs one specific document by
   name for a specific task, in which case name the file. Adding a document
   then requires no entry-point edit in the common case, and descriptive
   filenames let a browsing reader find it. This is drift rule 1 applied to
   the entry point.
3. **Re-check the entry point whenever you write docs.** After adding or
   changing a document, check whether the entry point now states,
   duplicates, or contradicts what was just written. This fires only while
   someone is already editing documentation — the same shape as the
   twin-declaration rule.

A hand-maintained inventory may stay in an entry point at **name plus a
one-line purpose** only. It is admissible against drift rule 1 despite being
concrete because it describes the project's own code — touched constantly,
and a wrong row fails loudly, since the named artifact is not where it says.
Once such a table grows per-artifact detail (endpoints, models,
dependencies) it crosses into believed-not-checked and must split: the table
keeps the name and the one line, and points at `docs/modules/<name>.md`.

## Documentation Layout

All project documentation lives under `docs/`:

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

| If you're documenting... | Location |
|---|---|
| Project overview | `README.md` (optional) |
| Project architecture | `docs/architecture/` |
| Development or operations | `docs/development/` |
| Completed feature or initiative | `docs/design/<initiative>/` |
| Work-in-progress investigations | `docs/workspace/<task>/` (temporary) |
| Long-lived module reference | `docs/modules/` |
| Generated information | `docs/generated/` |

## Documentation Categories

### architecture/

Long-lived documentation explaining how the application is organized —
system structure, application flow, major subsystems.

### development/

Operational documentation for developers — reference material with no
inherent order, kept as **flat files with descriptive names**. Agents (and
people) discover docs by listing the directory and grepping: the filename
is the index, so it must say what the file contains without being opened.
No numbered directories here; numbering is reserved for ordered history
(`design/`, `workspace/`). `README.md` as a filename is reserved for a
directory index, never for main content.

Shipped examples in this scaffold:

- `docker.md` — operate the Docker stack (setup, deploy, troubleshooting)
- `docker-internals.md` — edit the stack (hard rules, build gotchas)

### design/

Documentation for completed features and engineering initiatives. Each
initiative gets its own **numbered** directory — numbering records the
chronological order initiatives happened in, which is the point of this
category (permanent history).

Example:

``` text
docs/design/
└── 01-user-authentication/
    ├── spec.md          what was built and why
    ├── decisions.md     important design decisions, with rationale
    ├── handoff.md       final state, remaining work, context
    └── review.md        findings, validation results
```

Update existing documents instead of creating versioned copies
(`review2.md`, `handoff-final.md`).

### workspace/

Temporary working area for active investigations and feature development.
Each task gets its own numbered directory (e.g. `01-auth-overhaul`,
`02-caching-layer`) holding its working `spec.md` / `handoff.md` /
`review.md`, plus the operator's `prompts.md` session runbook.

**Important:** `workspace/` is not committed. After a task completes, its
useful knowledge is distilled into permanent documentation (`design/`,
`architecture/`, `development/`, `modules/`) or deleted if inconsequential.
This directory is a working area, not a historical record.

### modules/

Permanent documentation describing the current behavior of modules or
major components — the current state, not implementation history.

### generated/

Documentation produced automatically from tooling. Never edit generated
files manually.

## Documentation Lifecycle

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

`workspace/` holds temporary working files during development. `design/`
preserves permanent project history. The remaining directories describe
the current project.

## Drift Rules

Documentation drifts from reality in known ways. These are invariants, not
suggestions — check them whenever editing or reorganizing docs:

1. **An index is a liability.** Any document that enumerates project
   artifacts (modules, commands, files) silently lies the moment an
   artifact is added or removed. Prefer pointing at self-describing
   artifacts over enumerating them; where a table is genuinely needed,
   keep exactly one and re-verify it whenever the artifact set changes.
2. **Every named path must exist.** A document referencing a missing file
   is a finding either way: the doc is stale, or the file is missing.
3. **Permanent docs never reference `workspace/` files, task numbers, or
   session history.** Workspace files vanish, and campaign labels ("Item
   5", "Phase 2", "see handoff §2.1") mean nothing once the workspace is
   deleted — a reader hits a pointer that resolves to nowhere. Distill the
   content itself into the permanent doc; the history behind it belongs in
   `design/` records and version control, nowhere else.
4. **Cross-reference by section name, never by section number** — numbers
   drift silently when sections are added. Exception: a procedure with
   more than 10 numbered steps, or more than 5 self-references to its own
   steps, may reference them by number instead — quoting a full heading
   at that scale adds more bloat than the drift risk it removes.
5. **Numbering is only for ordered history** (`design/`, `workspace/`).
   Reference documentation uses flat descriptive filenames — the name is
   the index.
6. **Write lessons as applying here.** Attributing a documented trap to
   "another project" or "an old version" invites readers to dismiss it.
7. **Twin documents declare each other.** Any file maintained in parallel
   with a sibling opens with a scope header naming the sibling and the
   audience split.
8. **Claims about "this repo" must be true of this repo** — not of the
   project the content was first written in. Copied content imports false
   facts: VCS type, hosting, infrastructure.

## Keeping Documentation Healthy

- Prefer a few well-maintained documents over many small ones.
- Use `workspace/` for active work — don't leave unfinished investigations
  in permanent docs.
- After completing workspace tasks, distill findings into permanent docs
  or delete them.
- Remove obsolete documentation instead of archiving multiple revisions.
- Keep documentation synchronized with the codebase.
- Generate documentation whenever it can be derived automatically.
- Do not use emojis in any Markdown documentation. Some IDEs and terminals
  fail to render them correctly, and plain words ("Yes"/"No", status
  labels) are clearer and searchable.
- Write table delimiter rows compactly — `|---|---|`, one `---` per column.
  Padded and column-width-matched rows render identically but churn the diff
  every time a cell's width changes.
- Hard-wrap prose at roughly 76-80 columns. A reviewer diffing an unwrapped
  paragraph sees one changed line and has to read the whole thing to find the
  edit. Tables, fenced code, and long URLs are exempt — never break those to
  satisfy the width.
- Indent code inside fenced blocks with spaces, never tabs. A tab renders at
  whatever width the reader's viewer chooses, and outside a fence Markdown
  expands leading tabs to four-column stops, where they turn into structure.
  A document whose whole purpose is to be pasted verbatim into a source file
  is the exception — it carries that language's real indentation.

## Documentation Hierarchy

1. Code is the source of truth.
2. Generated documentation reflects the current code.
3. Hand-written documentation explains intent, architecture, and
   operation.
4. Entry-point documents help readers discover information.

Documentation should improve discoverability without increasing
repository clutter.

## Repository Configuration

The `docs/workspace/` directory should be excluded from version control:

``` text
# .gitignore
docs/workspace/
```

This keeps the repository free of temporary working files while allowing
`workspace/` to serve as the staging ground for active investigations.
