<?php

/**
 * Report every image/release pin in docker/ against the newest upstream tags.
 *
 * Usage (from the project root):
 *   ./docker_manage.sh -e <instance> run --rm -T [-e GITHUB_TOKEN] tools php bin/docker-pin-report.php [--hold-days=7]
 *
 * For each pin it prints the pin itself, the newest tag in the same series at
 * every level (8.4.x, 8.x, newest) within the pin's variant (fpm-alpine,
 * noble…), and the newest tag of each release channel an official image
 * publishes (lts, stable, innovation…), each with its release date and the
 * series' support window. A release younger than --hold-days is marked HOLD.
 * Where upstream gives no date, Docker Hub's last push stands in and marks
 * HOLD? — a rebuild moves it too. Report only — never edits a file. Queries
 * Docker Hub, GitHub (60 requests/hour unless GITHUB_TOKEN is set) and
 * endoflife.date; other registries are listed as skipped.
 */

const PIN_FILES = ['docker/Dockerfile', 'docker/docker-compose*.yml'];

const PIN_NOTES = [
	'aptible/supercronic' => 'a bump also needs new checksums: bin/supercronic-checksums.sh <version>',
];

// endoflife.date product, plus the GitHub repo whose releases carry the real release date
const UPSTREAM = [
	'library/alpine' => ['product' => 'alpine-linux'],
	'library/php' => ['product' => 'php', 'releases' => 'php/php-src', 'tag' => 'php-{version}'],
	'library/nginx' => ['product' => 'nginx', 'releases' => 'nginx/nginx', 'tag' => 'release-{version}'],
	'valkey/valkey' => ['product' => 'valkey', 'releases' => 'valkey-io/valkey', 'tag' => '{version}'],
	'library/mysql' => ['product' => 'mysql'],
	'library/mariadb' => ['product' => 'mariadb', 'releases' => 'MariaDB/server', 'tag' => 'mariadb-{version}'],
	'library/postgres' => ['product' => 'postgresql'],
];

// bare alias tags that name a release channel rather than a variant (`fpm`, `alpine`…)
const CHANNEL_ALIASES = ['latest', 'lts', 'stable', 'mainline', 'innovation', 'rolling'];

const LABEL_WIDTH = 12;

$hold_days = 7;
foreach (array_slice($argv, 1) as $argument) {
	if (preg_match('/^--hold-days=(\d+)$/', $argument, $match) === 1) {
		$hold_days = (int) $match[1];
		continue;
	}
	fwrite(STDERR, "unknown argument: {$argument}\nusage: php bin/docker-pin-report.php [--hold-days=7]\n");
	exit(2);
}

$failures = run_report(root: dirname(__DIR__), hold_days: $hold_days);
exit($failures === 0 ? 0 : 1);

/**
 * Print the report; returns the number of pins whose lookup failed.
 */
function run_report(string $root, int $hold_days): int
{
	$pins = collect_pins($root);
	$failures = 0;
	$distros = [];

	echo 'Docker pin report — ' . gmdate('Y-m-d') . ", hold window {$hold_days} days\n";
	echo "(released = upstream release date; pushed = Docker Hub's last push, no upstream date found — a rebuild moves it, so HOLD? needs confirming)\n";

	foreach ($pins as $pin) {
		echo "\n" . format_locations($pin['locations']) . "  {$pin['ref']}\n";

		if ($pin['kind'] === 'github') {
			$failures += report_github_pin(pin: $pin, hold_days: $hold_days);
			continue;
		}

		$image = parse_image_ref($pin['ref']);
		if ($image === null) {
			echo "  skipped: only Docker Hub images are queried\n";
			continue;
		}

		$parsed_pin = parse_tag($image['tag']);
		if ($parsed_pin !== null && str_contains($parsed_pin['family'], 'alpine') && $parsed_pin['distro'] !== []) {
			$distros['alpine ' . implode('.', $parsed_pin['distro'])][] = $pin['ref'];
		}
		if ($image['repository'] === 'library/alpine' && $parsed_pin !== null) {
			$distros['alpine ' . implode('.', array_slice($parsed_pin['version'], 0, 2))][] = $pin['ref'];
		}

		$failures += report_image_pin(image: $image, parsed_pin: $parsed_pin, hold_days: $hold_days);
	}

	if (count($distros) > 1) {
		echo "\nBase distro MISMATCH across pins:\n";
		foreach ($distros as $distro => $refs) {
			echo "  {$distro}: " . implode(', ', $refs) . "\n";
		}
	} elseif ($distros !== []) {
		echo "\nBase distro: " . array_key_first($distros) . ' across all ' . count(reset($distros)) . " alpine pins\n";
	}

	return $failures;
}

