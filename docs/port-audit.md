# Gutenberg 23.9.1 port audit

## Source baseline

This plugin is ported from the official Gutenberg `v23.9.1` tag at commit `c29617a19a0197efdf3f53a820833c2d68c0b405`, the final release containing the experiment before its removal.

The original source audit covered all 40 files in the four `packages/block-library/src/form*` directories, the relevant KSES and script-module-data functions under `lib/experimental`, and all 60 form serialization fixture files. Gutenberg contains no dedicated form-block PHP unit test or form-block E2E spec in that tag.

## Literal source files

Of the 38 retained upstream source files, 33 are byte-identical to Gutenberg `v23.9.1`:

- all edit, save, icon, utility, and variation JavaScript;
- all four `init.js` files;
- all component Sass files;
- all four generated block README files;
- `form/view.js`;
- all 28 retained canonical serialization fixture files.

The two retained block registration files, `form/index.js` and `form-input/index.js`, differ only by removal of their deprecation imports and settings. The three PHP block files are retained in their original block directories and preserve their original function bodies and hooks. The KSES and script-module-data functions are likewise retained as discrete ports of their Gutenberg counterparts.

The two historical JavaScript deprecation implementations (`form/deprecated.js` and `form-input/deprecated.js`) and their 32 migration-only fixture files were subsequently removed. The remaining 28 upstream fixture files are byte-identical. This standalone plugin now supports one canonical block schema and does not migrate markup saved by earlier experimental versions.

## Standalone-only changes

The maintained differences from Gutenberg are limited to:

1. PHP files use the `WPFormsBlocks` namespace to avoid global function collisions.
2. Gutenberg experiment-flag guards are removed; activating the plugin is the opt-in mechanism.
3. PHP metadata paths point at each standalone build directory.
4. PHP callback strings include the namespace.
5. The standalone asset loader registers and enqueues the consolidated editor bundle, the original Gutenberg front-end style handles, and the original `@wordpress/block-library/form/view` script-module ID.
6. `src/index.js` imports the four original `init.js` entry points into one plugin editor bundle.
7. Plugin bootstrap, build configuration, package metadata, and consolidated Sass entry points are added around the port.
8. A minimal PHP registration file is added for `core/form-submit-button`, which Gutenberg registered through its shared block-library loader and therefore did not give a per-block PHP file.
9. WordPress 7.0 is the minimum version because the unchanged Gutenberg `view.js` relies on WordPress's native script-module data API.
10. Historical deprecation registrations and migration-only fixtures are omitted so the standalone plugin exposes only its canonical schema.

All four `block.json` files are byte-identical. The historical `core/form`, `core/form-input`, `core/form-submit-button`, and `core/form-submission-notification` names, `__experimental` metadata, text domain, variation labels, saved markup, render behavior, email transport, privacy processing, notification filtering, KSES allowlist, AJAX action, view-module data shape, and front-end JavaScript behavior remain unchanged.

## Tests

`tests/fixtures/blocks/` contains all seven canonical current-format upstream cases with four files per case. The JavaScript suite checks parser output, block registration, all variations, exact reserialization, and the unchanged view module's original data and submission contract.

The unit suite additionally covers the metadata and templates for all four blocks, every variation and activation branch, editor rendering and every settings callback, current save branches, shared hooks, and all front-end response outcomes. Coverage gates require at least 95% statements/lines and 90% branches/functions across the executable ported JavaScript.

The standalone WordPress integration runner uses a disposable SQLite database and checks asset/module registration plus the original PHP behavior for rendering, successful and failed email handling, comments, custom actions, visibility permissions, notifications, privacy requests, and KSES.

The Playwright suite boots a separate disposable WordPress/SQLite site and verifies seven complete browser workflows: editor insertion and round-trip persistence, all field types and successful email submission, failed AJAX submission, a custom method/action submission, logged-in/logged-out visibility, both privacy-request types, and comment submission. Neither integration runner touches the development site's database.
