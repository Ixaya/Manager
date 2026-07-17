Guide for upgrading a legacy project from **Manager 1.x — which shipped
framework files inside the project tree** (`application/third_party/MX/`,
`manager_*` helpers, `Ix_*`/`MNGR_*` classes) — to **Manager 2.0, where the
framework lives only in the composer package**. Written for a human or a
coding agent; every phase ends with a verification step.

Scope note: the REQUIRED migration is phases 0–6 (package install, deletions,
shims, renames, the array-returns behavioral change, config wiring), followed
by phases 7–9 (PHPStan, code style, line endings — cleanup passes, each its
own commit). Moving to `.env`-based single configs is NOT required — it's in
"Big picture" at the end, because it's more nuanced and can be done later,
config file by config file.

---

## Phase 0 — Inventory & safety

1. Clean VCS checkpoint. No unrelated changes mixed into the migration.
2. Requirements: PHP 8.2+, composer.
3. Size the work:

```bash
grep -rc "MNGR_\|mngr_\|MNGRPATH" application/ public/ | grep -v ":0"
grep -rl "IX_Rest_Controller\|API_Model\|ix_mailing\|ix_upload_lib\|ix_domain\|ix_theme" application/
ls application/third_party/MX application/helpers/manager_*_helper.php 2>/dev/null
```

**Verify:** you have a list of affected files and the framework copy is located.

## Phase 1 — Install the package, replace the entry point

1. In `composer.json`, set `"ixaya/manager": "^2."` (bump from `^1.`, or add
   if absent) and align companion dependencies with 2.0's expectations — the
   reference migration also bumped `phpoffice/phpspreadsheet` `^1.28` →
   `^5.0`; diff against `vendor/ixaya/manager/sample/composer.json` for the
   current set, plus any `extra`/patch entries (msgpack). Then
   `composer update ixaya/manager` (with the companion bumps).
2. Replace `public/index.php` and `public/.htaccess` with the sample's
   (`vendor/ixaya/manager/sample/public/`), then re-apply project
   customizations by diffing against your old copies. What the 2.0 entry
   point changes — this is the migration's structural core:
   - Defines `MGRPATH` + `APPMGRPATH` pointing at
     `vendor/ixaya/manager/system` (validated with 503 exits). Every shim
     and config-wiring entry in later phases resolves through these —
     nothing in the project may define framework paths itself anymore.
     `$system_path` (the CodeIgniter core via `nielbuys/framework`) is
     unchanged from 1.x.
   - Boots the env layer BEFORE CodeIgniter: `Env_lib::load()` +
     `manager_env_helper`, then
     `define('ENVIRONMENT', mgr_env('APP_ENV') ?? $ci_env ?? 'development')`.
     The fallback chain is deliberate: with no `.env` files, `CI_ENV`
     deployments keep selecting per-env config dirs exactly as in 1.x —
     this is what makes the full env migration optional (Big picture).
     A `local` environment case is also recognized now.
   - **Environment-selection gotcha:** the 2.0 `.htaccess` DROPS the 1.x
     `<IfModule mod_env.c> SetEnv CI_ENV ...` block. If your deployment
     relied on it, replacing `.htaccess` makes `ENVIRONMENT` silently fall
     back to `development` — on production that means dev error display.
     Either set `APP_ENV` in the minimal `.env` below (recommended) or
     re-add your `SetEnv` block as a kept customization.
   - **Timezone gotcha:** 1.x hardcoded the timezone in `index.php`; 2.0
     reads `APP_TIMEZONE` and does NOTHING when it's unset — the app then
     silently runs on php.ini's timezone. Even on the bare-minimum path,
     create a minimal `.env` next to `public/`'s parent with just:

     ```
     APP_ENV=development        # production on prod — replaces the .htaccess SetEnv
     APP_TIMEZONE=America/Mexico_City
     ```
3. Copy `phpstan.neon` + `phpstan-bootstrap.php` from the sample (points the
   analyzer at the vendor framework instead of the in-tree copy).

**Verify:** `composer install` succeeds; `vendor/ixaya/manager/system/` exists.

## Phase 2 — Delete the copied framework, shim the extension points

