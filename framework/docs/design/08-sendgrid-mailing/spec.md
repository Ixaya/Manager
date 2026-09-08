# SendGrid mailing support — what was built and why

`MGR_Mailing_lib` could previously only send through CI3's core `Email`
library, whose transport is hardcoded to `mail`/`sendmail`/`smtp` — there
was no HTTP-API transport and no support for a provider's server-side
templates. This adds SendGrid as a selectable transport, chosen the same
way SMTP already is (a mailing profile's `protocol` key), plus SendGrid's
dynamic and legacy templates.

## What shipped

- `MGR_Sendgrid_lib` (`system/libraries/`) — a plain-curl client for
  SendGrid's v3 Mail Send API, config-mode-A like `MGR_Amazon_aws_lib`:
  `send()`, `send_template()`, `get_template_id()`, `set_config_key()`,
  `build_recipients()`, `build_from()`. No vendor SDK dependency (see
  `decisions.md`). The unprefixed alias `Sendgrid_lib` and config
  `lib_sendgrid.php` complete the framework-library 3-file shape.
- `MGR_Mailing_lib` composes it rather than absorbing SendGrid logic
  directly: `send_email()` still renders the CI view exactly as before, but
  delegates to `sendgrid_lib` instead of CI3's `email` library when the
  active profile's `protocol` is `sendgrid`. A new `send_template()` method
  bypasses view rendering entirely — SendGrid's server-side templating has
  no CI-view equivalent — and requires the `sendgrid` protocol.
- A `protected array $templates` contract on `MGR_Mailing_lib`: a subclass
  declares the fixed, code-owned set of logical template names it uses; the
  actual SendGrid template id for each name lives entirely in
  `lib_sendgrid.php`'s env-driven config, since ids are account/environment
  dependent and must never be hardcoded.
- `set_config_key()` added to `MGR_Mailing_lib`, closing a parity gap with
  `MGR_Amazon_aws_lib`, which already supports runtime profile switching.
- A worked example, `Auth_mailing`
  (`sample/application/modules/auth/libraries/`), demonstrating the
  subclass pattern with a real `$templates` contract.
