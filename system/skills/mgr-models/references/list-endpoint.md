# Canonical list-endpoint model (`get_list`)

The model half of a paginated, searchable list endpoint (from the vendor
sample `User` model). The controller passes `build_list_params()` output
straight in; the model returns `['data' => rows, 'total' => count]`. The
controller half is in the mgr-rest-controller skill's
`references/full-example.md`.

```php
class User extends APP_Model_Dyn
{
    // Associative array: external order_by key => internal column (indexed
    // array works too when the external and internal names already match).
    private const ALLOWED_ORDER = ['email', 'first_name', 'last_name', 'created_on'];

    public function get_list_validate(array $params): ?string
    {
        $order_by = $params['order_by'] ?? 'created_on';
        if (mgr_validate_order_by($order_by, self::ALLOWED_ORDER) === null) {
            return "Invalid order_by column: {$order_by}.";
        }

        return null;
    }

    public function get_list(array $params): ?array
    {
        $fields = [
            'id', 'email', 'first_name', 'last_name',
            $this->build_field_select('created_on', MgrFunctionType::FromUnixtime),
        ];

        $where = [];
        if (!empty($params['search'])) {
            $search[MGR_Model_Dyn_clause::OR_LIKE] = [
                'first_name' => $params['search'],
                'last_name'  => $params['search'],
                'email'      => $params['search'],
            ];
            if (is_numeric($params['search'])) {
                $search[MGR_Model_Dyn_clause::OR_EQUAL] = ['id' => (int)$params['search']];
            }
            $where[MGR_Model_Dyn_clause::OR_GROUP] = $search;
        }

        $limit_page = mgr_build_limit_page($params['limit'], $params['page']);
        $order_by   = mgr_build_order_by($params['order_by'], $params['order'], self::ALLOWED_ORDER);
        if ($order_by === null) {
            return null;
        }

        $rows  = $this->get_all_dynamic(fields: $fields, where: $where, limit: $limit_page, order_by: $order_by);
        $count = $this->get_all_dynamic(fields: 'count(*) AS count', where: $where);

        return ['data' => $rows, 'total' => $count[0]['count'] ?? 0];
    }
}
```

These carry over to any list model you write:

- **Validate `$order_by` against an allowed-columns list**, never pass
  request input into it raw — `mgr_build_order_by()` returns `null` instead
  of substituting a default when the value isn't in the list.
- **Check for that `null` and return `null` from `get_list()`.** Running the
  query with a `null`/missing `order_by` after an invalid one silently
  accepts the bad input — the exact thing this pattern exists to prevent.
- **`get_list_validate()` is what lets the controller answer `400`** instead
  of the generic `500` a bare `get_list()` `null` return produces — call it
  before `get_list()`, reusing the same allowed-columns list via
  `mgr_validate_order_by()`. Only add it once a caller needs the 400/500
  split; a model can rely on `get_list()`'s own `null` guard alone.
- **Count with the same `$where`.** The total must reflect the filters, so
  reuse the built clause set rather than a bare `count_all()`.
