# REST error contract — decisions

Operator decisions with rationale, condensed from the workspace records.
Item numbers (01 #n, 02 #n, 06 #n) are the workspace objective numbering,
kept for traceability.

## The envelope

- **2026-07-29 (the `status` ruling): the three-tier `1`/`0`/`-1` is retired
  for binary `0`/`1`, as integers, with the HTTP status code carrying the
  failure kind.** `status` answers one question — did the request do what was
  asked. The tier was never a second dimension; it was a second source of
  truth for the same fact, and it already disagreed with the first. The corpus
  proved it unlearnable as written: the skill declared `-1` = framework error
  while its own example emitted `0` for an unexpected exception,
  `MGR_Exceptions` emitted `-1` for everything, `_remap()`'s 401s emitted `0`,
  and all four sample controllers emitted `-1` for plain domain failures.
  Secondary but real: `0` vs `-1` is project-idiosyncratic knowledge that
  drifts, while `404` vs `422` vs `409` is universal. And every layer below
  the envelope — proxies, APM, monitoring, retry libraries — acts on the
  status line and is blind to a body `-1`, so a 500-worthy failure returning
  `200` is invisible to all of them. Sub-rule that makes it checkable rather
  than a per-site judgment: **tier and HTTP class must agree** — `0` with a
  4xx or 5xx, `1` with a 2xx.
- **2026-07-30 (02 #1): no back-compat carve-out, anywhere.** `Login.php`
  returned `-1` with HTTP 200 for bad credentials, and live mobile clients
  plausibly branch on that. Changed in one step regardless, because the
  migration surface is smaller than it looks: ints mean `status === 1` success
  checks are untouched and `status === 0` checks catch strictly more, so only
  a client branching on `-1` is affected — on a request that already failed.
  The happy path cannot break. And the framework hard-enforces the rule only
  at `MGR_Exceptions`' four emission points, all already 5xx/404; everything
  else is `sample/`, copied once into a project, so no existing project's
  contract changes until that project chooses.
- **2026-07-30 (02 #11): diagnostics nest under an `error` object; `error`
  and `response` are mutually exclusive.** `{status, message, error: {class,
  file, line}}` for an exception, `{heading}` for a non-DB `show_error()`,
  `{severity, file, line}` for a PHP warning, `{errno, file, line, query?}`
  for a DB error. A failure has no success payload to carry, and vice versa.
  `show_error()`'s odd `details` key was renamed `message` to match every
  other site.
- **2026-07-30 (02 #11, post-review): a fixed diagnostic string is preserved,
  not dropped.** Reshaping flat keys into a structured `error` object gave
  three of the four methods an obvious new home for their old `error` value
  (`$heading`, `get_class()`, `$severity`) and left `_parse_db_error()`'s
  fixed `'A Database Error Occurred'` with none — so it was silently lost.
  Restored as `error.heading`, mirroring `show_error()` exactly: top-level
  `message` carries the *specific* text, `error` carries the *categorical*
  label. Putting the specific text under `error.message` instead would have
  been the one place in the file where `message` is generic.
- **2026-07-30 (02 #6): `response` always holds an object.** A bare scalar id
  becomes `{'id': $id}`; where there is nothing to report, the key is omitted
  rather than filled with a placeholder.

## The disclosure boundary

- **2026-07-29 (01 #1): production suppression is one generic envelope —
  `{status: 0, message: 'An unexpected error occurred.'}` and nothing else.**
  Every 5xx is byte-identical from outside, so a caller cannot probe the API
  and work out which internal failure they hit by comparing which keys came
  back or how the wording differed.
- **2026-07-29 (01 #5): the predicate is `is_cli() || <CI's display_errors
  test>`, not `ENVIRONMENT`.** CI's own `str_ireplace` expression is copied
  verbatim so the framework splits on exactly the signal CI does. The
  `is_cli()` half is load-bearing: production CLI runs with
  `display_errors = 0`, and the tools controller depends on full detail.
- **2026-07-29 (01 #1): the gate covers `show_error()` as well, but only for
  5xx.** `show_error()` is also the 404 renderer, so a blanket gate would
  answer 404s with a generic 500. The consequence is deliberate and stated in
  the skill: **4xx is never suppressed**, in any environment.
- **2026-07-29 (01 #2/#3): the leak and the `Error` case fix at two different
  seams, in two files.** The `third_party` catch sits *inside*
  `parent::_remap()`, so an outer catch in `MGR_Rest_Controller` can never see
  an `Exception` — only an `Error`. Any proposal claiming to solve both at one
  seam is wrong, and this was verified before ruling rather than assumed.
- **2026-07-29 (01 #3): `system/third_party/` carries the hook, never the
  logic.** The catch body was extracted verbatim into
  `REST_Controller::_handle_dispatch_throwable(\Throwable $ex)` — a pure,
  behavior-identical extraction that an upstream merge still reads as such —
  and `MGR_Rest_Controller` overrides it. The parameter is typed `\Throwable`
  deliberately: PHP forbids narrowing a parameter type in an override, so
  anything narrower would have blocked the override and forced a second
  method.
- **2026-07-30 (01 #3, at review): the override renders directly instead of
  delegating to `parent::`.** It already holds the `Exceptions` instance, and
  the log and the render are the two halves of "log before responding" —
  adjacent in one method, nothing can slide between them. Trade accepted
  knowingly: a future upstream change to those two lines is no longer
  inherited. That is the intended consequence of the hook pattern.
- **2026-07-29 (01 #9): two production paths still answer a body-less 500 —
  accepted and documented, not fixed.** A throw in a controller constructor
  and a fatal only the shutdown handler sees both bypass the boundary
  entirely; CI's global handlers gate *rendering* on `display_errors`, so in
  production they log, set 500 and exit with nothing written. Rendering them
  means displacing `set_exception_handler()` and
  `register_shutdown_function()` — ownership of the terminal error path, a
  state this framework has been in before and one that bred bad patterns
  downstream. The cost of not fixing is bounded: both paths **are** logged and
  the caller still gets a 500; what is lost is a parseable body on two rare
  paths. Documented instead in `mgr-rest-controller` and in the scaffold's
  Docker troubleshooting ladder, because the symptom names nothing on its own.

## The model layer's failure signal

- **2026-07-29 (01 #6): the remedy was aimed at the wrong layer first, and the
  correction is the transferable part.** The original fix was to force
  `db_debug` on in production — one config override, apparently the whole
  fix. The defect was not "CI suppresses the failure" but "our model layer
  discards the signal CI hands us". Aimed at the config, it would have left
  the discard in place and covered only projects that changed a flag they had
  no reason to change. Aimed at the layer that loses the information, it
  became one method's return contract. Recorded as a diagnostic in
  `docs/development/spec-campaigns.md`.
- **2026-07-30 (01 #6a): `execute_list()` returns `?array` — `null` when the
  query failed, `[]` when it ran and matched nothing.** Eight `get_all*`
  signatures follow, and `count_all()` became `?int` on the same contract
  (not in the item as written: it consumed `execute_list()` and coerced a
  failure to `0`, which would have preserved the exact defect one method
  over). **No throw was introduced** — the throwing form was explicitly
  rejected, because projects have validated logic against empty results.
- **2026-07-30 (01 #6a): `Rest_user_group::get_user_group_names()` fails
  closed.** The signature change broke it (`array_column()` TypeErrors on
  `null`); resolved as `$rows ?? []` → no groups, per the safe-default rule,
  because it feeds authorization and an unfiltered or fatal result is worse
  than "belongs to no group".
- **2026-07-30 (01 #6a): a failed count passes through to the client as
  `null` rather than being checked into a whole-request failure.** Ruled
  against the general rule, narrowly: a dashboard aggregates many independent
  metrics, and one failed tile must not fail the others. A `-1` sentinel was
  rejected as an in-band magic value inside a valid int's own domain —
  immediately after the `status` ruling retired the first `-1`. JSON `null`
  cannot collide with a real count. Documented at the call site, and the skill
  states the exception is narrow: this holds only where the nullable value is
  *one of several* independent values in one payload, never where it is the
  whole response.
- **2026-07-30 (01 #6c): the two DB failure modes are documented as a
  project's choice, not forced from framework code.** Mode (i), `db_debug`
  on: a failed query stops the request. Mode (ii), off: the call returns and
  every DB call must check. Forcing the flag was dropped — it overrides a
  decision that is the project's to make.

## Traceability

- **2026-07-29 (06, standing): no suppression without a log, and no quieting
  of development.** An acceptance gate on every other fix, not a findings
  list: a change that hides detail from a production client must be shown to
  write a log entry in the same change, and must leave every development row
  unchanged or louder. Now a hard rule in `mgr-code-style`.
- **2026-07-29 (01 #6b): one sanctioned exception to the no-quieting rule.**
  "Louder" is about the *signal*, not which handler emits it. Suppressing the
  driver's own `pg_query()`/`SQLite3::query()` warning lets CI's `db_debug`
  report render instead — trading `{severity: 2, message: "pg_query(): Query
  failed…", file: postgre_driver.php}` for `{errno, message, file, line,
  query}`, which names the failing SQL. Both are HTTP 500 and both stop the
  request; the second carries strictly more. This **homologates Postgres and
  SQLite to a shipped behavior rather than inventing one** — MySQL already
  behaved this way, and served as the unchanged control in the verification.
- **2026-07-30 (01 #6b): the discriminator is the statement call, not the
  origin path.** Changed from the item as written (`BASEPATH.'database'`) on
  challenge, before the probe run, and the reason is not aesthetic: a path
  test catches every non-fatal warning from the whole database tree, and where
  such a warning is *not* a failed statement there is no `db_debug` report
  behind it — so the response would render nothing at all and execution would
  continue. That is plain quieting, which the licence does not cover. Cost
  accepted: a message-prefix test is PHP's wording, not a stable API. Its
  failure mode is benign — if a future PHP rewords the warning, the gate stops
  matching and behavior reverts to louder, never to silence. Scoped to
  non-fatal severities as a correctness constraint, not a preference: CI
  `exit(1)`s on the fatal set regardless, so an early return on a fatal would
  delete the body without preventing the exit.
- **2026-07-30 (01 #6b): connection failures are deliberately not gated.** A
  bad password fails at `pg_connect()`, whose message names the actual cause,
  while CI's `error_db` replacement is a generic "Unable to connect…".
  Yielding there would trade a precise message for a vaguer one — the inverse
  of the item's own justification. Verified by probe, not deduced.
- **2026-07-29 (06 #3): the correlation id is wanted, and deferred out of this
  initiative.** An id in both the log line and the client response is what
  makes a suppressed production error supportable at all — otherwise "we
  suppressed the detail and logged it" leaves an operator with a timestamp and
  a guess. Deferred because it is less urgent than the fixes it supports and
  building it here would have expanded both the implementation surface and the
  QA matrix. The consequence is accepted deliberately: this initiative swept
  the envelope with no id key and the deferred work edits it a second time — a
  scheduled double edit, not an accidental one. One constraint binds whoever
  implements it: **the id, and only the id.** It is the first key added back to
  a deliberately minimal envelope, and the risk is precedent — it must not
  become the argument for putting an error class or a failure code back.
- **2026-07-30 (06 #2): `show_error()` logs its own 5xx.** CI's `show_error()`
  never logged and `MGR_Exceptions` did not add one, so once the production
  gate landed, a misconfigured deploy answered 54 bytes and wrote **nothing** —
  a live breach of this initiative's own rule, found by the audit rather than
  listed in any spec, and closed immediately. The renderer knows whether its
  caller already logged structurally rather than by state, because each entry
  point has one caller shape: `show_exception()` and `show_php_error()` are
  always preceded by a log, `error_db` by the driver, `error_404` deliberately
  by nothing. Two residuals ruled acceptable: a caller that logs and then calls
  `show_error()` itself produces a duplicate — two entries beat none — and the
  three rare `error_db` siblings that skip the driver's log stay unlogged, all
  three being developer-error conditions.
- **2026-07-30 (06 #2): the 404 suppression is confirmed, not reversed.** A
  404 is a client error that discloses nothing, so "no suppression without a
  log" does not apply to it; nginx and php-fpm record every one; and
  un-suppressing it would bury this initiative's signal under scanner traffic.
  The unrouted 404 and the 405 unknown-method are the same class and are left
  unlogged for the same reasons.
- **2026-07-30 (06 #4): log-write failures get a preflight command, not a
  runtime check.** A runtime check would have to run on every request to catch
  a misconfiguration made once at deploy time. `manager/tools/log_check`
  reports the resolved paths, ownership, mode and writability, and performs a
  real append test. It deliberately does **not** create a missing log file — a
  file it creates belongs to whoever ran it, which is the exact mismatch it
  exists to detect — and it warns when run as root, because root appends to
  anything and would report success on the failing state. Proposed as an
  extension of `env_check` and as a `Health_checks` endpoint; both declined,
  the latter because that endpoint ships `auth_override = 'none'` and must not
  expose filesystem state.
- **2026-08-01 (06 #5): development + `db_debug` off answering `200` on a
  failed query is the contract, not a breach.** Raised at the closing review as
  possible quieting. A project that turns the flag off has accepted "every DB
  call must check", which the shipped `database.php` comment states with no
  environment qualifier; making development alone still stop the flow would
  mean the two environments disagree about which mode the project is in — the
  Postgres-only accident #6b had just removed. Two facts make the silence safe
  rather than merely intended: #6a made a failed read distinguishable, without
  which "every DB call must check" was impossible for the shape callers use
  most; and every framework-internal transaction site checks `trans_status()`
  and rolls back, so nothing the gate swallows is undetectable by its caller.

## Controller hygiene, decided along the way

- **2026-07-30 (02 #2): a precautionary `try/catch` is removed, not
  rewritten.** The dispatch boundary now logs and renders a disclosure-gated
  envelope, which is strictly more than these blocks produced — they swallowed
  the exception, never logged, and handed the caller a raw `getMessage()`.
  Every call inside each block was traced for an actual `throw` before
  removal, not assumed.
- **2026-07-30 (02, follow-up): tracing for `throw` is not tracing for
  failure.** The removal passes checked only whether a call could throw, and
  missed that several returns already carried a failure signal — a `null`
  `data` key reaching the client as `status: 1` **and being cached**, and two
  discarded `ion_auth->update()` returns answering "updated successfully" on a
  real conflict. Both fixed; the rule is now in `mgr-rest-controller`, and it
  is the reason the skill's own anti-pattern example had to be corrected too.
- **2026-07-30 (02 #10): self-registration stays open in the sample, with a
  hardening comment.** Verified live before ruling — the endpoint really does
  register, activate and return a usable key with no API key presented. It is
  deliberate for this sample's portal; the comment recommends 2FA/email
  verification and stricter rate limiting before real production use.
- **2026-07-30 (02): the two identical credential-failure messages are a
  property, not duplication.** Both branches keep emitting the same string;
  status and HTTP code change together, the text never diverges. Likewise
  `password_recovery_post` keeps returning success for an unknown username.
  These were re-confirmed as a **false positive** in the originating findings,
  not fixed.
