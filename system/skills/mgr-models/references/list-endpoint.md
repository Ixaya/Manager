# Canonical list-endpoint model (`get_list`)

The model half of a paginated, searchable list endpoint (from the vendor
sample `User` model). The controller passes `build_list_params()` output
straight in; the model returns `['data' => rows, 'total' => count]`. The
controller half is in the mgr-rest-controller skill's
`references/full-example.md`.

```php
class User extends APP_Model_Dyn
{
    public function get_list(array $params)
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

        // whitelist sortable columns — never pass request input into order_by raw
        $allowed_order = ['email', 'first_name', 'last_name', 'created_on'];
        $limit_page = mgr_build_limit_page($params['limit'], $params['page']);
        $order_by   = mgr_build_order_by($params['order_by'], $params['order'], $allowed_order);

        $rows  = $this->get_all_dynamic(fields: $fields, where: $where, limit: $limit_page, order_by: $order_by);
        $count = $this->get_all_dynamic(fields: 'count(*) AS count', where: $where);

        return ['data' => $rows, 'total' => $count[0]['count'] ?? 0];
    }
}
```

Two things carry over to any list model you write:

- **Whitelist the sortable columns.** `$order_by` reaches the query as a raw
  SQL fragment; `mgr_build_order_by()` is what keeps request input out of it.
- **Count with the same `$where`.** The total must reflect the filters, so
  reuse the built clause set rather than a bare `count_all()`.
