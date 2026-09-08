# SendGrid mailing support — validation record

## SDK-source cross-check

The initial curl implementation's request-building assumptions were checked
against the official `sendgrid/sendgrid` PHP SDK's own source (used as
ground truth for the real API contract, independent of whether the SDK
ships at runtime) before any live-account testing. Confirmed structurally
correct: the endpoint and bearer-auth header; the
`personalizations`/`from`/`content`/`template_id` body shape;
`dynamic_template_data` nesting per-personalization, never top-level; the
attachment shape; `text/plain`-before-`text/html` content ordering; and the
`mail_settings.sandbox_mode.enable` path. One gap was found and fixed:
dynamic versus legacy templates need different personalization keys
(`dynamic_template_data` vs. `substitutions`), branched on the SendGrid
`d-` template-id prefix. Two points could not be settled from SDK source
alone — the SDK never asserts a success status code or parses an error
body — and were confirmed only against a live account, below.

## Live-account findings

- **Error-body shape confirmed**: `{"errors":[{"message", "field",
  "help"}]}`, observed identically across five independent real responses
  (a wrong-HTTP-method request, an invalid API key, an insufficiently
  scoped key, and an invalid template id), with `help` populated on at
  least one of them — a real documentation link, not always `null`.
- **Success status code is not `202` alone.** A sandboxed
  (`sandbox_mode=true`) send returns `200`; a real accepted send returns
  `202`. The initial implementation checked `=== 202` only, which logged a
  genuinely successful sandboxed send as a failure — fixed to accept both.
- **`X-Message-Id` is present** on every successful response observed,
  sandboxed and real, a different value each time — confirmed live since
  the official SDK never references this header anywhere in its own
  source.
- **A real encoding bug**: `send_template()`'s default (empty) template
  data serialized as a JSON array (`json_encode([]) === '[]'`), which
  SendGrid's API rejects — it requires a JSON object even when empty.
  Fixed with an `(object)` cast; reconfirmed against the live account.
- **A real send accepted by SendGrid still bounced** at the recipient's own
  mail server, independent of anything this library does — the receiving
  server's own policy (most likely missing sender-domain authentication on
  the SendGrid account) rejected it after SendGrid had already returned
  `202` and a real `message_id`. Recorded here as a reminder that a `202`
  means "SendGrid accepted the request," not "the recipient received the
  email."
