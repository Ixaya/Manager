# Library dependencies — vendor SDK or hand-rolled

> Scope: deciding whether a new library that talks to an external service
> needs a vendor SDK as a dependency, or should be hand-rolled (curl, a
> native protocol client) instead. For the mechanics of building the
> library itself — naming, config modes, the framework's 3-file shape —
> see the `mgr-helpers-libraries` skill.

## The decision

A vendor SDK earns its place as a dependency only when the external API is
genuinely complex to implement correctly by hand: request signing, a
multi-step protocol, streaming responses, or a large surface where
hand-rolling would mean reimplementing meaningful parts of the SDK anyway.
The framework's own AWS integration (`aws/aws-sdk-php`) is the concrete
example — it backs S3 uploads, CloudFront invalidation, and Bedrock
inference, none of which has a reasonable curl equivalent (request signing
alone is enough to justify it).

A single external endpoint behind a bearer token and a JSON body does not
clear that bar, however official or well-maintained the vendor's own SDK
is. A transactional-email send — one POST, one JSON payload — is exactly
this case: hand-rolled with curl is the right call, matching every other
mail transport this framework ships (SMTP has no vendor library either).
The SDK would not buy anything beyond what a few dozen lines of curl
already state explicitly, and every added dependency is one more thing to
version, patch, and audit.
