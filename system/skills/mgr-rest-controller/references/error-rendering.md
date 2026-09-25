# How uncaught errors render for API clients

What `MGR_Exceptions` sends when an API request fails without the controller
answering it — for debugging an error response, or writing a client against
one. The controller-side rules (when to catch, checking nullable returns)
are in the skill itself.

## Two 5xx shapes, chosen by `should_disclose_details()`

`MGR_Exceptions` renders uncaught exceptions, PHP errors and 404s as JSON for
API clients, with CORS headers. A **5xx** arrives in one of two shapes, chosen
by `should_disclose_details()` — which is `is_cli() || display_errors`, **not**
`ENVIRONMENT`:

- **Disclosed** (CLI, or `display_errors` on — development): `status` and
  `message` at the root, plus an `error` object carrying internals —
  `{status: 0, message, error: {class, file, line}}` for an uncaught
  exception. A query that fails while `db_debug` is on renders the parsed DB
  envelope instead — `error: {heading, errno, file, line, query?}`, with the
  driver's own text in `message`. A PHP warning/notice renders
  `error: {severity, file, line}`.
- **Suppressed** (otherwise — production): `{status: 0, message: 'An unexpected
  error occurred.'}` and nothing else, so one failure mode cannot be told from
  another by comparing responses.

This `error` key is internals only (`class`/`file`/`line`/`severity`/`errno`),
never data a controller chose to expose, and it renders only in the disclosed
shape — never in production, and never something a controller hand-builds
itself. Reaching it needs no code: don't catch the exception, and its
propagation to the dispatch boundary renders whichever of the two shapes
`should_disclose_details()` selects, always as HTTP 500.

**4xx is never suppressed** — it is deliberate and client-facing. **Detail is
always logged**, under either shape, so a generic response costs the server
nothing. Write clients against the suppressed shape: the framework's
diagnostic `error` does not exist in production — a controller's own `error`
can still appear there, since it was safe to send from the start.

## Two 5xx paths with no body at all

An exception thrown in a controller *constructor*, and a true fatal (memory
exhaustion). CI's global handlers own those and render only while
`display_errors` is on, so production returns a body-less 500 — accepted,
because taking those over means the framework owning the terminal error
path. Both are still logged. A client must treat an empty 500 body as a
failure to report, not a protocol error.
