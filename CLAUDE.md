# CLAUDE.md — Checkout Summit Demo

Notes for AI assistants working in this repo. Read this **before** touching the
plugin or the Playground blueprints — every section here documents a trap we
already fell into.

## What this plugin is

A small WordPress plugin built for the Checkout Summit talk in Palermo.
Adds an "AI Gallery Generator" metabox to WooCommerce product edit screens:
pick a source image (or fall back to the product's featured image) → calls
the **WordPress 7.0 AI Client** with two scene templates (`json/*.json`) →
attaches both generated images to the product gallery.

Public Playground link is in the [README](./README.md#try-it-in-your-browser);
the GitHub Action under `.github/workflows/playground-preview.yml` posts a
per-PR link too.

## Code organization

- `checkout-summit-demo.php` — bootstrap, constants, hook wiring on `init`
- `includes/class-prompt-builder.php` — pure PHP, unit-tested, flattens scene JSON to text
- `includes/class-generator.php` — calls `wp_ai_client_prompt()`, saves to Media Library, appends to gallery meta
- `includes/class-ajax.php` — `wp_ajax_csd_generate_gallery` handler; nonce + cap + source resolution
- `includes/class-metabox.php` — registers metabox, enqueues `wp.media` + admin assets
- `includes/class-playground-seed.php` — Playground-only seeder, runs on first admin request (see Playground section below)
- `assets/admin.{js,css}` — vanilla JS, opens `wp.media` frame, posts to AJAX
- `json/*.json` — scene templates (one per output image); add a file here and list it in `class-ajax.php` to add a scene
- `playground/` — blueprint, seed PHP, sample images (Sicilian ceramics)
- `.agents/skills/blueprint/SKILL.md` — installed blueprint-authoring skill, **read it before editing any `playground/*.json` file**

## Conventions

- Namespace `CheckoutSummitDemo\` for everything in `includes/`.
- File naming: `class-<thing>.php`, lowercase with hyphens.
- Every PHP file in `includes/` starts with `if ( ! defined( 'ABSPATH' ) ) { exit; }`.
- No build step. Vanilla JS, no jQuery beyond what `wp.media` brings in.
- Synchronous AJAX is intentional — generation takes ~10–30s; no Action Scheduler / queue for the demo.

## Tests

```bash
composer install
vendor/bin/phpunit
```

Only `Prompt_Builder` is unit-tested. Everything else is verified by manual
smoke test in Playground (`README.md` link) or on a real WP install.

---

## WordPress 7.0 AI Client — API reference

This plugin **must** call the AI Client through the WordPress wrapper. Do not
call any AI provider directly. Provider selection is delegated to whatever the
site has configured under **Settings → AI Connectors**.

References:
- Repo: <https://github.com/WordPress/wp-ai-client>
- Underlying PHP lib: <https://github.com/WordPress/php-ai-client>
- AI Client announcement: <https://make.wordpress.org/core/2026/03/24/introducing-the-ai-client-in-wordpress-7-0/>
- Practical examples (Rich Tabor): <https://rich.blog/wordpress-ai-client/>

### Method naming — snake_case, NOT camelCase

The WordPress wrapper class `WP_AI_Client_Prompt_Builder` exposes
**snake_case** method names. The underlying `WordPress\AiClient` PHP library
uses camelCase, but those methods are not proxied by the WP wrapper. Calling
camelCase methods on the WP wrapper does NOT throw — it silently returns the
builder, which then shows up downstream as `Unrecognised image object returned
by AI Client: WP_AI_Client_Prompt_Builder`.

Confirmed snake_case methods:
- `with_file( string $file, string $mime_type )` — attach a reference image
- `using_system_instruction( string $text )`
- `using_model_preference( string ...$model_ids )`
- `using_temperature( float $t )` / `using_max_tokens( int $n )` / `using_top_p()` / `using_top_k()`
- `as_json_response()`
- `generate_text(): string|WP_Error`
- `generate_image(): object|WP_Error` — returns a file object
- `generate_images( int $count ): array|WP_Error`
- `generate_result(): object|WP_Error`

### Return shape of `generate_image()`

Returns either `WP_Error` or a file object. Documented accessors include at
least `getDataUri()` (returns a `data:image/...;base64,...` URI). Other
accessors (`getBase64Data()`, `getBytes()`, `getPath()`, `getUrl()`) may or
may not be present depending on the connector. The plugin's
`Generator::extract_bytes()` probes for all of these in priority order before
failing.

### Canonical usage

```php
$image = wp_ai_client_prompt( $prompt_text )
    ->with_file( $source_path, $source_mime )   // e.g. 'image/png', 'image/jpeg'
    ->generate_image();

if ( is_wp_error( $image ) ) {
    // bail with $image->get_error_message()
}
// Then turn the returned file object into bytes via getDataUri()/getBase64Data()/etc.
```

**Always** check `is_wp_error()` after a `generate_*` call. The wrapper does
not throw on failure.

### What we do NOT do

- Do **not** call `->using_provider()` / `->usingProvider()`. Let WP core's configured connector pick.
- Do **not** import the underlying `WordPress\AiClient\AiClient` directly.
- Do **not** use any provider SDK (OpenAI, Anthropic, Google) directly.

### Capability gotcha — image input + image output

When you chain `with_file()` and then `generate_image()`, the AI Client looks
for a model that supports **both** `image_input` and `image_generation`. That
set is narrow:

- **OpenAI:** only `gpt-image-1` (DALL·E does NOT accept image input). `gpt-image-1` requires OpenAI account verification.
- **Google:** `gemini-2.5-flash-image` ("Nano Banana") — most reliable for this demo.

A connected OpenAI plugin showing "Text and image generation with GPT and
DALL·E" is **not** sufficient on its own — the request will fail with
`No models found that support image_generation for this prompt`.

Mitigation in `Generator::generate_for_product`: prefer
`gpt-image-1` / `gemini-2.5-flash-image` via `->using_model_preference(...)`,
and fall back to a text-only prompt without `with_file()` if no
multimodal-output model is available.

---

## WordPress Playground — what we know

References (read these before editing the blueprint):
- **Local skill** (most useful): `.agents/skills/blueprint/SKILL.md`
- Blueprint docs: <https://wordpress.github.io/wordpress-playground/blueprints/>
- For plugin developers: <https://wordpress.github.io/wordpress-playground/guides/for-plugin-developers/>
- Blueprint JSON schema: <https://playground.wordpress.net/blueprint-schema.json>
- PR-preview action guide: <https://wordpress.github.io/wordpress-playground/guides/github-action-pr-preview/>
- Make/Playground blog: <https://make.wordpress.org/playground/>
- WooCommerce demo blueprint reference: <https://jamesckemp.com/launch-woocommerce-playground-sites-with-a-single-keystroke/>

### Validating a blueprint locally

```bash
NODE_PATH=/tmp/node_modules node /tmp/validate.mjs playground/blueprint.json
```

(That tiny ajv-based validator is checked in to `/tmp/` during dev sessions
— recreate it with `npm install --prefix /tmp ajv` and the script that
fetches `blueprint-schema.json` then runs `ajv.compile(schema)`. **Schema-VALID
is necessary but not sufficient** — Playground's runtime does additional
checks the JSON Schema does not enforce; see traps below.)

### Inline blueprint URL encoding (the worst trap)

Playground's hash parser does **not** call `decodeURIComponent`. It parses the
raw URL fragment as JSON. Consequences:

- Structural JSON characters (`{`, `}`, `:`, `,`, `[`, `]`, `/`, `?`, `=`, `&`, `$`) **must be raw** in the URL hash. If you encode them as `%7B` etc., `JSON.parse` chokes on the literal `%` characters.
- Only characters that would otherwise break the URL (`"`, space, newline, `<`, `>`) need encoding.
- The `$` in `$schema` is the most common offender — encoding it to `%24schema` makes Playground report `unexpected property "%24schema"`.

The GitHub Action under `.github/workflows/playground-preview.yml` uses a
Python `urllib.parse.quote(..., safe='{}[]:,/?=&_-.!~*()$')` call to do the
right thing. Don't switch back to `jq @uri` or `encodeURIComponent`.

### `wp` (WordPress) version preset

Schema accepts any string for `preferredVersions.wp`, but the Playground
runtime only accepts a fixed list of presets. Confirmed values:

| Value     | What you get                  |
| --------- | ----------------------------- |
| `latest`  | Latest GA release (currently 6.x) — does **not** include the WP 7.0 AI Client |
| `beta`    | Latest WP 7.0 RC — what we want for this demo |
| `nightly` | **Rejected at runtime** as of April 2026 — gives an opaque "Invalid blueprint" error with no path info |

**Use `beta`** while WP 7.0 is in RC. After GA, switch to `latest`.

### `phpExtensionBundles` is deprecated and unnecessary

The schema marks it as deprecated. Playground bundles MySQL by default — you
do **not** need `["kitchen-sink"]`. If you remove it, things still work.

### `git:directory` resource — install plugin from a PR

Use this instead of zipping and hosting the plugin yourself:

```json
{
  "step": "installPlugin",
  "pluginData": {
    "resource": "git:directory",
    "url": "https://github.com/{{REPO}}",
    "ref": "{{PR_REF}}",
    "refType": "refname"
  }
}
```

`refType` enum: `branch`, `tag`, `commit`, `refname`. `refs/pull/N/head`
needs `refname`. The PR-preview workflow substitutes `{{REPO}}` and
`{{PR_REF}}` before encoding. (The Playground AI assistant in the error
dialog has previously claimed `refname` is invalid — the actual schema says
otherwise. Trust the schema.)

### WooCommerce in Playground — onboarding redirect

A fresh WooCommerce install on Playground will hijack the first admin page
load and redirect to its onboarding wizard, which makes the products list
appear empty even when you've imported products. Fix:

1. Set `csd_seed_products` (or any custom trigger) in `siteOptions` so it's
   in the DB before WC's first request.
2. Pre-set onboarding-skip options in `siteOptions` too (they need to exist
   in the DB before WC activates so its `add_option()` doesn't reset them):
   - `woocommerce_task_list_hidden: "yes"`
   - `woocommerce_extended_task_list_hidden: "yes"`
   - `woocommerce_show_marketplace_suggestions: "no"`
3. Hook your seeder on `admin_init` priority 0 — WC's onboarding redirect
   handler runs at the default priority 10, so priority 0 wins.
4. In your seeder, also `delete_transient( '_wc_activation_redirect' )`.

This is exactly what `Playground_Seed::register()` does. Don't reinvent it.

### `wp-cli` step — works, but with caveats

`extraLibraries: ["wp-cli"]` enables it. Then:

```json
{ "step": "wp-cli", "command": "wp wc generate products 20" }
```

(That command requires the `wc-smooth-generator` plugin — install from
`https://github.com/woocommerce/wc-smooth-generator/releases/latest/download/wc-smooth-generator.zip`.
It generates random products with placeholder images. There is no `--image`
flag — wc-smooth-generator includes images by default.)

**Quoting trap:** the `command` string is shell-tokenized inside Playground.
Single quotes are stripped. Anything more complex than a single command with
flags should be moved into a PHP file under `playground/` and invoked via
`wp eval-file`, **or** moved into a Playground-detection class in the plugin
(see `Playground_Seed` for the pattern we settled on). We tried inline
`runPHP` first — the URL encoding makes PHP code with `;`, `<`, `>`, `'`
unworkable.

### Hosting the blueprint

We don't host the zip. Two patterns:

- **PR previews**: GitHub Action renders `playground/blueprint.json`
  (substituting `{{REPO}}` and `{{PR_REF}}`), encodes it into the Playground
  URL hash, and posts a sticky comment. No hosting needed.
- **`main` preview** (the README link): a separate `playground/blueprint-main.json`
  with `main` baked in, fetched by Playground via `?blueprint-url=...`
  pointing at `raw.githubusercontent.com`. Both files must stay in sync.

### Detecting Playground at runtime

In PHP, the simplest reliable check (used in `Playground_Seed::is_playground()`):

```php
defined( 'PLAYGROUND_BLUEPRINT_RUNNER' )
  || ( isset( $_SERVER['HTTP_HOST'] ) && str_contains( (string) $_SERVER['HTTP_HOST'], 'playground.wordpress.net' ) )
  || file_exists( '/wordpress/wp-config.php' );
```

The `/wordpress/wp-config.php` file path is Playground-specific (it uses
`/wordpress/` as docroot), so it's the most reliable fallback.

### Useful Playground URLs

- Hosted instance: <https://playground.wordpress.net/>
- Builder (paste blueprint JSON, run interactively): <https://playground.wordpress.net/builder/builder.html>
- Schema (live): <https://playground.wordpress.net/blueprint-schema.json>
- Inline blueprint URL form: `https://playground.wordpress.net/#<minified-json-with-only-quotes-encoded>`
- URL-fetched blueprint form: `https://playground.wordpress.net/?blueprint-url=<https-url-to-json>`

---

## wp-cli — when used outside Playground

When working with the local DDEV install (`https://checkoutsummit.ddev.site/`)
or any other WP-CLI-enabled environment:

- Docs: <https://developer.wordpress.org/cli/commands/>
- WC commands: <https://woocommerce.github.io/code-reference/files/woocommerce-includes-cli-class-wc-cli.html>
- wc-smooth-generator (random products): <https://github.com/woocommerce/wc-smooth-generator>

Useful one-liners:

```bash
# What WP version is running?
wp core version

# Quickly create a product for testing the AI Gallery Generator metabox:
wp wc product create --name="Test Product" --type=simple --regular_price=10 --user=admin

# Tail debug.log while testing
wp config set WP_DEBUG true --raw && wp config set WP_DEBUG_LOG true --raw
tail -f wp-content/debug.log
```

---

## Things we deliberately **don't** do (don't add them)

- No background queue / Action Scheduler — generation is synchronous and that's fine for the demo.
- No caching of generated images.
- No provider selection UI — WordPress core handles this.
- No regenerate / per-image delete UI inside our metabox — use the standard product gallery UI.
- No backwards-compat shims for WP < 7.0 — we hard-fail with a clear message instead.
- No mocking the entire WP + AI Client stack to unit-test `Generator` — the cost outweighs the benefit; verify in Playground instead.
