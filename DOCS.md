# Fabric Routing (FRR) — Documentation

How UnraidFRR installs and manages the [FRRouting](https://frrouting.org/) suite on Unraid—package catalog download on **Apply** (when Auto-download is Yes), daemon selection, and safe defaults for the rest of the network stack.

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

## Contents

- [When do I need this?](#when-do-i-need-this)
- [What it does](#what-it-does)
- [FRR vs Unraid Routing Table](#frr-vs-unraid-routing-table)
- [Supported Unraid versions](#supported-unraid-versions)
- [Install / update](#install-update)
- [Uninstall (clean removal)](#uninstall-clean-removal)
- [With Thunderbolt Net](#with-thunderbolt-net)
- [Documentation map](#documentation-map)
- [Related](#related)

## When do I need this?

| Your setup | Install UnraidFRR? |
|------------|--------------------|
| Two hosts, **one** TB cable, static IPs, file copy / SMB | **No** — Thunderbolt Net underlay is enough |
| **Ring / mesh / multi-hop** (reach a host through a neighbor) | **Yes** — packages here; OpenFabric policy on Thunderbolt Net |
| **Unraid + Proxmox** (or other Linux FRR) multi-node fabric | **Yes** — same OpenFabric idea as apt FRR on Proxmox |
| Want OSPF/BGP/etc. on Unraid for non-TB reasons | **Yes** — standalone FRR manager; enable daemons you need |
| Curious about USB4STREAM / `thunderbolt_stream` | **Wrong plugin** — that is a **Linux kernel** feature, not FRR |

**Roles:** UnraidFRR = **packages + daemons**. Thunderbolt Net = **tbn IPs + OpenFabric conf/metrics**. Neither installs the other.

On Thunderbolt Net, the orange chip **needs FRR packages** jumps to the Multi-hop companion card that points here.

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
| **UI** | **Settings → Network Settings → Fabric Routing** tab (with Routing Table) — status, channel, daemons |
| **Companion marker** | `companion.json` so Thunderbolt Net can detect UnraidFRR |

You choose **options** only (channel, which daemons, auto-download). You do **not** need to manually copy packages once Auto-download has populated the flash cache.

Deep design: [docs/automation-design.md](docs/automation-design.md).  
LAN safety: [docs/scope-and-safety.md](docs/scope-and-safety.md).  
FRR vs stock routing: [docs/frr-and-unraid-routing.md](docs/frr-and-unraid-routing.md).

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
| Replace stock Routing Table / Network Settings | Still Unraid’s job for IPs and simple statics |

---

## FRR vs Unraid Routing Table

| | Stock **Routing Table** | **Fabric Routing** (this plugin) |
|--|-------------------------|----------------------------------|
| Purpose | Unraid/kernel simple routes | FRR packages + dynamic/multipath routing |
| Multi-hop mesh / OpenFabric | Not the tool | **Yes** (with conf / Thunderbolt Net policy) |
| Configures br0 / Wi‑Fi IPs | Network Settings | **No** |
| Writes kernel routes | Unraid stack | **zebra** when protocols/statics are active |

**Can leverage:** multi-hop private fabrics, metrics (prefer fast paths), Proxmox/Linux FRR peers, AI multi-node underlays, inspect with `vtysh`.  
**Cannot expect:** FRR to replace shares, Docker networking, Tailscale, or auto-mesh your LAN.

Full write-up: [docs/frr-and-unraid-routing.md](docs/frr-and-unraid-routing.md).

---

## Supported Unraid versions

Packages are selected by **Unraid product version + arch**, not kernel.

| Status | Meaning |
|--------|---------|
| **Lab-confirmed** | Tested on real hardware (today: **7.3.2 x86_64**) |
| **Suggested** | Same catalog range; expected compatible (other **7.x x86_64**) |
| **Not in catalog** | No auto-download (e.g. 6.12, aarch64 until built) |

Matrix: [packages/SUPPORTED.md](packages/SUPPORTED.md).

---

## Install / update

Same pattern as Storage Guard and Thunderbolt Net—**two equivalent ways** to get the same plugin.

### Option A — Community Applications (recommended)

1. **Apps** tab → search **FRR** or **UnraidFRR**.  
2. **Install** or **Update**.  
3. Hard-refresh the browser (**Ctrl+Shift+R**).  
4. Open **Settings → Network Settings → Fabric Routing** (tab with eth / Routing Table / Thunderbolt).  
5. First time: Auto-download **Yes** → **Apply** (leave progress open). Later: Auto-download can stay **No**.

CA is fed from [unraid-templates](https://github.com/ibigsnet/unraid-templates) (`plugins/unraidfrr.xml`). Updates may lag a short time after GitHub.

### Option B — Plugins → Install Plugin (raw URL)

1. **Plugins → Install Plugin**.  
2. Paste a **raw** `.plg` URL (must end in `.plg`—not a GitHub blob page).  
3. **Install** → hard-refresh → **Settings → Network Settings → Fabric Routing**.

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

Use **Plugins → Fabric Routing (FRR) → Remove** (or remove via CA). The plugin **Method=remove** script is designed to avoid leaving Unraid in a broken state (same discipline as Storage Guard / Thunderbolt Net):

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
| Automation / catalog / flash cache flow | [docs/automation-design.md](docs/automation-design.md) |
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
