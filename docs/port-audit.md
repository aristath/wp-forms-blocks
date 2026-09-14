# Gutenberg 23.9.1 port audit

## Source baseline

This plugin was audited against the official Gutenberg `v23.9.1` tag at commit `c29617a19a0197efdf3f53a820833c2d68c0b405`, the final release containing the experiment before its removal.

The audit searched the full tag for the four block names, `gutenberg-form-blocks`, the PHP render callbacks, and the AJAX action. The implementation surface consists of:

- 40 files in `packages/block-library/src/form*`
- 3 server-rendering files among those 40
- `lib/experimental/kses-allowed-html.php`
- experiment-only registration and editor-setting gates
- 60 shared serialization fixture files under `test/integration/fixtures/blocks`

No dedicated form-block PHP unit test or form-block E2E spec exists in the tag. The 60 shared serialization files are the entire upstream form-specific test corpus.

## File mapping

| Gutenberg source | Standalone destination | Result |
| --- | --- | --- |
| `packages/block-library/src/form/*.{js,scss}` | `src/form/` | Ported; `view.js` uses the standalone secure endpoint described below. |
| `packages/block-library/src/form-input/*.{js,scss}` | `src/form-input/` | Ported. |
| `packages/block-library/src/form-submit-button/*.{js,scss}` | `src/form-submit-button/` | Ported. |
| `packages/block-library/src/form-submission-notification/*.{js,scss}` | `src/form-submission-notification/` | Ported. |
| Four per-block `init.js` files | `src/index.js` | Consolidated into one plugin editor entry; all four original `init()` functions are called. |
| Three per-block `index.php` files | `includes/blocks.php`, `includes/submissions.php` | Ported into prefixed plugin functions. The submit-button block had no upstream PHP file. |
| `lib/experimental/kses-allowed-html.php` | `includes/kses.php` | Ported and expanded for every attribute emitted by the saved markup. |
| Upstream block-style handles | `src/editor.scss`, `src/frontend.scss`, `src/style.js` | Consolidated into plugin editor and front-end bundles. |
| Gutenberg script-module registration/data | `includes/assets.php`, block metadata | Replaced with plugin-owned editor, style, and view-module handles. |
| `test/integration/fixtures/blocks/core__form*` | `tests/fixtures/blocks/` | All 60 files copied byte-for-byte. |

All ordinary edit, save, icon, utility, deprecation, and variation modules are unchanged from the baseline apart from removing the word “Experimental” from two variation labels. Sass differences are formatting-only. README changes identify the standalone continuation.

## Required standalone metadata changes

The four block names remain `core/form`, `core/form-input`, `core/form-submit-button`, and `core/form-submission-notification`, preserving existing content and fixture compatibility.

The following metadata differences are deliberate:

- remove the Gutenberg-only `__experimental` flag;
- change the text domain from `default` to `wp-forms-blocks`;
- name plugin-owned editor, front-end style, and view-module handles;
- replace individual Gutenberg style handles with the consolidated plugin stylesheet.

The Gutenberg experiment flag, editor setting, and experiment UI were not copied. Plugin activation is the explicit opt-in. Registration also skips a historical block name if another provider has already claimed it, preventing fatal duplicate registration.

## Submission compatibility and hardening

The upstream email adapter trusted a browser-supplied `formAction` as the recipient. Copying that endpoint unchanged would expose a public arbitrary mail relay. The standalone adapter preserves the same editor workflow and success/error notification behavior while changing the transport contract:

- recipients are normalized on the server, signed with the WordPress auth salt, and verified on submission;
- a WordPress nonce and honeypot protect public email submissions;
- native `FormData` preserves repeated checkbox and other multi-value fields;
- the originating page URL is retained in the email;
- submitted values become sanitized plain text;
- the handler redirects to the success notification only for both an HTTP success and a WordPress JSON success;
- submit buttons are disabled in flight and restored afterward.

Custom URL, comment, and privacy forms continue to submit normally. `{SITE_URL}` and `{ADMIN_URL}` expansion, GET/POST selection, comment post IDs, visibility permissions, privacy export/removal requests, and notification filters are retained. Privacy requests additionally receive nonce and honeypot checks, and mail failures select the error notification.

## Verification coverage

The repository's checks cover:

- all four block registrations and all 12 variations;
- all 15 upstream serialization cases, including every deprecated form, text, textarea, checkbox, and radio shape;
- parser output, migrated block structures, and exact serialized HTML;
- browser success, application error, HTTP error, network error, repeated values, navigation, and custom-form bypass;
- WordPress asset metadata, dynamic rendering, URL placeholders, request methods, extension filters, comments, both visibility states, notifications, and KSES;
- signed-recipient round trips and tampering, sanitization, source URLs, AJAX success, honeypot rejection, signature rejection, and privacy request creation;
- duplicate block-registration safety.
