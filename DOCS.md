# Fabric Routing (FRR) — Documentation

How FabricRouting installs and manages the [FRRouting](https://frrouting.org/) suite on Unraid: sha256-verified packages via **Download & Install packages**, the `/etc/frr` config it builds from flash, daemon selection, and safe defaults for the rest of the network stack.

**Install (recommended):** Apps (Community Applications) → search **FRR** or **FabricRouting** → Install.

**Manual install:** Plugins → Install Plugin →  
`https://raw.githubusercontent.com/ibigsnet/FabricRouting/main/fabricrouting.plg`

**Support:** [Thunderbolt Net forum thread](https://forums.unraid.net/topic/200065-plugin-thunderbolt-net-host-to-host-networking-over-thunderbolt-345-and-usb44v2/) (shared with Thunderbolt Net until FRR has its own thread) · [GitHub Issues](https://github.com/ibigsnet/FabricRouting/issues)  
**Source / project:** [github.com/ibigsnet/FabricRouting](https://github.com/ibigsnet/FabricRouting)  
**CA templates:** [ibigsnet/unraid-templates](https://github.com/ibigsnet/unraid-templates)  
**Support development:** [Patreon](https://www.patreon.com/cw/IBIGSNet) · [PayPal](https://www.paypal.com/paypalme/RifleJock)

`README.md` is only the short Unraid Plugins-list blurb (`**Name**` + one paragraph). Unraid runs it through Markdown for the Plugins description—keep it tiny. This file is the full documentation.

**Related plugin:** [Thunderbolt Net](https://github.com/ibigsnet/ThunderboltNet) — Thunderbolt host-net underlay and OpenFabric *policy* when FRR is present. FabricRouting does **not** require it.

---

## Contents

- [First-time setup](#first-time-setup)
- [When do I need this?](#when-do-i-need-this)
- [What it does](#what-it-does)
- [FRR config files](#frr-config-files)
- [Fabric route guard](#fabric-route-guard)
- [FRR vs Unraid Routing Table](#frr-vs-unraid-routing-table)
- [Supported Unraid versions](#supported-unraid-versions)
- [Install / update](#install-update)
- [Uninstall (clean removal)](#uninstall-clean-removal)
- [With Thunderbolt Net](#with-thunderbolt-net)
- [Documentation map](#documentation-map)
- [Related](#related)

## First-time setup

**New to FRR or this plugin?** Start here:

**[docs/first-time-setup.md](docs/first-time-setup.md)** — do I need it, plugin map, first package download, what each setting means, success checks, common mistakes.

## When do I need this?

| Your setup | Install FabricRouting? |
|------------|--------------------|
| Two hosts, **one** Thunderbolt cable, static IPs, file copy / SMB | **No** — Thunderbolt Net underlay is enough |
| **Ring / mesh / multi-hop** (reach a host through a neighbor) | **Yes** — packages here; OpenFabric policy on Thunderbolt Net |
| **Unraid + Proxmox** (or other Linux FRR) multi-node fabric | **Yes** — same OpenFabric idea as apt FRR on Proxmox |
| Want OSPF/BGP/etc. on Unraid for non-Thunderbolt reasons | **Yes** — standalone FRR manager; enable daemons, config in `frr.user.conf` |
| Curious about USB4STREAM / `thunderbolt_stream` | **Wrong plugin** — that is a **Linux kernel** feature, not FRR |

**Roles:** FabricRouting = **packages, daemons and `/etc/frr`**. Thunderbolt Net = **tbn IPs + the OpenFabric fragment (area, metrics, interfaces)**. Neither installs the other.

---

## What it does

Unraid does not ship FRR. This plugin **owns the FRR lifecycle** on the host:

| Area | Behavior |
|------|----------|
| **Catalog** | `packages/manifest.json` bundled with the plugin version: package names, URLs and **sha256** per **Unraid product version** + arch (not kernel version). No catalog lookup over the network |
| **Download** | Fetches `.txz` packages over HTTPS (TLS verified) and checks **sha256** before anything reaches flash. Any failure stops the job and leaves the previous set untouched |
| **Install** | `MANIFEST.txt` on flash lists each package with its sha256; every file is re-checked before `upgradepkg --install-new`. Files it does not list are never installed. **Array start** reinstalls into RAM after reboot (no network) |
| **Config** | Builds `/etc/frr/frr.conf`, `daemons` and `vtysh.conf` from flash — see [FRR config files](#frr-config-files) |
| **Start** | `/usr/sbin/frrinit.sh start`, or `restart` when the config or packages changed |
| **UI** | **Settings → Network Settings → Fabric Routing** tab — status, packages, daemons, route guard |
| **Companion marker** | `companion.json` so Thunderbolt Net can detect FabricRouting and its reload hook |

Deep design: [docs/automation-design.md](docs/automation-design.md).  
LAN safety: [docs/scope-and-safety.md](docs/scope-and-safety.md).  
FRR vs stock routing: [docs/frr-and-unraid-routing.md](docs/frr-and-unraid-routing.md).

### Product defaults

| Setting | Default | Why |
|---------|---------|-----|
| Package channel | **latest** | Recommended package set |
| Download & Install | Explicit button | Only network use; never on Apply settings, boot or array start |
| Install on array start | **Yes** | Unraid RAM root loses packages on reboot |
| zebra / fabricd / staticd | **Yes** | Routing core + OpenFabric |
| bgpd / ospfd / ospf6d / isisd / bfdd | **No** | Opt-in surface area |
| Start/restart FRR | **Yes** | Bring the stack up; restart after config or package changes |
| Fabric route guard | **Yes** | OpenFabric routes only inside `10.254.0.0/16`, `10.255.0.0/16`, `fc00::/7` |

### What it does *not* do

| Not in scope | Who owns it |
|--------------|-------------|
| Thunderbolt cables / tbn IPs | Thunderbolt Net |
| OpenFabric stanzas on `thunderbolt*` | Thunderbolt Net (fragment file in its flash folder) |
| Unraid eth0/br0 / `network.cfg` | Unraid Network Settings |
| Auto-enroll br0 into OSPF/BGP/OpenFabric | **Never** |
| IP forwarding | Not changed by this plugin |
| Replace stock Routing Table / Network Settings | Still Unraid’s job for IPs and simple statics |

---

## FRR config files

Fabric Routing owns `/etc/frr/frr.conf`, `/etc/frr/daemons` and `/etc/frr/vtysh.conf`. `/etc/frr` lives in RAM, so the plugin rebuilds these files from flash on **Apply settings**, on **Download & Install packages**, at **array start**, and when a companion plugin runs `scripts/frr-reload`.

`frr.conf` is built from, in order:

1. The Fabric Routing baseline (`frr defaults traditional`, hostname, syslog) and the [fabric route guard](#fabric-route-guard).
2. The Thunderbolt Net fragment `/boot/config/plugins/ThunderboltNet/frr/thunderboltnet-openfabric.conf`, when it exists and is not empty.
3. Your own file `/boot/config/plugins/FabricRouting/frr.user.conf`, when it exists.

Each piece sits between `! BEGIN FabricRouting include: …` and `! END …` lines. **Do not edit `/etc/frr/frr.conf` or use `write memory` for changes you want to keep**: the next rebuild replaces the file. Put them in `frr.user.conf`, then press **Apply settings** or run:

```bash
/usr/local/emhttp/plugins/FabricRouting/scripts/frr-reload
```

If `/etc/frr/frr.conf` held hand edits when you upgraded to 2026.10.30aa, they are copied into `frr.user.conf` once.

`daemons` gets the switches from **Advanced**; `fabricd` is also on whenever the Thunderbolt Net fragment exists. FRR restarts only when one of these files or the packages changed. (`frrinit.sh reload` needs Python, which Unraid does not ship, so the plugin restarts instead; OpenFabric adjacencies re-form within seconds.)

**Companion contract** (files only, no PHP coupling): a plugin writes or deletes its fragment file, then runs `scripts/frr-reload`. It waits up to 120 s for a running Fabric Routing job. Exit status 0 means the config was written and FRR is up when it should be.

## Fabric route guard

With **Fabric route guard = Yes** (default) zebra installs OpenFabric routes only inside the guard prefixes:

```text
ip protocol openfabric route-map FR-FABRIC-V4     permits 10.254.0.0/16, 10.255.0.0/16
ipv6 protocol openfabric route-map FR-FABRIC-V6   permits fc00::/7
```

The defaults match Thunderbolt Net's loopback (`10.254.x.x`) and link (`10.255.x.x`) addresses. A peer that advertises your LAN, a default route or anything else stays in the OpenFabric database but never reaches the kernel routing table, so `br0` management traffic cannot be pulled onto the fabric.

If your fabric uses other addresses (for example Proxmox peers with their own loopback range), add them to **Guard IPv4 prefixes** / **Guard IPv6 prefixes** (space-separated) and Apply. Invalid entries and `/0` are ignored and logged. Set the guard to **No** only when you deliberately route other networks over the fabric. BGP/OSPF are not filtered by this guard; filter them in `frr.user.conf`.

---

## FRR vs Unraid Routing Table

| | Stock **Routing Table** | **Fabric Routing** (this plugin) |
|--|-------------------------|----------------------------------|
| Purpose | Unraid/kernel simple routes | FRR packages + dynamic/multipath routing |
| Multi-hop mesh / OpenFabric | Not the tool | **Yes** (with Thunderbolt Net policy or `frr.user.conf`) |
| Configures br0 / Wi‑Fi IPs | Network Settings | **No** |
| Writes kernel routes | Unraid stack | **zebra** when protocols/statics are active |

**Can leverage:** multi-hop private fabrics, metrics (prefer fast paths), Proxmox/Linux FRR peers, multi-node underlays, inspect with `vtysh`.  
**Cannot expect:** FRR to replace shares, Docker networking, Tailscale, or auto-mesh your LAN.

Full write-up: [docs/frr-and-unraid-routing.md](docs/frr-and-unraid-routing.md).

---

## Supported Unraid versions

The plugin needs **Unraid 7.0 or newer** (the package catalog starts at 7.0.0). Packages are selected by **Unraid product version + arch**, not kernel.

| Status | Meaning |
|--------|---------|
| **Lab-confirmed** | Tested on real hardware (today: **7.3.2 x86_64**) |
| **Suggested** | Same catalog range; expected compatible (other **7.x x86_64**) |
| **Not in catalog** | Nothing to download (aarch64 until built) |

Matrix: [packages/SUPPORTED.md](packages/SUPPORTED.md).

---

## Install / update

### Option A — Community Applications (recommended)

1. **Apps** tab → search **FRR** or **FabricRouting**.  
2. **Install** or **Update**.  
3. Hard-refresh the browser (**Ctrl+Shift+R**).  
4. Open **Settings → Network Settings → Fabric Routing**.  
5. First time: **Download & Install packages** (leave the window open until **Done**).

CA is fed from [unraid-templates](https://github.com/ibigsnet/unraid-templates) (`plugins/fabricrouting.xml`). Updates may lag a short time after GitHub.

### Option B — Plugins → Install Plugin (raw URL)

1. **Plugins → Install Plugin**.  
2. Paste a **raw** `.plg` URL (must end in `.plg`—not a GitHub blob page).  
3. **Install** → hard-refresh → **Settings → Network Settings → Fabric Routing**.

| Track | URL |
|-------|-----|
| **Production (`main`)** | `https://raw.githubusercontent.com/ibigsnet/FabricRouting/main/fabricrouting.plg` |

Pinned tags (when published):  
`https://raw.githubusercontent.com/ibigsnet/FabricRouting/vVERSION/fabricrouting.plg`

Updating the plugin while FRR runs rebuilds `/etc/frr` in the background (one FRR restart).

### After install

1. **Download & Install packages** (Latest).  
2. Confirm status / `vtysh -v`.  
3. Optional: install **Thunderbolt Net** and enable OpenFabric for Thunderbolt multi-hop.

If the catalog has no package set for your Unraid version, the status says so and nothing is downloaded.

Details and versioning rules: [RELEASES.md](RELEASES.md).

---

## Uninstall (clean removal)

Use **Plugins → Fabric Routing (FRR) → Remove** (or remove via CA). The remove step runs `scripts/frr-uninstall --all`:

1. **Stop FRR** that came from this plugin's packages: `frrinit.sh stop`, then the daemons named in `/var/run/frr/*.pid` (TERM, KILL after 10 s). Same-named processes elsewhere (for example in containers) are never touched.
2. **`removepkg`** only packages listed in the plugin's `MANIFEST.txt` whose names end in `_unraid`. FRR or libyang installed by anything else stays.
3. **Remove** the `frr.conf` / `vtysh.conf` this plugin wrote, FRR runtime folders (only when it removed its own FRR), the log and the lock.
4. **Remove** flash state under `/boot/config/plugins/FabricRouting` (settings, package cache, `frr.user.conf`) and the plugin files.
5. **Does not** edit Unraid `network.cfg`, br0, Docker, or Thunderbolt Net (its fragment under `/boot/config/plugins/ThunderboltNet/frr` stays).

Copy `frr.user.conf` somewhere else first if you want to keep it.

**Half-installed plugin:** run `bash /usr/local/emhttp/plugins/FabricRouting/scripts/frr-uninstall --all` by hand.

**Reinstall:** same CA or raw `.plg` URL, then **Download & Install packages**.

---

## With Thunderbolt Net

| Step | Action |
|------|--------|
| 1 | Install FabricRouting → Download & Install packages |
| 2 | Install Thunderbolt Net → OpenFabric **On** if you want multi-hop |
| 3 | TBN writes its OpenFabric fragment on flash and runs `scripts/frr-reload`; Fabric Routing rebuilds `frr.conf` and restarts FRR |
| 4 | After a reboot, array start rebuilds `frr.conf` with the fragment before FRR starts |
| 5 | Remove FabricRouting alone → TBN degrades to static Thunderbolt; no UI breakage |

Integration notes: [docs/integration-thunderboltnet.md](docs/integration-thunderboltnet.md).  
Mixed Unraid + Proxmox/Debian fabrics: [TBN fabric guide](https://github.com/ibigsnet/ThunderboltNet/blob/main/docs/fabric-proxmox-unraid.md).

---

## Documentation map

| Topic | Doc |
|-------|-----|
| **First-time setup** | [docs/first-time-setup.md](docs/first-time-setup.md) |
| Automation / catalog / flash cache flow | [docs/automation-design.md](docs/automation-design.md) |
| Boot and array-start lifecycle | [docs/boot-lifecycle.md](docs/boot-lifecycle.md) |
| Host-wide FRR vs Ethernet safety | [docs/scope-and-safety.md](docs/scope-and-safety.md) |
| Pairing with Thunderbolt Net | [docs/integration-thunderboltnet.md](docs/integration-thunderboltnet.md) |
| Package catalog | [packages/README.md](packages/README.md) |
| Install URLs (`stable` / freezes) | [RELEASES.md](RELEASES.md) |
| Topic index | [docs/README.md](docs/README.md) |

---

## Related

- Thunderbolt Net: https://github.com/ibigsnet/ThunderboltNet  
- Storage Guard: https://github.com/ibigsnet/StorageGuard  
- FRRouting: https://frrouting.org/ · https://docs.frrouting.org/en/latest/fabricd.html
