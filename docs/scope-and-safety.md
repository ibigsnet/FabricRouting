# Scope and safety — does UnraidFRR break Ethernet?

## Short answer

| Question | Answer |
|----------|--------|
| Is UnraidFRR **Thunderbolt-only**? | **No.** It installs a **host-wide** routing suite (FRRouting). |
| Does it reconfigure Unraid **eth0 / br0** by default? | **No.** It does not edit Unraid Network Settings, `network.cfg`, bonds, or Docker networks. |
| Can FRR *ever* affect LAN traffic? | **Yes, if you (or another tool) put LAN interfaces into a routing protocol or install routes that compete with Unraid’s.** Defaults aim to avoid that. |
| Needed for Thunderbolt OpenFabric? | **Optional supplier of FRR.** Thunderbolt Net can use any FRR; UnraidFRR is the packaged path. |

## Scope (what this plugin owns)

```text
IN SCOPE
  · Install FRR packages from flash (installpkg) when you provide them
  · Enable/disable FRR daemons (zebra, fabricd, optional bgpd/ospfd/…)
  · Start/stop FRR service best-effort
  · Status UI + companion marker for other plugins
  · Standalone use (no Thunderbolt Net required)

OUT OF SCOPE (by design)
  · Thunderbolt discovery, tbn IPs, cable UX  →  Thunderbolt Net
  · Unraid eth0/br0/bond0 UI or network.cfg
  · Docker / VM libvirt networks
  · Enabling net.ipv4.ip_forward (Thunderbolt Net may do that only when OpenFabric + FRR)
  · Putting br0/eth* into OpenFabric/OSPF/BGP automatically
```

## Why FRR is not “TB-only”

FRRouting is a **general** Linux routing stack (same class of tool as running FRR on Proxmox/Debian). Once `zebra` is running it *can* install routes into the kernel. Protocol daemons only advertise/learn on interfaces **you configure** in `/etc/frr/frr.conf` (or that Thunderbolt Net adds inside its marked block for `thunderbolt*` / `lo`).

So:

- **Empty / minimal conf + fabricd with no interfaces** → little or no effect on Ethernet forwarding.  
- **Thunderbolt Net OpenFabric** → configures **TB underlay + loopback**, not br0 (product default).  
- **You enable ospfd and add `interface br0`** → then yes, LAN is in scope of that protocol — expert opt-in.

## Default safety posture

| Control | Default | Why |
|---------|---------|-----|
| Packages missing | **Idle** (no installpkg, no start) | Empty `packages/` cannot break LAN |
| zebra | On (when FRR live) | Needed for any FRR use |
| fabricd | On (when FRR live) | OpenFabric for optional TB mesh |
| bgpd / ospfd / isisd / … | **Off** | Avoid surprise LAN protocols |
| IP forwarding | **Not set by UnraidFRR** | Unraid LAN behavior stays Unraid’s |
| Auto-add eth*/br* to protocols | **Never** | Hard rule |

## What can still go wrong (honest)

1. **Bad or incompatible `.txz`** — library conflicts, failed boot services (rare; pick builds for your Unraid line).  
2. **Hand-edited `frr.conf`** that redistributes or defaults into the LAN.  
3. **Another tool** writing FRR config for eth interfaces.  
4. **Enabling OSPF/BGP** in this UI and then adding LAN interfaces yourself.  
5. **Thunderbolt Net OpenFabric** with forwarding on — host routes TB prefixes; still should not steal default route via br0 unless you set that on a tbn tab.

## Interaction with normal Unraid Ethernet

Unraid continues to manage:

- br0 / eth bonds / VLANs via **Settings → Network Settings**  
- Docker macvlan/ipvlan  
- WireGuard Unraid plugin, etc.

UnraidFRR does not replace those. Think of it as installing “Cisco IOS on a stick” software that stays **quiet** until a conf puts interfaces into a protocol.

## Related

- [DOCS.md](../DOCS.md)  
- [integration-thunderboltnet.md](integration-thunderboltnet.md)  
- Thunderbolt Net path cost / rings: [routing-openfabric.md](https://github.com/ibigsnet/ThunderboltNet/blob/main/docs/routing-openfabric.md)  
