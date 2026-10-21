#!/usr/bin/env bash
# Regenerate the verified SHA256 pins in docker/Dockerfile for a downloaded release.
#
# Usage: bin/pin-checksums.sh supercronic <version>     e.g. v0.2.49
#        bin/pin-checksums.sh pie <version>             e.g. 1.5.2
#
# Prints a ready-to-paste block; never edits a file. Host/maintenance tool
# only — never part of any image build.
#
# supercronic: aptible publishes only a SHA1 per arch in the release notes,
# independent of the asset bytes. GitHub's own SHA256 digest is computed FROM
# the bytes, so on a mutable release it self-heals if the binary is swapped.
# The pin is therefore the SHA256 of a binary verified against aptible's SHA1.
#
# pie: php/pie publishes no author-side hash, but its releases are GitHub
# immutable releases — assets cannot be replaced after publication, so the
# release's own digest cannot self-heal. This target refuses a release that is
# not immutable, then requires the downloaded pie.phar to match that digest.

set -euo pipefail

target="${1:?usage: $0 <supercronic|pie> <version>}"
version="${2:?usage: $0 <supercronic|pie> <version>}"

workdir="$(mktemp -d)"
trap 'rm -rf "$workdir"' EXIT

supercronic() {
	local arches=(amd64 arm64) body arch asset sha1 bin
	declare -A sha256s

	body="$(curl -fsSL "https://api.github.com/repos/aptible/supercronic/releases/tags/${version}" \
		| python3 -c 'import sys, json; print(json.load(sys.stdin)["body"])')"

	for arch in "${arches[@]}"; do
		asset="supercronic-linux-${arch}"

		# The URL line ends in "<asset> \" — the trailing " \" anchors the exact
		# asset name so arm64 never matches the plain-arm stanza.
		sha1="$(printf '%s\n' "$body" | awk -v asset="$asset" '
			index($0, "SUPERCRONIC_URL=") && index($0, "/" asset " \\") { want = 1; next }
			want && sub(/.*SUPERCRONIC_SHA1SUM=/, "") { sub(/[^0-9a-f].*/, ""); print; exit }')"

		if [[ ! "$sha1" =~ ^[0-9a-f]{40}$ ]]; then
			echo "FAIL: no SHA1 found for ${asset} in the ${version} release notes" >&2
			exit 1
		fi

		bin="${workdir}/${asset}"
		curl -fsSL -o "$bin" \
			"https://github.com/aptible/supercronic/releases/download/${version}/${asset}"

		echo "${sha1}  ${bin}" | sha1sum -c - >&2

		sha256s[$arch]="$(sha256sum "$bin" | awk '{print $1}')"
	done

	echo
	echo "# Verified against aptible's published SHA1s for ${version} — paste over the pins:"
	for arch in "${arches[@]}"; do
		echo "      ${arch}) sha256=\"${sha256s[$arch]}\" ;; \\"
	done
}

pie() {
	local immutable digest phar sha256

	read -r immutable digest < <(curl -fsSL "https://api.github.com/repos/php/pie/releases/tags/${version}" \
		| python3 -c '
import sys, json
release = json.load(sys.stdin)
digest = next((a.get("digest") or "none" for a in release["assets"] if a["name"] == "pie.phar"), "none")
print(str(release.get("immutable", False)).lower(), digest)')

	if [[ "$immutable" != "true" ]]; then
		echo "FAIL: release ${version} is not an immutable release — its digest is not an independent attestation" >&2
		exit 1
	fi
	if [[ ! "$digest" =~ ^sha256:[0-9a-f]{64}$ ]]; then
		echo "FAIL: no sha256 digest for pie.phar in the ${version} release" >&2
		exit 1
	fi

	phar="${workdir}/pie.phar"
	curl -fsSL -o "$phar" "https://github.com/php/pie/releases/download/${version}/pie.phar"
	sha256="$(sha256sum "$phar" | awk '{print $1}')"

	if [[ "sha256:${sha256}" != "$digest" ]]; then
		echo "FAIL: downloaded pie.phar (${sha256}) does not match the release digest (${digest})" >&2
		exit 1
	fi

	echo
	echo "# Immutable release ${version}, download matches its digest — paste over the pin:"
	echo "    pie_sha256=\"${sha256}\"; \\"
}

case "$target" in
	supercronic) supercronic ;;
	pie) pie ;;
	*) echo "unknown target: ${target} (expected supercronic or pie)" >&2; exit 2 ;;
esac
