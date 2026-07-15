---
name: ixaya-rest-controller
description: Use when creating or editing a REST API endpoint (controllers under modules/*/controllers/api/), handling API authentication, or returning JSON responses in this codebase. Teaches the APP_Rest_Controller conventions of the ixaya/manager framework — auth_override ordering, group/level gating, response envelope — instead of vanilla CI3 controllers or hand-rolled JSON output.
---

# Ixaya REST Controllers (APP_Rest_Controller)

API endpoints extend `APP_Rest_Controller` (alias chain: `MGR_Rest_Controller` →
`REST_Controller`, a CI3 REST library fork). Auth, HTTP-verb routing, and output
formatting are handled by the base — never `echo json_encode()` or check API keys
by hand.

Source of truth (only read if something here is insufficient):
- `vendor/ixaya/manager/system/core/MGR_Rest_Controller.php` — auth gating, helpers
- `vendor/ixaya/manager/system/third_party/REST_Controller.php` — key validation, response(), HTTP constants
- `vendor/ixaya/manager/system/package/config/rest.php` — REST config (header name, key table…)
- `vendor/ixaya/manager/system/package/models/Rest_key_model.php` — API key issuance/lifecycle
- `vendor/ixaya/manager/system/core/MGR/Exceptions.php` — content-negotiated error rendering
- `application/core/APP_Rest_Controller.php`, `application/core/APP_API_Model.php` — app aliases
- Canonical example: `vendor/ixaya/manager/sample/application/modules/admin/controllers/api/Sysusers.php`
  (the vendor sample is the reference — API controllers inside `application/` may predate current conventions)
- Public-endpoint example (`auth_override`): `application/modules/auth/controllers/api/Login.php`

Note: `REST_Controller` extends `MY_Controller`, so API controllers inherit the
framework controller helpers too — `upload_image()`, `upload_file()`, `put_file()`,
`get_file_base64()`, `display_image()`.

## Placement and routing

File: `application/modules/{module}/controllers/api/{Name}.php` — reached at
`/{module}/api/{name}/{method}`. Method names are `{action}_{http_verb}`:

```
index_get      GET  /module/api/name
show_get       GET  /module/api/name/show?id=1
create_post    POST /module/api/name/create
update_post    POST /module/api/name/update
delete_post    POST /module/api/name/delete
```

The codebase convention is GET for reads and POST for everything else (not
PUT/DELETE verbs). Plural controller class names (`Examples`, `Cards`, `Suppliers`).

## Authentication

By default every request must carry a valid API key in the `X-API-KEY` header
(config `rest_key_name`), checked against the keys table **in the constructor**.
A valid key sets `$this->_apiuser`, and `MGR_Rest_Controller` then populates:

- `$this->user_id` — the key's user
- `$this->logged_in_level` — highest Ion Auth group level of that user
- stamps `last_api_date` / `last_api_os` on the `user` row

### Rule 1 — auth overrides go BEFORE parent::__construct()

The parent constructor runs the key check immediately; overrides set after it are dead code.

```php
class Login extends APP_Rest_Controller
{
    public function __construct()
    {
        $this->methods['*']['auth_override'] = 'none'; // public endpoint — BEFORE parent
        parent::__construct();
    }
}
```

`auth_override` values: `'none'` (skip all auth), `'allow'`, `'basic'`, `'digest'`,
`'session'`, `'whitelist'`. Scope per method instead of `'*'` with
`$this->methods['index_get']['auth_override'] = 'none';`.

### Rule 2 — permission gating via group_methods

`_remap()` checks these before any action runs, responding 401 automatically:

```php
public function __construct()
{
    $this->group_methods['*']['level'] = LEVEL_ADMIN;      // min Ion Auth group level
    // $this->group_methods['*']['group'] = GROUP_ADMIN;   // or named group
    // $this->group_methods['delete_post']['level'] = LEVEL_ADMIN; // per action_verb
    parent::__construct();
}
```

If both `level` and `group` are set, passing **either** grants access. Constants
(`LEVEL_ADMIN` = 10, `GROUP_ADMIN` = 'admin', `GROUP_ADMIN_ID`, `GROUP_MEMBER_ID`, …)
live in `application/config/constants.php`.

Manual checks inside a method: `$this->validate_level($level)`,
`$this->validate_group($group)`, `$this->validate_access($level, $group)`.

### Rule 3 — load libraries in the method that uses them

The constructor runs on every request to the controller; loading libraries there
taxes endpoints that don't need them. Load libraries AND models at point of use —
the vendor sample loads `$this->load->model('admin/user')` inside each action, not
in the constructor.

```php
public function report_get()
{
    $this->load->library('async_exec_lib'); // here, not in __construct
}
```

### Issuing API keys (login/registration/device-pairing endpoints)

Clients obtain their `X-API-KEY` through `Rest_key_model` — never write to the
key table directly. Typical flow after `$this->ion_auth->login()` succeeds:

```php
$this->load->model('rest_key_model');
$key = $this->rest_key_model->get_user_key($user_id, $device_uuid);  // existing key for this device
if (empty($key)) {
    $key = $this->rest_key_model->add_key(['user_id' => $user_id], $level, true, $device_uuid);
}
// also available: regenerate_post($old_key), suspend_key($key),
//                 set_key_level($key, $level), delete_key($key), delete_user_key($user_id)
```

### Password-reset endpoints

Use `$this->ion_auth->reset_password_with_code($code, $new_password)` and
never expose a reset path reachable with an identity alone — the full rules
(raw-method gating, sessions, lockout, tenancy, do-not-regress invariants)
are in the ixaya-auth skill.

## Request input

```php
$this->get('key')      // query string        $this->post('key')   // form/JSON body
$this->put('key')      $this->delete('key')   $this->query('key')  // any of the above
// all support ($key = null) to fetch everything, and an $xss_clean flag
```

Pagination/search params, validated with sane fallbacks:

```php
$p = $this->build_list_params(default_order_by: 'id', default_order: 'ASC', default_limit: 10);
// => ['page' => int, 'limit' => int, 'search' => string, 'order' => 'ASC'|'DESC', 'order_by' => string]
// reads GET params: page, limit, search_query, order, order_by
```

Client platform: `$this->get_platform()` (0 web / 1 iOS / 2 Android),
`$this->add_agent_data($data)` adds `os_kind` + `user_agent` to a write payload.

## Responses

`$this->response($data, $http_code)` serializes (JSON by default) and **exits** —
code after it never runs, which is why guard clauses need no `return`.

Envelope convention — `status` is three-tier: `1` = success, `0` = domain failure
(validation, not found, auth), `-1` = exception/framework-level error (you rarely
emit this yourself — the framework does). `message` = human text; `response` =
payload wrapper:

```php
// success
$this->response(['status' => 1, 'message' => 'User created successfully.', 'response' => $user], REST_Controller::HTTP_OK);
// validation / missing input
$this->response(['status' => 0, 'message' => 'No data provided, please try again.'], REST_Controller::HTTP_BAD_REQUEST);
// resource not found
$this->response(['status' => 0, 'message' => 'The user ID not found.'], REST_Controller::HTTP_NOT_FOUND);
// unexpected exception
$this->response(['status' => 0, 'message' => 'Error: ' . $e->getMessage()], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
// auth failure (emitted by the framework): ['status' => 0, 'message' => 'User not authorized'] + HTTP 401
```

Match the HTTP code to the failure with the `REST_Controller::HTTP_*` constants
(`HTTP_OK`, `HTTP_BAD_REQUEST`, `HTTP_NOT_FOUND`, `HTTP_UNAUTHORIZED`,
`HTTP_INTERNAL_SERVER_ERROR`, …), never bare ints.

### Uncaught errors already return JSON

`MGR_Exceptions` content-negotiates all error output: when the client doesn't
accept HTML (API calls), uncaught exceptions, PHP errors, and 404s automatically
render as `{status: -1, error: <class>, message, file, line}` with CORS headers
and parsed DB errors. So an endpoint without try/catch still fails with
structured JSON — don't wrap everything defensively. Use `try/catch (Exception $e)`
when you want a friendlier message, cleanup, or a specific HTTP code
(respond `HTTP_INTERNAL_SERVER_ERROR`, log with `mgr_process_exception($e)`).
CLI-visible logging inside API code: `$this->print_log($object)` (timestamped, class-tagged).

### Response caching (expensive list endpoints)

```php
$params = $this->build_list_params();

$this->load->driver('cache');
$cache_key = mgr_cache_key('sysusersidx', $params);   // stable key from params
$response = $this->cache->get($cache_key);
if (!empty($response)) {
    $this->response($response, REST_Controller::HTTP_OK);
}

// ...build $response from the model...
$this->cache->save($cache_key, $response);
$this->response($response, REST_Controller::HTTP_OK);
```

## API models

Models that need to know the calling user extend `APP_Api_Model` (a `MY_Model` with
`public $user_id`). Wire them with:

```php
$this->setup_model('billing/invoice', 'invoice');
// loads the model if needed, applies the controller's time zone to its DB session,
// and injects $this->user_id when the model is an API model
```

Plain `$this->load->model('module/name')` is fine for models that don't need user context.

## Full example

A complete authenticated list + detail + delete controller (level gating,
point-of-use loading, caching, envelope, HTTP codes) is in
`references/full-example.md` beside this file — read it when building a new
controller from scratch.

## Anti-patterns

```php
// WRONG — override after parent: key check already ran, endpoint stays locked
public function __construct()
{
    parent::__construct();
    $this->methods['*']['auth_override'] = 'none';
}

// WRONG — hand-rolled output: skips content negotiation and doesn't exit
echo json_encode(['ok' => true]);

// WRONG — bare status code and ad-hoc envelope
$this->response(['success' => true], 200);
```
