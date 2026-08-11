# FRR (FRRouting) — Documentation

How UnraidFRR installs and manages the [FRRouting](https://frrouting.org/) suite on Unraid—**fully automated** package download/install (Nvidia Driver–style), daemon selection, and safe defaults for the rest of the network stack.

**Install (recommended):** Apps (Community Applications) → search **FRR** or **UnraidFRR** → Install.

**Manual install:** Plugins → Install Plugin →  
`https://raw.githubusercontent.com/ibigsnet/UnraidFRR/main/unraidfrr.plg`

**Support:** [GitHub Issues](https://github.com/ibigsnet/UnraidFRR/issues)  
**Source / project:** [github.com/ibigsnet/UnraidFRR](https://github.com/ibigsnet/UnraidFRR)  
**CA templates:** [ibigsnet/unraid-templates](https://github.com/ibigsnet/unraid-templates)  
**Support development:** [Patreon](https://www.patreon.com/cw/IBIGSNet) · [PayPal](https://www.paypal.com/paypalme/RifleJock)

`README.md` is only the short Unraid Plugins-list blurb (`**Name**` + one paragraph). Unraid runs it through Markdown for the Plugins description—keep it tiny. This file is the full documentation.

**Related plugin:** [Thunderbolt Net](https://github.com/ibigsnet/ThunderboltNet) — TB host-net underlay and OpenFabric *policy* when FRR is present. UnraidFRR does **not** require it.

---

## What it does

Unraid does not ship FRR. This plugin **owns FRR lifecycle** on the host:

| Area | Behavior |
|------|----------|
| **Catalog** | Reads `packages/manifest.json` (or your catalog URL) for builds matching this **Unraid product version** + arch (not Linux kernel version) |
| **Download** | Fetches `.txz` packages over HTTPS, verifies **sha256**, caches under flash |
| **Install** | `installpkg` into the live system; **array start** rehydrates RAM after reboot |
| **Daemons** | Enables selected entries in `/etc/frr/daemons` (zebra, fabricd, …) |
| **Start** | Best-effort start/restart of the FRR service |
| **UI** | **Settings → FRR (FRRouting)** — status, channel, daemons |
| **Companion marker** | `companion.json` so Thunderbolt Net can detect UnraidFRR |

You choose **options** only (channel, which daemons, auto-download). You do **not** manually copy packages (same idea as the Nvidia Driver plugin).

Deep design: [docs/automation-design.md](docs/automation-design.md).  
LAN safety: [docs/scope-and-safety.md](docs/scope-and-safety.md).

### Product defaults

| Setting | Default | Why |
|---------|---------|-----|
| Package channel | **latest** | Recommended automated set |
| Auto-download | **Yes** | Full automation |
| Install on array start | **Yes** | Unraid RAM root loses packages on reboot |
| zebra / fabricd / staticd | **Yes** | Useful defaults for routing + OpenFabric |
| bgpd / ospfd / isisd / bfdd | **No** | Opt-in surface area |
| Start FRR on Apply | **Yes** | Bring stack up after install |

### What it does *not* do

| Not in scope | Who owns it |
|--------------|-------------|
| Thunderbolt cables / tbn IPs | Thunderbolt Net |
| OpenFabric stanzas on `thunderbolt*` | Thunderbolt Net (marked conf) |
| Unraid eth0/br0 / `network.cfg` | Unraid Network Settings |
| Auto-enroll br0 into OSPF/BGP/OpenFabric | **Never** by default |
| Instant FRR for every Unraid version before a catalog build exists | Maintainer publishes bundles; UI waits safely |

---

## Install / update

Same pattern as Storage Guard and Thunderbolt Net—**two equivalent ways** to get the same plugin.

### Option A — Community Applications (recommended)

1. **Apps** tab → search **FRR** or **UnraidFRR**.  
2. **Install** or **Update**.  
3. Hard-refresh the browser (**Ctrl+Shift+R**).  
4. Open **Settings → FRR (FRRouting)**.  
5. Leave automation defaults (or adjust daemons) → **Apply**.

CA is fed from [unraid-templates](https://github.com/ibigsnet/unraid-templates) (`plugins/unraidfrr.xml`). Updates may lag a short time after GitHub.

### Option B — Plugins → Install Plugin (raw URL)

1. **Plugins → Install Plugin**.  
2. Paste a **raw** `.plg` URL (must end in `.plg`—not a GitHub blob page).  
3. **Install** → hard-refresh → **Settings → FRR**.

| Track | URL |
|-------|-----|
| **Latest (`main`)** | `https://raw.githubusercontent.com/ibigsnet/UnraidFRR/main/unraidfrr.plg` |

Pinned tags (when published):  
`https://raw.githubusercontent.com/ibigsnet/UnraidFRR/vVERSION/unraidfrr.plg`

After install, confirm the version under **Plugins**.

### After install

1. **Apply** with auto-download **Yes** so the plugin can fetch a matching package set when one exists in the catalog.  
2. Confirm status / `vtysh -v` when a build is available for your Unraid version.  
3. Optional: install **Thunderbolt Net** and enable OpenFabric for TB multi-hop.

If the catalog has no bundle for your Unraid version yet, status says so—leave auto-download on; no manual package hunt.

Details and versioning rules: [RELEASES.md](RELEASES.md).

---

## Uninstall (clean removal)

Use **Plugins → FRR (FRRouting) → Remove** (or remove via CA). The plugin **Method=remove** script is designed to avoid leaving Unraid in a broken state (same discipline as Storage Guard / Thunderbolt Net):

1. **Stop FRR** (service + common daemon processes) so nothing holds package files.  
2. **`removepkg`** packages listed in the plugin’s managed `MANIFEST.txt` when present (undoes what we installed into the live system).  
3. **Remove** emhttp plugin paths (`/usr/local/emhttp/plugins/UnraidFRR`, case variants).  
4. **Remove** flash state under `/boot/config/plugins/UnraidFRR` (config, package cache, marker)—full clean so a later reinstall starts fresh.  
5. **Does not** edit Unraid `network.cfg`, br0, Docker, or Thunderbolt Net.  
6. **Does not** require Thunderbolt Net to be installed or uninstalled first. If TBN remains, it simply sees FRR missing and stays on static underlay.

**After remove:** hard-refresh the browser. A reboot clears any stray RAM leftovers if `removepkg` could not run.

**Reinstall:** same CA or raw `.plg` URL as install. Automation will download packages again when the catalog has a match.

---

## With Thunderbolt Net

| Step | Action |
|------|--------|
| 1 | Install UnraidFRR → Apply (FRR present when catalog matches) |
| 2 | Install Thunderbolt Net → OpenFabric **On** if you want multi-hop |
| 3 | TBN writes marked OpenFabric conf for TB ifaces + lo; reloads FRR |
| 4 | Remove UnraidFRR alone → TBN degrades to static TB; no UI breakage |

Integration notes: [docs/integration-thunderboltnet.md](docs/integration-thunderboltnet.md).  
Mixed Unraid + Proxmox/Debian fabrics: [TBN fabric guide](https://github.com/ibigsnet/ThunderboltNet/blob/main/docs/fabric-proxmox-unraid.md).

---

## Documentation map

| Topic | Doc |
|-------|-----|
| Automation / catalog / Nvidia-style flow | [docs/automation-design.md](docs/automation-design.md) |
| Host-wide FRR vs Ethernet safety | [docs/scope-and-safety.md](docs/scope-and-safety.md) |
| Pairing with Thunderbolt Net | [docs/integration-thunderboltnet.md](docs/integration-thunderboltnet.md) |
| Maintainer package catalog | [packages/README.md](packages/README.md) |
| Install URLs, versioning, tags | [RELEASES.md](RELEASES.md) |
| Topic index | [docs/README.md](docs/README.md) |

---

## Related

- Thunderbolt Net: https://github.com/ibigsnet/ThunderboltNet  
- Storage Guard: https://github.com/ibigsnet/StorageGuard  
- FRRouting: https://frrouting.org/ · https://docs.frrouting.org/en/latest/fabricd.html  