**THE RULE — diff before delete.** Every file below may carry local
customizations that accumulated while it lived in the project. Before
deleting, diff it against the package's version
(`vendor/ixaya/manager/system/...`). Anything project-specific gets ported to
an app-side subclass (the same pattern as `Attachment_invoice_lib extends`
the package attachment lib) — never edited into `vendor/`.

### 2a. Delete outright — the package now provides these

| Delete from project | Package replacement (loads by the same or mapped name) |
|---|---|
| `application/third_party/MX/` (entire dir) | `system/third_party/MX/` |
| `application/helpers/manager_*_helper.php` (all) | `system/package/helpers/` (autoloaded) |
| `application/core/REST_Controller.php` | `system/third_party/REST_Controller.php` |
| `application/controllers/Language.php` | package `Language` controller (`language/change/{locale}`) |
| `application/controllers/Check.php`, `Media.php` | legacy, unused — delete, NO replacement; remove any routes pointing at them |
| `application/modules/manager/controllers/Tools.php`, `Health_checks.php` (+`api/`), `Example_crons.php`, `models/Slack.php` | `system/package/modules/manager/` |
| `application/views/auth/*.tpl.php` | `system/package/views/auth/` |
| `application/libraries/`: `Format.php`, `Seeder.php`, `Ion_auth.php`, `Amazon_aws.php`, `Async_exec_lib.php`, `Ix_mailing.php`, `Ix_upload_lib.php`, `Bcrypt.php`, `MY_Image_lib.php`, `Dummy_lib.php` | `system/package/libraries/` aliases (see the load-name map in Phase 3; `Bcrypt`/`MY_Image_lib` are obsolete — no replacement) |
| `application/models/`: `Ion_auth_model.php`, `Manager_option.php`, `Rest_key_model.php`, `Rest_user.php`, `Ix_domain.php`, `Ix_theme.php` | `system/package/models/` (`Ix_domain`→`Domain`, `Ix_theme`→`Theme` — references must update, Phase 3) |
| Module-level framework copies (e.g. a module's own `Attachment_lib.php`) | package equivalent; keep only true subclasses |

### 2b. Replace body with a thin shim — copy from `sample/application/`

```
application/core/MY_Model.php        → class MY_Model extends MGR_Model {}
application/core/MY_Controller.php   → extends MGR_Controller
application/core/MY_Loader.php       → extends MGR_Loader
application/core/MY_Router.php       → extends MGR_Router
application/core/MY_Exceptions.php   → extends MGR_Exceptions
application/libraries/MY_Migration.php           → extends MGR_Migration
application/libraries/Cache/MY_Cache.php         → extends MGR_Cache
application/libraries/Cache/drivers/MY_Cache_redis.php → extends MGR_Cache_redis
```

Each shim is exactly: BASEPATH guard + `require MGRPATH . "...";` + an empty
subclass. Custom logic found in the old bodies moves into these subclasses.

### 2c. New app-side base classes — copy from `sample/application/core/`

```
APP_Rest_Controller.php   (replaces IX_Rest_Controller.php — delete the old file)
APP_Api_Model.php         (replaces API_Model.php — delete the old file)
APP_Model_Dyn.php         (new capability)
```

`Admin_Controller`, `Site_Controller`, `Private_Controller` stay app-owned —
update them to the new parents/patterns using the sample's copies as
reference, keeping project logic. One 2.0 requirement is easy to miss:
layout/view resolution now depends on `$this->_container` and
`$this->_theme`, so each web base controller must set them explicitly in its
constructor BEFORE `parent::__construct()` (the reference migration added
`$this->_container = 'frontend'; $this->_theme = 'default';` to
`Site_Controller`; `Admin_Controller` sets `'admin'`/`'default'`). Blank
pages or wrong-layout rendering after migration usually trace back to this.

**Verify:** `grep -rl "third_party/MX\|manager_helper" application/` returns nothing.

## Phase 3 — Mechanical renames (grep-driven)

Run each replace, then its verification grep; expect ZERO remaining matches
outside comments/docs.

| Old | New | Kind |
|---|---|---|
| `MNGR_` | `MGR_` | class prefix |
| `mngr_` | `mgr_` | helper functions |
| `MNGRPATH` | `MGRPATH` | constant |
| `IX_Rest_Controller` | `APP_Rest_Controller` | base class |
| `API_Model` | `APP_Api_Model` | base class |
| `ix_mailing` / `Ix_mailing` | `mailing_lib` / `Mailing_lib` | library load name |
| `ix_upload_lib` / `Ix_upload_lib` | `upload_lib` / `Upload_lib` | library load name |
| `amazon_aws` / `Amazon_aws` | `amazon_aws_lib` / `Amazon_aws_lib` | library load name |
| `ix_domain` / `Ix_domain` | `domain` / `Domain` | model + property refs |
| `ix_theme` / `Ix_theme` | `theme` / `Theme` | model + property refs |

```bash
# example pass (repeat per row; review each diff, don't fire blind on binary/vendor)
grep -rl "MNGRPATH" application/ | xargs sed -i '' 's/MNGRPATH/MGRPATH/g'
# verification (repeat per row)
grep -rn "MNGR_\|mngr_\|MNGRPATH\|IX_Rest_Controller\|ix_mailing\|ix_upload_lib\|ix_domain\|ix_theme" application/ public/
```

Out of scope: database table names and legacy migration files — leave history
untouched.

## Phase 4 — Model return types: objects → pure arrays (BEHAVIORAL)

The biggest non-mechanical change. Depending on the legacy version, the old
base model returned a MIX of objects and arrays (typically: single rows as
objects, lists as arrays). Manager 2.0's `MGR_Model` returns **arrays
everywhere** (`row_array()`/`result_array()`) — code doing `$row->name` on a
`get()`/`get_where()` result breaks at runtime, not at parse time.

**Preferred path — migrate to pure arrays:** convert property access on model
results to array access (`$user->name` → `$user['name']`). Detection is
heuristic, not mechanical:

- grep for `->` on variables assigned from `get(`/`get_where(`/`by_hash(`
  calls (and on values passed to views from those);
- exercise the app and watch for `Attempt to read property ... on array`;
- PHPStan flags many of these once the shims are in place (Phase 2).

**Stopgap for large codebases — `$legacy_mode`:** setting
`protected bool $legacy_mode = true;` on a model restores OBJECT returns for
its **single-row** methods only (lists stay arrays — matching the old mixed
behavior). Use it to migrate module-by-module instead of big-bang: enable it
on the models whose consumers you haven't converted yet, keep a burn-down
list, and remove each flag as its consumers go array-pure. Never enable it on
new models (the `ixaya-code-style` skill forbids it in new code).

**Verify:** grep `legacy_mode` returns only the models on your burn-down
list — ideally zero.

## Phase 5 — Config wiring (REQUIRED minimum)

The package resolves through CI's package-path mechanism; two files MUST
match the sample's wiring (compare against
`vendor/ixaya/manager/sample/application/config/`):

