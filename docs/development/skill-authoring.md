# Skill Authoring and Review

> Scope: the `mgr-*` skills in `system/skills/`. Use it when writing a new
> skill, editing an existing one, or running a validation pass over skills
> written by another agent. Read `docs/documentation.md` and the shipped
> standard `sample/docs/documentation.md` first — this file adds only what is
> specific to the skill format. The framework-only drift rules there
> (references are one-way; nothing shipped cites workspace history) apply in
> full and are not repeated here.

## The medium

Five facts about how skills are consumed. Most rules below are consequences
of one of them, and an author who does not hold them makes the same mistakes
repeatedly.

1. **A skill is loaded whole.** There is no partial read. Anything in
   `SKILL.md` is present for the entire task; anything in `references/` is
   not present until the agent chooses to open it.
2. **`references/` is opt-in, and the agent who most needs a reference is the
   one least likely to open it.** An agent confident enough to hand-write SQL
   does not first go looking for the model API. Gating content behind a
   deliberate read is a real cost, not a neutral reorganization.
3. **Skills ship to consuming projects** through Composer. The default reader
   is working in an application with the framework under
   `vendor/ixaya/manager/`, not in this repository.
4. **The `description` decides whether any of it is read.** It is the only
   text an agent sees before choosing to load the skill.
5. **Skills are read by agents, not compiled.** Nothing validates them. A
   wrong parameter name survives indefinitely and fails at runtime in someone
   else's project.

## Shape

Frontmatter, then `# Manager <Topic> (<governing class>)`, then a
`> **Prerequisite:**` blockquote naming `mgr-code-style`, one orientation
paragraph, a `Source of truth` list, task sections, and a closing
`## Anti-patterns`. `mgr-models`, `mgr-rest-controller` and
`mgr-web-controllers` are the reference implementations.

The H1 parenthetical names the governing class where there is one
(`(MY_Model / APP_Model_Dyn)`); skills covering several subsystems omit it.

### When a missing section is correct

Three skills legitimately omit a standard section. Before adding one, apply
the **equivalent-shape test**: does the skill already carry that content in a
shape matching its own structure? If so, a second format adds a format, not
information.

| Skill | Omits | Because |
|---|---|---|
| `mgr-code-style` | Prerequisite, Source of truth, Anti-patterns | It is the baseline skill; its `Hard rules` list is its own closing block, and its rules already state wrong-vs-right in a line each |
| `mgr-helpers-libraries` | Source of truth | Catalog-shaped; every table row carries its own vendor path |
| `mgr-auth` | Anti-patterns | `DO NOT REGRESS` is its closing block, in the register security invariants need |

A section with no real material is worse than its absence. If you find
yourself hunting for a third example to fill a block, stop — that is the
signal the section does not belong.

## Writing rules

**Orientation paragraph.** Every skill has one, and it pairs what the thing
is with what the reader must not do. "Every model extends `MY_Model` … never
query `$this->db` directly." A purely descriptive opening wastes the one
paragraph guaranteed to be read.

**Source of truth.** A short list, each entry a path plus what it answers,
introduced by "Source of truth (only read if something here is
insufficient):". Paths are project-relative
(`vendor/ixaya/manager/system/…`).

**Anti-patterns.** A fenced PHP block of WRONG cases and, where it helps, a
RIGHT counterpart. Two rules govern it:

- **The canon test: every contrast must be unconditional.** Skills ship to
  consuming projects. Where the framework deliberately delegates a choice —
  response wording, delivery channel, anything behind an extension seam — a
  contrast that picks a side teaches canon the framework cannot enforce, in
  the most authoritative register the skill has. Test: would this be wrong in
  *every* project, or only in ours? If only ours, it is a default, and
  defaults go in prose that says so.
- **Prose the block duplicates is not redundant.** The prose carries the
  mechanism and the reason; the block carries the shape. Removing the prose
  leaves a block asserting things it never explains.

