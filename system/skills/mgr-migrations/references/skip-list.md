# Skip list: migrations recorded without running

A project on a database shared with other platforms can mark chosen
migrations, its own or the package's, as applied without running them, instead
of seeding each environment by hand with `version_set`. The list is a property
of the coordinator, set in a project shim.

## Setting it up

The shim goes in place of the alias (see mgr-helpers-libraries):

```php
<?php

defined('BASEPATH') or exit('No direct script access allowed');

// application/libraries/Migration_module_lib.php
require_once MGRPATH . 'libraries/MGR_Migration_module_lib.php';

class Migration_module_lib extends MGR_Migration_module_lib
{
	protected array $_skipped_migrations = [
		'vendor:ixaya:manager:system:package:modules:manager:default' => [
			'20260213175005_Manager_ion_auth_v2',
		],
	];
}
```

Keys are the target labels `plan` prints; the application's is
`application:<conn>`. Values are file names without `.php`, as
`version_list <module_key> <conn>` prints them. `plan` adds `skipped:N` to a
target's line, and `version_list` marks the files `(skipped)`.

## Behavior

- A skip is recorded as applied and never run. Removing the entry later does
  not run the file; adding one for a file already applied changes nothing going
  up, and a downgrade across it does not run its `down()`. `migrate
  <skipped version> <module_key>` is a valid target and records it.
- An entry that matches nothing makes `migrate` and `plan` print
  `[FAIL] skip list: …` and run nothing on any connection. That covers a
  label on no connection the run reaches (`migration_db` plus the connection
  being run), a file name that is not in its target, and a value that is not a
  list. A package update that renames or removes a skipped file fails the same
  way until the entry is edited. `version_list` and `version_set` do not
  validate, so they still help to fix an entry.
- Running in segments around a skip weakens the up-front structural check: a
  file with a missing class or `up()` that comes after a skip in the same run
  is found only after the files before it ran. The stored version stays at the
  last good step; fix the file and run again.
- The skip says the change already exists, or must not exist, in that
  database. Migrations that depend on it are the project's concern.
