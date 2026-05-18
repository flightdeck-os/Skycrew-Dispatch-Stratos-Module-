# SkyCrew Dispatch for Stratos

A Stratos airline plugin that connects the Stratos app to the FlightDeck OS / SkyCrew dispatch system.

This repository is designed to be used as the GitHub repository for the Stratos plugin install.

## What this plugin does

The plugin adds a SkyCrew Dispatch page inside Stratos and connects to the airline's configured Stratos API base URL.

It uses SkyCrew/FlightDeck OS endpoints under:

```text
/api/stratos/dispatch/current
/api/stratos/dispatch/briefing?booking_id=ID
/api/stratos/dispatch/manifest?booking_id=ID
/api/stratos/dispatch/release
```

The SkyCrew server-side route update is included in the `skycrew-update/` folder.

## Repository layout

```text
.
├── plugin.json
├── package.json
├── vite.config.ts
├── tsconfig.json
├── src/ui/index.tsx
├── src/ui/styles.css
├── assets/icon-light.svg
├── assets/icon-dark.svg
└── skycrew-update/
    ├── update/stratos-dispatch-module-update.php
    └── fdos-core/routes/stratos_dispatch_module_routes.php
```

## Install the SkyCrew server-side update

Upload the contents of `skycrew-update/` to the SkyCrew site root, then run:

```text
https://skycrew.flightdeck-os.com/update/stratos-dispatch-module-update.php
```

This adds the dispatch endpoints used by the Stratos plugin.

## Local development

Requirements:

- Node.js 20+
- pnpm
- Stratos app with developer mode enabled

Install dependencies:

```bash
pnpm install
```

Run in development mode:

```bash
pnpm dev
```

Build and create the Stratos bundle:

```bash
pnpm bundle
```

This creates `bundle.zip`. If `SKYVEX_API_TOKEN` is configured, the Stratos SDK can upload it automatically. Otherwise, upload the bundle manually through Skyvex.

## GitHub setup

Create a new GitHub repository, then from this folder run:

```bash
git init
git add .
git commit -m "Initial SkyCrew Dispatch Stratos plugin"
git branch -M main
git remote add origin https://github.com/YOUR-USERNAME/skycrew-dispatch-stratos-plugin.git
git push -u origin main
```

Then use that GitHub repository URL in the Stratos plugin install workflow.

## Notes

This plugin does not replace the main Stratos Core API. It adds a dispatch-focused UI module that reads from the SkyCrew Stratos API connection.

## GitHub Actions Deployment

This repository includes `.github/workflows/deploy.yml`.

Before pushing, add this GitHub repository secret:

```text
SKYVEX_API_TOKEN
```

The workflow runs automatically when changes are pushed to `main`, or manually from the GitHub Actions tab using **Run workflow**.

The workflow will:

1. Install Node 20 and pnpm.
2. Read `plugin.json` for the plugin ID and version.
3. Install dependencies.
4. Build the Stratos plugin.
5. Zip the `dist` output into `bundle.zip`.
6. Upload the bundle to the Skyvex Stratos plugin API.