- **`config.php`** — the package bootstrap entries: `subclass_prefix`,
  composer autoload path, the `Modules::$locations` entry mapping
  `MGRPATH . 'package/modules/'`, enabled hooks. Port these entries into your
  existing config.php; keep every project value as-is.
- **`autoload.php`** — `$autoload['packages'] = [MGRPATH . 'package'];` plus
  the `manager_*` helper autoload list.
- **`hooks.php`** — the `MGR_Bootstrap` hook registration (optional — the
  sample ships it commented out; port it only if your project enables it).

Keeping your per-environment config dirs (`development/`, `production/`) is
FINE at this stage — env migration is optional (see Big picture). Only the
wiring above is mandatory, in the base config dir and any per-env overrides
of those two files.

**Verify:** `php public/index.php manager/tools/help` prints the tools help —
proves package modules, loader, and hooks resolve.

## Phase 6 — Full verification

```bash
composer dump-autoload
php public/index.php manager/tools/help
php public/index.php manager/health_checks
vendor/bin/phpstan analyse
```

- Web smoke: login page renders; log in; one authenticated API endpoint
  responds with the standard envelope.
- Flush caches (redis/apc/file) when deploying the migration — cache
  serialization changed across Manager versions, and entries written by the
  old serializer may not unserialize under 2.0.
- Final grep audit (Phase 3 verification list) — zero hits.
- Nothing under `vendor/` was edited: your VCS status shows changes only in
  `application/`, `public/`, `composer.json`, analyzer configs.

