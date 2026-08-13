# Fabric Routing (FRR) + companions — product roadmap & ambitions

**Date:** 2026-08-12  
**Lab-confirmed pattern:** two Unraid **7.3.2** x86_64 hosts; FRR **10.7.0** + libyang **2.1.148**; OpenFabric up on private **2.5G eth0** underlay (see [lab-two-node-fabric.md](lab-two-node-fabric.md)).

This document is the **north star** for UI, docs, CA, forum, and what we build next. It is intentionally ambitious and honest.

---

## Contents

- [Ambition (why this exists)](#ambition-why-this-exists)
- [Who it is for](#who-it-is-for)
- [Product split (roles)](#product-split-roles)
- [What works today](#what-works-today)
- [Gap: interface & metric policy UI](#gap-interface--metric-policy-ui)
- [Roadmap phases](#roadmap-phases)
- [Install / uninstall quality bar](#install--uninstall-quality-bar)
- [Documentation multi-pass plan](#documentation-multi-pass-plan)
- [User-facing visibility (what they should see)](#user-facing-visibility-what-they-should-see)
- [Community Applications](#community-applications)
- [Forum strategy](#forum-strategy)
- [Messaging for users “tired of waiting on Unraid”](#messaging-for-users-tired-of-waiting-on-unraid)
- [Non-goals / safety](#non-goals--safety)

---

## Ambition (why this exists)

Unraid’s stock **Network Settings** are excellent for a **NAS on a home LAN**. They are not a full **Linux routing workstation**.

Meanwhile the rest of the homelab world already runs:

- **FRRouting** (zebra + OpenFabric / OSPF / BGP) on Proxmox, Debian, Ubuntu  
- **Multi-path Thunderbolt / 10G / multi-homed** fabrics between mini-PCs and storage  
- **AI/inference boxes** (Strix Halo–class, DGX Spark–class, multi-GPU peers) that need **fast, multi-hop, multipath L3** next to a roomy Unraid array  

**Ambition:** bring **modern Linux networking quality-of-life** to Unraid **without** waiting for official product support for FRR, OpenFabric, TB host-net policy, or multi-node fabric UX.

| Theme | Outcome |
|-------|---------|
| **Adjacent Proxmox** | Same OpenFabric / static underlay ideas as Debian FRR; Unraid is a first-class fabric member, not a dumb SMB island |
| **Multi-node LLM / inference** | Fast private paths between Unraid storage and several AI peers (TB, 10G, multi-homed) with **metrics** that prefer the fat path |
| **Homelab rings** | Multi-hop reachability when a cable is missing; failover when an alternate path exists |
| **Operator UX** | Optional catalog download + flash cache; policy in the Unraid UI, not only hand-edited `frr.conf` |
| **Respect Unraid** | Defaults **never** hijack `br0` / Wi‑Fi management; LAN-safe unless the user opts in |

This is **not** “replace Unraid networking.” It is **optional power tooling** for people who already outgrew a single static cable.

---

## Who it is for

| Audience | Need |
|----------|------|
| Unraid + **Proxmox** cluster | Shared L3 fabric; storage on Unraid, VMs/LXC on Proxmox |
| **Multi-node AI** (Strix Halo, Spark-class, GPU boxes) | High-bandwidth private underlay; optional multi-hop; block/file jobs (NBD/SMB) over the right path |
| Thunderbolt **rings / multi-host** | Host-net underlay (TBN) + FRR OpenFabric when one hop is not enough |
| Advanced homelab | 10G + 2.5G + TB metrics, clear “which iface is in the fabric” |
| Everyday media NAS only | **Can ignore** — leave plugins uninstalled |

---

## Product split (roles)

Keep the split **clear forever** (docs, UI, forum, CA blurbs):

| Plugin | Owns | Does **not** own |
|--------|------|------------------|
| **Fabric Routing (FabricRouting)** | FRR **packages**, install/rehydrate, daemon on/off, **global** fabric policy surface over time, status | TB discovery, cable UX, tbn IP assignment |
| **Thunderbolt Net** | TB host-net underlay, peers, tbn IPs, **per-TB-iface** OpenFabric participate/metric (today), OpenFabric conf **marked blocks** for `thunderbolt*` | Shipping FRR `.txz` |
| **NBD Export** | Block device over network (imaging, remote disk) | Routing protocols |
| **Stock Unraid** | br0, Wi‑Fi, GUI mgmt, array, Docker | FRR |

**Long-term UX goal:** users can answer in the UI:

1. “Is FRR installed and healthy?”  
2. “Which interfaces participate in OpenFabric (or OSPF later)?”  
3. “What metric / cost does each path get (auto from bandwidth vs manual)?”  
4. “Am I still safe on Wi‑Fi / br0 for management?”

Today **(1)** is Fabric Routing; **(2)(3)** for **Thunderbolt only** are Thunderbolt Net; **eth0/10G/wlan** policy is **lab/manual** or future Fabric Routing **Interfaces** tab.

---

## What works today (2026-08-12)

| Capability | Status |
|------------|--------|
| Automated FRR download (catalog + sha256 + installpkg) | **Yes** — first bundle FRR **10.7.0** / Unraid **7.x x86_64** |
| Fabric Routing Settings tab | Packages + daemon toggles + status |
| Thunderbolt Net OpenFabric participate + metric auto/manual | **Per thunderbolt\* iface** |
| OpenFabric lab on **eth0** (manual conf) | **Proven** Machine A ↔ Machine B |
| UI to pick eth0/10G/wlan for fabric | **No** (gap) |
| Multi-protocol OSPF/BGP wizard | **No** (daemons can install; conf is advanced) |
| CA listing | Template exists (`unraid-templates`); keep Overview current |
| Forum launch post | **Not yet** — strategy below |

---

## Gap: interface & metric policy UI

### Why users need it

Without it, only Thunderbolt paths get guided policy. Real fabrics mix:

- TB4 host-net  
- 10G / 2.5G Ethernet  
- Occasional USB NIC lab links  
- Explicit **exclude** of `wlan0` / `br0` / Docker bridges  

Metrics matter: a 40G TB path should beat 2.5G for multi-hop preference (auto: `reference_mbps / trained_mbps`, or manual integer).

### Proposed model (Fabric Routing **Interfaces** section or tab)

**Do not** auto-enroll everything. Explicit opt-in list:

| Field | Meaning |
|-------|---------|
| Interface | From live `ip link` (filter junk: veth, docker0, virbr, shim-*, by default hide) |
| Role | **Ignore** (default) · **OpenFabric active** · **OpenFabric passive** · (later: OSPF/BGP) |
| Metric mode | **Auto** (from link speed / trained rate) · **Manual** |
| Metric | Integer when manual; show computed auto cost |
| IPv4 / IPv6 | Toggle protocol families on that iface |
| Notes | Optional label (“AI peer uplink”) |

**Global fabric settings** (same page or top of Fabric Routing):

| Field | Default idea |
|-------|----------------|
| OpenFabric area / net / system-id | Safe generators; override optional |
| Metric reference Mb/s | e.g. 100000 (100G ref) or 40000 (TB4-class) |
| Never-touch list | `wlan0`, `br0`, `docker0`, `virbr0`, `tailscale*`, `wg*` unless user unlocks |
| Apply policy | Writes marked block in `/etc/frr/frr.conf` (same discipline as TBN) |

### Interaction with Thunderbolt Net

| Option | Recommendation |
|--------|----------------|
| **A — TBN keeps TB-only policy; FRR UI does eth/general** | Clear ownership; risk of two writers if both touch same iface |
| **B — FRR becomes single “fabric policy” writer; TBN only underlay** | Cleaner long-term; bigger TBN migration |
| **C — Shared marked-conf convention; last Apply wins with banner** | Pragmatic mid-term |

**Recommended path:** **C short-term**, migrate toward **B** for non-TB ifaces, keep TBN as the **best TB underlay + per-port TB OpenFabric** UX (already built). Document: “TB metrics: Thunderbolt tab; Ethernet/other: Fabric Routing Interfaces (when shipped).”

### Implementation sketch (phases)

1. **Read-only Interfaces table** on Fabric Routing: live ifaces, speed, whether currently in `frr.conf` / TBN participate list, zebra/fabricd up.  
2. **Editable policy** for non-TB ifaces → generate marked conf block `! BEGIN FabricRouting Fabric Policy` …  
3. **Metric auto** from `ethtool` / sysfs speed (and TBN trained Mbps when iface is thunderbolt*).  
4. **Conflict UI** if TBN and FRR both claim the same iface.  
5. Optional: import TBN participate rows into the FRR table as read-only “managed by Thunderbolt Net”.

---

## Roadmap phases

| Phase | Deliverable | Lab |
|-------|-------------|-----|
| **P0** | Package pipeline + first catalog (done) | Done |
| **P1** | Uninstall/reinstall + auto-download proven; fix start path (frrinit) | Primary lab host |
| **P2** | Status UX: version, neighbors snippet, “mgmt still on wlan/br0” health line | Both |
| **P3** | **Interfaces** table (read-only) + docs | Both |
| **P4** | **Interfaces** policy apply (eth/10G opt-in) + metrics | Both |
| **P5** | Docs multi-pass + CA Overview refresh + forum launch | — |
| **P6** | Proxmox peer guide (mixed fabric) | + Proxmox box |
| **P7** | AI multi-node guide (Strix/Spark-class examples) | Optional peers |
| **P8** | aarch64 bundle if needed | — |

---

## Install / uninstall quality bar

Every release must pass on a lab Unraid:

| Step | Pass |
|------|------|
| CA or raw `.plg` install | Plugin appears under Network Settings → Fabric Routing |
| Apply (auto-download Yes) | Packages on flash + `vtysh` present |
| Array restart / rehydrate | FRR binaries return |
| `plugin remove` | `removepkg` via MANIFEST; daemons dead; emhttp + flash config gone; **no** leftover Settings pages |
| Mgmt path | Default route / Wi‑Fi / br0 still work |
| Reinstall + Apply | Clean download + install again |

**Test host preference:** primary lab host for full cycle; light checks on a second peer.

Known follow-ups:

- Uninstall must also call `/usr/sbin/frrinit.sh stop` (not only `/usr/lib/frr/...`)  
- Preserve optional user `frr.conf` backup on remove? (product decision: clean remove vs keep `/boot/config/.../frr.conf.bak`)  
- Harmless vtysh.conf / conf warning noise — polish later  

---

## Documentation multi-pass plan

Run **several deliberate passes** (not one giant rewrite). Public tone only — no chat leftovers.

| Pass | Focus | Files |
|------|--------|-------|
| **1 — Ambition & map** | Why / who / roles / non-goals | `README.md`, `DOCS.md` opener, this roadmap |
| **2 — Install truth** | Catalog works; uninstall/reinstall; version labels | `DOCS.md`, `RELEASES.md`, `packages/README.md` |
| **3 — Operator day-2** | Status meanings, neighbors, when to use eth vs TB | new `docs/day-two-ops.md`, how-to style |
| **4 — Interfaces & metrics** | When UI ships; until then manual eth OpenFabric recipe | `docs/interfaces-and-metrics.md` |
| **5 — Integration** | TBN, Proxmox, NBD, AI peers | `integration-*.md`, TBN fabric docs cross-links |
| **6 — Safety** | LAN-safe defaults; never-touch Wi‑Fi story | `scope-and-safety.md` |
| **7 — Forum/CA freeze** | Short Overview + FAQ pulled from passes 1–6 | CA xml, forum draft |

Each pass: fix TOC, kill awkward tables, align names (**Fabric Routing** public / FabricRouting id).

---

## User-facing visibility (what they should see)

### Fabric Routing tab (target)

1. **Lead:** one sentence — packages + daemons for Linux routing on Unraid.  
2. **Status badges:** Plugin ver · Unraid product · FRR version · zebra/fabricd up · catalog match · flash cache.  
3. **Health strip:** “Management default route still on `wlan0`/`br0`” when true; warn if FRR installed a default.  
4. **Neighbors (collapsible):** last `show openfabric neighbor` snippet when fabricd up.  
5. **Packages:** channel, auto-download, array-start (current).  
6. **Daemons:** zebra/fabricd/staticd + optional protocols (current).  
7. **Interfaces (future):** table above.  
8. **Docs row:** Scope · Day-two · Proxmox · TBN.

### Thunderbolt tab (keep)

- Underlay + per-TB OpenFabric participate/metric (already the right place for TB).  
- Companion chip → Fabric Routing when FRR missing.

### What users can do “further”

| Skill | Path |
|-------|------|
| Just packages | Install FRR, leave policy to TBN or static |
| TB multi-hop | TBN OpenFabric On + FRR packages |
| Eth/10G fabric | Interfaces UI (P4) or documented vtysh recipe |
| Proxmox mix | Shared OpenFabric nets / underlay guide |
| Imaging over fabric | NBD Export on private IP (not Wi‑Fi for multi-TB) |

---

## Community Applications

| Item | Action |
|------|--------|
| Template | `unraid-templates/plugins/fabricrouting.xml` — refresh **Overview** with “first packages live for Unraid 7.x x86_64”, OpenFabric, Proxmox/AI one-liners |
| Category | Keep Network:Management |
| Support | GitHub issues + forum thread |
| Icon | Keep; consistent with suite |
| After each package/UI milestone | Bump Overview date-sensitive claims |

CA lag after GitHub push is normal; template accuracy matters more than hourly sync.

---

## Forum strategy

### Recommendation: **new dedicated thread** + soft link from Thunderbolt Net

| Approach | Pros | Cons |
|----------|------|------|
| **New thread: “Fabric Routing (FRR) for Unraid”** | Clear scope; package/install/uninstall noise does not bury TB cable help; CA users land on the right topic | Slightly more discovery work |
| Extend TBN thread only | One place for “TB multi-hop” | FRR packaging, eth fabrics, Proxmox, AI nodes **drown** TB underlay support |

**Do both lightly:**

1. **New thread** (primary): Fabric Routing — install, packages, daemons, eth/OpenFabric, Proxmox, ambitions.  
2. **First post of TBN thread** (or a sticky reply): “Multi-hop OpenFabric packages moved to companion **Fabric Routing (FRR)** → [link]. TBN still owns Thunderbolt underlay + per-port metrics.”  
3. Cross-link in both plugins’ UI footers.

### Forum first-post skeleton

1. **Why** (ambition paragraph — Unraid as fabric peer, not waiting on official FRR).  
2. **What it is / is not** (packages vs TBN vs stock Routing Table).  
3. **Requirements** (Unraid 7.x x86_64 for v1 packages; private links).  
4. **Install** (CA when live / raw URL).  
5. **Safe defaults** (no auto br0).  
6. **Lab status** (honest: first bundle, eth OpenFabric proven, TB policy in TBN).  
7. **Roadmap** (Interfaces UI, metrics, Proxmox guide).  
8. **Support** (GitHub issues; attach diagnostics).  

---

## Messaging for users “tired of waiting on Unraid”

**Tone:** respectful of Lime Technology; clear that this is **community power tooling**.

**Do say:**

- Unraid is a great NAS OS; **advanced multipath / FRR / TB host-net policy** are still DIY or missing.  
- We package **upstream Linux capabilities** (FRR, OpenFabric) with **Unraid-native install and UI**.  
- You get **Proxmox-adjacent** routing patterns without abandoning the Unraid array/UI.  
- Defaults protect the **management path**; power is opt-in.  
- Companions (TBN, NBD) solve **underlay** and **block imaging**, not “one mega-plugin that does everything poorly.”

**Don’t say:**

- “Unraid is broken / incompetent.”  
- “Replace br0 with FRR for everyone.”  
- “This is official.”  
- Promise multi-writer SAN or enterprise SLA.

**One-liner for CA/forum:**

> **Fabric Routing** brings automated FRRouting (including OpenFabric) to Unraid so multi-node labs — Thunderbolt rings, 10G peers, Proxmox neighbors, AI boxes — can use real Linux multipath networking without waiting on stock Network Settings to grow a full routing stack.

---

## Non-goals / safety

- No default enrollment of **Wi‑Fi / br0 / Docker bridges** into OpenFabric  
- No multi-writer shared block SAN via NBD  
- No claim that FRR replaces Unraid’s array networking  
- No WAN exposure of NBD or unauthenticated FRR management  
- No requirement to install Thunderbolt Net for FRR packages alone  

---

## Immediate next actions (execution order)

1. **P1:** Finish documented uninstall→reinstall→auto-download on the primary lab host; patch remove script for `/usr/sbin/frrinit.sh` if needed.  
2. **P2:** Status strip (mgmt route + neighbor snippet) on Fabric Routing page.  
3. **P3–P4:** Interfaces table design + implement eth opt-in + metrics.  
4. **Docs passes 1–3** in sequence (ambition, install truth, day-two).  
5. **CA Overview** refresh + **new forum thread** draft (with TBN cross-link).  
6. **Proxmox** mixed-fabric doc when a peer is available.

---

## Decision log

| Date | Decision |
|------|----------|
| 2026-08-12 | First packages + OpenFabric eth0 lab proven |
| 2026-08-12 | Interface/metric UI is a first-class roadmap item (not “TB only forever”) |
| 2026-08-12 | Forum: **new FRR thread** + link from TBN; do not only extend TBN thread |
| 2026-08-12 | Ambition framing: Proxmox-adjacent + multi-node AI + modern Linux networking QoL on Unraid |
