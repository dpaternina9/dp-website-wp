# ADR-0004 — Tag-driven signed releases

## Status

Superseded by [ADR-0015](0015-adopt-the-wp-update-client-library.md) —
pruned to this tombstone 2026-08-29.

## What survives

The decision stands and lives in ADR-0015: releases are driven by semver tags
(`theme-v*` / `core-v*`), builds are signed with an Ed25519 key compiled into the
client, and updates fail closed. The in-repo implementation this ADR designed —
signed-envelope manifests, `DP_UPDATE_SIGNING_KEY` and `R2_*` secrets, the
`updates.dpaternina.com` host — was extracted into the `fanxielab/wp-update-client`
library and the `fanxie-lab/wordpress-updater` reusable workflow, serving from
`wp-updates.fanxie.cloud`. The original reasoning is in this file's git history.
