# Targets

A target tells the lab what it is testing: a theme or a plugin, how its package is built, which pages to compare,
and which checks are specific to it. The lab itself (`bin/`, `php/`, `tests/`) knows nothing about any one project.

```
targets/<name>/
  target.json     what, where, how: see targets/_template/target.json (every key is explained there)
  setup.php       lab_target_setup(): menus, widgets, options that make a state repeatable
  checks/*.php    in-WordPress checks (listed in target.json, php.checks)
  specs/*.spec.js Playwright specs, run in addition to tests/browser/generic/
```

Add a target in five steps:

1. `cp -r targets/_template targets/<name>`, then edit `target.json` (slug, repo, zip name, version places, forbidden content).
2. `bin/lab build <name> --src /path/to/checkout --label local` and `bin/lab test static <name> --label local` until it is green.
3. `bin/lab use <name> local && bin/lab seed && bin/lab state <name> ltr`, then open `bin/lab url` in a browser.
4. Write `setup.php` so the state looks right, then add checks and specs for what is specific to the project.
5. `bin/lab test php <name> --update` once to record the settings snapshot (themes with a Customizer), review it, commit it.

Targets for plugins use `"type": "plugin"` and `"main": "<slug>/<slug>.php"`; `bin/lab use` then links the build into
`wp-content/plugins/` and activates it.
