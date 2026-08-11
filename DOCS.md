# UnraidFRR — Documentation

**FRRouting for Unraid** — an opt-in, standalone plugin that installs and manages the [FRRouting](https://frrouting.org/) suite on Unraid.

---

## What this plugin does (in detail)

Unraid does **not** ship a full IP routing stack (no FRR, no OpenFabric, no OSPF/BGP daemons). UnraidFRR fills that gap as a **lifecycle and control panel** for FRR:

| Capability | What happens |
|------------|----------------|
| **Package install** | Runs Slackware-style `installpkg` on `.txz`/`.tgz` files you place under `/boot/config/plugins/UnraidFRR/packages/` (and again on array start if configured) |
| **Daemon selection** | Edits `/etc/frr/daemons` so chosen daemons are enabled (`zebra`, `fabricd`, optional `bgpd` / `ospfd` / …) |
| **Service start** | Best-effort start/restart of the FRR service after packages and daemons are set |
| **Status UI** | **Settings → FRR (FRRouting)** — FRR present?, version, package list, zebra/fabricd running? |
| **Companion marker** | Writes `/boot/config/plugins/UnraidFRR/companion.json` so other plugins (e.g. Thunderbolt Net) can detect that UnraidFRR is installed |
| **Safe idle** | If `packages/` is **empty** and FRR is not already on the system, Apply and array-start do **nothing harmful** |

### What it does *not* do

| Not in scope | Who does that instead |
|--------------|------------------------|
| Thunderbolt discovery, tbn IPs, cables | [Thunderbolt Net](https://github.com/ibigsnet/ThunderboltNet) |
| Generate OpenFabric interface stanzas for `thunderbolt*` | Thunderbolt Net (marked FRR conf blocks) |
| Edit Unraid **Network Settings** / `network.cfg` / br0 / eth bonds | Unraid core UI |
| Enable `net.ipv4.ip_forward` by default | Not UnraidFRR (Thunderbolt Net may enable forwarding only when OpenFabric is on **and** FRR is present) |
| Auto-enroll eth0/br0 into OSPF/BGP/OpenFabric | **Never** by default |
| Ship pre-built FRR `.txz` for every Unraid version (yet) | You drop packages in `packages/`; project Releases may host builds later |

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
2. Status will usually say packages missing / FRR not live — **expected**.  
3. Place compatible FRR packages in:

   ```text
   /boot/config/plugins/UnraidFRR/packages/
   ```

   See [packages/README.md](packages/README.md).  

4. Click **Apply** (or reboot so array-start runs install).  
5. Confirm `vtysh -v` (or the status table shows FRR present).

Until packages exist, the plugin is **idle and safe**.

---

## Settings (product defaults)

| Setting | Default | Meaning |
|---------|---------|---------|
| Install packages on array start | **Yes** | Re-run `installpkg` from flash packages when needed |
| Enable zebra | **Yes** | Kernel RIB/FIB interface — needed for almost all FRR use |
| Enable fabricd (OpenFabric) | **Yes** | OpenFabric daemon — Thunderbolt multi-host fabric happy path |
| Enable staticd | **Yes** | Static routes via FRR (optional but common) |
| Enable bgpd / ospfd / ospf6d / isisd / bfdd | **No** | Opt-in; more surface area |
| Start/restart FRR on Apply | **Yes** | Best-effort service start after config |
| Auto-download packages | **No** | Reserved until trusted package URLs exist |

---

## Behavior matrix

### Standalone (no Thunderbolt Net)

1. Plugin installs → Settings page works.  
2. Empty packages, no system FRR → **idle**.  
3. Packages present → install → enable daemons → start FRR.  
4. You own `/etc/frr/frr.conf` (or any tool you use). UnraidFRR may create a **minimal baseline** conf that does **not** add eth/br interfaces, and will not wipe existing conf.  
5. Uninstalling UnraidFRR does not require Thunderbolt Net.

### With Thunderbolt Net

1. Install **UnraidFRR** and get FRR packages live.  
2. Install **Thunderbolt Net**; leave OpenFabric **On** if you want multi-hop.  
3. Thunderbolt Net detects `vtysh`/`fabricd`, writes marked OpenFabric snippets for **TB ifaces + loopback**, reloads FRR.  
4. Remove UnraidFRR later → Thunderbolt Net degrades to **static underlay** (existing design).

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

## Packages (honest status)

Unraid is Slackware-based. This plugin is the **installer and UX** first. Compatible FRR builds must match your Unraid userspace.

- Drop files under `packages/` — see [packages/README.md](packages/README.md).  
- Project **GitHub Releases** may later host tested `.txz` bundles.  
- Until then: empty `packages/` = idle (by design).

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