/**
 * Collect pins from the Dockerfile and compose files, merging repeats of the same ref.
 *
 * @return list<array{kind: string, ref: string, locations: list<string>}>
 */
function collect_pins(string $root): array
{
	$pins = [];
	foreach (PIN_FILES as $pattern) {
		foreach (glob("{$root}/{$pattern}") ?: [] as $path) {
			$relative_path = substr($path, strlen($root) + 1);
			$stages = [];
			foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $index => $line) {
				foreach (extract_refs(line: $line, stages: $stages) as [$kind, $ref]) {
					$pins[$ref] ??= ['kind' => $kind, 'ref' => $ref, 'locations' => []];
					$pins[$ref]['locations'][] = "{$relative_path}:" . ($index + 1);
				}
			}
		}
	}

	return array_values($pins);
}

/**
 * Pin refs on one line as [kind, ref] pairs; tracks Dockerfile stage names so `FROM <stage>` is skipped.
 *
 * @param list<string> $stages
 * @return list<array{0: string, 1: string}>
 */
function extract_refs(string $line, array &$stages): array
{
	$refs = [];
	if (preg_match('/^#\s*syntax=(\S+)/', $line, $match) === 1) {
		$refs[] = ['image', $match[1]];
	}
	if (preg_match('/^\s*FROM\s+(?:--platform=\S+\s+)?(\S+)(?:\s+AS\s+(\S+))?/i', $line, $match) === 1) {
		if (!in_array($match[1], $stages, true)) {
			$refs[] = ['image', $match[1]];
		}
		if (isset($match[2])) {
			$stages[] = $match[2];
		}
	}
	if (preg_match('/COPY\s+--from=(\S+:\S+)/', $line, $match) === 1) {
		$refs[] = ['image', $match[1]];
	}
	if (preg_match('/^\s*image:\s*["\']?([^"\'\s]+)/', $line, $match) === 1) {
		$refs[] = ['image', $match[1]];
	}
	if (preg_match('#github\.com/([\w.-]+/[\w.-]+)/releases/download/([^/\s"]+)/#', $line, $match) === 1) {
		$refs[] = ['github', "{$match[1]}@{$match[2]}"];
	}

	// ${VAR} refs are the project's own built images
	return array_values(array_filter($refs, static fn (array $ref): bool => !str_contains($ref[1], '$')));
}

/**
 * Split a Docker Hub image ref; null for any other registry.
 *
 * @return ?array{repository: string, tag: string}
 */
function parse_image_ref(string $ref): ?array
{
	$separator = strrpos($ref, ':');
	$name = $separator === false ? $ref : substr($ref, 0, $separator);
	$tag = $separator === false ? 'latest' : substr($ref, $separator + 1);

	$first_segment = explode('/', $name)[0];
	if (str_contains($name, '/') && (str_contains($first_segment, '.') || str_contains($first_segment, ':'))) {
		return null;
	}

	return ['repository' => str_contains($name, '/') ? $name : "library/{$name}", 'tag' => $tag];
}

/**
 * Parse a versioned tag; null for a non-version tag such as `latest` or `alpine`.
 *
 * `family` is the variant with distro digits and pre-release markers removed
 * (`-fpm-alpine`), so tags compare only within the same variant.
 *
 * @return ?array{version: list<int>, family: string, distro: list<int>, prerelease: bool}
 */
