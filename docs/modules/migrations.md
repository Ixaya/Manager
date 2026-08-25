# Migrations

> Scope: runtime decisions in the per-module migration runner
> (`MGR_Migration_module_lib`, `manager/tools`'s `migrate`/`migrate_database`
> commands). For how to write a migration or use these commands day to day,
> see the `mgr-migrations` skill instead; this document doesn't repeat that.

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
