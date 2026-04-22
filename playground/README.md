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

The `.github/workflows/playground-preview.yml` workflow renders this blueprint
on every pull request — substituting `{{REPO}}` and `{{PR_REF}}` — and posts a
sticky comment with a one-click Playground link.

## API key

No Gemini API key is preloaded. After Playground boots, go to **Settings →
AI Connectors → Google** and paste your own key (get one at
<https://aistudio.google.com/>). Playground keeps it in IndexedDB across
reloads of the same blueprint, so you only do this once per browser.
