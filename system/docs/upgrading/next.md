# Upgrading — unreleased

Changes between 2.x releases that alter behavior a project already depends on.
Everything else in a minor release is additive.

## Migrations: the package runs first

`manager/tools/migrate` (and `plan`) now order targets as the package's own
migrations, then the application's `database/migrations`, then the other
modules by key. Before, the package ran last, so a project migration that
altered a package table (for example adding a column to `user`) failed on a
fresh install with the table missing.

Nothing to do for an installed database: recorded versions are untouched. Two
things to check:

- A fresh install whose app-level history creates a package table itself
  (`user`, `group`, `user_group`, `login_attempt`) now finds the package's
  table already there. Remove or skip that migration; the package owns those
  tables.
- A pending package migration in an upgrade now runs before the project's
  pending ones.
