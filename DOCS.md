# UnraidFRR — Documentation

**FRRouting for Unraid** — an opt-in, standalone plugin that installs and manages the [FRRouting](https://frrouting.org/) suite on Unraid.

---

## What this plugin does (in detail)

Unraid does **not** ship a full IP routing stack (no FRR, no OpenFabric, no OSPF/BGP daemons). UnraidFRR fills that gap as a **lifecycle and control panel** for FRR:

| Capability | What happens |
|------------|----------------|
| **Auto package pipeline** | Fetches a **catalog** (`manifest.json`), picks a bundle for this Unraid version + arch + channel, **downloads** `.txz` files, **sha256-verifies**, caches on flash |
| **Package install** | Runs `installpkg` into the live system; on array start, rehydrates RAM (Unraid does not persist packages) |
| **Daemon selection** | Edits `/etc/frr/daemons` for chosen daemons (`zebra`, `fabricd`, optional `bgpd` / `ospfd` / …) |
| **Service start** | Best-effort start/restart of FRR after install |
| **Status UI** | **Settings → FRR** — host version, bundle match, FRR present?, daemons running? |
| **Companion marker** | `/boot/config/plugins/UnraidFRR/companion.json` for Thunderbolt Net / others |
| **Safe until builds exist** | If the catalog has **no** matching bundle yet, status explains that; no broken half-install |

**Users never manually copy packages** (same idea as the Nvidia Driver plugin). Options only: channel, daemons, auto-download on/off.

Full design: [docs/automation-design.md](docs/automation-design.md).

### What it does *not* do

| Not in scope | Who does that instead |
|--------------|------------------------|
| Thunderbolt discovery, tbn IPs, cables | [Thunderbolt Net](https://github.com/ibigsnet/ThunderboltNet) |
| Generate OpenFabric interface stanzas for `thunderbolt*` | Thunderbolt Net (marked FRR conf blocks) |
| Edit Unraid **Network Settings** / `network.cfg` / br0 / eth bonds | Unraid core UI |
| Enable `net.ipv4.ip_forward` by default | Not UnraidFRR (TBN may when OpenFabric + FRR) |
| Auto-enroll eth0/br0 into OSPF/BGP/OpenFabric | **Never** by default |
| Instant FRR on every Unraid version day one | Maintainer publishes catalog bundles; until then UI waits safely |

---

## Why it exists (and why it is separate)

Installing a routing suite is **more invasive** than a Thunderbolt settings page: packages on the flash, boot-time `installpkg`, long-running daemons under `/etc/frr`.

Same pattern as **Unassigned Devices** vs optional deeper companions:

| Plugin | Role | Invasive? |
|--------|------|-----------|
| **UnraidFRR** (this) | Get FRR onto Unraid + which daemons run | Higher (opt-in) |
| **Thunderbolt Net** (optional) | TB underlay + OpenFabric *policy* when FRR exists | Lower |

- Users who only want TB host-to-host **static** IPs never need this plugin.  
- Users who want **multi-hop / ring / mesh** (OpenFabric) or other FRR protocols need FRR **somewhere** — UnraidFRR is the packaged path.  
- FRR is also useful **without** Thunderbolt (lab routing, advanced users).

Neither plugin PHP-`require`s the other. Missing either side must not crash Settings pages.

---

## Scope: not Thunderbolt-only

UnraidFRR manages a **host-wide** routing suite (same class of software as FRR on Debian/Proxmox).  

**Defaults are written for LAN safety** (no auto protocol on br0/eth0, no network.cfg edits, no ip_forward toggle).  

Full honesty about Ethernet risk: [docs/scope-and-safety.md](docs/scope-and-safety.md).

---

## Install

### From Community Applications (when listed)

1. **Apps** → search **FRR** or **UnraidFRR**  
2. Install  
3. Hard-refresh the browser (**Ctrl+Shift+R**)  
4. Open **Settings → FRR (FRRouting)**

### From raw plugin URL

**Plugins → Install Plugin** → paste:

```text
https://raw.githubusercontent.com/ibigsnet/UnraidFRR/main/unraidfrr.plg
```

See [RELEASES.md](RELEASES.md) for pins/tags when published.

### After install (first run)

1. Open **Settings → FRR (FRRouting)**.  
2. Leave **Auto-download = Yes**, channel **Latest**, daemons as desired.  
3. Click **Apply** — plugin downloads + installs when a catalog bundle matches your Unraid version.  
4. Confirm status shows FRR present / `vtysh -v`.  

If the catalog has no build for your Unraid version yet, status says so clearly; leave auto-download on and Apply again after a plugin/catalog update. **No manual package download required.**

---

## Settings (product defaults)

| Setting | Default | Meaning |
|---------|---------|---------|
| Package channel | **latest** | Catalog channel to auto-download |
| Auto-download packages | **Yes** | Fetch catalog + packages automatically |
| Install on array start | **Yes** | Reinstall into RAM after reboot |
| Enable zebra | **Yes** | Kernel RIB/FIB interface |
| Enable fabricd (OpenFabric) | **Yes** | OpenFabric for multi-host TB fabric path |
| Enable staticd | **Yes** | Common |
| Enable bgpd / ospfd / ospf6d / isisd / bfdd | **No** | Opt-in surface area |
| Start/restart FRR on Apply | **Yes** | Bring daemons up |
| Catalog URL | (official) | Advanced mirror override |

---

## Behavior matrix

### Standalone (no Thunderbolt Net)

1. Plugin installs → Settings page works.  
2. Apply with auto-download **Yes** → catalog → download → installpkg → daemons → start (when a bundle exists).  
3. If catalog has no match for this Unraid version → safe wait message (no manual package hunt).  
4. You may still edit `/etc/frr/frr.conf` for advanced use; plugin baseline does not enroll eth/br.  
5. Uninstall does not require Thunderbolt Net.

### With Thunderbolt Net

1. Install **UnraidFRR** → Apply (auto FRR when catalog has a build).  
2. Install **Thunderbolt Net**; OpenFabric **On** for multi-hop.  
3. TBN detects FRR, writes marked OpenFabric snippets for **TB + lo**, reloads FRR.  
4. Remove UnraidFRR later → TBN degrades to static underlay.  

Details: [docs/integration-thunderboltnet.md](docs/integration-thunderboltnet.md).  
Mixed Unraid + Proxmox/Debian fabrics: [Thunderbolt Net fabric guide](https://github.com/ibigsnet/ThunderboltNet/blob/main/docs/fabric-proxmox-unraid.md).

---

## Files and paths

| Path | Purpose |
|------|---------|
| `/boot/config/plugins/UnraidFRR/UnraidFRR.cfg` | Plugin settings (survives reboot) |
| `/boot/config/plugins/UnraidFRR/packages/` | Your FRR `.txz` / `.tgz` (and optional `MANIFEST.txt`) |
| `/boot/config/plugins/UnraidFRR/companion.json` | Marker for other plugins |
| `/usr/local/emhttp/plugins/UnraidFRR/` | Plugin UI and scripts (RAM, reinstalled from `.plg`) |
| `/etc/frr/daemons` | Which FRR daemons start (when packages installed) |
| `/etc/frr/frr.conf` | FRR running config (shared; TBN uses marked blocks only) |
| `/var/log/unraidfrr.log` | Install/apply log |

---

## Uninstall

**Plugins → UnraidFRR → Remove**

- Removes plugin UI/scripts from the emhttp tree.  
- **Keeps** flash config and `packages/` by default (so reinstall is easy).  
- FRR binaries in the RAM root may remain until reboot; stop with `systemctl stop frr` if needed.  
- Does not require Thunderbolt Net to be present or absent.

---

## Packages (automated)

Unraid is Slackware-based. The plugin is the **full package owner**:

1. Read `packages/manifest.json` (or your catalog URL)  
2. Select bundle for **this** Unraid version + arch + channel  
3. Download + sha256 verify into flash cache  
4. `installpkg` + enable daemons + start  

Maintainer builds and publishes catalog entries — see [packages/README.md](packages/README.md) and [docs/automation-design.md](docs/automation-design.md).  
Until a matching bundle exists, the plugin **waits safely** (no user package hunting).

---

## Safety summary

| Concern | Default |
|---------|---------|
| Empty packages | No installpkg, no forced daemon start |
| br0 / eth0 Unraid UI | Untouched |
| Docker / VMs | Untouched |
| IP forwarding | Not set by this plugin |
| LAN in OpenFabric/OSPF/BGP | Not auto-configured |
| Thunderbolt Net missing | Fully supported |

Deep dive: [docs/scope-and-safety.md](docs/scope-and-safety.md).

---

## Related

| | |
|--|--|
| **This plugin** | https://github.com/ibigsnet/UnraidFRR |
| **Thunderbolt Net** | https://github.com/ibigsnet/ThunderboltNet |
| **CA templates repo** | https://github.com/ibigsnet/unraid-templates |
| **FRRouting** | https://frrouting.org/ · https://github.com/FRRouting/frr |
| **OpenFabric (fabricd)** | https://docs.frrouting.org/en/latest/fabricd.html |
