# sepomex_sql.sh

Download the latest national postal code catalog (TXT) from SEPOMEX and save
it as `CPdescarga.txt` in this directory, then run:

```bash
./sepomex_sql.sh
```

This produces `sepomex.sql`: a `DELETE FROM sepomex;` followed by batched
`INSERT`s, matching the `sepomex` table's columns and row shape. It does not
create the table — run the `Sepomex` migration first.

`CPdescarga.txt` and `sepomex.sql` are both transitory (gitignored) — re-run
the script whenever you need a fresh catalog.

## Loading the result into Docker

PostgreSQL:

```bash
docker cp sepomex.sql <instance>-postgres-1:/tmp/sepomex.sql
docker exec <instance>-postgres-1 psql -U <db_user> -d <db_name> -f /tmp/sepomex.sql
docker exec <instance>-postgres-1 rm -f /tmp/sepomex.sql
```

MySQL/MariaDB:

```bash
docker cp sepomex.sql <instance>-mysql-1:/tmp/sepomex.sql
docker exec <instance>-mysql-1 bash -c 'mysql -u <db_user> -p<db_password> <db_name> < /tmp/sepomex.sql'
docker exec <instance>-mysql-1 rm -f /tmp/sepomex.sql
```

`<instance>` is the compose project name (`-e` in `docker_manage.sh`), and
`<db_user>`/`<db_name>`/`<db_password>` come from that instance's
`docker/env/<instance>.env` and `<instance>.priv.env`.
