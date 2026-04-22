# Playground Preview GitHub Action — Design

**Date:** 2026-04-23
**Plugin:** checkout-summit-demo
**Audience:** Checkout Summit Palermo demo prep

## Goal

On every pull request to this repo, post a comment with a one-click WordPress
Playground link that boots a fully-configured environment so the reviewer can
manually verify the plugin works end-to-end. The environment includes
WordPress 7.0, WooCommerce, the WP AI plugin and its Google + OpenAI provider
plugins, the plugin under review (loaded directly from the PR branch), and a
handful of beanie sample products imported from WooCommerce's bundled CSV.

The Gemini API key is **never** embedded — the reviewer pastes it once after
Playground boots; Playground's IndexedDB persists it across reloads of the same
blueprint.

## Hosting model — no zip, no gh-pages, no third-party

The plugin is loaded into Playground via the `git:directory` blueprint resource,
which clones a directory straight from the PR ref on GitHub. Consequences:

- The Action does **not** build a zip.
- Nothing is hosted outside GitHub.
- The blueprint lives in this repo (`playground/blueprint.json`), versioned and
  reviewable in PR diffs.
- The Action's only output is a sticky PR comment containing a Playground URL
  with the rendered blueprint inlined in the URL hash.

## File layout

```
.github/workflows/playground-preview.yml         # the workflow
playground/
  blueprint.json                                 # template with {{REPO}} and {{PR_REF}} placeholders
  sample-products.csv                            # ~3 beanie products, extracted from WC's sample_products.csv
  sample-images/                                 # PNG featured images for those beanies
    beanie.png
    beanie-with-logo.png
    sunglasses-beanie.png                        # name as appropriate to actual products picked
  README.md                                      # short note: what's here, how to set the API key
```

## Workflow

`.github/workflows/playground-preview.yml`

**Triggers:**
- `pull_request`: `opened`, `synchronize`, `reopened`
- `workflow_dispatch` (manual run from Actions tab; required so Karolina can
  iterate on the workflow itself before merging it)

**Permissions:** `pull-requests: write`, `contents: read`.

**Single job, ubuntu-latest:**
1. `actions/checkout@v4` (default ref is fine — we only read `playground/`).
2. **Render blueprint:** read `playground/blueprint.json`, substitute
   `{{REPO}}` → `${{ github.repository }}` and `{{PR_REF}}` →
   `refs/pull/${{ github.event.number || github.ref_name }}/head` (the `||`
   fallback covers `workflow_dispatch` runs against a branch).
3. **URL-encode** the rendered JSON (using `jq -sRr @uri` or a `node -e` one-liner).
4. **Build the Playground URL:**
   `https://playground.wordpress.net/#${ENCODED_JSON}`
5. **Post sticky comment** via `marocchino/sticky-pull-request-comment@v2` with:
   - The Playground link as a prominent button-style link.
   - One-line reminder: *"After it loads: **Settings → AI Connectors → Google** → paste your Gemini key. Playground keeps it in IndexedDB across reloads."*
   - Footer line listing what's preinstalled (so reviewers know what to expect).

   The comment is identified by a fixed header so it updates in place on each
   push instead of accumulating.

There is **no second job** for cleanup. Sticky comments update in place on
re-push and are left alone on PR close — that's acceptable.

## Blueprint

`playground/blueprint.json`

```json
{
  "$schema": "https://playground.wordpress.net/blueprint-schema.json",
  "preferredVersions": { "php": "8.2", "wp": "7.0" },
  "phpExtensionBundles": ["kitchen-sink"],
  "features": { "networking": true },
  "landingPage": "/wp-admin/edit.php?post_type=product",
  "login": true,
  "steps": [
    { "step": "installPlugin", "pluginData": { "resource": "wordpress.org/plugins", "slug": "woocommerce" }, "options": { "activate": true } },
    { "step": "installPlugin", "pluginData": { "resource": "wordpress.org/plugins", "slug": "ai" }, "options": { "activate": true } },
    { "step": "installPlugin", "pluginData": { "resource": "wordpress.org/plugins", "slug": "ai-provider-for-google" }, "options": { "activate": true } },
    { "step": "installPlugin", "pluginData": { "resource": "wordpress.org/plugins", "slug": "ai-provider-for-openai" }, "options": { "activate": true } },
    {
      "step": "installPlugin",
      "pluginData": {
        "resource": "git:directory",
        "url": "https://github.com/{{REPO}}",
        "ref": "{{PR_REF}}",
        "refType": "refname",
        "path": "/"
      },
      "options": { "activate": true }
    },
    {
      "step": "writeFile",
      "path": "/wordpress/wp-content/uploads/csd-import.csv",
      "data": { "resource": "url", "url": "https://raw.githubusercontent.com/{{REPO}}/{{PR_REF}}/playground/sample-products.csv" }
    },
    {
      "step": "runPHP",
      "code": "<?php require '/wordpress/wp-load.php'; require_once WP_PLUGIN_DIR . '/woocommerce/includes/import/class-wc-product-csv-importer.php'; $importer = new WC_Product_CSV_Importer( '/wordpress/wp-content/uploads/csd-import.csv', array( 'parse' => true, 'update_existing' => false ) ); $importer->import(); ?>"
    }
  ]
}
```

