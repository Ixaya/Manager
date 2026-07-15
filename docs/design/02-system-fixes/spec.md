# System fixes (2026-07) — scope

Companion initiative to `01-auth-hardening`: 18 general framework/system
findings (non-auth), collected while authoring the `ixaya-*` agent skills
and from the docs/skills standardization work (2026-07-09). Ran in the
(gitignored, since deleted) `docs/workspace/04-system-migration/` section.

## What was in scope

- Dead/broken code: numeric-ID search dead branch (admin User model),
  `resolve_theme()` (injectable interpolation + wrong signature + wrong
  access style), wrong-property model guard in `MGR_Rest_Controller`.
- Cross-engine SQL violations (`!sync_enabled`, `SET SESSION time_zone`)
  vs the `MgrDriver` match pattern.
- Convention debt: legacy `CI_Migration` scaffold in `Tools.php`, sample API
  controller conventions, copy-paste cache log labels, disagreeing cache TTL
  defaults, loose comparisons in `MGR/Model.php`, untyped legacy properties,
  Spanish/typo strings, cemented typos (`MGR_Bootsrap`, `*_extention`
  helpers), style pass on the upload/attachment libraries, env sample sync.
- Two deliberate non-goals kept as open items: the `__get` magic proxy
  removal (deferred indefinitely) and composer post-install symlinks
  (optional nice-to-have).

## Where the knowledge lives now

- Conventions: the `ixaya-*` skills (migrations, models, rest-controller,
  helpers-libraries, code-style) — updated in the same passes.
- Decisions with rationale: `decisions.md` here.
- Validation and live-test record: `review.md` here.
- Remaining open items: `handoff.md` here.
