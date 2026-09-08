# SendGrid mailing support — decisions

Operator decisions with rationale. Entries are dated and append-only: a
ruling that later changed keeps its original entry and gains a superseding
one, so the reasoning that held at the time stays readable.

## Transport

- **2026-09-08: curl, not the official `sendgrid/sendgrid` Composer SDK.**
  The framework does have precedent for an optional heavy vendor SDK
  (`aws/aws-sdk-php`, `require-dev`+`suggest`), but that dependency is
  justified by S3/CloudFront/Bedrock's genuinely complex operations
  (request signing, multi-step, streaming). SendGrid's Mail Send API is one
  endpoint, one bearer header, one JSON body — no other mail transport in
  this framework carries a vendor SDK either. See
  `sample/docs/development/libraries.md` for the general decision this
  follows.

## Config shape

- **2026-09-08: SendGrid-account data lives in `lib_sendgrid.php`, not
  `lib_mailing.php`.** `lib_mailing.php`'s per-profile `protocol` key is
  only the discriminator that picks `sendgrid_lib` as transport; the API
  key, sandbox flag, and template-id map are scoped to "which SendGrid
  account," not "which mailing persona." This keeps the two profile systems
  — mailing personas and SendGrid accounts — from overlapping or drifting,
  and keeps `MGR_Sendgrid_lib` independently usable outside the mailing
  façade.
- **2026-09-08: `MGR_Mailing_lib::$templates` stays a generic name, not
  `sendgrid_templates`.** It holds logical template names only — a
  provider-agnostic contract. The SendGrid-specific id translation is
  already isolated one layer down, in `MGR_Sendgrid_lib`'s own config and
  `get_template_id()`. A future second provider would resolve the same
  logical names through its own map, with no rename needed here. What *is*
  SendGrid-specific today, and would need generalizing for a real second
  provider, is `send_template()`'s hard `protocol === 'sendgrid'` gate and
  its direct `sendgrid_lib` call — left as-is, not abstracted speculatively.
- **2026-09-08: `MGR_Mailing_lib` calls `MGR_Sendgrid_lib::build_recipients()`
  and `::build_from()` directly rather than reimplementing them.** An
  earlier version duplicated both — including `build_from()`'s "only add
  the `name` key if non-empty" rule — inside `MGR_Mailing_lib`. Both methods
  are now `public` on `MGR_Sendgrid_lib`; `MGR_Mailing_lib` is reduced to
  `current_from_override()`, which only decides *whether* the calling
  profile has its own sender, not how to shape or fall back on one.

## Customization placement

- **2026-09-08: a `mailing_lib` customization is module-scoped
  (`application/modules/{module}/libraries/`), not top-level.** This is
  specific to `mailing_lib`, not a general rule for library customizations
  — top-level `application/libraries/` stays the default for those.
  `mailing_lib` is the case where different modules genuinely need their
  own templates and mailing profile, matching the framework's only other
  shipped example, `Frontend_mailing`.
- **2026-09-08: a file named to override the unprefixed alias via CI3's
  `subclass_prefix` convention (e.g. `MY_Mailing_lib.php`) does not work
  for a package-provided library.** `MX_Loader::library()` resolves a
  library via `Modules::find()` against module/package paths before CI3's
  core loader — the only one honoring `subclass_prefix` — ever runs. A
  customization needs a distinct class name, loaded by that name.