function parse_tag(string $tag): ?array
{
	if (preg_match('/^(\d+(?:\.\d+)*)(.*)$/', $tag, $match) !== 1) {
		return null;
	}

	$rest = $match[2];
	$prerelease = false;
	if (preg_match('/(?<![a-z])(?:alpha|beta|rc)\d*(?![a-z])/i', $rest) === 1) {
		$prerelease = true;
		$rest = (string) preg_replace('/-?(?<![a-z])(?:alpha|beta|rc)\d*(?![a-z])/i', '', $rest);
	}
	if ($rest !== '' && $rest[0] !== '-') {
		return null;
	}

	$distro = [];
	if (preg_match('/(alpine|oraclelinux|ubi)(\d+(?:\.\d+)*)/', $rest, $distro_match) === 1) {
		$distro = array_map('intval', explode('.', $distro_match[2]));
		$rest = str_replace($distro_match[0], $distro_match[1], $rest);
	}

	return [
		'version' => array_map('intval', explode('.', $match[1])),
		'family' => $rest,
		'distro' => $distro,
		'prerelease' => $prerelease,
	];
}

/**
 * Print the candidates for one Docker Hub pin; returns 1 when a lookup failed.
 *
 * @param array{repository: string, tag: string} $image
 * @param ?array{version: list<int>, family: string, distro: list<int>, prerelease: bool} $parsed_pin
 */
function report_image_pin(array $image, ?array $parsed_pin, int $hold_days): int
{
	if ($parsed_pin === null) {
		echo "  floating: `{$image['tag']}` is not a version, not compared\n";
		return 0;
	}

	$tags = fetch_registry_tags($image['repository']);
	if ($tags === null) {
		echo "  LOOKUP FAILED — not compared\n";
		return 1;
	}

	$parsed_tags = [];
	foreach ($tags as $tag) {
		$parsed = parse_tag($tag);
		if ($parsed !== null) {
			$parsed_tags[$tag] = $parsed;
		}
	}

	$floating_reason = floating_reason(parsed_pin: $parsed_pin, parsed_tags: $parsed_tags);
	if ($floating_reason !== null) {
		echo "  floating: {$floating_reason}, not compared\n";
		return 0;
	}

	$depth = count($parsed_pin['version']);
	$by_version = [];
	foreach ($parsed_tags as $tag => $parsed) {
		if ($parsed['prerelease'] || $parsed['family'] !== $parsed_pin['family'] || count($parsed['version']) !== $depth) {
			continue;
		}
		$version = implode('.', $parsed['version']);
		// prefer the tag that names its distro, highest distro first
		if (!isset($by_version[$version]) || compare_versions($parsed['distro'], $parsed_tags[$by_version[$version]]['distro']) > 0) {
			$by_version[$version] = $tag;
		}
	}

	$candidates = [];
	foreach ($by_version as $version => $tag) {
		$candidates[] = $by_version[$version] = ['name' => $tag, 'version' => $parsed_tags[$tag]['version']];
	}

	$upstream = UPSTREAM[$image['repository']] ?? [];
	$cycles = isset($upstream['product']) ? fetch_cycles($upstream['product']) : [];
	$channels = str_starts_with($image['repository'], 'library/') ? fetch_channels(substr($image['repository'], 8)) : [];
	$lines = series_lines(pin_version: $parsed_pin['version'], candidates: $candidates);
	$lines = [...$lines, ...channel_lines(pin_version: $parsed_pin['version'], channels: $channels, candidates: $by_version, shown: $lines)];
	$failures = 0;

	$print_candidate = static function (string $label, array $candidate) use ($image, $upstream, $cycles, $channels, $hold_days, &$failures): bool {
		$cycle = find_cycle(version: $candidate['version'], cycles: $cycles);
		$release = release_date(repository: $image['repository'], tag: $candidate['name'], version: $candidate['version'], upstream: $upstream, cycle: $cycle);
		$failures += $release['date'] === null ? 1 : 0;
		$channel = $channels === [] ? null : channel_label(version: $candidate['version'], channels: $channels);
		$annotation = array_filter([format_support($cycle), $channel === null ? '' : "channel {$channel}"]);

		return print_line(label: $label, name: $candidate['name'], release: $release, hold_days: $hold_days, annotation: $annotation);
	};

	$pin_held = $print_candidate('pin', ['name' => $image['tag'], 'version' => $parsed_pin['version']]);
	$previous = $pin_held ? previous_candidate(pin_version: $parsed_pin['version'], candidates: $candidates) : null;
	if ($previous !== null) {
		$print_candidate('previous', $previous);
	}
	print_series_current(pin_version: $parsed_pin['version'], lines: $lines);
	foreach ($lines as [$label, $candidate]) {
		$print_candidate($label, $candidate);
	}

	$newest_stable = $candidates === [] ? $parsed_pin['version'] : max_version(array_column($candidates, 'version'));
	report_other_families(parsed_pin: $parsed_pin, parsed_tags: $parsed_tags, newest_stable: $newest_stable, channels: $channels);

	return $failures;
}

