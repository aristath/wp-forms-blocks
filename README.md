# WP Forms Blocks

WP Forms Blocks is a standalone continuation of the experimental form blocks that shipped behind a Gutenberg experiment flag from Gutenberg 16.9 through 23.9.

It provides:

- Form
- Input Field
- Form Submit Button
- Form Submission Notification
- Contact Form, Comment Form, and Privacy Request Form variations
- Email-to-site-administrator and custom-URL submission methods

A newly inserted Form uses the default Contact Form variation. It starts with the original success and error notifications, Name, Email, and Comment fields, and a Submit button. Comment Form and Privacy Request Form are available as additional variations.

The standalone blocks use the plugin-owned `formblox` namespace: `formblox/form`, `formblox/form-input`, `formblox/form-submit-button`, and `formblox/form-submission-notification`. The former experimental `core/form*` names are not registered because the `core` namespace is reserved for WordPress. As requested, the plugin contains no migration or compatibility layer for old experimental block names or schemas.

## Development

```sh
pnpm install --ignore-scripts
composer install
npm run build
npm run lint
npm run test:unit
npm run test:php
npm run test:coverage
npm run test:wordpress
npm run test:e2e
# Or run every test suite:
npm test
```

`lint` runs WordPress Prettier checks, ESLint, Stylelint, Markdownlint, package metadata validation, translation-catalog validation, Lefthook configuration validation, PHP syntax checks, PHPCS with WPCS and PHPCompatibilityWP, and PHPStan. `npm run format` applies the corresponding JavaScript and PHP formatters. Lefthook gates every commit on the complete lint, reproducible-build, Plugin Check, JavaScript coverage, PHPUnit, WordPress integration, and Playwright suites. See [CONTRIBUTING.md](CONTRIBUTING.md) for individual commands and the pre-commit/CI contract.

`test:unit` covers block registration, metadata, templates, every variation, editor controls and callbacks, current save output, every canonical current-format Gutenberg serialization fixture, front-end submission outcomes, and shared hooks. `test:php` covers isolated PHP callbacks and hook registration. `test:coverage` enforces at least 95% statement/line coverage and 90% branch/function coverage across the executable ported JavaScript.

`test:wordpress` creates a disposable SQLite database alongside the local WordPress checkout and covers asset and module registration, rendering branches, successful and failed email handling, custom actions, comments, visibility permissions, notification filtering, privacy requests, and KSES. It never uses the development site's database.

`test:e2e` launches another disposable local WordPress/SQLite instance on an available port and drives Chromium through the real editor and front end. It verifies the default Contact Form variation and its complete template, block insertion and persistence, every field type, successful and failed submissions, custom browser submissions, logged-in and logged-out visibility, both privacy-request workflows, and comment submission. The runner requires the plugin to be located inside a local WordPress checkout, plus PHP, WP-CLI, and Playwright's Chromium browser (`pnpm exec playwright install chromium`).

`lint:plugin` builds the release archive, downloads the official WordPress Plugin Check plugin, and checks the extracted distributable in a disposable WordPress/SQLite installation. It does not scan development-only files, touch the development database, or install Plugin Check permanently.

Built assets are committed so a checkout of a release can be installed directly as a WordPress plugin. See [docs/port-audit.md](docs/port-audit.md) for the file-by-file upstream audit and intentional standalone changes.

## Email submissions

Email forms always send submissions to the Administration Email Address configured in WordPress. Visitors cannot select or override the recipient. Individual forms can still use different fields and layouts; they share the site's administrative destination.

## History

The original blocks were proposed by [Aristeidis Stathopoulos](https://github.com/aristath) in [Gutenberg issue #44186](https://github.com/WordPress/gutenberg/issues/44186) and introduced by [PR #44214](https://github.com/WordPress/gutenberg/pull/44214). This repository began with the implementation from Gutenberg 23.9.1, immediately before its removal from Gutenberg in [PR #82451](https://github.com/WordPress/gutenberg/pull/82451).

## License

GPL-2.0-or-later.
