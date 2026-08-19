# Upgrading — unreleased

Changes between 2.x releases that alter behavior a project already depends on.
Everything else in a minor release is additive.

### `field_timestamps()` is frozen — don't call it in new migrations

`MGR_Migration_builder::field_timestamps()`'s two field specs (`create_date`
`NOT NULL`, `last_update` nullable) are unchanged and will stay unchanged —
but calling it was never actually safe for a migration to depend on. A
migration is supposed to be an immutable record of what DDL it ran; a shared
helper method is not, so any future change to it would have silently changed
what every already-applied migration calling it produces on a *fresh*
install, without that migration's own file ever showing a diff. This
framework's own shipped migrations no longer call it (`Manager_attachment`'s
original migration now declares both fields explicitly instead — the
resulting DDL is identical, only the source changed), and
`manager/tools/migration_file`'s scaffold template generates explicit
`field()` calls for both columns now too.

Nothing changes for an existing project on `composer update` — this is a
recommendation, not a behavior change. Two things worth doing in your own
migrations:

- **If you have a migration that already calls `field_timestamps()`**,
  replace the call with the explicit fields it expands to today
  (`create_date` `NOT NULL`, `last_update` nullable) — a no-op change to
  what that migration produces, but it stops depending on shared code for an
  already-applied migration's behavior.
- **Going forward, declare both fields explicitly** in new migrations rather
  than calling `field_timestamps()` — matches what the scaffold now
  generates.

```bash
# find every migration in your own app that still calls field_timestamps()
grep -rn "field_timestamps()" application/
```

If you'd rather move `create_date` to the same nullable shape this
framework's own `Manager_theme`/`Manager_domain`/`Manager_attachment` tables
moved to (no default DB-enforced value, `create_date` starts `NULL` until an
explicit or default-driven write sets it), that's a deliberate `_v2`
migration against your own table — not automatic, and not required.

**Verify:** no migration in your own app calls `field_timestamps()` going
forward; any existing call site you've frozen still produces the same DDL it
did before (`NOT NULL` `create_date`, nullable `last_update`) unless you
deliberately chose to relax it.

