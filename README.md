## Developer Guidelines

### For dev. environment

Run the following command for development environment.

```
composer update
npm install
npm run build
```

### For production environment
Run the following command for production environment to ignore the dev dependencies.

```
composer update --no-dev
npm install
npm run build
```

### Build Release
Set execution permission to the script file by `chmod +x bin/build.sh` command. Now, Run the following bash script.
```
bin/build.sh
```

### Plugin Check (WordPress.org readiness)

A Claude Code agent at `.claude/agents/plugin-check.md` runs the official [Plugin Check](https://wordpress.org/plugins/plugin-check/) plugin against this checkout and triages the output into real problems, already-justified findings and dev-only noise (files that `.distignore` strips from the release ZIP).

From a Claude Code session opened in this directory:

```
run plugin-check
```

or `@plugin-check` followed by what you want (e.g. `security checks only`, `release build`). The agent:

- installs and activates `plugin-check` from WordPress.org if it is not already active on the site,
- derives the WordPress root from the checkout location, so it works wherever the site lives,
- can build the release ZIP first (`bin/build.sh`) and check exactly what ships,
- ends with a **Ready / Not ready for WordPress.org** verdict and never edits plugin files.

To run the check by hand instead: `wp plugin check storesuite` from any directory inside the WordPress install (`wp plugin list-checks` lists the available checks).