/**
 * Why a pin is floating (an alias of a more specific tag), or null when it is exact.
 *
 * @param array{version: list<int>, family: string, distro: list<int>, prerelease: bool} $parsed_pin
 * @param array<string, array{version: list<int>, family: string, distro: list<int>, prerelease: bool}> $parsed_tags
 */
function floating_reason(array $parsed_pin, array $parsed_tags): ?string
{
	$depth = count($parsed_pin['version']);
	$newest_alias_target = null;
	foreach ($parsed_tags as $tag => $parsed) {
		if ($parsed['family'] !== $parsed_pin['family'] || $parsed['prerelease']) {
			continue;
		}
		if ($parsed_pin['distro'] === [] && $parsed['distro'] !== [] && $parsed['version'] === $parsed_pin['version']) {
			return "base distro not pinned (`{$tag}` exists)";
		}
		if (count($parsed['version']) > $depth && array_slice($parsed['version'], 0, $depth) === $parsed_pin['version']
			&& ($newest_alias_target === null || compare_versions($parsed['version'], $parsed_tags[$newest_alias_target]['version']) > 0)) {
			$newest_alias_target = $tag;
		}
	}

	return $newest_alias_target === null ? null : "alias of more specific tags, newest `{$newest_alias_target}`";
}

/**
 * Newest candidate per series level (8.4.x, 8.x, newest), dropping levels that repeat the previous one.
 *
 * @param list<int> $pin_version
 * @param list<array{name: string, version: list<int>}> $candidates
 * @return list<array{0: string, 1: array{name: string, version: list<int>}}>
 */
function series_lines(array $pin_version, array $candidates): array
{
	$lines = [];
	$previous = null;
	for ($prefix_length = count($pin_version) - 1; $prefix_length >= 0; $prefix_length--) {
		$prefix = array_slice($pin_version, 0, $prefix_length);
		$best = null;
		foreach ($candidates as $candidate) {
			if (array_slice($candidate['version'], 0, $prefix_length) !== $prefix || compare_versions($candidate['version'], $pin_version) <= 0) {
				continue;
			}
			if ($best === null || compare_versions($candidate['version'], $best['version']) > 0) {
				$best = $candidate;
			}
		}
		if ($best === null || $best['name'] === $previous) {
			continue;
		}
		$label = $prefix_length === 0 ? 'newest' : implode('.', $prefix) . '.x';
		$lines[] = [$label, $best];
		$previous = $best['name'];
	}

	return $lines;
}

/**
 * `8.4.x  up to date` when the pin's own series has nothing newer, so a quiet series is never ambiguous.
 *
 * @param list<int> $pin_version
 * @param list<array{0: string, 1: array{name: string, version: list<int>}}> $lines
 */
function print_series_current(array $pin_version, array $lines): void
{
	$own_label = count($pin_version) < 2 ? 'newest' : implode('.', array_slice($pin_version, 0, -1)) . '.x';
	if (!in_array($own_label, array_column($lines, 0), true)) {
		echo '  ' . str_pad($own_label, LABEL_WIDTH) . "up to date\n";
	}
}

