# Migrations

> Scope: runtime decisions in the per-module migration runner and its
> file-generation commands (`MGR_Migration_module_lib`, `Tools`). For how to
> write a migration or use these commands day to day, see the
> `mgr-migrations` skill instead; this document doesn't repeat that.

## Decisions

- **`manager/tools/migrate` kept its original argument order
  (`version`, `module_key`, `confirm_downgrade`) while `migrate_database`'s
  was reordered to (`connection`, `module_key`, `version`,
  `confirm_downgrade`).** `migrate` is the long-standing, most-used entry
  point — any script already calling it positionally must keep working.
  `migrate_database` is the newer, less-depended-on single-connection
  variant, and reordering it puts the commonly-supplied argument
  (`module_key`) before the rarely-supplied, destructive one (`version`),
  matching the sentinel-default convention documented in
  `mgr-cli-modules`. Don't "fix" this asymmetry by reordering `migrate` to
  match — that breaks its contract for no benefit, since `migrate` never
  needed reordering: it already put `version` first.

- **Generated migration class names are qualified by module
  (`Migration_{Module}_{table}`), not just versioned (`_v{n}`).**
  Decision (2026-08-18): `Tools::migration_file()`/`migration_path()` derive
  `{Module}_{table}[_v{n}]`, prefixing the owning module, instead of a bare
  `{table}[_v{n}]`.
  Why: every migration class here lives in PHP's bare global namespace — the
  vendored `CI_Migration::version()` resolves classes as a flat
  `'Migration_'.ucfirst(strtolower($name))`, no namespace involved anywhere
  in that chain, and it is not editable (Composer dependency).
  `Tools::migrate()` loops every configured connection, and
  `MGR_Migration_module_lib::run()` loops every module target per
  connection, all inside one PHP process — two same-named migration classes
  from different modules or connections both pending in one run get
  `include_once`'d back to back, which is a fatal `Cannot redeclare class`,
  not a per-module nuisance. Module directory names are already unique
  within a project, so qualifying by module makes cross-module collision
  impossible by construction rather than merely unlikely — the only lever
  available given no real namespace exists to borrow. `$table_name` itself
  stays unqualified (`invoice`, not `billing_invoice`) — this only affects
  the migration's own class/file identity, never the SQL table name, which
  would be its own schema-breaking decision.
  Evidence: live-reproduced 2026-08-18 on the `local` Docker instance — two
  throwaway modules each declaring `class Migration_Zzconflict`, a fresh DB
  (both pending): `tools migrate` → `PHP Fatal error: Cannot redeclare class
  Migration_Zzconflict`, and the crash pre-empted the real `manager`
  module's own pending migrations later in the same run. Full write-up
  (design alternatives considered, the naming-scheme discussion):
  `docs/workspace/00-proposals/migration-versioning/spec.md`, archived to
  `docs/workspace/archive/00-proposals/` once committed.
  Cost: the 9 migrations already shipped under
  `system/package/modules/manager/migrations/default/` predate this and are
  not retroactively qualified.
  Revisit when: the `manager-migrations-homologation` proposal is picked up
  (or dropped) — decides whether the shipped 9 ever get qualified.

- **`Tools::migration_file()`/`migration_path()` take a
  `$force_modification` flag to seed a table whose history predates
  module-qualified naming.**
  Decision (2026-08-18): a truthy `$force_modification` (CLI: a 4th arg)
  skips the existence check in `_migration_path()` and returns the
  modification branch unconditionally, so the first tool-managed migration
  for such a table lands at `_v2` rather than a bare/`_v1` name.
  Why: the module-qualification decision above created a gap for every
  table in every project adopting this tool, not just the 9 shipped here —
  `_module_migration_names()` only matches filenames whose tail is already
  `{module}_{name}`, so a table with pre-qualification history is invisible
  to the existence check and would otherwise get handed a create-table
  template for a table that already exists. The `_v2` landing spot is
  deliberately the same numbering a second module's copy of the same table
  name would get, since `_v{n}` was never a lifetime count of a table's
  modifications, only a collision key scoped to this tool's own qualified
  series. The generated file gets a one-line pointer comment
  (`_migration_modification_template()`'s `$legacy_cutover` param) noting
  that earlier migrations predate the naming convention, so `_v2` with no
  `_v1` in the module doesn't read as a mistake.
  Cost: only needed once per table — after the qualified name exists on
  disk, later modifications are detected normally without the flag.
  Revisit when: nothing pending.

- **`Tools::migration_file()`/`model_file()` print a
  `cat > ... <<'MGR_EOF'` command instead of writing the file themselves.**
  Decision (2026-08-18): both commands compute the versioned name/path and
  render the template exactly as before, but the final step changed from
  `fopen()`/`fwrite()` against `APPPATH` to printing a heredoc-style shell
  command via a shared `_write_file_command()` helper; the developer pastes
  the full output into their own host shell to create the file.
  Why: a container-side write to `application/` only ever reaches the host
  filesystem when that path is bind-mounted read-write, and this stack's
  own live-code dev bind (`-b`) mounts it **read-only** on purpose
  (`docker.md`'s "Live-code dev modes" — the bind exists for hot-reloading
  host edits into the container, not the reverse); without `-b` the write
  lands in the container's own ephemeral layer and is gone the moment
  `--rm` cleans it up. There is no bind mode in this stack where a
  container-side write both succeeds and persists to the host tree a
  developer would commit. Printing a command shifts the actual
  file-creation step to the host shell, which can always write its own
  filesystem — the container's only job becomes computing the correct
  name/version/template, something `migration_path()` already conceded (it
  has only ever printed JSON, never written a file). The heredoc delimiter
  (`MGR_EOF`) is single-quoted specifically to disable the shell's
  `$`/backtick expansion inside the pasted block — unquoted, a migration
  full of `$this->table_name` etc. would have every `$expression`
  substituted by the shell before `cat` ever saw it.
  Evidence: live-reproduced 2026-08-18 on the `local` Docker instance
  before this fix — `migration_file` under `-b` threw on `mkdir()` against
  the read-only bind with **zero console output** (no `display_errors`,
  nothing in `/var/log/manager`): a silent failure a developer could easily
  miss.
  Cost: `migration_file`/`model_file` no longer create a file as a
  side-effect of one command; every use requires copying the printed block
  into a second, host-side paste. Accepted because the prior "creates a
  file" behavior was already broken (silently, in the most common dev-loop
  configuration) rather than merely inconvenient.
  Revisit when: a writable, host-persisted bind mode is added to this
  stack for a use case that isn't live-code hot-reload — unlikely, since
  that would reintroduce the same class of "container mutates a tree it
  doesn't own" risk the read-only `-b` bind was built to avoid.