Commit the migration here. Phases 7–9 are cleanup passes that follow as
**separate commits each** (the reference history did exactly this: r178
migration, r179 style, r180 line endings) — mixing them into the migration
commit buries real changes under mechanical noise and ruins reviewability.

## Phase 7 — PHPStan pass (fix the egregious, park the noise)

```bash
vendor/bin/phpstan analyse --memory-limit=512M
```

Fix the **egregious errors** — they are usually real migration leftovers:
unknown classes/functions (a missed rename from Phase 3), property access on
arrays (a missed Phase 4 conversion), calls to methods that no longer exist
on the 2.0 base classes. Add `@property` docblocks where PHPStan can't see
CI's magic loader properties.

If the remaining findings are numerous and non-severe (implicit-mixed
warnings, legacy type looseness), do NOT chase them now — park them:

```bash
vendor/bin/phpstan analyse --generate-baseline
```

and burn the baseline down later. The migration commit's job is behavioral
equivalence, not code quality.

**Verify:** `phpstan analyse` exits 0 (clean or baselined).

## Phase 8 — Coding style pass (own commit)

Copy `.php-cs-fixer.php` from the package root (PSR-12, tabs, LF) if the
project doesn't have one, then:

```bash
vendor/bin/php-cs-fixer fix
```

Review the diff is style-only (whitespace, imports, array syntax — no logic)
and commit it alone.

## Phase 9 — Line endings (own commit)

**FIRST add `.gitattributes`, THEN normalize** — without it, CRLF creeps
straight back in from Windows checkouts and editor defaults, and you'll be
re-running this phase forever. Project root:

```gitattributes
# Line Ending Configurations
*.sh  text eol=lf
*.php text eol=lf
*.js   text eol=lf
*.css  text eol=lf
*.json text eol=lf
*.yml  text eol=lf
*.yaml text eol=lf
```

Then normalize the tree. On git, adding the file enables the native way:

```bash
git add --renormalize .
```

Otherwise (or additionally, to fix the working tree in place):

```bash
grep -rlI $'\r' application/ public/ --include="*.php" | xargs -r dos2unix
```

(or `perl -pi -e 's/\r\n/\n/g'` where dos2unix isn't available; extend the
grep to the other extensions in `.gitattributes`). SVN-hosted projects: the
`.gitattributes` file is dormant there — the operative mechanism is
`svn:eol-style LF` via propset, set by the operator.

Commit alone — this diff touches every CRLF line and must not share a commit
with anything reviewable.

**Verify:** the grep above returns nothing; `php-cs-fixer fix --dry-run` is
still clean.

---

## Legacy drift audit — for projects that lagged behind 1.x updates

The reference project tracked Manager closely; a project that didn't will
carry older patterns that the mechanical phases above won't touch. Audit
each of these during the migration — they're cheap to grep and expensive to
discover in production.

### DB charset: utf8 is secretly utf8mb3

Older configs used `utf8`, which MySQL treats as the 3-byte `utf8mb3` —
emoji and some CJK input silently corrupt or reject. Connections must use
the per-engine values from the env sample's charset/collation matrix
(MySQL 8: `utf8mb4`/`utf8mb4_0900_ai_ci`; MariaDB: `utf8mb4`/
`utf8mb4_uca1400_ai_ci`; PostgreSQL: `UTF8`/empty).

```bash
grep -rn "utf8'" application/config/ | grep -v utf8mb4   # connection side
```

Note: this fixes the CONNECTION. Existing tables created as utf8mb3 keep
their column charset — converting them (`ALTER TABLE ... CONVERT TO
CHARACTER SET utf8mb4`) is a data migration with its own risks; plan it
separately, don't bundle it here.

### Legacy global error handlers: delete them

Older projects registered their own global handlers (typically a
`my_error_handler` / `my_exception_handler` / `my_fatal_handler` /
`assert_options` block in a config or bootstrap file). Delete the whole
block, no replacement — it shadows the framework's own exception
rendering, and removing it is what lets `MGR_Exceptions` take over:
uncaught errors then return the proper JSON/REST response (or the CLI
error format) instead of raw HTML dumps.