/**
 * Newest candidate older than the pin in its own series — the fallback while the pin is on hold.
 *
 * @param list<int> $pin_version
 * @param list<array{name: string, version: list<int>}> $candidates
 * @return ?array{name: string, version: list<int>}
 */
function previous_candidate(array $pin_version, array $candidates): ?array
{
	$series = array_slice($pin_version, 0, -1);
	$previous = null;
	foreach ($candidates as $candidate) {
		if (array_slice($candidate['version'], 0, count($series)) !== $series || compare_versions($candidate['version'], $pin_version) >= 0) {
			continue;
		}
		if ($previous === null || compare_versions($candidate['version'], $previous['version']) > 0) {
			$previous = $candidate;
		}
	}

	return $previous;
}

/**
 * Flag newer versions published only under another variant, and newer pre-releases.
 *
 * @param array{version: list<int>, family: string, distro: list<int>, prerelease: bool} $parsed_pin
 * @param array<string, array{version: list<int>, family: string, distro: list<int>, prerelease: bool}> $parsed_tags
 * @param list<int> $newest_stable
 * @param array<string, list<string>> $channels
 */
function report_other_families(array $parsed_pin, array $parsed_tags, array $newest_stable, array $channels): void
{
	$depth = count($parsed_pin['version']);
	$other_version = null;
	$other_tags = [];
	$prerelease_tag = null;
	$prerelease_version = null;

	foreach ($parsed_tags as $tag => $parsed) {
		if ($parsed['prerelease']) {
			// equal versions (19beta1 vs 19beta4) fall back to natural tag order
			if ($parsed['family'] === $parsed_pin['family'] && compare_versions($parsed['version'], $newest_stable) > 0
				&& ($prerelease_tag === null || (compare_versions($parsed['version'], $prerelease_version ?? []) ?: strnatcmp($tag, $prerelease_tag)) > 0)) {
				$prerelease_version = $parsed['version'];
				$prerelease_tag = $tag;
			}
			continue;
		}
		if (count($parsed['version']) !== $depth || compare_versions($parsed['version'], $newest_stable) <= 0) {
			continue;
		}
		$comparison = $other_version === null ? 1 : compare_versions($parsed['version'], $other_version);
		if ($comparison > 0) {
			$other_version = $parsed['version'];
			$other_tags = [];
		}
		if ($comparison >= 0) {
			$other_tags[] = $tag;
		}
	}

	if ($other_version !== null) {
		sort($other_tags);
		$channel = channel_label(version: $other_version, channels: $channels);
		echo '  ' . str_pad('note', LABEL_WIDTH) . implode('.', $other_version) . ($channel === null ? '' : " [{$channel}]")
			. ' exists only as other variants: ' . implode(', ', array_slice($other_tags, 0, 4)) . "\n";
	}
	if ($prerelease_tag !== null) {
		echo '  ' . str_pad('note', LABEL_WIDTH) . "pre-release {$prerelease_tag} (not a candidate)\n";
	}
}

/**
 * Print the candidates for one GitHub release pin; returns 1 when the lookup failed.
 *
 * @param array{kind: string, ref: string, locations: list<string>} $pin
 */
