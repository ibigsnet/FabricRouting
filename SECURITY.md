# Security — Fabric Routing

Copyright (c) 2026 ibigs, LLC · Author: RifleJock · License: GPL-3.0-or-later

## Privilege model

- Runs as root (Unraid plugin model).
- Can install FRR Slackware-style packages into the RAM root (`installpkg`) and start FRR daemons.
- Does **not** edit Unraid `network.cfg`, `eth0`, or `br0` by default.
- Does **not** enable IP forwarding globally as a side effect of install.

## Defaults (safe until you opt in)

| Setting | Default | Meaning |
|---------|---------|---------|
| Plugin install / boot | Files only | No package download at install or boot |
| Package download | Explicit **Download & Install packages** | No surprise multi‑MB fetch |
| Install on array start | Yes | Rehydrate packages **already on flash** only |
| Optional protocols (BGP/OSPF/…) | Off | Enable only if you need them |
| Catalog | GitHub `stable` | HTTPS + sha256 verification |

Fresh install is **idle** until you install packages from the Fabric Routing page.

## Package download

- Only when you run **Download & Install packages** (or equivalent Apply path with download enabled).
- Never during boot `plugin install` or array-start rehydrate.
- Refuses non-HTTPS package URLs; verifies sha256 when present.

## Uninstall

- Stops FRR and removes packages this plugin managed (when known).
- Removes plugin emhttp + flash config/package cache.
- Does not touch Thunderbolt Net, NBD Export, or Unraid `network.cfg`.
- If FRR was installed only via this plugin, uninstall removes those packages. Mixing independent third-party FRR with the same package names on one host is not recommended.

## Contact

- Support: GitHub Issues (forum when listed on the Apps card)  
- Project: https://github.com/ibigsnet/FabricRouting  