```bash
grep -rn "set_error_handler\|set_exception_handler\|assert_options" application/ --include="*.php"
# expect zero hits — each one found is a legacy handler to delete
```

### REST controllers: missing permission gates and stale properties

Older API controllers often declare NO `group_methods` restrictions — any
valid API key reaches every action. Every controller under `controllers/api/`
must declare its gate (see the `ixaya-rest-controller` skill):

```bash
grep -rL "group_methods\|auth_override" application/modules/*/controllers/api/*.php
# every file listed is ungated — decide level/group or an explicit auth_override
```

While in there, delete stale legacy properties the cleanup commits removed
from the reference project: unused cache-enabled flags and hand-rolled
api-key properties on controllers — auth state comes from the base class
(`$this->user_id`, `$this->logged_in_level`), never from a controller's own
property.

### Models: direct `$this->db` access

`$this->db` in a model bypasses the base model's connection management and
its multi-engine handling — 2.0 models go through the base-model API
(`get`/`get_all`/`update`/`query()`...) or, where the query builder is
genuinely needed, `$this->my_db`:

```bash
grep -rn '\$this->db->' application/modules/*/models/ application/models/ 2>/dev/null
# expect zero hits when done
```

### Controllers reimplementing file upload/download/image display

Older controllers sometimes hand-roll the whole file paradigm —
`move_uploaded_file()`, `readfile()` + manual headers, ad-hoc image
resizing. The framework already provides it, inherited by every controller
(web AND REST): `$this->upload_file()`, `$this->upload_image()` (with
resizing), `$this->put_file()`, `$this->get_file_base64()`,
`$this->display_image()` — plus `attachment_lib` when files belong to a DB
record. Replace, don't keep parallel implementations:

```bash
grep -rn "move_uploaded_file\|readfile(\|Content-Disposition\|imagecreate" \
  application/modules/*/controllers/ application/controllers/ 2>/dev/null
```

**Verify:** all four greps return nothing (or only justified, reviewed hits).

### Ion Auth: renamed forgotten-password key and dropped `login()` 4th arg

The 2.0 Ion Auth backport (Phase 2 replaces the in-tree `Ion_auth` /
`Ion_auth_model` with the package version) changed two consumer-facing
contracts that fail SILENTLY — no error, just wrong behavior. (Current auth
conventions and invariants: the `ixaya-auth` skill; this section covers only
the legacy-to-2.0 traps.)

**1. `forgotten_password()` return key renamed** — `forgotten_password_code`
→ `forgottenPasswordCode`. A controller reading the old key off the return
array gets `null`, so the reset email ships an empty link.

```bash
grep -rn "forgotten_password_code" application/ --include="*.php" \
  | grep -vE "get_user_by_forgotten_password_code|clear_forgotten_password_code"
# each remaining hit reads the return-array KEY: rename it to forgottenPasswordCode
```

The METHOD names `get_user_by_forgotten_password_code()` /
`clear_forgotten_password_code()` are unchanged (the grep filter above drops
them) — only the array key moved. Note `clear_forgotten_password_code($x)` and
`remember_user($x)` also changed to take the IDENTITY, not a code/id — audit
those call sites pass an identity.

**2. `login()` lost its 4th `$returnUser` arg.** Legacy
`login($identity, $password, $remember, $returnUser = true)` returned the user
object before establishing a session; the 2.0 signature is
`login(string $identity, string $password, bool $remember = false)`. A legacy
`login($u, $p, false, true)` still runs, but the 4th arg is silently
DISCARDED — whether you get a session or the bare user object then depends on
whether a session library happens to be loaded. For the REST/API path (you
want the user object and no session), declare intent explicitly:

```bash
grep -rnE "->login\([^)]*,[^)]*,[^)]*," application/ --include="*.php"
# each hit: drop the extra args and opt out of sessions for the sessionless path
```
```php
// before — 4th arg ignored; behavior varies with ambient session state
$result = $this->ion_auth->login($username, $password, false, true);
// after — no session/cookie even if a session library is loaded; returns the user object
$this->ion_auth->disable_session();
$result = $this->ion_auth->login($username, $password);
```

