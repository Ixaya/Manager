# Ion Auth: upstream-merge rulebook

The Ion Auth stack in `system/third_party/BE/` is a CI3 backport of the CI4
Ion Auth 4 fork, deliberately kept close to upstream so future upstream
merges apply cleanly (see AGENTS.md, "third_party is upstream-tracked").
Two subclasses carry the Ixaya-specific behavior:
`system/libraries/MGR_Ion_auth.php` and
`system/models/MGR_Ion_auth_model.php` (the package files
`system/package/libraries/Ion_auth.php` / `system/package/models/
Ion_auth_model.php` are empty alias shims, per the standard alias chain),
configured by `system/package/config/ion_auth.php`.

This file is the distilled record of the 2026-07 review/fix campaign
(workspace sections 03/04/05, since groomed). It answers two questions when
a newer upstream Ion Auth lands:

1. **Which edits live directly in the `BE_` files** and must be re-applied /
   re-verified after the merge (they WILL be clobbered or drift).
2. **Which differences from upstream and from the legacy CI3 production code
   are on purpose** — so nobody "fixes" them back.

## 1. Deliberate `BE_` file edits — re-apply / re-verify after every upstream merge

These were applied directly to `system/third_party/BE/Ion_auth.php` /
`Ion_auth_model.php` as a documented exception (no clean subclass seam).
After merging a newer upstream, diff against this list and re-run the probe
suite (§4).

### Bug-fix batch (mechanical)

- **Typed properties initialized:** `public ?string $activationCode = null;`,
  `protected ?object $ionHooks = null;` (model).
- **`random_token()`:** body is one line, `bin2hex(random_bytes(intdiv($resultLength, 2)))`
  — the PHP-7 `function_exists('random_bytes')` fallback/throw was deleted and
  the float division replaced with `intdiv` (odd sizes deprecated otherwise).
- **`userExtendOnLogin`** casing fixed at its read site (was `userExtendonLogin`,
  which silently disabled remember-me extension).
- **Strict comparisons:** `$token === false` (two sites); assorted docblock/
  return-type fixes (`reset_password(): bool`, `errors(): string`,
  `get_last_attempt_ip(): string`, `group(): self`, `clear_messages(): bool`).
- **`register()`:** stray `->where([...], 1)` second arg deleted;
  `clear_login_attempts()` param renamed `$oldAttemptsExpirePeriod` (typo);
  `recheck_session()` local renamed `$lastCheck`.

### Behavioral fixes

- **`register()` failure signaling (cross-engine):** the INSERT itself is the
  failure signal — `if (! $this->my_db->insert(...)) return false;` plus an
  `if (!$id)` belt after `insert_id()`, and the tail returns `return $id;`
  (no `?? false`). Rationale: Postgres `LASTVAL()` returns a burned truthy id
  after a failed insert, so an id-only guard is MySQL-only.
- **`register()` data array does NOT hardcode `'username' => $identity`**
  (deliberate deviation from CI4 — see §2). Only
  `$this->identityColumn => $identity` is framework-owned; `username` belongs
  to the caller via `$additionalData` (merge order would otherwise clobber it).
- **`login()` — the "lockout trio":**
  - Lockout check runs BEFORE the credentials query (the `extra_where`
    trigger moved with the query). A locked-out attempt costs no users-table
    read and **no KDF op** — do not add a hash on the lockout path (lockout is
    already disclosed by the distinct `login_timeout` error; hashing there is
    a CPU-amplification gift).
  - The dummy `hash_password()` lives in the `else` of `isset($user)`: every
    non-lockout failure costs exactly ONE KDF op (absent user = 1 dummy hash,
    existing user + wrong password = 1 `verify_password`), closing the
    username-enumeration timing oracle. Inline comments in the file explain
    both; keep them.
- **`update_group()`:** admin-rename guard requires `!empty($groupName)`
  (data-only updates to the admin group must work); null guard
  `if (! $group) return false;` after the group fetch.
- **Builder-state hygiene in `users()`/`groups()`:** `groups()` calls
  `$this->my_db->reset_query()` (mirroring `users()`); both methods null
  `ionLimit`/`ionOffset` unconditionally after the limit block (a lone offset
  is discarded, never leaks into the next call) and null
  `ionOrder`/`ionOrderBy` after use.