function report_github_pin(array $pin, int $hold_days): int
{
	[$repository, $pinned_tag] = explode('@', $pin['ref'], 2);
	$releases = github_get_json("repos/{$repository}/releases?per_page=100");
	if ($releases === null) {
		echo "  LOOKUP FAILED — not compared\n";
		return 1;
	}

	$pin_version = array_map('intval', explode('.', ltrim($pinned_tag, 'v')));
	$candidates = [];
	$published = [];
	foreach ($releases as $release) {
		if ($release['draft'] || $release['prerelease'] || preg_match('/^v?(\d+(?:\.\d+)*)$/', $release['tag_name'], $match) !== 1) {
			continue;
		}
		$version = array_map('intval', explode('.', $match[1]));
		if (count($version) === count($pin_version)) {
			$candidates[] = ['name' => $release['tag_name'], 'version' => $version];
			$published[$release['tag_name']] = $release['published_at'];
		}
	}

	$pin_held = print_line(label: 'pin', name: $pinned_tag, release: ['date' => $published[$pinned_tag] ?? null, 'source' => 'released'], hold_days: $hold_days);
	$previous = $pin_held ? previous_candidate(pin_version: $pin_version, candidates: $candidates) : null;
	if ($previous !== null) {
		print_line(label: 'previous', name: $previous['name'], release: ['date' => $published[$previous['name']], 'source' => 'released'], hold_days: $hold_days);
	}

	$lines = series_lines(pin_version: $pin_version, candidates: $candidates);
	print_series_current(pin_version: $pin_version, lines: $lines);
	foreach ($lines as [$label, $candidate]) {
		print_line(label: $label, name: $candidate['name'], release: ['date' => $published[$candidate['name']], 'source' => 'released'], hold_days: $hold_days);
	}
	if (isset(PIN_NOTES[$repository]) && $lines !== []) {
		echo '  ' . str_pad('note', LABEL_WIDTH) . PIN_NOTES[$repository] . "\n";
	}

	return 0;
}

/**
 * Release channels of an official image, keyed by full version (`9.7.2` => ['lts']).
 *
 * @return array<string, list<string>>
 */
function fetch_channels(string $image_name): array
{
	$manifest = http_get_text("https://raw.githubusercontent.com/docker-library/official-images/master/library/{$image_name}");
	if ($manifest === null) {
		return [];
	}

	$channels = [];
	preg_match_all('/^Tags:\s*(.+)$/m', $manifest, $matches);
	foreach ($matches[1] as $tag_line) {
		$tags = array_map('trim', explode(',', $tag_line));
		$parsed = parse_tag($tags[0]);
		$aliases = array_values(array_intersect(CHANNEL_ALIASES, $tags));
		if ($parsed === null || $parsed['prerelease'] || $aliases === []) {
			continue;
		}
		$version = implode('.', $parsed['version']);
		$channels[$version] = array_values(array_unique([...$channels[$version] ?? [], ...$aliases]));
	}

	return $channels;
}

/**
 * Channels whose current version shares this version's series (all but the last part), or null.
 *
 * @param list<int> $version
 * @param array<string, list<string>> $channels
 */
function channel_label(array $version, array $channels): ?string
{
	$series = array_slice($version, 0, max(1, count($version) - 1));
	$labels = [];
	foreach ($channels as $channel_version => $aliases) {
		if (array_slice(array_map('intval', explode('.', (string) $channel_version)), 0, count($series)) === $series) {
			$labels = [...$labels, ...$aliases];
		}
	}

	return $labels === [] ? null : implode(', ', array_unique($labels));
}

/**
 * One line per newer channel version (lts, stable…) that the series lines did not already show.
 *
 * @param list<int> $pin_version
 * @param array<string, list<string>> $channels
 * @param array<string, array{name: string, version: list<int>}> $candidates keyed by version
 * @param list<array{0: string, 1: array{name: string, version: list<int>}}> $shown
 * @return list<array{0: string, 1: array{name: string, version: list<int>}}>
 */
function channel_lines(array $pin_version, array $channels, array $candidates, array $shown): array
{
	$shown_names = array_map(static fn (array $line): string => $line[1]['name'], $shown);
	$lines = [];
	foreach ($channels as $channel_version => $aliases) {
		$candidate = $candidates[(string) $channel_version] ?? null;
		if ($candidate === null || compare_versions($candidate['version'], $pin_version) <= 0 || in_array($candidate['name'], $shown_names, true)) {
			continue;
		}
		$lines[] = [implode('/', $aliases), $candidate];
	}

	return $lines;
}

/**
 * All tag names of a Docker Hub repository in one registry call.
 *
 * @return ?list<string>
 */
