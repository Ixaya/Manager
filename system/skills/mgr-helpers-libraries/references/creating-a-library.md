# Creating a new library

Exemplars to copy from:
`vendor/ixaya/manager/system/libraries/MGR_Amazon_aws_lib.php` +
`system/package/config/lib_amazon_aws.php` (multi-profile config), and
`system/libraries/MGR/Migration.php` + `system/package/config/migration.php`
(plain config read).

### Naming contract

- Library file/class end in `_lib`: `application/libraries/Payment_lib.php` →
  `class Payment_lib` (module-level: `application/modules/{module}/libraries/`).
- Its config file starts with `lib_`: `application/config/lib_payment.php`.
- Every config value comes from `mgr_env()` with a sane default — never `getenv()`,
  never hardcoded secrets.

### Accessing the CI instance

Do NOT add the magic proxy `public function __get($var) { return get_instance()->$var; }`
— some older MGR libraries still carry it; don't copy it (it hides dependencies and
breaks static analysis). Instead:

```php
// CI used extensively across the class -> keep a property
protected $CI;
public function __construct()
{
    $this->CI = &get_instance();
}
// ...later: $this->CI->load->model('billing/invoice');

// CI used once or twice -> grab it locally, right where it's needed
$CI = &get_instance();
$path = $CI->config->path('lib_payment');
```

### Config mode A — multi-profile (external services, multiple accounts/tenants)

Config file declares an active profile plus one block per profile; common values
can sit alongside:

```php
// application/config/lib_payment.php
$active_config = 'default';
$config['default']['api_key'] = mgr_env('LIB_PAYMENT_API_KEY', null);
$config['default']['sandbox'] = mgr_env_bool('LIB_PAYMENT_SANDBOX', true);
// more profiles: $config['client_b']['api_key'] = mgr_env('LIB_PAYMENT_B_API_KEY', null);
```

The library resolves the file path, `include`s it into **local scope** (never into
CI's global config array), loads the active profile into typed properties, and
exposes `set_config_key()` for runtime switching:

```php
class Payment_lib
{
    protected array $config;
    protected string $config_key;
    protected string $api_key;

    public function __construct()
    {
        $file_path = get_instance()->config->path('lib_payment');   // env-aware resolution
        if ($file_path === null) {
            show_error('The configuration file lib_payment.php does not exist.');
        }

        include($file_path);   // $active_config and $config are now local vars

        if (!isset($this->config_key) && isset($active_config)) {
            $this->config_key = $active_config;
        }
        $this->config = $config ?? [];

        if (!empty($config[$this->config_key])) {
            $this->load_config($config[$this->config_key]);
        }
    }

    public function set_config_key(string $key)
    {
        if (!empty($this->config[$key])) {
            $this->config_key = $key;
            $this->load_config($this->config[$key]);
        }
    }

    protected function load_config(array $config)
    {
        $this->api_key = $config['api_key'] ?? '';
    }
}
```

### Config mode B — plain read (flat internal options)

For a single flat option set, read the array directly with the MX config `read()`
— it returns the file's `$config` **without merging it into the global config
array**. Never `$this->config->load('lib_payment')` — global loading risks key
collisions and leaks library options into every context:

```php
$base_config = get_instance()->config->read('lib_payment');   // ?array, not merged
$config = array_merge($base_config, $overrides);               // runtime overrides win
```

Pick mode A when the library talks to an external service that may need multiple
accounts/profiles; mode B when it's a flat set of internal options.

