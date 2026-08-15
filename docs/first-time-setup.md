# First-time setup — Fabric Routing (FRR)

**Settings path:** Settings → Network Settings → **Fabric Routing**

Use this page the first time you install the plugin. It answers **do I need this?**, **what each setting does**, and **how to get packages installed safely**.

---

## Contents

- [1. Do you need Fabric Routing?](#1-do-you-need-fabric-routing)
- [2. How the pieces fit (plugin map)](#2-how-the-pieces-fit-plugin-map)
- [3. First-time checklist (packages on this host)](#3-first-time-checklist-packages-on-this-host)
- [4. What “success” looks like](#4-what-success-looks-like)
- [5. Settings reference (this page)](#5-settings-reference-this-page)
- [6. Optional next steps](#6-optional-next-steps)
- [7. Common mistakes](#7-common-mistakes)
- [Related](#related)

---

## 1. Do you need Fabric Routing?

| Your setup | Install this plugin? |
|------------|----------------------|
| One Unraid, normal LAN, SMB/NFS only | **No** |
| Two machines, **one** cable (Thunderbolt or Ethernet), static IPs, file copy | **No** — underlay is enough (Thunderbolt Net for Thunderbolt; Unraid Network Settings for eth) |
| **Multi-hop** (A reaches C through B), ring/mesh, alternate path when a link drops | **Yes** |
| Unraid + **Proxmox** / other Linux FRR peers on a private fabric | **Yes** |
| Multi-node lab / AI boxes that need real multipath L3 next to Unraid storage | **Yes** (packages here; path design is yours) |
| “I want USB4STREAM / raw Thunderbolt stream” | **Wrong tool** — that is a **kernel** feature; see [Thunderbolt Net — USB4STREAM](https://github.com/ibigsnet/ThunderboltNet/blob/main/docs/usb4stream.md) |

If every row you care about says **No**, stop here. You do not need FRR.

---

## 2. How the pieces fit (plugin map)

| Piece | What it does | What it does **not** do |
|-------|----------------|-------------------------|
| **Stock Unraid** (eth / br0 / Wi‑Fi / Routing Table) | IPs, bridges, simple static routes, shares | OpenFabric / multipath fabric daemons |
| **Fabric Routing (this plugin)** | Install **FRR packages**, choose daemons, start FRR | Assign Thunderbolt IPs; draw cables |
| **Thunderbolt Net** (optional) | Thunderbolt host-net underlay, peers, OpenFabric **policy** (metrics, participate) | Ship FRR `.txz` packages |
| **NBD Export** (optional) | Whole-disk over the network | Routing protocols |

```text
  Thunderbolt Net (optional)     Fabric Routing (this tab)
  ─────────────────────────      ─────────────────────────
  tbn IPs, cables, peers    →    FRR binaries + daemons
  OpenFabric policy/metrics      zebra / fabricd / …
```

**OpenFabric** = multi-hop fabric protocol (daemon name **`fabricd`**).  
**zebra** = FRR’s core process that can install routes into the Linux kernel.

More detail: [frr-and-unraid-routing.md](frr-and-unraid-routing.md).

---

## 3. First-time checklist (packages on this host)

Assumes Community Applications or raw `.plg` install already done. Supported Unraid versions: [SUPPORTED.md](../packages/SUPPORTED.md) (lab-confirmed vs suggested).

### Step A — Open the page

**Settings → Network Settings → Fabric Routing**  
Hard-refresh once (**Ctrl+Shift+R**) so you are not looking at a cached UI.

### Step B — First package download (once)

1. Under **Packages**, select **Latest** (or **Previous** only if you need rollback).  
2. Click **Download & Install packages**.  
3. A popup log opens (same idea as the Nvidia Driver plugin / plugin installs).  
4. Leave it open until the **DONE** button appears — do **not** use the red X early.  
5. Hard-refresh the page (**Ctrl+Shift+R**). Status should show FRR present / zebra / fabricd.  

**Advanced** (collapsed): install-on-array-start, catalog mirror, daemon toggles — save with **Apply settings** only (never downloads packages).

Typical time: a few minutes (package set is tens of MB).

### Step C — After Apply

1. Hard-refresh Fabric Routing again.  
2. Status should show **FRR Present**, **Catalog bundle Match** (for supported Unraid/arch), and packages listed under flash cache.  
3. Optional: set **Auto-download packages** back to **No** so later Applies do not hit the network. Reboots still rehydrate from flash if **Install on array start** is Yes.

### Step D — If download was interrupted

- Prefer **Plugins → remove Fabric Routing**, then reinstall (only when the plugin shows as installed).  
- If the plugin is half-broken (files on disk but missing from Plugins), use **Safe Mode** or console cleanup, then reinstall from [RELEASES.md](../RELEASES.md).

---

## 4. What “success” looks like

| Check | Expect |
|-------|--------|
| Status → FRR live | **Present** |
| Status → Catalog bundle | **Match** (on supported Unraid product + arch) |
| Status → Flash package cache | Two (or more) managed `.txz` names listed |
| Daemons | **zebra up**, **fabricd up** (if those are enabled) |
| Management | Unraid GUI/SSH still works; default route usually still on br0/Wi‑Fi — FRR does not steal mgmt by default |
| CLI (optional) | `vtysh -c 'show version'` prints FRR version |

**Having packages live does not by itself mesh your LAN.** Multi-hop on Thunderbolt still needs Thunderbolt Net OpenFabric policy. Ethernet fabric needs explicit FRR conf (advanced) or future Interfaces UI.

---

## 5. Settings reference (this page)

### Packages

| Setting | Default | When to use **Yes** | When to use **No** | What it does |
|---------|---------|---------------------|--------------------|--------------|
| **Package channel** | Latest | Normal installs | Only if catalog has a **Previous** rollback and you need it | Picks which catalog channel to resolve |
| **Auto-download packages** | **No** | First install, or upgrading package set | Day-to-day after cache is filled | On Apply: fetch catalog + `.txz` over HTTPS, verify sha256, store on flash |
| **Install on array start** | **Yes** | Almost always if you use FRR | You never want FRR after reboot without a manual Apply | Re-`installpkg` from **flash only** (no download) into RAM root |
| **Catalog URL** | empty | Leave empty | You host a private mirror | Override official `manifest.json` URL |

**Repercussion of Auto-download Yes on every Apply:** may re-check catalog and refresh packages; uses network and time.  
**Repercussion of Auto-download No with empty flash:** Apply cannot install FRR until you turn Yes or place packages manually.

### Core daemons

| Setting | Default | What it is | Leave Yes unless… |
|---------|---------|------------|-------------------|
| **zebra** | Yes | Talks to the Linux routing table | You are not using FRR at all |
| **fabricd** | Yes | OpenFabric multi-hop fabric | You only want packages for another protocol later |
| **staticd** | Yes | FRR-managed static routes (optional) | You only use Unraid’s Routing Table for statics |

### Optional protocols

| Setting | Default | Notes |
|---------|---------|--------|
| **bgpd / ospfd / ospf6d / isisd / bfdd** | **No** | Expert. Enabling the daemon does **not** put `br0` into OSPF/BGP by itself — you must configure FRR. Defaults stay LAN-safe. |

### Start on Apply

| Setting | Default | What it does |
|---------|---------|--------------|
| **Start/restart FRR on Apply** | **Yes** | After install/rehydrate, start FRR so Status/daemons match checkboxes |

Why these defaults: [defaults-rationale.md](defaults-rationale.md).

---

## 6. Optional next steps

| Goal | What to do |
|------|------------|
| Thunderbolt multi-hop / rings | Install [Thunderbolt Net](https://github.com/ibigsnet/ThunderboltNet) → underlay working → Advanced **OpenFabric** (metrics, participate). Packages stay on this tab. |
| Proxmox peer | Same OpenFabric idea on Debian/Proxmox FRR; see Thunderbolt Net mixed-fabric guide when using Thunderbolt |
| Ethernet-only fabric lab | Packages here + careful `frr.conf` / future Interfaces UI — do **not** put management Wi‑Fi/br0 into the fabric |
| Only needed packages once | Auto-download Yes → Apply → Auto-download No |

---

## 7. Common mistakes

| Mistake | Fix |
|---------|-----|
| Expect FRR after plugin install alone | Plugin install is **UI + scripts**. Packages need **Apply** (with Auto-download Yes) or array-start rehydrate of an existing cache |
| Close progress window early | Leave it open; re-Apply if unsure |
| “No match yet” forever | Unsupported Unraid/arch, or stale page — hard-refresh; see [SUPPORTED.md](../packages/SUPPORTED.md) |
| Think this replaces Routing Table | Different tool — [frr-and-unraid-routing.md](frr-and-unraid-routing.md) |
| Put br0/Wi‑Fi into OpenFabric “to try it” | Can break management; use private underlays |
| Confuse FRR with USB4STREAM | Kernel raw stream ≠ routing packages |

---

## Related

| Doc | Topic |
|-----|--------|
| [defaults-rationale.md](defaults-rationale.md) | Why each default exists |
| [frr-and-unraid-routing.md](frr-and-unraid-routing.md) | FRR vs stock routing; can/cannot leverage |
| [scope-and-safety.md](scope-and-safety.md) | LAN-safe posture |
| [integration-thunderboltnet.md](integration-thunderboltnet.md) | Pairing with Thunderbolt Net |
| [../packages/SUPPORTED.md](../packages/SUPPORTED.md) | Lab-confirmed vs suggested Unraid versions |
| [../DOCS.md](../DOCS.md) | Full product docs |
| [boot-blocker-plugin-install-stall.md](boot-blocker-plugin-install-stall.md) | Boot stuck after plugin install |
