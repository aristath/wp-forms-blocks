# WP Forms Blocks

WP Forms Blocks is a standalone continuation of the experimental form blocks that shipped behind a Gutenberg experiment flag from Gutenberg 16.9 through 23.9.

It provides:

- Form
- Input Field
- Form Submit Button
- Form Submission Notification
- Contact Form, Comment Form, and Privacy Request Form variations
- Email and custom-URL submission methods

A newly inserted Form uses the default Contact Form variation. It starts with the original success and error notifications, Name, Email, and Comment fields, and a Submit button. Comment Form and Privacy Request Form are available as additional variations.

The plugin intentionally retains the historical `core/form`, `core/form-input`, `core/form-submit-button`, and `core/form-submission-notification` block names. Content saved with the final Gutenberg experiment schema therefore remains compatible. Earlier experimental schemas that depended on Gutenberg's migration handlers are intentionally unsupported.

## Development

```sh
pnpm install --ignore-scripts
pnpm run build
pnpm run lint:js
pnpm run lint:css
pnpm run lint:php
pnpm run test:unit
pnpm run test:coverage
pnpm run test:wordpress
pnpm run test:e2e
# Or run every test suite:
pnpm test
```

`test:unit` covers block registration, metadata, templates, every variation, editor controls and callbacks, current save output, every canonical current-format Gutenberg serialization fixture, front-end submission outcomes, and shared hooks. `test:coverage` enforces at least 95% statement/line coverage and 90% branch/function coverage across the executable ported JavaScript.

`test:wordpress` creates a disposable SQLite database alongside the local WordPress checkout and covers asset and module registration, rendering branches, successful and failed email handling, custom actions, comments, visibility permissions, notification filtering, privacy requests, and KSES. It never uses the development site's database.

`test:e2e` launches another disposable local WordPress/SQLite instance on an available port and drives Chromium through the real editor and front end. It verifies the default Contact Form variation and its complete template, block insertion and persistence, every field type, successful and failed submissions, custom browser submissions, logged-in and logged-out visibility, both privacy-request workflows, and comment submission. The runner requires the plugin to be located inside a local WordPress checkout, plus PHP, WP-CLI, and Playwright's Chromium browser (`pnpm exec playwright install chromium`).

Built assets are committed so a checkout of a release can be installed directly as a WordPress plugin. See [docs/port-audit.md](docs/port-audit.md) for the file-by-file upstream audit and intentional standalone changes.

## Security note

This is a faithful port of experimental Gutenberg code, including its original email and privacy-request behavior. In particular, the email AJAX handler accepts the form's `mailto:` destination from the submitted request. That behavior should be reviewed before using the block on an untrusted public site.

## History

The original blocks were proposed by [Aristeidis Stathopoulos](https://github.com/aristath) in [Gutenberg issue #44186](https://github.com/WordPress/gutenberg/issues/44186) and introduced by [PR #44214](https://github.com/WordPress/gutenberg/pull/44214). This repository began with the implementation from Gutenberg 23.9.1, immediately before its removal from Gutenberg in [PR #82451](https://github.com/WordPress/gutenberg/pull/82451).

## License

GPL-2.0-or-later.
