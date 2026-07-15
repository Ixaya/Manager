---
name: ixaya-code-style
description: Use when writing or reviewing ANY PHP code in this codebase — naming, typing, PHPDoc, comments, formatting, error handling, and where documentation/decisions belong. The style baseline for all other ixaya skills.
---

# Ixaya Code Style

The style exemplar is `vendor/ixaya/manager/system/core/MGR/Model.php` (with
`MGR_Migration_builder.php` and `MGR_Model_Dyn.php`) — when unsure how something
should look, look there. New code should be indistinguishable in style from those
files.

## Hard rules

1. **Everything is typed.** Properties, parameters, and returns — union
   (`int|string|bool`) and nullable (`?array`) as needed. No untyped signatures
   in new code.
2. **PHPDoc on every public-facing function** — and keep it SHORT. It's read in
   IDE popups, not as documentation prose. One summary line, `@param`/`@return`
   with array shapes (`array<string, mixed>`, `array{key: ?string, path: string}`)
   where arrays are structured, `@throws` when it throws. No essays.
3. **Named parameters at call sites.** Mandatory for boolean arguments
   (`set_alter_keys(data: $data, delete: true)`) and when skipping defaults;
   standard for calls with 3+ arguments (`field(name: 'email', type: MgrFieldType::VarChar, constraint: 254)`).
   This is what makes future refactors and signature changes cheap.
4. **Clear names over short code.** `$connection_name`, not `$conn`;
   `get_unique_hash()`, not `uhash()`. A longer descriptive name beats a comment
   explaining a short one. Loop vars and tiny closures may stay short.
5. **PHP 8.4-era style on an 8.2 floor.**
   Allowed: enums, `readonly`, promoted constructors, `match`, named arguments,
   first-class callables, DNF types, spread of string-keyed arrays.
   NOT allowed (needs 8.3/8.4): typed class constants, `#[\Override]`,
   property hooks, asymmetric visibility.
6. **Strict comparisons** (`===`/`!==`). Older framework code mixes `==` — don't
   copy that.
7. **No `else` after `return`/`throw`** — use guard clauses and early returns.
8. **English only** for identifiers and comments (legacy files have Spanish
   remnants — don't imitate).
9. **One purpose per function.** When a method grows a second responsibility,
   extract it.

## Formatting & tooling

- PSR-12 via php-cs-fixer with **tabs** (`.php-cs-fixer.php`: `setIndent("\t")`),
  LF endings, short array syntax.
- Before finishing any change: `vendor/bin/php-cs-fixer fix` and
  `vendor/bin/phpstan analyse` (level 5) — both must pass.
- Every PHP file starts with `defined('BASEPATH') or exit('No direct script access allowed');`.
- snake_case for methods, properties, and variables (CI3 heritage);
  enums and value-object classes in PascalCase (`MgrFieldType`, `MgrDriver`).

## Comments

Two kinds, two audiences:

- **Function-header comments (PHPDoc): for whoever *calls* the function.** What it
  does, what to pass, what comes back, what it throws — enough to use it without
  reading the body. Keep it SHORT (see Hard rule 2); no internals, no history.
- **Inline comments inside a function: for whoever next *edits* it.** Only when
  necessary, and short — **1–2 lines**, up to **4** for a constraint/consequence
  warning stated as a direct effect ("must stay above X, or Y breaks"). Each one
  earns its place by flagging a gotcha the next dev/agent would miss from the code
  alone — a non-obvious constraint, an assumption, a *why* the code can't express.
  Never narrate what the next line does.

Both kinds:

- **No pointers to other docs.** A comment must never say "see README.md", "see
  gotchas.md", or link any document. If a comment feels like it needs that
  pointer, the content itself belongs in that doc — not a pointer in the code.
- **Never address the comment to an LLM or agent** ("verify this before
  proceeding", "read the version via `…`"). Write for the reader of the code.
- **No history in comments:** no dates, no "Item 5"/"Phase 2"/review-session
  labels, no "we decided X" — that belongs in the Decisions log.
- Good: `// runs all upstream logic`, `// 64-bit assumed for 14-digit timestamps`,
  the cross-engine behavior matrix in `MGR_Migration_builder`'s DocBlock.
- Bad: `// loop through the users`, `// call the model`, `// fixed per review`, `// see gotchas.md`.

## Patterns from the reference code

- **Guard clauses / early returns** over nested conditionals.
- **`match` over `switch`** when mapping values (`set_database_time_zone()`).
- **Backed enums with behavior methods** instead of class constants
  (`MgrDriver::fromCI(...)->isMysqlFamily()`, `MgrFieldType::supportsUnsigned()`).
- **Validate at construction** so invalid states can't exist
  (`MgrFieldBuilder::_validate()`), and **fail loud**: throw
  `InvalidArgumentException` with a class-prefixed message —
  `"MGR_Model_Dyn: unknown clause kind '{$kind}' — condition would be silently dropped."`
  Never silently skip bad input.
- **`readonly` promoted constructor properties** for value objects
  (`MGR_Model_Dyn_join`), constructed with named arguments; mark classes
  `final` when they're not designed for extension.
- **By-ref output params** (`?string &$error = null`) are the accepted pattern
  for secondary outputs alongside a primary return.
- Typed properties with initializers at the top of the class, configuration
  properties before state properties.

## Where knowledge lives (documentation rules)

Principle: **knowledge lives at the scope it applies to**, and documents the
*why* — code and git already record the *what*.

| Scope | Where |
|---|---|
| Framework conventions (models, REST, migrations…) | The `ixaya-*` skills — canonical home is the `ixaya/manager` package (shipped via composer; projects symlink them into tool-specific dirs like `.claude/skills/`) |
| Project-wide rules | Root `AGENTS.md` (the cross-tool standard; `CLAUDE.md` is a one-line `@AGENTS.md` import for Claude Code) |
| Module domain knowledge | Two files at the module root, only for modules with real domain knowledge (e.g. a billing or external-integrations module) — see template below |
| Feature specs | Ephemeral — implement against them, then delete/archive; fold durable residue into the module Decisions log or a skill |

Per-module template (don't create these mechanically for every module):

```
{module}/README.md          # for humans AND agents
  Purpose: 2-3 sentences
  Boundaries: what this module owns / must not touch
  Decisions: dated one-liners, append-only
    - 2026-07: polling instead of webhooks for <provider> because <reason>
  Gotchas: external-service quirks the code can't show

{module}/AGENTS.md          # agent hard rules for this module only
```

**The Decisions rule:** when you make a non-obvious choice (algorithm, external
API workaround, schema tradeoff), append ONE dated line to the module README's
Decisions section. Not an essay — one line. Never auto-generate documentation,
never document method signatures in READMEs (PHPDoc + skills cover that), never
duplicate what git history says.
