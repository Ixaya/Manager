#!/usr/bin/env bash
#
# Converts a SEPOMEX national postal-code catalog export (CPdescarga.txt)
# into a SQL dump matching the sepomex table shipped in
# extras/sepomex/database/sql/sepomex.zip (same columns, same row shape).
# See README.md for where to get CPdescarga.txt and how to load the result.
#
# The table itself is left to its own migration — this only flushes and
# reloads rows, so it works whichever engine the migration built it on
# (DELETE FROM instead of TRUNCATE, since SQLite has no TRUNCATE statement;
# unquoted identifiers instead of backticks, since MySQL is not the only
# target anymore).
#
# Both files this script touches are transitory (see .gitignore next to
# this script) — re-run it whenever a fresh catalog is needed.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TXT_FILE="${SCRIPT_DIR}/CPdescarga.txt"
SQL_FILE="${SCRIPT_DIR}/sepomex.sql"
BATCH_SIZE=1000

generate() {
	if [[ ! -f "${TXT_FILE}" ]]; then
		echo "sepomex_sql.sh: ${TXT_FILE} not found." >&2
		echo "Download the latest national postal code catalog (TXT) from SEPOMEX and save it as CPdescarga.txt in this directory, then run this script again." >&2
		exit 1
	fi

	local awk_program
	awk_program="$(mktemp)"
	trap 'rm -f "${awk_program}"' RETURN

	# Source columns (pipe-delimited, 1-indexed): 1 d_codigo, 2 d_asenta,
	# 3 d_tipo_asenta, 4 D_mnpio, 5 d_estado, 6 d_ciudad, 8 c_estado,
	# 12 c_mnpio, 14 d_zona. cp/idEstado/idMunicipio come from d_codigo/
	# c_estado/c_mnpio, not their d_* counterparts, to match the reference dump.
	cat >"${awk_program}" <<'AWK_EOF'
function esc(s) {
	gsub(/\\/, "\\\\", s)
	gsub(/'/, "''", s)
	return s
}
BEGIN {
	FS = "|"
	print "DELETE FROM sepomex;"
	print ""
	row = 0
}
NR <= 2 { next }
NF < 14 { next }
{
	row++
	if ((row - 1) % batch_size == 0) {
		if (row > 1) {
			print ";"
		}
		print "INSERT INTO sepomex (id, id_estado, estado, id_municipio, municipio, ciudad, zona, cp, asentamiento, tipo) VALUES"
	} else {
		print ","
	}
	printf "\t(%d,%d,'%s',%d,'%s','%s','%s','%s','%s','%s')", row, $8 + 0, esc($5), $12 + 0, esc($4), esc($6), esc($14), esc($1), esc($2), esc($3)
}
END {
	if (row > 0) {
		print ";"
	}
}
AWK_EOF

	iconv -f ISO-8859-1 -t UTF-8 "${TXT_FILE}" \
		| tr -d '\r' \
		| awk -v batch_size="${BATCH_SIZE}" -f "${awk_program}" \
		> "${SQL_FILE}"
}

generate
