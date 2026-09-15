# WP Forms Blocks

WP Forms Blocks is a standalone continuation of the experimental form blocks that shipped behind a Gutenberg experiment flag from Gutenberg 16.9 through 23.9.

It provides:

- Form
- Input Field
- Form Submit Button
- Form Submission Notification
- Comment form and privacy request form variations
- Email and custom-URL submission methods

The plugin intentionally retains the historical `core/form`, `core/form-input`, `core/form-submit-button`, and `core/form-submission-notification` block names. Content created with the Gutenberg experiment therefore remains compatible.

## Development

```sh
pnpm install --ignore-scripts
pnpm run build
pnpm run lint:js
pnpm run lint:css
pnpm run test:unit
pnpm run test:wordpress
```

`test:unit` runs Gutenberg's complete form-block serialization fixture corpus and verifies the original view-module data contract. `test:wordpress` creates a disposable SQLite database alongside the local WordPress checkout and covers block registration, rendering, email, custom actions, comments, privacy requests, and KSES. It never uses the development site's database.

Built assets are committed so a checkout of a release can be installed directly as a WordPress plugin. See [docs/port-audit.md](docs/port-audit.md) for the file-by-file upstream audit and intentional standalone changes.

## Experimental status

This is a faithful port of experimental Gutenberg code, including its original email and privacy-request behavior. In particular, the email AJAX handler accepts the form's `mailto:` destination from the submitted request. That behavior should be reviewed before using the block on an untrusted public site.

## History

The original blocks were proposed by [Aristeidis Stathopoulos](https://github.com/aristath) in [Gutenberg issue #44186](https://github.com/WordPress/gutenberg/issues/44186) and introduced by [PR #44214](https://github.com/WordPress/gutenberg/pull/44214). This repository began with the implementation from Gutenberg 23.9.1, immediately before its removal from Gutenberg in [PR #82451](https://github.com/WordPress/gutenberg/pull/82451).

## License

GPL-2.0-or-later.
