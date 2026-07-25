---
name: ixaya-auth
description: Use when working on authentication — login/logout, sessions vs sessionless API auth, account lockout, password reset, remember-me, user groups, multi-tenant client_id, or bootstrapping the first admin credential/API key on a fresh install — in this codebase. Teaches the Ion Auth stack of the ixaya/manager framework (BE_ fork → package subclasses → config), its extension seams, and the security invariants that must never be regressed.
---

# Ixaya Auth (Ion Auth stack)

> **Prerequisite:** this skill assumes `ixaya-code-style` is loaded — invoke it
> before writing any code. It owns naming, typing, PHPDoc, and the comments
> policy; this skill only covers the auth stack.

Authentication is a CI3 backport of Ion Auth 4, layered so upstream merges
stay clean and all Ixaya behavior lives in thin subclasses:

```
system/third_party/BE/Ion_auth.php, Ion_auth_model.php   (BE_ fork — upstream-tracked)
        ↑ extends
system/libraries/MGR_Ion_auth.php                         (library subclass — the code)
system/models/MGR_Ion_auth_model.php                      (model subclass — the code)
        ↑ extends
system/package/libraries/Ion_auth.php                     (unprefixed alias shim)
system/package/models/Ion_auth_model.php                  (unprefixed alias shim)
        + system/package/config/ion_auth.php               (config, AUTH_* env keys)
```

## Pick your mode first

- **Project mode** — you are in a consuming project and the stack above
  lives under `vendor/ixaya/manager/` — composer-managed, **never edit any
  of it** (the next `composer update` silently reverts you). To change auth
  behavior:
  1. Config: copy `vendor/.../system/package/config/ion_auth.php` to
     `application/config/ion_auth.php` and edit the copy (or just set the
     `AUTH_*` env keys — most knobs are env-overridable).
  2. Code: check whether the project already has its own
     `application/libraries/Ion_auth.php` /
     `application/models/Ion_auth_model.php`. If yes, edit those. If not,
     create one extending the vendor `MGR_` class — the loader prefers
     `application/` over the package shim, so it transparently takes over:

     ```php
     require MGRPATH . 'libraries/MGR_Ion_auth.php';

     class Ion_auth extends MGR_Ion_auth
     {
     	// project overrides here
     }
     ```
- **Framework mode** — you are in the `ixaya/manager` repo itself. Changes
  go in the `MGR_` subclasses (`system/libraries/MGR_Ion_auth.php`,
  `system/models/MGR_Ion_auth_model.php`) or config — the package files are
  empty alias shims (standard alias chain), and never the `BE_` files.
  The `BE_` fork carries a small set of deliberate direct edits as a
  documented exception; that inventory and the merge procedure live in
  `docs/development/auth-upstream.md` (this repo only, not shipped). If
  a change seems to need a `BE_` edit, stop and get an explicit operator
  decision first.

Everything below applies to both modes.

Source of truth (read only if something here is insufficient):
- `vendor/ixaya/manager/system/libraries/MGR_Ion_auth.php` — wrappers, `get_client_id()`, `reset_password_with_code()`
- `vendor/ixaya/manager/system/models/MGR_Ion_auth_model.php` — `disable_session()`, seams, identifier guards
- `vendor/ixaya/manager/system/package/config/ion_auth.php` — every key below, with comments
- Endpoint example: the ixaya-rest-controller skill's `references/public-endpoint.md` (sessionless login + API key issuance)

## Bootstrap: the first admin credential

A fresh database is seeded (Ion_Auth migration) with one admin user whose
factory password is treated as unusable. The ONLY sanctioned way to obtain
the first working credential is the one-shot CLI claim:

```bash
bin/cli_run.sh manager/tools/claim_admin
# Docker: ./docker_manage.sh -e <instance> exec php bash /var/www/html/bin/cli_run.sh manager/tools/claim_admin
```

It prints the identity and a generated password ONCE. It only works while
the admin row still carries the exact factory hash — after the claim (or any
password change) the gate closes permanently and the normal password-reset
flow applies. From there: log in through the normal auth endpoint to get an
`api_key`, and store credentials only in gitignored files (e.g.
`docker/env/<instance>.agent.env`). Never bootstrap by inserting into
`user`/`user_key` with raw SQL, and never write the factory password's
plaintext into docs, templates, or code.

## Login: session vs sessionless

`use_sessions()` re-checks session availability at CALL time (not frozen in
the constructor), and can be forced off per request. In sessionless mode
`login()` returns the sanitized user object (password stripped) instead of
`true`, and establishes no session and no cookie:

```php
$this->ion_auth->disable_session(); // model method, reached via the library's __call
$user = $this->ion_auth->login($username, $password);
if ($user !== false) {
	// $user is the object — feed it to the API-key flow below
}
```

- `disable_session(false)` re-enables sessions for the same request.
- Session mode (no `disable_session()`, session library loaded): `login()`
  returns `true`, stores the session, and regenerates the session id.
- There is no 4th `login()` argument; `disable_session()` replaced the
  legacy `$returnUser` flag (see MIGRATION.md, Ion Auth item 2).

API endpoints then issue an `X-API-KEY` via `Rest_key_model` — flow and key
lifecycle live in the ixaya-rest-controller skill.

**Uniform failure messaging:** login, registration, and recovery endpoints
must return the same response for every failure cause (wrong password,
locked out, already-registered, unknown email) — differentiated messages are
a username-enumeration surface. The endpoint example above shows it: bad password
and locked-out both get "Username/password incorrect"; registration failure
is a neutral "Unable to register."; recovery always claims success.

