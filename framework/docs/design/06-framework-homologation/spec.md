# Framework homologation (2026-07) — scope

Does the code the framework ships actually follow the conventions the
framework documents? Ran as objectives 03, 04 and 05 of the (gitignored,
since archived) campaign preserved at
`framework/docs/workspace/archive/14-general-improvements.tar.xz`. Its other
half — the REST error contract and response envelope — is
`05-rest-error-contract` beside this directory.

The generic failure mode behind all three: an agent or a developer reusing
whatever existing code sits nearby will copy an inconsistent pattern as
readily as a good one, so a convention that only lives in a skill drifts in
the corpus that teaches it.

## What was in scope

- **The `BASEPATH` guard.** `AGENTS.md` stated it as a universal hard rule for
  every PHP file, and 26 framework-owned files did not have it. The rule, not
  the corpus, was treated as the suspect part.
- **Model query conventions.** The originating candidate expected widespread
  raw SQL. It was largely a false positive — 10 of 12 models are correct
  `MY_Model`/`APP_Model_Dyn` subclasses — but the two exceptions carried a
  live cross-engine defect and a silently split database connection.
- **CLI/cron controller shape.** Three different guard shapes across three
  CLI-invoked controllers, and one web controller suspected of missing a
  guard it should not have.

## What the validation refuted before any work started

Recording these because each would otherwise be re-raised:

- **Migration style** — all nine package migrations already use
  `MGR_Migration_builder` with typed `field()` calls. The candidate was
  already resolved.
- **Auth failure messaging** — the login failure messages are deliberately
  identical and password recovery always returns success. No enumeration
  surface; a false positive.
- **Helper usage** — no ad hoc reimplementations of the shipped `mgr_*`
  helpers were found. Kept as a watch item with a conclusive sweep defined,
  not as a known defect.
- **`extras/`** — legacy example code kept so legacy CI3 projects can upgrade,
  and `export-ignore`d, so it never reaches a consuming project. Ruled out of
  scope campaign-wide: not homologated, not fixed, and never cited as a
  pattern source.

## Where the knowledge lives now

- Conventions: `AGENTS.md`'s hard rules, and the `mgr-code-style`,
  `mgr-models` and `mgr-cli-modules` skills — updated in the same passes.
- Decisions with rationale: `decisions.md` here.
- Final state and remaining open items: `handoff.md` here.
- Validation record, including the engine matrix: `review.md` here.
