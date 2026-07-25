# Public endpoint example — login with `auth_override`

Condensed from the sample skeleton's auth module (`Login.php`). Demonstrates:
disabling key auth for a public endpoint, sessionless login, uniform failure
messaging, and API-key issuance via `Rest_key_model`.

```php
<?php

class Login extends APP_Rest_Controller
{
    public function __construct()
    {
        // public endpoint: no X-API-KEY required, for any method
        $this->methods['*']['auth_override'] = 'none';

        parent::__construct();

        $this->load->database();
        $this->load->library('ion_auth');
    }

    public function index_post()
    {
        $username = $this->post('username');
        $password = $this->post('password');

        // every failure cause returns the same message — differentiated
        // responses are a username-enumeration surface
        if (empty($username) || empty($password)) {
            $this->response(['status' => -1, 'message' => 'Username/password incorrect'], REST_Controller::HTTP_OK);
        }

        $this->ion_auth->disable_session();
        $user = $this->ion_auth->login($username, $password);

        if ($user === false) {
            $this->response(['status' => -1, 'message' => 'Username/password incorrect'], REST_Controller::HTTP_OK);
        }

        $this->response($this->build_login_response($user, $this->post('device_uuid')), REST_Controller::HTTP_OK);
    }

    /**
     * Sanitizes the user object and attaches an API key.
     *
     * @param object      $user        Sessionless login() result.
     * @param string|null $device_uuid Optional device identifier.
     * @return array{status: int, info: object, api_key: string, device_uuid: ?string}
     */
    private function build_login_response(object $user, ?string $device_uuid = null): array
    {
        unset($user->password, $user->active, $user->last_login);

        $this->load->model('Rest_key_model', 'api_key');

        return [
            'status'      => 1,
            'info'        => $user,
            'api_key'     => $this->api_key->get_user_key($user->id, $device_uuid),
            'device_uuid' => $device_uuid,
        ];
    }
}
```

Registration and password recovery follow the same uniform-failure rule:
registration failure is a neutral "Unable to register."; recovery always
claims success regardless of whether the identity exists.
