# WP Forms Blocks

WP Forms Blocks provides native blocks for building forms in the WordPress block editor. Forms can send submissions to the site administrator, post to a custom URL, create comments, or start WordPress personal-data export and deletion requests.

## Requirements

- WordPress 7.0 or newer
- PHP 7.4 or newer

## Installation

1. Download or clone the plugin into `wp-content/plugins/wp-forms-blocks`.
2. Activate **WP Forms Blocks** from **Plugins** in the WordPress administration area.
3. Open a post or page in the block editor and insert a **Form** block.

Release archives include the compiled assets and do not require Node.js or Composer on the WordPress server.

## Create a contact form

The default **Contact Form** includes:

- Editable success and error messages
- Required Name, Email, and Comment fields
- A Submit button

To publish one:

1. Insert the **Form** block. The Contact Form is selected by default.
2. Edit the field labels and notification text directly in the canvas.
3. Select an Input Field to change whether it is required, make its label inline, or set its field name in **Advanced**.
4. Add, remove, or reorder Input Field blocks as needed.
5. Publish the post or page and submit a test entry.

Contact-form submissions are sent as plain-text email to the **Administration Email Address** configured under **Settings → General**. Each message contains the source page URL and the submitted field names and values. The recipient cannot be changed by a visitor or configured separately per form.

During submission, the form displays a localized status message, disables its submit controls, and prevents duplicate requests. The result page focuses the success or error notification so the outcome is available to keyboard and screen-reader users.

## Form types

### Contact Form

Sends the form fields to the site's Administration Email Address. This is the default form type and uses an asynchronous request before displaying the configured success or error notification.

### Comment Form

Submits a comment for the post or page containing the form. Name and Email fields are displayed to logged-out visitors; logged-in visitors use their account details. Submitted comments follow the site's normal WordPress discussion and moderation settings.

### Privacy Request Form

Allows a visitor to request a personal-data export, personal-data deletion, or both. WordPress sends the visitor its standard confirmation email before processing the request. The form displays an error notification if a request cannot be created or its confirmation email cannot be sent.

### Custom form action

To submit a form to another endpoint:

1. Select the Form block.
2. In **Settings**, set **Submissions method** to **Custom**.
3. In **Advanced**, choose `GET` or `POST` and enter the destination in **Form action**.

Custom forms use native browser submission. Their destination is responsible for validation, processing, responses, and redirects. The action supports two placeholders:

- `{SITE_URL}` expands to the site's public URL.
- `{ADMIN_URL}` expands to the WordPress administration URL.

## Available blocks

### Form

The form container controls the submission method and contains the fields, submit button, notifications, and supporting content. It accepts Paragraph, Heading, Group, and Columns blocks in addition to the plugin's form blocks.

The Form block supports the standard editor controls for anchors, colors, gradients, spacing, and typography.

### Input Field

Input Field variations are available for:

- Text
- Textarea
- Checkbox
- Email
- URL
- Telephone
- Number
- Hidden values

Visible fields support an editable label, an optional placeholder, required-field validation, and an explicit field name. If the field name is empty, the plugin generates one from the label. Hidden fields expose Name and Value controls under **Advanced**.

Required fields display a localized required indicator and retain the browser's native required-field semantics.

### Form Submit Button

Contains the button used to submit the form. Select the nested Button block to edit its text and use the normal WordPress button design controls.

### Form Submission Notification

Contains editable content shown after a successful or failed plugin-managed submission. Success notifications use polite status semantics; error notifications use assertive alert semantics.

## Styling

Use the block editor's design controls to style the form, fields, and nested content. Additional site-specific styling can target the public block classes:

```text
.wp-block-formblox-form
.wp-block-formblox-form-input
.wp-block-formblox-form-submit-button
.wp-block-formblox-form-submission-notification
```

## Privacy and data handling

- Contact forms send submitted values by email to the site administrator.
- Comment forms create normal WordPress comments.
- Privacy Request forms create normal WordPress export or deletion requests.
- Custom forms send their values to the configured destination.

Site owners are responsible for explaining these uses in their privacy notice, collecting only necessary information, securing the destination of custom forms, and configuring an appropriate email-delivery service for WordPress.

## AI agents and WordPress abilities

Agents can discover, validate, create, read, edit, duplicate, and delete forms through the native WordPress Abilities API. No AI provider or API key is required by this plugin. The agent supplies a structured definition; the plugin produces saved Gutenberg blocks that remain editable in WordPress.

The eight abilities use the `formblox/` prefix:

- `describe-capabilities`: supported templates, fields, blocks, schemas, and conventions.
- `list-forms`: paginated discovery in content the authenticated user can edit.
- `get-form`: complete definition, child paths, owner, and content version.
- `validate-form`: check a proposed definition and preview saved markup.
- `create-form`: insert a form into an existing content owner.
- `update-form`: update attributes or insert, remove, and move blocks.
- `duplicate-form`: copy a form within or between editable owners.
- `delete-form`: remove one form while preserving its surrounding content.

Templates are `contact`, `comment`, `privacy`, and `custom`. Custom forms require an HTTP(S) action URL. Fields support the eight types listed above. Definitions can include native Paragraph, Heading, Group, Columns, Column, and submit-button blocks. Supported styling includes color presets and gradients, margin/padding, typography, and input border radius; the capability schema describes accepted properties. Attribute support also depends on the selected block.

### Connect an agent

Use authenticated WordPress REST requests, or install the official [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) and connect an MCP-capable agent to its default server at `/wp-json/mcp/mcp-adapter-default-server`. The adapter exposes these abilities through its discovery and execution tools. Follow the adapter's authentication instructions; the agent acts with the permissions of its WordPress user. The adapter is optional and is installed separately.