`disable_session(bool $disable = true)` lives on the model and is reachable
through the library via `__call`; it forces `use_sessions()` to false for the
request. Pass `disable: false` to re-enable. Drop the `remember` arg on the
sessionless path — the remember-me block only runs when sessions are on.

**2b. `forgotten_password_check()` lost its by-ref `&$profile` param.** Legacy
`forgotten_password_check($code, &$profile)` returned a bool and filled
`$profile` by reference; 2.0 returns the user object directly (`object|false`).
A legacy call still runs — PHP silently ignores the extra argument — and the
truthiness check still passes, but `$profile` stays null, so downstream code
reading it half-works and masks the break.

```bash
grep -rnE "forgotten_password_check\([^)]+,[^)]+\)" application/ --include="*.php"
# each hit: drop the 2nd arg and capture the return value instead
```
```php
// before — $profile filled by reference
if ($this->ion_auth->forgotten_password_check($code, $profile)) { ... }
// after — the user object IS the return value
if ($profile = $this->ion_auth->forgotten_password_check($code)) { ... }
```

**2c. `messages()` / `errors()` output format changed.** Legacy returned
delimiter-wrapped strings (configurable, incl. the
`delimiters_source = 'form_validation'` reflection option); 2.0 renders view
templates (`templates` config keys → packaged
`views/auth/messages/{list,list_errors,single}.php`). Pages echoing them get
`<ul><li>…` markup instead of the old delimiters, `messages_array()` /
`errors_array()` items arrive unwrapped, and the delimiter config keys plus
`set_message_delimiters()` / `set_error_delimiters()` are GONE (calls throw via
`__call`).

```bash
grep -rnE "ion_auth->(messages|errors)(_array)?\(" application/ --include="*.php"
# each echo site: restyle via the packaged view templates — override the views
# or point the ion_auth `templates` config keys at your own
```

**3. `get_users_groups()` / `add_to_group()` — id-less fallback is LIBRARY-only.**
The CI4 originals defaulted the id and fell back to the session user. In 2.0 the
MODEL methods require the id (`get_users_groups(int $id)`,
`add_to_group(array|int $groupIds, int $userId)`), but the LIBRARY provides
session-fallback wrappers — so `$this->ion_auth->get_users_groups()` /
`add_to_group($gid)` still work id-less against the current session user (the
path old session+HTML code uses). A **model-direct** id-less call
(`$this->ion_auth_model->get_users_groups()`) throws `ArgumentCountError`.

```bash
# find model-direct id-less calls — route these through the library, or pass the id
grep -rnE "ion_auth_model->(get_users_groups|add_to_group)\(\s*\)|ion_auth_model->add_to_group\([^,)]+\)\s*;" application/ --include="*.php"
```

Fallback shapes when there is no session user: `add_to_group()` returns `0`;
`get_users_groups()` returns an empty (but chainable) result — `->result()` /
`->row()` keep working.

**4. `client_id` session lifecycle is now framework-managed (tenant projects).**
Legacy projects set the `client_id` session key in their own login controllers
and read it back via `get_client_id()`. In 2.0 the whole cycle lives in the
package: `set_session()` stores the tenant id at login when the user row
carries a `client_id` column, `get_client_id()` returns it (repaired — the 1.x
accessor guarded on a nonexistent flag and always returned `null`), and it is
cleared on logout AND on the periodic active-user recheck when the user was
deactivated (1.x left it lingering on a half-torn-down session).

Opt in by selecting the column instead of writing the key manually:

```bash
# .env — add the tenant column to the login SELECT (validated as a plain identifier)
AUTH_IDENTITY_EXTRA_COLUMNS=client_id,first_name,last_name
```

Manual `set_userdata('client_id', ...)` in a login controller keeps working
when the column is NOT selected. When it IS selected, `set_session()` mirrors
the user row — including UNSETTING the key when the row's `client_id` is
empty — so drop the manual write to avoid the two fighting:

```bash
grep -rn "set_userdata('client_id'\|set_userdata(\"client_id\"" application/ --include="*.php"
# each hit: prefer AUTH_IDENTITY_EXTRA_COLUMNS + the framework mirror; delete the manual write
```

