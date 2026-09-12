# Upgrading — unreleased

Changes between 2.x releases that alter behavior a project already depends on.
Everything else in a minor release is additive.

### A project's own config/library/model/helper/view/language file now actually overrides the package's

Previously, a project file at the documented override path — same
filename under `application/{config,libraries,models,helpers,views}/` or
`application/language/<idiom>/` — was **silently ignored** for every one
of these six resource types: the package's own copy always won, no error,
no warning. Root cause and full route table:
`framework/docs/architecture/framework-wiring.md`, "Package resource
override precedence"; decision record:
`framework/docs/design/09-package-resource-overrides/`.

This is now fixed: a project's override wins, in the "it now does what
you meant" direction. Concretely:

- **Config, helpers, language** are per-item: a project file declares only
  the keys/functions/lang-keys it changes, and the package's remaining
  items still load. A whole-file copy sitting in your project today keeps
  working exactly as before (every key/function it declares still wins) —
  nothing to change there.
- **Libraries and models** are subclass-based: `application/{libraries,models}/<Name>.php
  extends MGR_<Name>` under a name of your own choosing. If you don't
  already have such a file, nothing changes for you.
- **Views** are whole-file replacement, same as before.

**The new risk: accidental collisions.** The package loads several of its
own resources by their bare (unprefixed) name —
`$this->load->model('domain')`, `->model('theme')`, `->model('attachment')`,
`->model('manager_option')`, `->model('rest_key_model')`,
`->model('rest_user')`, `->model('rest_user_group')`,
`->model('ion_auth_model')`, `->library('format')`, `->library('ion_auth')`,
`->library('seeder')`. If your project already has its own model or
library with one of these exact names — not intended as an override — it
now silently answers the framework's own internal calls instead, and any
failure surfaces from inside package code. **Audit your
`application/{models,libraries}/` for these eleven names before
upgrading**; renaming the package's own resources to avoid this was
considered and rejected (existing projects have long depended on the
unprefixed names).
