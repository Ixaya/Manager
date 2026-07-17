# Security Policy

## Supported Versions

| Version | Supported |
| ------- | --------- |
| 2.x     | Yes — security fixes land here |
| 1.x     | No — end of life; upgrade via `MIGRATION.md` |

The framework requires PHP 8.2 or newer; security fixes are only validated
against supported PHP versions.

## Reporting a Vulnerability

Please do NOT open a public issue for security problems.

Report privately through GitHub's vulnerability reporting on this
repository (Security tab, "Report a vulnerability"). If that is not
available to you, email the maintainers listed in `composer.json`.

What to include: an affected version, a description of the issue and its
impact, and reproduction steps or a proof of concept if you have one.

What to expect: an acknowledgment within a few business days; if the
report is accepted, a fix is developed privately and released with credit
to the reporter (unless you prefer otherwise). If declined, you get the
reasoning.

Auth-related invariants worth reading before reporting (what the framework
deliberately does and why): the `ixaya-auth` skill in `system/skills/`.
