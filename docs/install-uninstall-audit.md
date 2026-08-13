# Install / uninstall audit (full stack)

**Lab:** primary host (Machine A) preferred for destructive install/remove cycles.  
**Date:** 2026-08-12.

---

## What broke before

| Symptom | Root cause |
|---------|------------|
| `plugin remove` said removed, but FRR packages + emhttp + flash remained | Plugin **not registered** (`/var/log/plugins/unraidfrr.plg` missing). Unraid only runs `Method=remove` when the installed plg path is valid; orphaned files need force cleanup. |
| “No match yet” after catalog shipped | **Stale empty** `manifest.cache.json` (1h TTL). Fixed: empty catalogs not long-cached; Apply force-refreshes. |
| Install interrupted mid-download → half state | Finish script ran **`frr_apply()`** (sync or background download) during boot plg rehydrate — blocked `rc.local` / registration. Fixed **2026.08.12da**: boot = **files only**; download only on UI **Apply**. |
| Array never auto-starts after reboot | Boot stuck on UnraidFRR package fetch inside `plugin install` (see boot-blocker-plugin-install-stall.md). Same root cause. |
| Second Apply blocked forever | Stale **`apply.lock`** after killed process. Fixed: clear lock on prepare/remove; dead-pid detection. |
| Progress window empty | `frr-update.php` called apply with no echo. Fixed: progress-frame lines. |

---

## Expected clean install

1. `plugin install …/unraidfrr.plg` completes in **seconds** (plugin files + cfg only — **no** FRR package download).  
2. Symlink `/var/log/plugins/unraidfrr.plg` → `/boot/config/plugins/unraidfrr.plg` exists.  
3. User opens **Settings → Network Settings → Fabric Routing → Apply** (auto-download on) → packages on flash, `installpkg`, FRR starts.  
4. Later reboots: boot plg stays fast; **array start** rehydrates from flash cache only.  
5. Status: catalog **Match**, flash cache files present, `vtysh` works.

---

## Expected clean uninstall

1. Plugins → Remove (or `plugin remove unraidfrr.plg`) **with symlink present**.  
2. After remove:
   - No `vtysh` / no frr|libyang under `/var/log/packages`
   - No FRR processes  
   - No `/usr/local/emhttp/plugins/UnraidFRR`  
   - No `/boot/config/plugins/UnraidFRR`  
   - No `/etc/frr` (we wipe plugin-managed conf)  
3. `network.cfg` / br0 / Wi‑Fi / Docker / Thunderbolt Net **untouched**.

**Broken half-install:** run  
`/usr/local/emhttp/plugins/UnraidFRR/scripts/frr-force-cleanup`  
if still present, or the same steps from the remove script manually.

---

## Features: present vs missing / broken

| Feature | Status |
|---------|--------|
| Catalog download + sha256 | **Works** |
| installpkg rehydrate | **Works** |
| Progress-frame Apply | **Works** (12c+) |
| Apply single-flight lock | **Works** |
| Array-start rehydrate | **Works** (`event/started`) |
| Uninstall when registered | **Fixed** (12d+ comprehensive) |
| Uninstall when orphaned | Use **force-cleanup** |
| Daemon toggles | **Works** |
| Status / catalog match UI | **Works** (refresh after Apply) |
| Interfaces / metrics UI | **Missing** (roadmap) |
| OpenFabric eth wizard | **Missing** (manual/`vtysh`/TBN for TB) |
| Neighbor status on page | **Missing** (roadmap P2) |
| Concurrent Apply safety | Lock only; no queue |
| Keep user `frr.conf` on remove | **No** — clean wipe (document) |

---

## Operator checklist (lab)

```bash
# Clean install
plugin install https://raw.githubusercontent.com/ibigsnet/UnraidFRR/main/unraidfrr.plg
ls -la /var/log/plugins/unraidfrr.plg   # must exist
# wait ~2 min or Apply in UI
vtysh -c 'show version'

# Clean remove (only if symlink exists)
plugin remove unraidfrr.plg
which vtysh; ls /var/log/packages/frr-* 2>/dev/null
```

---

## Related

- [SUPPORTED.md](../packages/SUPPORTED.md)  
- [frr-and-unraid-routing.md](frr-and-unraid-routing.md)  
- [product-roadmap.md](product-roadmap.md)  
