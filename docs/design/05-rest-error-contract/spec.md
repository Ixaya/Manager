# REST error contract and response envelope (2026-07) — scope

What a REST client receives when a request fails, and what the server
records when it does. Ran as objectives 01, 02 and 06 of the (gitignored,
since archived) homologation campaign, preserved at
`docs/workspace/archive/14-general-improvements.tar.xz`.
Its other half — guard scoping, model conventions, CLI shape — is
`06-framework-homologation` beside this directory.

The initiative began as a report that an uncaught exception in a REST action
returned a body-less 500 in production. That premise was wrong twice over,
and the corrections are the reason this record exists rather than a one-line
fix: reading the code showed `REST_Controller::_remap()` already catches
`Exception` and renders it, so the defect was **over**-disclosure; running it
then showed a third behavior nobody had predicted, a failed SQL query
answering **HTTP 200 with a success envelope**.

## What was in scope

- **The disclosure boundary.** An uncaught exception rendered class, message,
  file and line to a production client, and was the one failure path that
  wrote nothing to the log. An `Error`/`TypeError` escaped the catch entirely
  and produced a 0-byte 500. A failed query under `db_debug` returned the
  executed SQL.
- **The response envelope.** The documented three-tier `status`
  (`1`/`0`/`-1`) was unlearnable as written and disagreed with itself in the
  skill, the framework and all four shipped sample controllers. Nine further
  envelope divergences across those controllers: a field duplicating
  `status`, the reserved `error` key reused, two names for the payload
  wrapper, three value shapes under one key, HTTP codes not tracking the
  body, Spanish messages, a no-op cast.
- **The model layer's failure signal.** `execute_list()` coerced a failed
  query to `[]`, deliberately and documented as such, which made "check every
  DB call" impossible for the read shape callers use most.
- **Traceability as an acceptance gate.** Not a findings list but a property
  every other fix had to satisfy before it closed: no suppression without a
  log, and no quieting of the development signal.

## What was deliberately not done

Four questions were ruled out of scope rather than left undecided, each
because answering it inside this initiative would have mixed a settled fix
with an open design question: the correlation id, the broken `api_only =
false` seam, the ambiguous auth model returns, and a silent CLI failure found
while switching engines. Each is now a standalone proposal with its mechanics
verified; their current state is in `handoff.md`, "Remaining open work", and
the rulings that put them there are in `decisions.md`.

Two production paths that still answer a body-less 500 were **accepted**
rather than deferred — that ruling and its cost are in `decisions.md`.

## Where the knowledge lives now

- Conventions: the `mgr-rest-controller`, `mgr-models`, `mgr-code-style` and
  `mgr-live-probes` skills — updated in the same passes.
- Decisions with rationale: `decisions.md` here.
- Final state and remaining open items: `handoff.md` here.
- Observed validation record, all four engine × environment combinations:
  `review.md` here.
- Consumer-facing breaking changes: `MIGRATION.md`, "Upgrading within 2.x".
