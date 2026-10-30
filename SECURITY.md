# Security — Fabric Routing

Copyright (c) 2026 ibigs, LLC · Author: RifleJock · License: GPL-3.0-or-later

## Privilege model

- Runs as root (Unraid plugin model). The packaged FRR daemons also run as root.
- Installs FRR Slackware packages into the RAM root and starts FRR daemons.
- Owns `/etc/frr/frr.conf`, `daemons` and `vtysh.conf` (rebuilt from flash).
- Does **not** edit Unraid `network.cfg`, `eth0` or `br0`, and does not change IP forwarding.

## Defaults (safe until you opt in)

| Setting | Default | Meaning |
|---------|---------|---------|
| Plugin install / boot | Files only | No package download at install, boot or array start |
| Package download | Explicit **Download & Install packages** | The only network use |
| Install on array start | Yes | Reinstall verified packages **already on flash** |
| Optional protocols (BGP/OSPF/…) | Off | Enable only if you need them |
| Catalog | `packages/manifest.json` bundled with the plugin | Hashes for every package; never fetched at run time |
| Fabric route guard | On | OpenFabric routes outside the fabric prefixes never reach the kernel table |

## Package integrity

- The `.plg` pins the plugin `.txz` by SHA256; that `.txz` carries the catalog with each package's sha256.
- Downloads are https only with TLS verification. A package must match its bundled sha256 before it is written to flash; any failure stops the job.
- `MANIFEST.txt` on flash records each package's sha256. Every file is checked again before every install (package job and array start). Files it does not list are never installed.
- A package mirror only supplies files; it cannot supply hashes.

## Web endpoints

- The settings page only reads state (no network, no flash writes).
- Settings are saved through Unraid's `update.php` (POST + CSRF); the include refuses GET.
- Package jobs run through Unraid's own job windows.
- Values from the form reach `frr.conf` only after strict validation (daemon switches are yes/no; guard prefixes are parsed as IP prefixes).

## Uninstall

- Stops FRR that came from this plugin's packages (init script, then pidfiles in `/var/run/frr`).
- Removes only packages listed in `MANIFEST.txt` with an `_unraid` suffix, plus the config files it wrote.
- Removes `/boot/config/plugins/FabricRouting` (settings, package cache, `frr.user.conf`).
- Does not touch Thunderbolt Net (including its FRR fragment), NBD Export, Unraid `network.cfg`, or processes it did not start.

## Contact

- **Support:** the [Thunderbolt Net forum thread](https://forums.unraid.net/topic/200065-plugin-thunderbolt-net-host-to-host-networking-over-thunderbolt-345-and-usb44v2/) is shared with Fabric Routing until it has its own thread.
- **Security reports and bugs:** https://github.com/ibigsnet/FabricRouting/issues
- **Project:** https://github.com/ibigsnet/FabricRouting