Notes on the blueprint:
- `git:directory` with `path: "/"` clones the entire repo and treats the repo
  root as the plugin directory. That works because this repo IS the plugin.
- The CSV is fetched from `raw.githubusercontent.com` at the same `{{PR_REF}}`,
  so PR changes to sample data also preview live.
- The `runPHP` step uses `WC_Product_CSV_Importer` directly. Image URLs in the
  CSV are full `https://raw.githubusercontent.com/{{REPO}}/{{PR_REF}}/playground/sample-images/<file>.png`
  references, sideloaded into the Media Library by the importer.

## Sample data

`playground/sample-products.csv` — extracted rows from WooCommerce's bundled
`sample-data/sample_products.csv`, restricted to the **beanie** products (the
classic "Beanie" and "Beanie with Logo" entries plus one variant if it makes
sense). Image columns rewritten to point at PNGs committed under
`playground/sample-images/` via raw GitHub URLs (so the importer can sideload
them without depending on woocommerce.com's sample image hosting).

The CSV is exactly what WooCommerce's importer expects (same headers as the
official sample), so no transformation logic is needed in the workflow.

## Testing the workflow itself

This is the chicken-and-egg case all PR-preview workflows have. Plan:

1. Add `workflow_dispatch` to the trigger so the workflow can be run manually
   from the Actions tab against any branch — this gives a feedback loop
   without needing a PR.
2. The first PR is "Add Playground preview workflow." On open, it runs
   itself, posts its own preview link, Karolina opens the link, and verifies
   the boot sequence.
3. If the boot fails, iterate by pushing fixes to the same branch (each push
   re-runs the workflow and updates the sticky comment).

## Guardrails

- **No secrets in the comment, blueprint, or repo.** Gemini API key is entered
  manually post-boot. CLAUDE.md and the README already document this.
- **Concurrency:** workflow uses `concurrency: pr-${{ github.event.number }}`
  with `cancel-in-progress: true` so rapid pushes don't pile up runs.
- **Permissions:** workflow declares only `pull-requests: write` and
  `contents: read`. No write access to repo contents from the workflow.
- **External PRs:** `pull_request` (not `pull_request_target`) is used, so
  forked PRs run in the safe default permission context — they get a comment
  with no token leakage risk. The `git:directory` clone of `refs/pull/N/head`
  works for forks too.

## README updates

`README.md` gets a tighter rewrite as part of this work — the current install
section assumes a local WordPress and is now out of date. New shape:

- One-line description.
- **Try it in your browser** — link to "open the latest `main` in
  Playground" (a permanent Playground URL pointing at the `main` ref via
  `git:directory`, mirroring what the PR-preview workflow generates per PR).
- **Bring your own Gemini API key** callout: a short paragraph telling
  attendees that no key is preloaded, how to get one
  (<https://aistudio.google.com/>), and where to paste it
  (Settings → AI Connectors → Google) once Playground boots.
- Trim Requirements and Install sections down to the essentials. Keep the Use
  section. Drop the file-tree breakdown — it now lives in `CLAUDE.md`.

Target length: under 60 lines.

## Out of scope

- Automated UI assertions inside Playground (Playwright, etc.).
- Multi-version PHP/WP matrix.
- Boot-time caching of dependencies.
- Cleanup job on PR close (sticky comment is left alone, no orphan artifacts).
- Pre-loading the Gemini API key (intentionally — see CLAUDE.md gotcha).

## Dependencies

- `marocchino/sticky-pull-request-comment@v2` — well-maintained sticky comment
  action.
- WordPress Playground (`playground.wordpress.net`) — the public hosted
  Playground service.
- The wp.org plugin slugs `woocommerce`, `ai`, `ai-provider-for-google`, and
  `ai-provider-for-openai` (verified to exist as of 2026-04).