function fetch_registry_tags(string $repository): ?array
{
	static $cache = [];
	if (array_key_exists($repository, $cache)) {
		return $cache[$repository];
	}

	$token = http_get_json("https://auth.docker.io/token?service=registry.docker.io&scope=repository:{$repository}:pull");
	$list = $token === null ? null : http_get_json(
		url: "https://registry-1.docker.io/v2/{$repository}/tags/list?n=100000",
		headers: ["Authorization: Bearer {$token['token']}"],
	);

	return $cache[$repository] = $list['tags'] ?? null;
}

/**
 * The tag's last push time on Docker Hub, or null when the lookup failed.
 */
function fetch_hub_push_date(string $repository, string $tag): ?string
{
	$details = http_get_json("https://hub.docker.com/v2/repositories/{$repository}/tags/{$tag}");
	return $details['tag_last_pushed'] ?? null;
}

/**
 * `file:line` locations with repeated file names folded (`compose.yml:156, 184`).
 *
 * @param list<string> $locations
 */
function format_locations(array $locations): string
{
	$lines_by_file = [];
	foreach ($locations as $location) {
		[$file, $line] = explode(':', $location, 2);
		$lines_by_file[$file][] = $line;
	}

	$parts = [];
	foreach ($lines_by_file as $file => $lines) {
		$parts[] = "{$file}:" . implode(', ', $lines);
	}

	return implode('; ', $parts);
}

/**
 * Print one report line; returns true when a confirmed upstream release falls inside the hold window.
 *
 * @param array{date: ?string, source: string} $release
 * @param array<string> $annotation
 */
function print_line(string $label, string $name, array $release, int $hold_days, array $annotation = []): bool
{
	$age_days = $release['date'] === null ? null : intdiv(time() - (int) strtotime($release['date']), 86400);
	$date = $age_days === null ? "{$release['source']} ?" : "{$release['source']} " . substr($release['date'], 0, 10) . " ({$age_days}d)";
	$held = $age_days !== null && $age_days < $hold_days;
	// a push date also moves on a rebuild, so it can only suggest a hold
	$marker = $held ? ($release['source'] === 'released' ? 'HOLD' : 'HOLD?') : '';
	$suffix = $annotation === [] ? '' : '[' . implode('; ', $annotation) . ']';

	echo rtrim('  ' . str_pad($label, LABEL_WIDTH) . str_pad($name, 30) . str_pad($date, 28) . str_pad($marker, 7) . $suffix) . "\n";

	return $held && $release['source'] === 'released';
}

/**
 * Upstream release date of a version, or Docker Hub's last push when upstream has none.
 *
 * @param list<int> $version
 * @param array{product?: string, releases?: string, tag?: string} $upstream
 * @param ?array<string, mixed> $cycle
 * @return array{date: ?string, source: string}
 */
function release_date(string $repository, string $tag, array $version, array $upstream, ?array $cycle): array
{
	$version_name = implode('.', $version);
	// endoflife.date dates only a cycle's latest patch, and can lag behind it
	if ($cycle !== null && ($cycle['latest'] ?? null) === $version_name && is_string($cycle['latestReleaseDate'] ?? null)) {
		return ['date' => $cycle['latestReleaseDate'], 'source' => 'released'];
	}
	if (isset($upstream['releases'], $upstream['tag'])) {
		$release_tag = str_replace('{version}', $version_name, $upstream['tag']);
		$release = github_get_json(path: "repos/{$upstream['releases']}/releases/tags/{$release_tag}", missing_ok: true);
		if (is_string($release['published_at'] ?? null)) {
			return ['date' => $release['published_at'], 'source' => 'released'];
		}
	}

	return ['date' => fetch_hub_push_date(repository: $repository, tag: $tag), 'source' => 'pushed'];
}

/**
 * Release cycles of an endoflife.date product; empty when unavailable.
 *
 * @return list<array<string, mixed>>
 */
function fetch_cycles(string $product): array
{
	$cycles = http_get_json("https://endoflife.date/api/{$product}.json");
	return $cycles === null ? [] : array_values($cycles);
}

