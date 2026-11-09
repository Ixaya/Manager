#!/bin/sh
# Refuses POSTGRES_USER over TCP except from loopback, as MariaDB's root is: adds
# a reject line ahead of the image's catch-all `host all all all` rule. Idempotent,
# so a cluster initialized before this file existed runs it by hand, then reloads.
# Never `exit 0`: the image sources this file when it is not executable.

hba="$PGDATA/pg_hba.conf"
reject="host all $POSTGRES_USER all reject"
catch_all_count="$(grep -c '^host all all all ' "$hba" || true)"

if grep -qxF "$reject" "$hba"; then
    echo "20-superuser-local-only.sh: $hba already rejects $POSTGRES_USER over TCP"
elif [ "$catch_all_count" != 1 ]; then
    echo "20-superuser-local-only.sh: expected one 'host all all all' line in $hba, found ${catch_all_count:-none}; not edited" >&2
    exit 1
else
    tmp="$(mktemp)" || exit 1
    # Rewritten in place, not moved: the file keeps its postgres owner even when run as root.
    if awk -v reject="$reject" '/^host all all all / { print reject } { print }' "$hba" > "$tmp" && cat "$tmp" > "$hba"; then
        rm -f "$tmp"
        echo "20-superuser-local-only.sh: $POSTGRES_USER is now refused over TCP except from loopback"
    else
        rm -f "$tmp"
        echo "20-superuser-local-only.sh: rewriting $hba failed" >&2
        exit 1
    fi
fi