- **Response accessors fail soft:** `row()`/`row_array()` → `null`,
  `result()`/`result_array()` → `[]`, `num_rows()` → `0` when
  `$this->response` is empty.
- **`add_to_group()`:** `(int)` casts on group/user ids (were `(float)` —
  precision loss above 2^53).
- **`remember_user()`:** guard is `affected_rows() > 0` (was `> -1`, always
  true — phantom cookie for nonexistent identities).
- **`clear_login_attempts()`:** the ungrouped `or_where('time <', …)` is
  DELIBERATE (one delete clears this identity's attempts AND purges expired
  rows table-wide) — there's an inline comment; don't "fix" the precedence.
- **`logout()` (library):** reads the identity VALUE from
  `session->userdata('identity')` BEFORE unsetting session keys, then
  `if ($identity)` gates `clear_forgotten_password_code()`/`clear_remember_code()`
  (upstream passed the identity column NAME — a no-op).
- **`get_hash_algo()` / `get_hash_parameters()`:** `'argon2'` maps to
  `PASSWORD_ARGON2ID` (legacy semantics; upstream CI4 maps it to ARGON2I —
  silent downgrade for migrating projects), with a new explicit `'argon2i'`
  case in both methods for anyone who really wants argon2i.
- **Lazy sessions:** the constructor-frozen `$useSessions` property is GONE
  from both `BE_` classes. The model owns `public use_sessions(): bool`
  (call-time `isset($CI->session) && instanceof CI_Session` re-check); the
  library's `use_sessions()` DELEGATES to the model. Deliberately NOT
  legacy's call-time `load->library('session')` — force-loading would flip
  the intentional sessionless/API mode permanently on. All former
  `$this->useSessions` reads go through `use_sessions()`.
- **Seam methods (extension points the subclasses rely on):**
  `login_select_columns()`, `users_groups_select_columns()`, and
  `recheck_session_unset_keys()` (returns every key `set_session()` stores —
  the two carry reciprocal keep-in-sync comments). If upstream restructures
  `login()`/`recheck_session()`/`set_session()`, these seams must survive.
- **Deleted dead code:** the `__call` aliases `create_user`/`update_user`
  (library) and the constructor identity-cookie auto-login block (+ the
  `identityCookieName` config key) — the cookie was never written anywhere;
  remember-me auto-login in `logged_in()` covers it.
- **Lang keys are FLAT:** all `IonAuth.`-prefixed keys stripped (26 sites),
  four keys re-cased to the lang-file spelling
  (`account_creation_missing_default_group` etc.), and two pre-ion_auth
  orphan keys remapped (`account_creation_duplicate_identity` →
  `account_creation_duplicate_username`; ditto `..._invalid_...`).
  **Verify after merge:** `grep -rnE "IonAuth\." system/third_party/BE/ system/package/`
  must return zero, and every `set_error`/`set_message` key must exist in
  all three `system/package/language/*/ion_auth_lang.php` files.
- **PC-7 uniformity:** `send_activation_email()`'s return array includes
  `'subject' => lang('email_activation_subject')`, matching
  `forgotten_password()`/`register()`.
- **`cacheUserInGroup`** is `protected` (upstream widened it to `public` for
  a CI3-era library-side `in_group()`; that reason is gone).

## 2. Purposeful deviations from upstream CI4 — do NOT restore parity

- **No CI email subsystem.** Sending email is the controller's job; the
  library methods return data arrays (`forgotten_password()`,
  `register()`, `send_activation_email()` include `'email'`/`'subject'`).
  Config keys `useCiEmail`/`adminEmail`/`siteTitle`/`emailTemplates`/etc.
  intentionally absent.
- **`register()` doesn't hardcode `username`** (see §1). Deviation exists
  because operator projects use email identities with non-email usernames in
  `additionalData` — CI4's line would silently clobber them.
- **`'argon2'` → ARGON2ID** mapping (see §1) deviates from CI4 on purpose.
- **`is_admin()` has no `logged_in()` gate** — coherent with the
  sessionless/API design; `is_admin($id)` means "is X in the admin group";
  callers wanting caller-authorization combine it with `logged_in()`.
