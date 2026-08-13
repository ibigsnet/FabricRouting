# FRR and Unraid’s normal routing table

How **Fabric Routing (FRR)** relates to stock Unraid networking — what Unraid can leverage, and what it cannot.

**Settings map:**

```text
Settings → Network Settings
  · eth / br0 / bonds / VLANs / Wi‑Fi     → stock Unraid (network.cfg)
  · Routing Table                        → stock kernel routes (view/edit Unraid’s way)
  · Fabric Routing                       → this plugin (FRR packages + daemons)
  · Thunderbolt (if installed)           → Thunderbolt Net underlay + OpenFabric policy
```

---

## Contents

- [Two different layers](#two-different-layers)
- [What stock Unraid already does well](#what-stock-unraid-already-does-well)
- [What FRR adds](#what-frr-adds)
- [What Unraid can leverage with FRR](#what-unraid-can-leverage-with-frr)
- [What Unraid cannot / should not expect from FRR](#what-unraid-cannot--should-not-expect-from-frr)
- [Effect on the normal routing table](#effect-on-the-normal-routing-table)
- [Safe defaults (this plugin)](#safe-defaults-this-plugin)
- [Related](#related)

---

## Two different layers

| Layer | Who owns it | What it is |
|-------|-------------|------------|
| **Host / NAS networking** | Unraid Network Settings | Interfaces, bridges (`br0`), DHCP/static on LAN, DNS, bonds, Wi‑Fi, Docker networks |
| **Dynamic / multipath routing suite** | FRR (`zebra` + protocol daemons) | Learns and installs **kernel routes** via protocols (OpenFabric, OSPF, BGP, staticd, …) |

FRR does **not** replace the Unraid GUI for “give eth0 an IP.” It sits **beside** that stack as optional routing software — same idea as installing FRR on Proxmox/Debian next to the distro’s network config.

---

## What stock Unraid already does well

Without this plugin, Unraid is strong at:

- Single (or bonded) **LAN** uplink, **br0**, shares (SMB/NFS), Docker, VMs  
- Static routes and the **Routing Table** view for simple “send this subnet via that gateway” cases  
- Management access (GUI/SSH) on the interfaces you configure  

It is **not** a full multipath fabric OS out of the box: no packaged **OpenFabric/IS-IS**, no guided multi-hop TB/10G mesh, no packaged FRR lifecycle on stock Unraid.

---

## What FRR adds

Once packages are installed and daemons run:

| Piece | Role |
|-------|------|
| **zebra** | FRR’s RIB ↔ **Linux kernel** route install/withdraw |
| **fabricd** | **OpenFabric** (IS-IS based) — multi-hop / multipath fabrics (TB rings, eth lab links, mixed peers) |
| **staticd** | FRR-managed static routes (optional; Unraid can still own simple statics) |
| **bgpd / ospfd / …** | Classic dynamic routing — **off by default** in this plugin |
| **vtysh** | CLI to inspect neighbors, topology, routes |

Thunderbolt Net (optional) writes **policy** (which `thunderbolt*` ifaces, metrics). This plugin supplies **binaries + daemon enablement**.

---

## What Unraid can leverage with FRR

| Use | How |
|-----|-----|
| **Multi-hop private fabric** | OpenFabric (or OSPF) so host A reaches C via B when a direct cable is down |
| **Multipath / metric preference** | Prefer TB or 10G over 2.5G/Wi‑Fi for fabric prefixes (metrics; TBN auto metric on TB) |
| **Proxmox / Linux peers** | Same FRR/OpenFabric ideas as Debian — Unraid is a **peer**, not only an SMB server |
| **AI / multi-node lab** | Keep model/storage traffic on fast private underlays; Unraid holds array/cache data |
| **Inspectability** | `vtysh`: neighbors, topology, routes FRR owns |
| **NBD / SMB / NFS over the fabric** | Apps still use normal IPs; FRR only ensures **kernel routing** to those prefixes works |

Unraid still serves shares, Docker, and VMs as usual. FRR improves **how packets find private paths**, not how SMB is implemented.

---

## What Unraid cannot / should not expect from FRR

| Expectation | Reality |
|-------------|---------|
| “FRR replaces Network Settings” | **No** — still configure IPs/bridges in Unraid |
| “Stock Routing Table tab becomes OpenFabric UI” | **No** — that tab is kernel/Unraid static-oriented; fabric status is FRR/`vtysh`/TBN |
| “Install FRR and br0 joins a mesh automatically” | **No** — defaults **never** auto-enroll `br0` / Wi‑Fi |
| “FRR is a VPN / Tailscale replacement” | **No** — different problem |
| “FRR is multi-writer shared storage” | **No** — not SAN; use proper shared FS or NBD imaging patterns carefully |
| “One package for every Unraid version forever” | **No** — see [SUPPORTED.md](../packages/SUPPORTED.md) (lab-confirmed vs suggested) |
| “USB4STREAM / kernel features come from FRR” | **No** — kernel/driver; wrong tool |

---

## Effect on the normal routing table

### When FRR is installed but quiet

- Daemons may run (`zebra`, `fabricd`) with **no** (or minimal) interface enrollment.  
- Unraid’s **default route** (e.g. via Wi‑Fi or br0 gateway) typically **unchanged**.  
- Stock **Routing Table** page still shows kernel routes; most remain Unraid-owned.

### When FRR installs routes

- **zebra** programs the **kernel** FIB (what `ip route` shows).  
- Those routes appear alongside Unraid’s routes.  
- More specific prefixes usually win over a default route; a **bad** FRR default could steal management traffic — avoid putting LAN/Wi‑Fi into protocols carelessly.

### Lab observation (two-host private underlay)

With OpenFabric on **eth0 only** (a private fabric subnet) and management on **wlan0**:

- Default route stayed **`default via … dev wlan0`**  
- Fabric adjacency came up on eth0  
- GUI/SSH over Wi‑Fi remained usable  

That is the intended pattern: **mgmt on Unraid-managed path; fabric on private ifaces.**

### Stock “Routing Table” vs FRR

| | Stock Routing Table | FRR (`vtysh` / zebra) |
|--|---------------------|------------------------|
| Primary use | Simple static / Unraid-managed | Dynamic protocols + FRR statics |
| Multi-hop mesh | Not the tool | OpenFabric / OSPF / BGP |
| Who writes kernel | Unraid network stack | zebra (when FRR active) |
| Plugin home | Network Settings | **Fabric Routing** tab |

They **share the kernel**. They are **not** the same UI.

---

## Safe defaults (this plugin)

| Control | Default |
|---------|---------|
| Auto-enroll `br0` / `wlan0` / Docker bridges | **Never** |
| bgpd / ospfd / isisd | **Off** |
| fabricd / zebra / staticd | **On** (when packages live) — still need conf/policy for real fabric |
| Edit `network.cfg` | **Never** |
| IP forwarding | **Not set by FabricRouting** (TBN may when OpenFabric needs it) |

Details: [scope-and-safety.md](scope-and-safety.md).

---

## Related

- [scope-and-safety.md](scope-and-safety.md)  
- [product-roadmap.md](product-roadmap.md) — interface/metric UI plans  
- [integration-thunderboltnet.md](integration-thunderboltnet.md)  
- [packages/SUPPORTED.md](../packages/SUPPORTED.md) — version matrix  
- Thunderbolt Net: [routing-openfabric.md](https://github.com/ibigsnet/ThunderboltNet/blob/main/docs/routing-openfabric.md)  
