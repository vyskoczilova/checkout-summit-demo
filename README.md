# Checkout Summit Demo

By **Karolina Vyskočilová** · <https://kybernaut.cz/> · GPL-2.0-or-later

A small WordPress plugin built for Checkout Summit Palermo. Adds an
"AI Gallery Generator" metabox on WooCommerce product screens that turns one
source image into two AI-generated lifestyle photos and attaches them to the
product gallery — using **WordPress 7.0's built-in AI Client**.

## Try it in your browser

Every PR posts a one-click WordPress Playground link with WordPress,
WooCommerce, the WP `ai` plugin + Google provider, this plugin, and three
handcrafted Sicilian-ceramic sample products (Pigna, Testa di Moro, Piatto
Trinacria) preloaded. To preview the latest `main` (⌘/Ctrl-click
to open in a new tab):

▶️ **[Open `main` in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/vyskoczilova/checkout-summit-demo/main/playground/blueprint-main.json)**

> ⚠️ **Bring your own Gemini API key.** No key is preloaded — the repo is
> public and any embedded secret would leak. After Playground boots, go to
> **Settings → AI Connectors → Google** and paste your own key (free at
> <https://aistudio.google.com/>). Playground stores it in IndexedDB so you
> only paste it once per browser.

## Use

1. Open one of the imported Sicilian-ceramic products (Pigna, Testa di Moro, or Piatto Trinacria).
2. In the sidebar, find **AI Gallery Generator**.
3. Click **Choose source image** (or skip — the featured image is used as a
   fallback).
4. Click **Generate gallery images**. ~10–30s later, two new images appear in
   the gallery.

## Local install

```bash
cd wp-content/plugins
git clone <repo-url> checkout-summit-demo
```

Then activate it. Requires WordPress 7.0+, WooCommerce, PHP 7.4+, and an AI
connector with image-generation capability configured under **Settings → AI
Connectors**.

## Tests

```bash
composer install
vendor/bin/phpunit
```

Only the pure `Prompt_Builder` is unit-tested. The rest is verified in
Playground.