**Cross-references.** Backtick a skill name only in the Prerequisite
blockquote, where it is the argument you pass to load it. Elsewhere it is
bare: `(see mgr-models)` as a parenthetical, "the mgr-models skill" when the
name is a noun in the sentence. Point at first mention and only where the
pointer earns its place — never build an index. A reference-dense catalog
holds to one pointer per sibling skill; a topic skill with two or three
references is not at risk.

**Naming the repository — two terms only.** "framework repo" for the
`ixaya/manager` checkout, "this codebase" where the statement is genuinely
generic. Never "this repo": a skill is read from a consuming project, where
that phrase resolves to the reader's own repository and inverts the sentence.
The full `ixaya/manager` name is worth spending once, at a decision where the
reader has to identify which checkout they are in.

**Paths.** State them in project terms and let the reader swap the root (root
`AGENTS.md`, "Reading a skill's paths in this repo"). Add an explicit
framework-mode qualifier only where the mistake is expensive — currently two
places corpus-wide: the `.dockerignore` probes guard, and the `BE_` fork
warning. Before citing any path, confirm it resolves for the reader: check
`.gitattributes` for `export-ignore` (`docs/` and `extras/` do not ship;
`sample/` does) and check whether the scaffold supplies it.

**Commands.** Name what must pass, not how to invoke it, unless the
invocation is identical for both audiences. `php-cs-fixer fix` is correct
everywhere; `vendor/bin/php-cs-fixer fix` is correct in a project and
forbidden in this repo, where the gates run through the Docker `tools`
service.

**Code fences.** Spaces in `SKILL.md`, tabs in `references/` — reference
files are pasted verbatim into real PHP, where `mgr-code-style`'s tab indent
applies. Fenced code is exempt from the wrap rule; never break a line to
satisfy the width. Where a fence sits inside a list item, the list
indentation stays spaces regardless.

**Frontmatter description.** Two sentences: when to use it, phrased in the
words an agent would be thinking at that moment and ending "in this
codebase"; then what it teaches, and what it replaces. Trigger vocabulary
matters more than accuracy of scope — a skill that describes itself in
framework terms does not fire for an agent thinking in task terms.

## references/

Extract only **situational** content: needed in a specific scenario, not on
every task in the skill's domain. Authoring a library, building a controller
from scratch, a paste-once base class, one worked example.

Never extract the core API of the thing the skill teaches. That content must
arrive unbidden, because the failure it prevents — reimplementing what the
framework already provides — happens precisely when the agent does not know
to look.

## Review checklist

For validating a skill, especially one written by a weaker model. Ordered by
where defects are actually found.

1. **Parameter names against source.** Method existence is nearly always
   right; parameter names rot. This matters here specifically because
   `mgr-code-style` makes named arguments standard, so a wrong name is a
   fatal, not a typo. Diff the whole signature, not the method name.
2. **Every path resolves** for a reader in a consuming project. Run the
   export-ignore and scaffold checks above.
3. **Every named command, config key and constant exists.** Grep them.
4. **Anti-pattern contrasts are unconditional** (canon test).
5. **Claims about behavior trace to code.** Follow the call, do not trust the
   sentence. Behavior that crosses several frames — a by-reference flag, a
   condition wrapping an entire gate — is where skills are silently wrong,
   because the author documented the intent rather than the path.
6. **Reviewing a skill against its class also audits the class.** Properties
   the skill omits may be dead code; behavior the skill cannot state cleanly
   may be a defect. Park those separately rather than papering over them in
   prose.
7. **Structure:** orientation present and directive, sections in the standard
   order, file ends on its closing block, no stray trailing prose.
8. **Vocabulary:** no workspace or campaign nouns. Words like item, finding,
   batch and phase mean nothing in a consuming project.

## Working method

Review file by file, deciding each finding before moving on. Decisions
cascade — a ruling on one skill changes what counts as a defect in the
next — and a full up-front analysis produces confident recommendations that
then need retracting.

Measure before sweeping. A scan costs minutes and routinely shows a suspected
corpus-wide pattern is four legitimate cases and one real one. Never launch a
mechanical pass on an unmeasured hypothesis.
