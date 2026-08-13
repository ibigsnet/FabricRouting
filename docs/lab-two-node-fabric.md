# Lab fabric pattern (two Unraid hosts)

Generic reference for a **two-node** Fabric Routing / OpenFabric lab. Use your own hostnames and addresses.

## Example topology

| Role | Label | Management (example) | Fabric underlay (example) |
|------|--------|----------------------|---------------------------|
| Primary (build / first install) | **Machine A** | Wi‑Fi or LAN for SSH/GUI — leave Unraid defaults alone | Private NIC, e.g. `eth0` **2.5G** @ `10.254.0.1/24` |
| Secondary peer | **Machine B** | Separate mgmt path | Same private L2, e.g. `eth0` **2.5G** @ `10.254.0.2/24` |

Both in lab: Unraid **7.3.2**, **x86_64** (see [SUPPORTED.md](../packages/SUPPORTED.md)).

Optional later underlay: **Thunderbolt host-net** (e.g. 1-lane train ≈ **~20 Gbit/s** each way on Thunderbolt 4-class links) between Machine A ↔ Machine B — not required for the first FRR package set.

## Hard rules (keep mgmt separate)

1. **Do not** put management Wi‑Fi / `br0` LAN into OpenFabric or rewrite the default route for experiments.  
2. Fabric experiments stay on a **private** interface and subnet (dedicated eth, DAC, or Thunderbolt host-net).  
3. Prefer scratch paths such as `/tmp/frr-build` for package builds — never wipe production pools.  
4. Riskier steps (first `installpkg`, FRR daemon experiments) on **Machine A** first; light peer checks on **Machine B**.

## What “lab-confirmed” exercised

| Check | Machine A | Machine B |
|-------|-----------|-----------|
| Fabric Routing plugin | Installed | Installed |
| FRR **10.7.0** (zebra / fabricd / staticd) | Live | Live |
| OpenFabric on private **eth0** | Adjacency **Up** ↔ B | Adjacency **Up** ↔ A |
| Default route on mgmt iface | Unchanged | Unchanged |

**Catalog:** GitHub Release [`pkg-10.7.0`](https://github.com/ibigsnet/FabricRouting/releases/tag/pkg-10.7.0) + `packages/manifest.json` for Unraid 7.0–7.3 x86_64.

## Connectivity checks

```bash
# From either host — use your private fabric IPs
ping -c 2 10.254.0.1
ping -c 2 10.254.0.2
ethtool eth0 | grep Speed   # e.g. 2500Mb/s on a 2.5G link
```

## After packages exist

1. Cache `.txz` under `/boot/config/plugins/FabricRouting/packages/` (or use Auto-download → Apply).  
2. Smoke `vtysh -c 'show version'` on Machine A.  
3. Repeat on Machine B.  
4. OpenFabric / static tests only on the **private** underlay (not the management Wi‑Fi/LAN).

## Physical notes

- Direct copper/DAC between matching NICs is enough for a first fabric.  
- Thunderbolt / 10G can be added later; not required to validate the package bundle.  
