# Docker stack — maintainer pointer

The stack itself lives in `sample/docker/` (with `sample/docker_manage.sh`
as the only entrypoint), and **its documentation ships with it**:

- `sample/docs/development/docker.md` — setup, deploy, rotation, tuning,
  troubleshooting (operate the stack).
- `sample/docs/development/docker-internals.md` — conventions, hard rules,
  and build gotchas (edit the stack).

Consuming projects receive those two files as their own
`docs/development/`; anything a stack user or stack developer needs must
live there, never here.

Framework-side, only the rationale stays: `docker-decisions.md` (beside
this file) — design decisions with evidence, measurements, and
revisit-when conditions. Canonical home of *why*; the shipped docs carry
at most a one-line why per rule.

**Reference direction is one-way:** framework docs may deep-link into
`sample/…`; the shipped docs must never reference files under this repo's
`docs/` (those paths don't exist in a consuming project). Check:
`grep -rn 'docker-decisions' sample/` must stay empty — same for any other
framework-side doc name. (A plain `docs/workspace` mention inside
`sample/docs/documentation.md` is fine — that's the consuming project's own
convention, not a framework path.)
