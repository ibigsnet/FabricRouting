# Scope and safety — does Fabric Routing break Ethernet?

## Short answer

| Question | Answer |
|----------|--------|
| Is this **Thunderbolt-only**? | **No.** It installs a **host-wide** routing suite (FRRouting). |
| Does it reconfigure Unraid **eth0 / br0 / Wi‑Fi** by default? | **No.** It does not edit Unraid Network Settings or `network.cfg`. |
| Does it replace the stock **Routing Table** tab? | **No.** That tab is Unraid/kernel static-oriented. FRR is the **Fabric Routing** tab + `vtysh`. |
| Can FRR affect the normal routing table? | **Yes** — `zebra` can install/withdraw **kernel** routes when protocols or FRR statics are configured. Defaults aim to leave management alone. |
| Needed for Thunderbolt OpenFabric? | **Optional package supplier.** Thunderbolt Net can use any FRR; this plugin is the packaged path. |

Deeper “what can Unraid do with FRR?”: [frr-and-unraid-routing.md](frr-and-unraid-routing.md).

---

## Scope (what this plugin owns)

```text
IN SCOPE
  · Download/install FRR packages (catalog → flash → installpkg)
  · Enable/disable FRR daemons (zebra, fabricd, optional bgpd/ospfd/…)
  · Start/stop FRR best-effort (frrinit)
  · Status UI + companion marker for other plugins
  · Standalone use (no Thunderbolt Net required)

OUT OF SCOPE (by design)
  · Thunderbolt discovery, tbn IPs, cable UX  →  Thunderbolt Net
  · Unraid eth0/br0/bond0 / Wi‑Fi / network.cfg
  · Docker / VM libvirt networks
  · Stock Routing Table UI replacement
  · Enabling net.ipv4.ip_forward (Thunderbolt Net may when OpenFabric + FRR)
  · Auto-enrolling br0/wlan/eth* into OpenFabric/OSPF/BGP
```

---

## Why FRR is not “Thunderbolt-only”

FRRouting is a **general** Linux routing stack (same class of tool as FRR on Proxmox/Debian). Once `zebra` is running it *can* install routes into the kernel. Protocol daemons only speak on interfaces **you configure** in `/etc/frr/frr.conf` (or that Thunderbolt Net adds inside its marked block for `thunderbolt*` / `lo`).

So:

- **Minimal conf + no LAN ifaces in protocols** → little effect on Ethernet/Wi‑Fi management.  
- **Thunderbolt Net OpenFabric** → Thunderbolt underlay + loopback by product default, not br0.  
- **You enable ospfd and add `interface br0`** → LAN is in that protocol — expert opt-in.

---

## Default safety posture

| Control | Default | Why |
|---------|---------|-----|
| No catalog match | **Idle** download | Wrong Unraid version does not get a random binary |
| zebra / fabricd / staticd | On when packages live | Ready for fabric; still need policy |
| bgpd / ospfd / isisd / … | **Off** | Avoid surprise LAN protocols |
| IP forwarding | **Not set by FabricRouting** | Unraid LAN behavior stays Unraid’s |
| Auto-add br0 / wlan / docker0 | **Never** | Hard rule |

---

## What can still go wrong (honest)

1. **Incompatible package** for an untested Unraid build — see [SUPPORTED.md](../packages/SUPPORTED.md) (lab-confirmed vs suggested).  
2. **Hand-edited `frr.conf`** that redistributes or defaults into the LAN/Wi‑Fi.  
3. **Another tool** putting eth/br0 into FRR protocols.  
4. **Enabling OSPF/BGP** here and then adding management interfaces yourself.  
5. **Thunderbolt Net OpenFabric** with forwarding — Thunderbolt prefixes route; still should not steal default via br0 unless you configure that.

---

## Interaction with normal Unraid Ethernet / Wi‑Fi

Unraid continues to manage:

- br0 / eth bonds / VLANs / Wi‑Fi via **Network Settings**  
- Docker macvlan/ipvlan  
- WireGuard / Tailscale plugins, etc.

Fabric Routing does not replace those. Think: optional routing software that stays **quiet** on management paths until a conf puts interfaces into a protocol.

**Lab pattern:** management default route on **wlan0** or **br0**; fabric on **private eth0 / thunderbolt\*** only.

---

## Related

- [frr-and-unraid-routing.md](frr-and-unraid-routing.md) — can/cannot leverage FRR  
- [DOCS.md](../DOCS.md)  
- [integration-thunderboltnet.md](integration-thunderboltnet.md)  
- [packages/SUPPORTED.md](../packages/SUPPORTED.md)  
- Thunderbolt Net: [routing-openfabric.md](https://github.com/ibigsnet/ThunderboltNet/blob/main/docs/routing-openfabric.md)  
