# Full example — authenticated list + detail + delete

Condensed from the vendor sample `Sysusers`
(`vendor/ixaya/manager/sample/application/modules/admin/controllers/api/Sysusers.php`).
Demonstrates: level gating, point-of-use model loading, response caching,
the three-tier envelope, and correct HTTP codes.

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

        $this->load->driver('cache');
        $cache_key = mgr_cache_key('sysusersidx', $params);
        $response = $this->cache->get($cache_key);
        if (!empty($response)) {
            $this->response($response, REST_Controller::HTTP_OK);
        }

        try {
            $this->load->model('user');           // model loaded in the method that uses it
            $users = $this->user->get_list($params);  // ['data' => rows, 'total' => count]

            $response = [
                'status' => 1,
                'response' => [
                    'users'        => $users['data'] ?? [],
                    'recordsTotal' => $users['total'] ?? 0,
                ],
            ];
            $this->cache->save($cache_key, $response);
            $this->response($response, REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->response(['status' => 0, 'error' => $e->getMessage()], REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    public function details_get()
    {
        $id = $this->get('id');
        if (empty($id)) {
            $this->response(['status' => 0, 'message' => 'The user ID is required.'], REST_Controller::HTTP_BAD_REQUEST);
        }

        try {
            $this->load->model(['user', 'user_key']);
            $user = $this->user->get($id);
            if (empty($user)) {
                $this->response(['status' => 0, 'message' => 'The user ID not found.'], REST_Controller::HTTP_NOT_FOUND);
            }
            $this->response(['status' => 1, 'response' => ['user' => $user]], REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->response(['status' => 0, 'message' => 'Error: ' . $e->getMessage()], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function delete_post()
    {
        try {
            $result = $this->ion_auth->delete_user($this->post('id'));
            if ($result === true) {
                $this->response(['status' => 1, 'message' => 'User deleted successfully', 'response' => $result], REST_Controller::HTTP_OK);
            }
        } catch (Exception $e) {
            mgr_process_exception($e);
        }

        $this->response(['status' => 0, 'message' => 'Error deleting user'], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
    }
}
```

The paired `get_list()` model pattern (dynamic search, whitelisted ordering,
`['data','total']` return) is documented in the `ixaya-models` skill.
