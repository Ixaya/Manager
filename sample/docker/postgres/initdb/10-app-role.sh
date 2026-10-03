#!/bin/sh
# Fresh cluster only: creates DB_USER as a plain role that owns POSTGRES_DB.
# The password arrives as POSTGRES_APP_PASSWORD from postgres/entrypoint.sh and
# is read inside psql, never on argv. One psql call, so it works whether the
# image executes or sources this file.

# An empty password would make CREATE ROLE succeed with no password at all.
if [ -z "${POSTGRES_APP_PASSWORD:-}" ]; then
    echo "10-app-role.sh: POSTGRES_APP_PASSWORD is not set (the container must start through postgres/entrypoint.sh); role ${DB_USER} not created" >&2
    exit 1
fi

psql -v ON_ERROR_STOP=1 --no-psqlrc --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
    -v app_user="$DB_USER" -v db_name="$POSTGRES_DB" <<'EOSQL'
\getenv pw POSTGRES_APP_PASSWORD
-- A failed or statement-logged CREATE ROLE would put the password in the log.
SET log_min_error_statement = panic;
SET log_statement = none;
SET log_min_duration_statement = -1;
SET log_min_duration_sample = -1;
SET log_transaction_sample_rate = 0;
CREATE ROLE :"app_user" LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE PASSWORD :'pw';
ALTER DATABASE :"db_name" OWNER TO :"app_user";
EOSQL
