# System fixes — final state

Initiative CLOSED 2026-07-14 for all actionable items (#1-#3, #6-#18).
Everything verified per `review.md`.

## Open / deferred

- **#4 — `__get` magic proxy in `MGR_Upload_lib` / `MGR_Attachment_lib` /
  `MGR_Migration_builder`: deferred indefinitely.** Requires an explicit
  operator go-ahead plus a subclass inventory across consuming projects
  first; constraints in `decisions.md`. Do not pick up in a routine pass,
  and do not add the pattern anywhere new.
- **#5 — composer post-install symlinks for the skills: optional
  nice-to-have.** Projects create `.claude/skills/ixaya-*` symlinks
  manually per the README one-liner.
- **README canonicality flag: RESOLVED** — `README-v2.md` was promoted to
  `README.md` (the tracked README now carries the "Agent skills" table;
  ixaya-auth added 2026-07-14).
- **SQL Server:** `sync_commit_enabled()`'s driver match has no `SQLServer`
  branch (T-SQL rejects `NOT <col>` as a scalar) — handle if that driver
  ever becomes a real target.