## Lockout

Config (`config/ion_auth.php`, all env-overridable):

| Key | Env | Default | Meaning |
|---|---|---|---|
| `trackLoginAttempts` | `AUTH_TRACK_LOGIN_ATTEMPTS` | true | Record failed attempts, enable lockout |
| `trackLoginIpAddress` | `AUTH_TRACK_LOGIN_IP` | true | Scope lockout to the identity+IP PAIR |
| `maximumLoginAttempts` | `AUTH_MAX_LOGIN_ATTEMPTS` | 3 | Attempts within the window before lockout |
| `lockoutTime` | `AUTH_LOCKOUT_TIME` | 600 | Window seconds; lockout auto-expires |

`trackLoginIpAddress` is NOT IP-only tracking: attempts are always keyed on
the submitted identity, and the flag additionally requires the same IP — it
strictly narrows lockout (a shared-office IP cannot lock a whole team out;
an attacker who knows a username can only lock it per-IP). Lockout is
time-windowed — it expires on its own after `lockoutTime`, no manual
`clear_login_attempts()` needed (that method also purges expired attempt
rows table-wide by design).

## Password reset

`reset_password_with_code()` is THE way — atomic, and the identity comes
from the code's own user row, never from the caller:

```php
$ok = $this->ion_auth->reset_password_with_code($code, $new_password);
```

- Single-use by construction: setting the password nulls
  `forgotten_password_code`/`_time`, and validation requires a non-null code.
- Expired codes are cleared by the check itself.
- The lingering `forgotten_password_selector` is a deliberate trace that a
  code was issued; it is cleared on the user's next successful login.
- The raw `reset_password($identity, $new)` does NOT validate the code — if
  raw methods are used, `forgotten_password_check($code)` must succeed first
  and the identity must come from the user object it returns, never from
  request input. **Never expose a reset path reachable with an identity
  alone.**

## Tenancy: client_id lifecycle

For multi-tenant projects the framework manages the `client_id` session key
end-to-end. Opt in by adding the column to the identity extra columns:

```
AUTH_IDENTITY_EXTRA_COLUMNS=first_name,last_name,image_url,client_id
```

- **Written on login:** the model's `set_session()` override mirrors the
  user row. `property_exists` semantics matter: a SELECTED but empty/null
  `client_id` column PURGES any stale session value (back-to-back logins
  keep the session data, so a stale tenant id would otherwise survive); a
  column that was never selected leaves the key alone, so legacy projects
  that write `client_id` themselves stay compatible.
- **Read:** `$this->ion_auth->get_client_id(): ?int` — null in sessionless
  mode or when unset.
- **Cleared:** on logout (session destroyed) and on the deactivation recheck
  (`recheck_session_unset_keys()`, see seams below).

## Extension seams

Override these in your subclass layer (project mode: the `application/`
copy; framework mode: the `MGR_` classes) instead of copying upstream
method bodies:

- `login_select_columns(): string` — columns fetched by `login()`; the
  subclass appends `identityExtraColumns` from config.
- `users_groups_select_columns(): string` — columns for `get_users_groups()`;
  the subclass appends `groups.level` (the REST controller's
  `logged_in_level` depends on it).
- `recheck_session_unset_keys(): array` — session keys removed when the
  periodic recheck finds the user deactivated. The vendor default MIRRORS
  every key `set_session()` stores — if you override one, keep the other in
  sync (both methods carry reciprocal comments). The subclass appends
  `client_id`.
- `set_session(\stdClass $user): bool` — call `parent::set_session()` first
  (its `false` return is the sessionless-mode guard), then add your own
  session keys; the `client_id` mirror above is the pattern to copy.

Any config-sourced column or table name concatenated into SQL must pass
`mgr_is_sql_identifier()` (helper, see ixaya-helpers-libraries) — the
subclass select builders already assert this and throw
`InvalidArgumentException` on anything that is not a plain, optionally
dotted, identifier. CI's `escape_identifiers()` is NOT a guard (it passes
parens and quotes through).

Library conveniences with session fallback: `user()`, `in_group()`,
`is_admin()`, `get_users_groups()`, `add_to_group()` — all take an optional
id and fall back to the session user (empty chainable result / 0 when there
is no user). Note `is_admin($id)` answers "is user X in the admin group",
NOT "is the caller an admin" — combine with `logged_in()` for caller authz.

## DO NOT REGRESS — security invariants

These are correct on purpose; a cleanup or "simplification" must never
remove them:

1. **Uniform hash cost.** The model subclass sets
   `useRoleBasedHashing = false` and forces an empty identity in
   `get_hash_parameters()` — every user hashes at the same cost, so timing
   cannot reveal admin status. The `bcryptAdminCost`/`argon2AdminParams`
   config keys exist only so enabling the flag doesn't fatal.
2. **Selector/validator token design** (activation, forgotten-password,
   remember-me): the validator is stored HASHED in the DB; the plaintext
   `selector.validator` code exists only in the cookie/URL. Never store or
   log the plaintext, never compare codes with anything but the hashed
   validator check.
3. **Session regeneration**: login regenerates the session id; logout
   destroys and regenerates. Fixation protection — do not remove either.
4. **Exactly one KDF op per failed login.** Absent user → one dummy hash;
   existing user + wrong password → one `verify_password()`; locked-out →
   ZERO hashing (lockout is already disclosed by its distinct error, and
   hashing there would be a CPU-amplification vector). Failure timing must
   not reveal whether a username exists — do not add, move, or "restore" a
   hash call in `login()`'s failure paths.
