# SendGrid mailing support — final state

## What the code does now

**`MGR_Sendgrid_lib`** (`system/libraries/`, alias `Sendgrid_lib`, config
`lib_sendgrid.php`) is a plain-curl client for SendGrid's v3 Mail Send API.
`send(array $to, string $subject, string $html_body, ?array $from = null,
?array $bcc = null, array $attachments = [])` and `send_template(array
$to, string $template_id, array $template_data = [], ?array $from = null,
?array $bcc = null, array $attachments = [])` both return `{success,
status_code, headers, body, message_id}` — `message_id` is a parsed,
case-insensitive `X-Message-Id` header lookup, an extraction-only
extension point (no persistence built; a future need for send-status
lookups or traceability would build on this). `send_template()` branches
on the SendGrid `d-` template-id prefix, setting `dynamic_template_data`
(cast to `(object)`, since an empty PHP array would otherwise serialize as
a JSON array where SendGrid requires an object) for dynamic templates and
`substitutions` for legacy ones. `mail_settings.sandbox_mode.enable` is set
from the active profile's `sandbox_mode` config, letting a non-prod profile
validate sends without delivering them. Success is `status_code` in
`{200, 202}` — 200 is SendGrid's sandbox-validation response, 202 its real
accepted-send response.

**`MGR_Mailing_lib`** composes `sendgrid_lib` rather than absorbing its
logic. `send_email()` renders the CI view exactly as it always did; when
the active profile's `protocol` is `sendgrid`, it delegates the rendered
HTML to `sendgrid_lib->send()` instead of CI3's `email` library.
`send_template(string $email, string $template_key, array $data = [])` is
SendGrid-only — it requires the active profile's `protocol` to be
`sendgrid` and bypasses view rendering entirely. Fail-fast validation, before
any network call: `$template_key` must be declared in the subclass's
`protected array $templates` contract, and
`sendgrid_lib->get_template_id($template_key)` must resolve to a real id —
either failure logs and returns `false`. `current_from_override()` supplies
only the calling profile's sender override, if it has one; the actual
shape and fallback-to-account-default logic live entirely in
`MGR_Sendgrid_lib::build_from()`, which `MGR_Mailing_lib` calls directly
rather than reimplementing.

**Live-account behavior** (see `review.md`): SendGrid's non-2xx error body
is consistently `{"errors":[{"message", "field", "help"}]}`; a `202`/`200`
response from SendGrid means the request was accepted, not that the
recipient's mail server actually delivered it — a receiving server can
still reject at the final hop for reasons outside this library's control
(commonly, missing sender-domain authentication). `message_id` is the
handle for looking up what actually happened to a given send, in
SendGrid's own Activity Feed — a separate API surface, not implemented
here.

## What a second provider would need to generalize

`send_template()`'s hard `protocol === 'sendgrid'` gate and its direct
`$CI->sendgrid_lib` call are the SendGrid-specific parts of
`MGR_Mailing_lib` today. `$templates` and `current_from_override()` are
already provider-agnostic (see `decisions.md`) and would not need to
change.