- **`insert_id()` stays bare** (no Postgres sequence-name arg — that's a
  CI4-driver feature; CI3's postgre driver uses `LASTVAL()`, runtime-proven).
- **`ionSelect` is a `users()`-only feature**; `groups()` deliberately
  doesn't consume it.
- **Dropped upstream API kept dropped:** `getErrors()`,
  `checkCompatibility()`, `errors($template)` param — zero callers.

## 3. Purposeful deviations from the legacy CI3 production code

For migrating legacy consumers the silent traps are documented in
MIGRATION.md ("Ion Auth" items 1–5). Framework-side decisions:

- **Selector/validator token architecture** (activation, forgotten-password,
  remember-me): hashed validator in DB, plaintext only in the user-side
  code. Strictly better than legacy plaintext codes; in-flight legacy codes
  dead-end at cutover (accepted).
- **Legacy bugs stay fixed** — do not restore: dangling `or_where` in
  `get_attempts_num`/`get_last_attempt_time`, `delete_user` mid-transaction
  return without rollback, duplicate `activate` branches.
- **Time-windowed lockout** (auto-expires after `lockoutTime`) replaces
  legacy's all-time counter.
- **`login()` has 3 params** — the legacy 4th `$returnUser` arg is replaced
  by `disable_session()` on the model (reachable via the library `__call`):
  sessionless login returns the sanitized user object.
- **`get_client_id()` lives in the subclass**, guarded by `use_sessions()`
  (the legacy `allow_session` guard was dead). Lifecycle is framework-managed:
  written by the subclass `set_session()` override when the user row carries
  a `client_id` column (opt-in via `AUTH_IDENTITY_EXTRA_COLUMNS`), cleared on
  deactivation (`recheck_session_unset_keys()` + subclass append) and logout.
- **`forgotten_password_check()` returns `object|false`** (no by-ref
  `&$profile`); `messages()`/`errors()` render view templates (no
  delimiters); `update_group()` takes a typed `array` (v1-era string shim
  gone, fails loud); `in_group()` treats numeric as id; `delete_user()` is
  idempotent (true for nonexistent ids). All KEEP decisions.
- **Legacy identity cookie:** never written or deleted anymore; lingering
  legacy cookies on migrated browsers are accepted. If that's ever revisited,
  the literal cookie name `'identity'` must be hardcoded (config key removed).

## 4. Do-not-regress security properties

Canonical statement (with rationale): `system/skills/ixaya-auth/SKILL.md`,
"DO NOT REGRESS" section. Merge-time checklist form — a post-merge diff must
show all of these intact:

- Uniform hash cost: `useRoleBasedHashing = false` + forced-empty identity
  in the subclass `get_hash_parameters()`.
- Selector/validator token design (hashed validator in DB, plaintext only
  user-side).
- Session regeneration: login `sess_regenerate(false)`; logout
  `sess_destroy(); session_start(); sess_regenerate(true);`.
- One-KDF-op failure symmetry and the zero-KDF lockout path in `login()`
  (§1 lockout trio).
- `mgr_is_sql_identifier()` asserts in both subclass select builders.
- `trackLoginIpAddress = true` default (identity+IP pair scope).
- `reset_password_with_code()` takes the identity from the code's user row,
  never from the caller.

## 5. Post-merge verification recipe

1. Diff the merged `BE_` files against §1; re-apply anything lost.
2. Greps (all must be zero / explained):
   - `grep -rnE "IonAuth\." system/third_party/BE/ system/package/`
   - the two consolidated call-site greps in MIGRATION.md's Ion Auth section
     (removed APIs, 4-arg `login()`, legacy keys) over `sample/application/`
     — probe-module hits calling the current API are expected noise.
3. Run the gitignored probe suite against the `local` Docker stack
   (`-b -m`, real `X-Api-Key`, keyless hit must 403 — see
   `sample/docs/development/docker.md` "Live-code dev modes" and the
   `ixaya-live-probes` skill):
   - `GET /test/api/auth_migration/all` (b8, b2b3, lb1, lb3, pb1, c9, pb2,
     c2, c10, c5c6)
   - `GET /test/api/auth_security/all` (f1, f6)
   Green on postgres is the baseline; the 2026-07-14 pass was green on
   postgres, MySQL 8.4, and MariaDB 12.3.
4. Pre-deploy on a real target: the schema gate
   (`SHOW COLUMNS FROM user LIKE '%selector%'` — note the singular `user`
   table; three `*_selector` columns present, `salt` absent — migration
   `20260213175009_Ion_auth_v2.php`) and MIGRATION.md's consumer greps on the
   target's app tree.
