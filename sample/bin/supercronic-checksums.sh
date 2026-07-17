#!/usr/bin/env bash
# Regenerate the per-arch supercronic SHA256 pins for docker/Dockerfile.
#
# Usage: bin/supercronic-checksums.sh <version>        e.g. v0.2.47
#
# Prints a ready-to-paste `case` block. Host/maintenance tool only — never
# part of any image build.
#
# Provenance chain: aptible publishes only a SHA1 per arch in the release
# notes (author-authored, independent of the asset bytes); GitHub's own
# SHA256 digest is computed FROM the asset bytes, so it self-heals if the
# binary is swapped and is not an independent attestation. This script
# (1) reads aptible's SHA1 from the release notes, (2) downloads the binary
# and verifies it against that SHA1, (3) computes the SHA256 of the
# verified binary — anchoring the pin to bytes aptible vouched for while
# giving the build a collision-resistant check.

set -euo pipefail

version="${1:?usage: $0 <version>   e.g. v0.2.47}"
arches=(amd64 arm64)

workdir="$(mktemp -d)"
trap 'rm -rf "$workdir"' EXIT

body="$(curl -fsSL "https://api.github.com/repos/aptible/supercronic/releases/tags/${version}" \
	| python3 -c 'import sys, json; print(json.load(sys.stdin)["body"])')"

declare -A sha256s
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
