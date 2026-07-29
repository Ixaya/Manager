# System fixes — final state

Initiative CLOSED 2026-07-14 for all actionable items (#1-#3, #6-#18).
Everything verified per `review.md`. Reopened briefly 2026-07-29 for four
more fixes found writing a PHPUnit contract test for dyn-mode models — see
`decisions.md`.

## Open / deferred

- **#4 — `__get` magic proxy in `MGR_Upload_lib` / `MGR_Attachment_lib` /
  `MGR_Migration_builder`: deferred indefinitely.** Requires an explicit
  operator go-ahead plus a subclass inventory across consuming projects
  first; constraints in `decisions.md`. Do not pick up in a routine pass,
  and do not add the pattern anywhere new.
- **#5 — composer post-install symlinks for the skills: optional
  nice-to-have.** Projects create `.claude/skills/mgr-*` symlinks
  manually per the README one-liner.
- **README canonicality flag: RESOLVED** — `README-v2.md` was promoted to
  `README.md` (the tracked README now carries the "Agent skills" table;
  mgr-auth added 2026-07-14).
- **SQL Server: RESOLVED** — `sync_commit_enabled()`'s driver-matched
  `NOT`/`!sync_enabled` was replaced by a portable `CASE WHEN sync_enabled
  = 0 THEN 1 ELSE 0 END`, valid on every engine including SQL Server; no
  per-driver branch exists anymore.