/**
 * The cycle a version belongs to (longest matching `cycle` prefix), or null.
 *
 * @param list<int> $version
 * @param list<array<string, mixed>> $cycles
 * @return ?array<string, mixed>
 */
function find_cycle(array $version, array $cycles): ?array
{
	$match = null;
	$match_length = 0;
	foreach ($cycles as $cycle) {
		$cycle_version = array_map('intval', explode('.', (string) $cycle['cycle']));
		$length = count($cycle_version);
		if ($length > $match_length && array_slice($version, 0, $length) === $cycle_version) {
			$match = $cycle;
			$match_length = $length;
		}
	}

	return $match;
}

/**
 * Support window of a cycle (`8.4: active support to 2026-12-31, EOL 2028-12-31`); empty when unknown.
 *
 * @param ?array<string, mixed> $cycle
 */
function format_support(?array $cycle): string
{
	if ($cycle === null) {
		return '';
	}

	$today = gmdate('Y-m-d');
	$parts = [];
	if (($cycle['lts'] ?? false) !== false) {
		$parts[] = 'LTS';
	}
	$support = $cycle['support'] ?? null;
	if (is_string($support)) {
		$parts[] = ($support < $today ? 'active support ENDED ' : 'active support to ') . $support;
	}
	$eol = $cycle['eol'] ?? null;
	if (is_string($eol)) {
		$parts[] = ($eol < $today ? 'EOL PASSED ' : 'EOL ') . $eol;
	}
	if ($eol === false) {
		$parts[] = 'no EOL date yet';
	}

	return $parts === [] ? '' : "{$cycle['cycle']}: " . implode(', ', $parts);
}

/**
 * GET from the GitHub API, authenticated when GITHUB_TOKEN is set.
 */
function github_get_json(string $path, bool $missing_ok = false): ?array
{
	$token = getenv('GITHUB_TOKEN');
	$headers = is_string($token) && $token !== '' ? ["Authorization: Bearer {$token}"] : [];

	return http_get_json(url: "https://api.github.com/{$path}", headers: $headers, missing_ok: $missing_ok);
}

/**
 * GET a JSON document; logs the failure to stderr and returns null on any error (a 404 silently when $missing_ok).
 *
 * @param list<string> $headers
 */
function http_get_json(string $url, array $headers = [], bool $missing_ok = false): ?array
{
	$body = http_get_text(url: $url, headers: $headers, missing_ok: $missing_ok);
	$decoded = $body === null ? null : json_decode($body, true);
	if ($body !== null && !is_array($decoded)) {
		fwrite(STDERR, "lookup failed: {$url} (response is not JSON)\n");
	}

	return is_array($decoded) ? $decoded : null;
}

/**
 * GET a document body; logs the failure to stderr and returns null on any error (a 404 silently when $missing_ok).
 *
 * @param list<string> $headers
 */
function http_get_text(string $url, array $headers = [], bool $missing_ok = false): ?string
{
	$handle = curl_init($url);
	curl_setopt_array($handle, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_TIMEOUT => 30,
		CURLOPT_USERAGENT => 'manager-docker-pin-report',
		CURLOPT_HTTPHEADER => $headers,
	]);
	$body = curl_exec($handle);
	$status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
	$error = curl_error($handle);

	if ($status === 404 && $missing_ok) {
		return null;
	}
	if ($status !== 200 || !is_string($body)) {
		fwrite(STDERR, "lookup failed: {$url} (HTTP {$status}" . ($error === '' ? '' : ", {$error}") . ")\n");
		return null;
	}

	return $body;
}

/**
 * Compare version lists numerically, padding the shorter one with zeros.
 *
 * @param list<int> $left
 * @param list<int> $right
 */
function compare_versions(array $left, array $right): int
{
	$length = max(count($left), count($right));
	return array_pad($left, $length, 0) <=> array_pad($right, $length, 0);
}

/**
 * Highest of several version lists.
 *
 * @param list<list<int>> $versions
 * @return list<int>
 */
function max_version(array $versions): array
{
	usort($versions, 'compare_versions');
	return end($versions) ?: [];
}
