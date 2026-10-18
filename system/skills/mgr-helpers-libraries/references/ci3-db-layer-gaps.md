# Working around a gap in CI3's database layer

`DB_driver`, `DB_result`, `DB_forge` and every `pdo_*` subdriver live in the
`nielbuys/framework` dependency, and the `MGR_*` → alias → `MY_`/`APP_`
chain does not reach them. There is no subclass seam, so "override the
driver method" always costs a Composer patch — a dependency decision, not a
code change. Work outward instead:

1. **Use the driver's existing public methods.** A gap marked
   "unimplemented on PDO" is usually a missing override *point*, not a
   missing capability. `reconnect()` is the worked example: it differs per
   driver (a no-op on PDO; native `postgre` can reset the same link to a
   fresh session), so a driver-agnostic staleness check is a trivial query in
   a `try`/`catch` plus the already-public `$this->db->close()`, which nulls
   `conn_id` on every driver so the next query reconnects lazily.
2. **Reach the client through config.** `application/config/database.php`'s
   keys are copied onto the driver object verbatim: `options` passes into
   the PDO constructor, `dsn` replaces the driver's own DSN building.
3. **Only then call it a patch**, and raise it as a dependency decision
   rather than shadowing the class.

Confirm a caller actually reaches the broken path before costing any of
this — several of these gaps are unreachable through the public API.
