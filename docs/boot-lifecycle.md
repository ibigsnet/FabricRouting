# Boot / install lifecycle (Unraid best practice)

## Why `plugin install` runs every boot

Unraid is a **RAM OS**. LimeTech’s plugin manager **re-installs** every `.plg` under `/boot/config/plugins/` from `rc.local` on each boot so files under `/usr/local/emhttp/plugins/` exist again.

That is **normal and required**. It is **not** “first-time CA install only.”

Authors must keep that path **fast, offline-capable after first success, and non-blocking**.

## What FabricRouting does (2026.08.12da+)

| Phase | What runs | Network? | Blocks boot/emhttp? |
|-------|-----------|----------|---------------------|
| **Boot `plugin install`** | Deploy plugin files, chmod, default cfg, companion marker | Only small raw GitHub assets for plg FILE URLs (pages/scripts). **No FRR package download.** | Must finish in seconds |
| **Settings → Apply** | Full `frr_apply()` — catalog + packages (if auto_download) + installpkg + daemons + start | Yes, when downloading | User leaves progress window open |
| **Array `event/started`** | `frr_rehydrate_local()` — installpkg from **flash cache only** + start | **No** | Detached (`at` / `nohup`) so emhttp events stay free |

## Boot vs Apply (corrected)

| External driver plugins (often) | FabricRouting (this plugin) |
|---------------|---------------------|
| UI picks / Apply downloads large driver to flash | UI **Apply** downloads FRR `.txz` to flash |
| Boot rehydrates from **flash cache** | Array start rehydrates from **flash cache** |
| Does **not** re-download hundreds of MB every boot if cache good | Does **not** call `frr_apply` download path on boot |

## Anti-patterns we fixed

1. Synchronous `frr_apply()` in plg finish (blocked registration + boot).  
2. Background `nohup frr_apply()` still started multi-minute download from every boot rehydrate.  
3. `event/started` calling full `frr_apply()` (could download during array start and block emhttp events if not detached).

## Operator notes

- First time after install: open **Network Settings → Fabric Routing → Apply**.  
- After reboot: array start rehydrates if packages already on flash and `install_on_start=yes`.  
- Set `install_on_start="no"` to skip even local rehydrate.  
- Set `auto_download="no"` to use flash cache only even on Apply.

See also: [install-uninstall-audit.md](install-uninstall-audit.md), [automation-design.md](automation-design.md), [../RELEASES.md](../RELEASES.md).