REST discovery is `GET /wp-json/wp-abilities/v1/abilities?category=formblox`. Execute read-only abilities with `GET /wp-json/wp-abilities/v1/abilities/formblox/{ability}/run`, passing URL-encoded nested query parameters such as `input[post_id]=123&input[path][0]=0` for `get-form`. Execute mutations with `POST` to the same path and a JSON body containing `input`. Application Passwords over HTTPS are suitable for external agents; WordPress cookie authentication requires a REST nonce.

### Create and edit a form

1. Create or select a draft post/page using the normal WordPress content tools. Forms can also live in REST-enabled editable custom post types, synced patterns, and saved templates/template parts.
2. Read the owner's raw content with `context=edit` and compute its SHA-256 `content_version`. For an existing form, `get-form` returns that version directly.
3. Call `validate-form` with the proposed definition if a preview is useful.
4. Call `create-form` with the owner, version, a unique `request_id`, and definition. For example, send this body to `/wp-json/wp-abilities/v1/abilities/formblox/create-form/run`, substituting the actual post ID and 64-character hash:

```json
{
    "input": {
        "post_id": 123,
        "content_version": "<sha256-of-exact-post_content>",
        "request_id": "contact-form-123",
        "definition": { "template": "contact" }
    }
}
```

To change the default contact form's Name field, call `update-form` with the returned owner `post_id`, absolute form `path`, new `content_version`, and these operations:

```json
[
    {
        "operation": "update",
        "path": [ 2 ],
        "attributes": { "label": "Full name", "required": true }
    }
]
```

Use the paths returned by `get-form` for other definitions. Form paths are relative to the owning content; operation paths are relative to the form root (`[]`). Paths count named blocks only, excluding freeform HTML. Operations run in order against the preceding result. A move's destination index counts siblings after removing the source. `parent_path` and `index` control placement for creation, duplication, and insertion; omit the index to append. Style updates replace the style object, so include properties you want to retain. Unchanged field labels retain their saved rich-text markup. Fields and submit controls cannot be placed inside submission notifications, which are hidden before submission. Native email, comment, and privacy forms also check names after PHP normalizes dots to underscores; names must remain unique, and email fields cannot conflict with normalized submission controls. External custom destinations retain their raw-name conventions.

Every mutation checks the exact owner version inside a database transaction. A stale version returns a conflict; read again before editing. Creation and duplication keep the latest 50 request receipts per target owner: an identical retry returns the original result with `replayed: true`, while reusing the ID for different input returns a conflict. Refetch after a replay before editing further. Transactional database tables are required (InnoDB or the official SQLite integration).

Discovery reports synced-pattern and saved template-part references with their separate owner IDs instead of silently following them. Edit the referenced owner explicitly. Editing shared content affects all its uses. Copies receive fresh HTML IDs and update local label, ARIA, and fragment references. Explicit anchors supplied during creation are retained; duplicate IDs are rejected.

Reads and writes require permission to edit the actual owning content, including drafts and shared content. Saves use normal WordPress hooks, sanitization, and revisions. Publication status is preserved, and changes to published owners take effect immediately; returned `live` and `post_status` fields make that explicit. Publication-changing filters are rejected before the native update, and later hooks that change the saved status cause rollback. A save filter that materially changes the proposed content also causes rollback. Untouched blocks and surrounding content are preserved, including blocks outside the supported creation vocabulary.

These abilities manage form configuration. They do not submit forms, retrieve entries, send messages, or process privacy requests.

## Developer API

The plugin registers these block names:

```text
formblox/form
formblox/form-input
formblox/form-submit-button
formblox/form-submission-notification
```

The following PHP filters are available:

### `render_block_formblox_form_extra_fields`

Adds HTML immediately before a rendered form's closing tag.

```php
add_filter(
    'render_block_formblox_form_extra_fields',
    function ( $fields, $attributes ) {
        return $fields . '<input type="hidden" name="source" value="website">';
    },
    10,
    2
);
```

### `render_block_formblox_form_email_content`

Filters the complete plain-text contact-form email body. It receives the generated content and the submitted parameters.

```php
add_filter(
    'render_block_formblox_form_email_content',
    function ( $content, $params ) {
        return $content . "Processed by: Contact workflow\n";
    },
    10,
    2
);
```

### `formblox_show_form_submission_notification_block`

Controls whether a rendered success or error notification is displayed. It receives the current visibility decision, block attributes, and saved notification content.

```php
add_filter(
    'formblox_show_form_submission_notification_block',
    function ( $show, $attributes, $content ) {
        return $show;
    },
    10,
    3
);
```

## Development

Install the JavaScript and PHP dependencies:

```sh
pnpm install --ignore-scripts
composer install
npm run prepare
```

Common commands:

```sh
npm start              # Watch source files and rebuild assets.
npm run build          # Create a production build.
npm run format         # Apply JavaScript and PHP formatting.
npm run lint           # Run all code-quality checks.
npm run test:unit      # Run JavaScript unit tests.
npm run test:php       # Run PHPUnit tests.
npm run test:wordpress # Run isolated WordPress integration tests.
npm run test:e2e       # Run Playwright browser tests.
npm run test:mcp       # Verify the official MCP Adapter transport.
npm test               # Run every test suite.
npm run gate:commit    # Run the complete commit gate.
npm run plugin-zip     # Build an installable plugin archive.
```

The WordPress integration and Playwright suites use disposable SQLite databases and do not touch the development site's database. Browser tests require WP-CLI and Playwright's Chromium browser:

```sh
pnpm exec playwright install chromium
```

Lefthook runs the complete code-quality, build, Plugin Check, unit, integration, and browser gate before every commit. See [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

GPL-2.0-or-later.
