# Security / CA review notes — Fabric Routing

Copyright (c) 2026 ibigs, LLC · Author: RifleJock · License: GPL-3.0-or-later

## Privilege model

- Plugin runs as root (Unraid plugin model).
- Can install FRR Slackware-style packages into the RAM root (`installpkg`) and start FRR daemons.
- Does **not** edit Unraid `network.cfg`, `eth0`, or `br0` by default.
- Does **not** enable IP forwarding globally as a side effect of install.

## Defaults (safe until the user opts in)

| Setting | Default | Meaning |
|---------|---------|---------|
| Boot / plugin install | files only | No package download, no `frr_apply` |
| `auto_download` | **no** | No surprise multi‑MB network fetch |
| `install_on_start` | yes | Rehydrate packages **already on flash** only |
| zebra / fabricd / staticd | yes (after packages exist) | Optional protocols (BGP/OSPF/…) off |
| Package catalog | GitHub `stable` | HTTPS + sha256 verification |

Fresh install is **idle** until the user enables Auto-download and clicks **Apply** (or places packages on flash).

## Network package download

- Only on **Settings → Network Settings → Fabric Routing → Apply** when `auto_download=yes`.
- Never during boot `plugin install` or array-start rehydrate.
- Refuses non-HTTPS package URLs; verifies sha256 when present.
- Catalog: `https://raw.githubusercontent.com/ibigsnet/FabricRouting/stable/packages/manifest.json`

## Uninstall

- Stops FRR, `removepkg` of managed packages (manifest + `frr-*` / `libyang-*` sweep).
- Removes plugin emhttp + flash config/package cache.
- Does not touch Thunderbolt Net, NBD Export, or Unraid network.cfg.
- **Note:** if FRR was installed only via this plugin, uninstall removes those packages. Independent third-party FRR installs with the same package names may also be removed by the sweep — avoid mixing install methods on one host.

## What to read (5 minutes)

1. `fabricrouting.plg` — files-only install; Method=remove
2. `event/started` — local rehydrate only
3. `include/frr-lib.php` — `frr_apply` / `frr_download_bundle` / `frr_rehydrate_local`
4. `docs/boot-lifecycle.md`
5. This file

## Contact

- Support: GitHub issues (forum thread when published)
- Project: https://github.com/ibigsnet/FabricRouting