**5. Password reset: use the new atomic `reset_password_with_code()`.** The
model's raw `reset_password($identity, $new)` does NOT verify the
forgotten-password code — legacy controllers had to wire
`forgotten_password_check()` themselves, and one missed guard means an
identity-only account takeover. 2.0 adds a library wrapper that validates the
code + expiration and takes the identity from the code's own user row:

```php
// before — two calls; forgetting the first one is an account takeover
if ($user = $this->ion_auth->forgotten_password_check($code)) {
    $this->ion_auth->reset_password($user->email, $new_password);
}
// after — atomic; an identity-only reset is impossible by construction
$ok = $this->ion_auth->reset_password_with_code($code, $new_password);
```

```bash
grep -rnE "ion_auth(_model)?->reset_password\(" application/ --include="*.php"
# each hit: switch to reset_password_with_code(); the raw method stays for BC
# but every caller must be provably gated by forgotten_password_check()
```

The code is single-use (consumed by the reset itself) and a user's next
successful login clears any leftover reset codes.

**Verify:** all greps return only reviewed hits; a password-reset email
carries a non-empty code, an API login returns the user object with no
`Set-Cookie`, and — for tenant projects — `get_client_id()` returns the id
after a session login and `null` after logout/deactivation.

---

## Big picture — optional follow-ups (each its own effort)

**Env-based single configs** (what the reference migration did in the same
commit — you don't have to). The target state: delete
`application/config/{development,production}/` entirely; each base config
file reads env vars via `mgr_env()` (copy the sample's config as the base and
port your values); secrets live in `.env.priv`, the rest in `.env`, both
bootstrapped from the package's `.env.sample.dev/.prod/.priv` (two-section
layout: Package variables first, Project section below). File resolution:
`Env_lib` tries `.env.{CI_ENV}` / `.env.{CI_ENV}.priv` first (e.g.
`.env.dev`), then falls back to plain `.env` / `.env.priv`; missing files
are silently skipped, and process-level env vars (docker `env_file:`)
always win over file values.

**Server & CLI plumbing (part of the env migration).** Once the app reads
its environment from `.env`, remove the `CI_ENV` injection from EVERY
server layer on EVERY server — `.htaccess` `SetEnv` blocks (the 2.0 sample
already dropped it), Apache vhost `SetEnv`, nginx `fastcgi_param`, cron
line exports. A stale injection silently redirects which `.env.{CI_ENV}`
file gets loaded. Audit `bin/cli_run.sh` too — the repo copy AND the
deployed copies on each server, which drift:

- shebang must be `#!/bin/bash` (the arg-array syntax below is bash-only,
  not POSIX sh);
- no environment exports left in the script (`CI_ENV=...` lines go);
- it must end by `exec`-ing PHP (proper signal handling and exit codes):

```bash
exec /usr/bin/nice -n 10 $php_bin -f $public_path/index.php ${all_args[@]}
```

Nuances that make the env migration its own project: per-env value
differences must be flattened into
env-var defaults, session/cache/redis values interact with deployment shape
(see the docker env docs if containerizing), and every config file you
convert needs its own smoke test. Do it file-by-file, not big-bang —
`database.php` and `config.php` first, the `lib_*.php` tail last.

**Separation of project configs.** Project integrations get their own
app-side config files following the library conventions: `lib_{name}.php` in
`application/config/` (e.g. `lib_timetracking.php`, `lib_banking.php`,
`lib_pass.php`) consumed by `{Name}_lib` libraries — never added to the
vendor tree, and their env vars belong in the Project section of the env
samples. If the legacy project mixed integration settings into framework
config files, extract them during (or after) the env migration.

**Per-module migrations.** New schema changes use `MGR_Migration_builder`
(see the `ixaya-migrations` skill) with per-module `migrations/{conn}/` dirs;
adopt the existing DB state with `manager/tools/version_set` instead of
re-running history. Legacy migration files stay frozen where they are.

**Tests skeleton.** Copy `sample/application/tests/` (phpunit bootstrap +
example) if the project has none.

**Agent docs.** Symlink the package skills (`system/skills/` — command in the
README) and adopt a root `AGENTS.md` for project-wide rules.

**Not part of migrating:** the reference commit also shipped unrelated
feature work (new endpoints, new models). Resist bundling features into the
migration commit — it makes the diff unreviewable and the rollback story
worse.
