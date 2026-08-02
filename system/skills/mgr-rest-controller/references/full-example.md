# Full example — authenticated list + detail + delete

Condensed from the vendor sample `Sysusers`
(`vendor/ixaya/manager/sample/application/modules/admin/controllers/api/Sysusers.php`).
Demonstrates: level gating, point-of-use library/model loading, response
caching, the binary envelope, and correct HTTP codes — and the absence of
try/catch, since nothing in this flow can throw.

```php
<?php

class Sysusers extends APP_Rest_Controller
{
    public function __construct()
    {
        $this->group_methods['*']['level'] = LEVEL_ADMIN;
        parent::__construct();
    }

    public function index_get()
    {
        $params = $this->build_list_params();

        $this->load->model('user');              // model loaded in the method that uses it

        $validation_error = $this->user->get_list_validate($params);
        if ($validation_error !== null) {
            $this->response(['status' => 0, 'message' => $validation_error], REST_Controller::HTTP_BAD_REQUEST);
        }

        $this->load->driver('cache');
        $cache_key = mgr_cache_key('sysusersidx', $params);
        $response = $this->cache->get($cache_key);
        if (!empty($response)) {
            $this->response($response, REST_Controller::HTTP_OK);
        }

        $users = $this->user->get_list($params);  // ['data' => rows, 'total' => count] or null

        if ($users === null) {
            $this->response(['status' => 0, 'message' => 'Failed to load users.'], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }

        $response = [
            'status' => 1,
            'response' => [
                'users'        => $users['data'],
                'recordsTotal' => $users['total'],
            ],
        ];
        $this->cache->save($cache_key, $response);
        $this->response($response, REST_Controller::HTTP_OK);
    }

    public function details_get()
    {
        $id = $this->get('id');
        if (empty($id)) {
            $this->response(['status' => 0, 'message' => 'The user ID is required.'], REST_Controller::HTTP_BAD_REQUEST);
        }

        $this->load->model(['user', 'user_key']);
        $user = $this->user->get($id);
        if (empty($user)) {
            $this->response(['status' => 0, 'message' => 'The user ID not found.'], REST_Controller::HTTP_NOT_FOUND);
        }

        $this->response(['status' => 1, 'response' => ['user' => $user]], REST_Controller::HTTP_OK);
    }

    public function delete_post()
    {
        $this->load->library('ion_auth');       // library loaded in the method that uses it

        $result = $this->ion_auth->delete_user($this->post('id'));
        if ($result === true) {
            $this->response(['status' => 1, 'message' => 'User deleted successfully'], REST_Controller::HTTP_OK);
        }

        $this->response(['status' => 0, 'message' => 'Error deleting user'], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
    }
}
```

None of these three methods wrap anything in `try/catch`: `get_list()`,
`get()` and `delete_user()` all signal failure through their return value
(`null`/`empty`/`false`), never a throw — tracing the call chain first is
what tells you that, not a blanket "wrap defensively" habit. Each still gets
its own explicit check, though: `get_list_validate()`'s non-null message is
an invalid request and answers its own `400`; `get_list() === null` is a
failed query and answers its own `500`; `get()`'s empty result is a
legitimate not-found and answers its own 404; `delete_user()`'s `false`
answers its own 5xx. An endpoint like this still fails as structured, logged
JSON if something further down the stack genuinely does throw; nothing here
needs to catch it to get that behavior.

The paired `get_list()`/`get_list_validate()` model pattern (dynamic search,
allowed-columns ordering, `['data','total']` return) is the mgr-models
skill's `references/list-endpoint.md`.
