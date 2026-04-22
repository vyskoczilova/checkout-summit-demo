# Playground preview

A WordPress Playground blueprint that boots a fully-configured environment for
manually reviewing this plugin in a browser — no local WordPress required.

## What's preinstalled

- WordPress 7.0 (PHP 8.2, kitchen-sink extensions)
- WooCommerce
- WP `ai` plugin + `ai-provider-for-google`
- This plugin (loaded directly from the PR ref via `git:directory`)
- Two beanie sample products imported from `sample-products.csv`

## How it's used

Two files:

- **`blueprint.json`** — template with `{{REPO}}` and `{{PR_REF}}` placeholders.
  The `.github/workflows/playground-preview.yml` workflow substitutes these on
  every PR and posts a sticky comment with the rendered Playground link.
- **`blueprint-main.json`** — same content, but with `main` baked in.
  Standalone — opened from the README's "Open main in Playground" link via
  `?blueprint-url=`.

Don't try to open the template (`blueprint.json`) directly in Playground — it
has unsubstituted `{{REPO}}` placeholders and will fail validation.

## API key

No Gemini API key is preloaded. After Playground boots, go to **Settings →
AI Connectors → Google** and paste your own key (get one at
<https://aistudio.google.com/>). Playground keeps it in IndexedDB across
reloads of the same blueprint, so you only do this once per browser.
