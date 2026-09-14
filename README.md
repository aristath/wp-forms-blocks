# WP Forms Blocks

WP Forms Blocks is a standalone continuation of the experimental form blocks that shipped behind a Gutenberg experiment flag from Gutenberg 16.9 through 23.9.

It provides:

- Form
- Input Field
- Form Submit Button
- Form Submission Notification
- Comment form and privacy request form variations
- Email and custom-URL submission methods

The plugin intentionally retains the historical `core/form`, `core/form-input`, `core/form-submit-button`, and `core/form-submission-notification` block names. Content created with the Gutenberg experiment therefore remains compatible. Registration is skipped if another provider has already registered one of those names.

## Development

```sh
pnpm install --ignore-scripts
pnpm run build
pnpm run lint:js
pnpm run lint:css
pnpm run test:php
pnpm run test:unit
pnpm run test:wordpress
```

`test:unit` runs the browser submission tests and Gutenberg's complete form-block serialization fixture corpus. `test:wordpress` creates a disposable SQLite database alongside the local WordPress checkout and covers block registration, rendering, email, custom actions, comments, privacy requests, KSES, and rejected submissions. It never uses the development site's database.

Built assets are committed so a checkout of a release can be installed directly as a WordPress plugin. See [docs/port-audit.md](docs/port-audit.md) for the file-by-file upstream audit and intentional standalone changes.

## Security

Email recipient data is signed on the server and verified during submission. The public AJAX endpoint does not trust a client-provided destination. Email and privacy forms also contain a honeypot field; WordPress nonces protect their submission handlers.

## History

The original blocks were proposed by [Aristeidis Stathopoulos](https://github.com/aristath) in [Gutenberg issue #44186](https://github.com/WordPress/gutenberg/issues/44186) and introduced by [PR #44214](https://github.com/WordPress/gutenberg/pull/44214). This repository began with the implementation from Gutenberg 23.9.1, immediately before its removal from Gutenberg in [PR #82451](https://github.com/WordPress/gutenberg/pull/82451).

## License

GPL-2.0-or-later.
