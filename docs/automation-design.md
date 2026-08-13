# FabricRouting automation design (catalog + flash cache)

## Goal

Users **never** manually download or copy FRR packages. Product flow:

1. Install FabricRouting from CA / raw `.plg` (**files only** — seconds; no package download at boot)  
2. Choose options under **Network Settings → Fabric Routing** (daemons, channel)  
3. User clicks **Apply** → plugin **downloads**, **verifies**, **installs**, and **starts** FRR (catalog)  
4. On **array start**, plugin **rehydrates from flash cache into RAM** (no network)  

Manual `packages/` drops remain a **developer escape hatch only**, not the product path.

See [boot-lifecycle.md](boot-lifecycle.md).

---

## Ownership

| Step | Owner |
|------|--------|
| Detect Unraid version + arch | Plugin |
| Fetch package **catalog/manifest** | Plugin (HTTP) |
| Select matching package **bundle** | Plugin (from user channel + host facts) |
| Download `.txz` to flash cache | Plugin |
| SHA256 verify | Plugin |
| `installpkg` into live system | Plugin |
| Enable daemons + start FRR | Plugin |
| UI options (which daemons, channel) | User |
| FRR *routing policy* on TB links | Thunderbolt Net (optional) |

---

## Package catalog

### Location (default)

```text
https://raw.githubusercontent.com/ibigsnet/FabricRouting/main/packages/manifest.json
```

Large binaries may live on **GitHub Releases**; manifest entries carry full `url` + `sha256`.

Override: Settings → **Package catalog URL** (advanced; default above).

### Manifest shape (`packages/manifest.json`)

```json
{
  "schema": 1,
  "updated": "2026-08-11T00:00:00Z",
  "default_channel": "latest",
  "channels": {
    "latest": {
      "label": "Latest stable",
      "description": "Recommended for most users"
    },
    "previous": {
      "label": "Previous stable",
      "description": "Rollback if latest misbehaves"
    }
  },
  "bundles": [
    {
      "channel": "latest",
      "unraid_min": "6.12.0",
      "unraid_max": "6.12.99",
      "arch": "x86_64",
      "frr_version": "10.2.1",
      "packages": [
        {
          "file": "libyang-….txz",
          "sha256": "…",
          "url": "https://github.com/ibigsnet/FabricRouting/releases/download/pkg-10.2.1/libyang-….txz"
        },
        {
          "file": "frr-10.2.1-….txz",
          "sha256": "…",
          "url": "https://github.com/ibigsnet/FabricRouting/releases/download/pkg-10.2.1/frr-10.2.1-….txz"
        }
      ]
    }
  ]
}
```

**Selection rules:**

1. `arch` matches host (`uname -m` → `x86_64` / `aarch64`)  
2. Running Unraid version is within `[unraid_min, unraid_max]` (inclusive, lexicographic/`version_compare` style)  
3. Prefer `channel` = user setting (`latest` default)  
4. If multiple match, pick highest `frr_version`  

If **no bundle** matches: UI shows clear message (Unraid version not yet supported); **no** silent failure; do not require manual package drop as the fix path—fix the catalog.

---

## Flash layout (managed by plugin)

```text
/boot/config/plugins/FabricRouting/
  FabricRouting.cfg           # user options
  companion.json          # for Thunderbolt Net / others
  fabricrouting.log           # durable log
  packages/               # download cache (plugin-owned)
    .bundle-id            # which bundle is cached
    MANIFEST.txt          # install order (generated)
    *.txz
```

Users should not need to open this directory. Clearing cache = optional “Re-download packages” action.

---

## User-facing options only

| Setting | Default | Purpose |
|---------|---------|---------|
| Package channel | **latest** | latest / previous (when catalog has them) |
| Install/update on array start | **Yes** | Rehydrate RAM root after reboot |
| Auto-download / update packages | **Yes** | Fetch catalog + missing/outdated packages |
| Enable zebra | **Yes** | Required RIB |
| Enable fabricd | **Yes** | OpenFabric |
| Enable staticd | **Yes** | Common |
| Enable bgpd/ospfd/… | **No** | Opt-in surface |
| Start FRR after install | **Yes** | Bring daemons up |
| Catalog URL | (default GitHub) | Advanced override |

**No** “place files here” as a required step.

---

## Lifecycle (product)

### Plugin install / Apply

1. Read cfg  
2. Detect Unraid version + arch  
3. Fetch `manifest.json`  
4. Resolve bundle  
5. Download packages not present or checksum mismatch  
6. Write `MANIFEST.txt` install order  
7. `installpkg` each package  
8. Patch `/etc/frr/daemons`  
9. Ensure safe baseline `frr.conf` (no eth/br auto-enroll)  
10. Start/restart FRR  
11. Write companion marker  

### Array start (`event/started`)

**Local rehydrate only:** `installpkg` from flash cache + start FRR if enabled.  
**Never** download catalog/packages here (boot/array must not depend on GitHub).  
If cache empty: stay idle until user **Apply**.

### Unraid OS upgrade

Next **Apply**: re-resolve bundle for new Unraid version; download new packages if catalog has them.

### Plugin remove

Remove UI/scripts; **keep** flash cache by default (fast reinstall). Optional future: “purge packages on remove.”

---

## Lifecycle summary

| Phase | FabricRouting |
|--------|-------------------------|
| Pick driver branch/version in UI | Pick package **channel** (+ later explicit FRR version) |
| Plugin downloads ~100MB+ package | Plugin downloads FRR + deps from catalog |
| Installs into live system | `installpkg` into live system |
| Reapplies on boot from flash | Array-start rehydrate from flash cache (no download) |
| No manual file copy for normal users | Same |

---

## Building packages (maintainer duty, not end user)

1. Build FRR (+ libyang, etc.) for Unraid’s Slackware-compatible userspace per major Unraid line  
2. Upload `.txz` to GitHub Release `pkg-<frrver>`  
3. Update `packages/manifest.json` with urls + sha256  
4. Users get it automatically on next Apply / start  

Until a bundle exists for a given Unraid version, the plugin reports **“No automated package set for Unraid X.Y yet”** and remains safe (no half-broken install).

---

## Security

- HTTPS only for catalog and package URLs  
- Require **sha256** match before `installpkg`  
- Do not execute remote shell scripts—only download archives and `installpkg`  
- Catalog URL override is advanced; default is pinned to this project’s GitHub  

---

## Thunderbolt Net

Unchanged split: FabricRouting **owns FRR presence**; Thunderbolt Net **owns OpenFabric policy** when `vtysh`/`fabricd` exist. Automation here means TBN users who install FabricRouting get FRR without a second manual package hunt.
