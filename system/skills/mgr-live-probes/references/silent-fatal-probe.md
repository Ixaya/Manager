# Silent-fatal probe (when a request 500s and every log is empty)

For the failure mode where a request dies with an empty body and nothing in
any of the three log channels: the error happens before the app's logger
initializes, and sometimes the error-rendering path itself fails too — so
`display_errors` shows nothing either. The fix is a CLI probe that runs the
failing code path inside a wrapper that forces out both catchable throwables
AND true fatals (`E_ERROR`/`E_PARSE`/`E_CORE_ERROR`/`E_COMPILE_ERROR`, which
bypass try/catch entirely).

Run it as a **CLI script inside the php container** — not by swapping the
front controller or editing nginx routing. A docker smoke-test debug session
did it that way (`public/debug.php` + a rewrite retarget) and paid for it
with a revert checklist and a routing dead-end (`try_files` + directory
index resolution meant bare `/` never hit the debug file at all). The CLI
form needs no nginx change and cleans up by deleting one file.

```php
<?php
// silent_fatal_probe.php — throwaway, run via:
//   docker cp silent_fatal_probe.php <i>-php-1:/tmp/
//   docker exec <i>-php-1 php /tmp/silent_fatal_probe.php
// Delete from the container when done.

// Catches true fatals that never reach a catch block.
register_shutdown_function(function (): void {
	$e = error_get_last();
	if ($e !== null && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
		fwrite(STDERR, "FATAL: {$e['message']}\n  at {$e['file']}:{$e['line']}\n");
	}
});

error_reporting(E_ALL);
ini_set('display_errors', '1');

try {
	// >>> the failing code path goes here — e.g. bootstrap the front
	// >>> controller, or reproduce the specific call that dies:
	// $_SERVER['argv'] = ['index.php', 'manager/tools/migrate'];
	// require '/var/www/html/public/index.php';
} catch (\Throwable $t) {
	fwrite(STDERR, 'THROWABLE: ' . get_class($t) . ": {$t->getMessage()}\n"
		. "  at {$t->getFile()}:{$t->getLine()}\n{$t->getTraceAsString()}\n");
}
```

Notes:

- Output goes to **stderr** so it can't be swallowed by output buffering the
  app may have started.
- If the suspect is DB-shaped (`... on false` signature — see the docker
  doc's "Silent 500" troubleshooting entry, `docs/development/docker.md`;
  in the framework repo, `sample/docs/development/docker.md`), run
  `manager/tools/env_check` FIRST; it
  answers "did the credential even load" in one command and usually makes
  this probe unnecessary.
- If the failing path is an HTTP request specifically (auth, session,
  routing), reproduce it CLI-side when possible; only if the failure is
  genuinely web-only, put the same try/catch + shutdown wrapper inside a
  normal authenticated probe endpoint (Test_probe subclass) instead of
  touching `public/` or nginx config.
