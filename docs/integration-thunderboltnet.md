# Integration with Thunderbolt Net

FabricRouting and Thunderbolt Net are **optional companions**. Either can be installed alone.

## Dependency direction

```text
FabricRouting  ──provides──►  FRR binaries + daemons (vtysh, zebra, fabricd, …)
                                │
                                ▼
ThunderboltNet  ──uses──►  detect FRR; generate OpenFabric conf; reload when present
```

- Thunderbolt Net **never** requires FabricRouting PHP files.  
- FabricRouting **never** requires Thunderbolt Net PHP files.  
- Detection is via **filesystem / PATH** (`vtysh`, `fabricd`, `/etc/frr`), plus an optional marker file.

## Marker file (optional, for UI copy)

When FabricRouting is installed, it maintains:

```text
/boot/config/plugins/FabricRouting/companion.json
```

Example:

```json
{
  "plugin": "FabricRouting",
  "provides": ["frr", "fabricd", "zebra"],
  "version": "2026.08.11"
}
```

Thunderbolt Net may show **needs FRR packages** / companion card when OpenFabric is On and FRR is missing — pointing at **Network Settings → Fabric Routing**. Absence of the marker is fine; `vtysh` detection is enough for apply paths.

## Config ownership

| Path | Owner |
|------|--------|
| FRR packages, FabricRouting.cfg, install scripts | **FabricRouting** |
| `/etc/frr/daemons` enable flags | **FabricRouting** (primary) |
| OpenFabric interface/router stanzas | **Thunderbolt Net** (marked `BEGIN/END ThunderboltNet OpenFabric`) |
| Hand-edited FRR for non-TB use | **User** |

Thunderbolt Net must only rewrite its **marked** blocks (already the design). FabricRouting must not wipe `frr.conf` wholesale on every boot if a marked TBN block exists — prefer enabling daemons + package install only.

## Install order (recommended)

1. **Network Settings → Fabric Routing** → packages → Apply / reboot if needed → `vtysh -v` works  
2. **Network Settings → Thunderbolt** → OpenFabric On → Apply → conf generated + fabric reload  

Reverse order is OK: Thunderbolt Net stays in static/degraded mode until FRR appears, then Apply again.

## Uninstall scenarios

| Remove | Expected |
|--------|----------|
| **FabricRouting only** | Plugin remove **stops FRR**, `removepkg`s managed packages, clears emhttp + flash config. TBN stays installed; OpenFabric degrades to static underlay. No broken Unraid Network Settings. |
| **Thunderbolt Net only** | FRR keeps running if FabricRouting remains; TBN removes its TB hooks/config (see TBN remove script). |
| **Both** | Order does not matter; neither PHP-requires the other. No leftover cross-plugin hooks. |

Full FabricRouting removal steps: [DOCS.md — Uninstall](../DOCS.md#uninstall-clean-removal).

## Mixed Proxmox / Debian + Unraid fabrics

Many sites run OpenFabric between **Unraid** and **Proxmox (or other Debian/Linux)** over TB. FabricRouting only supplies FRR on Unraid; peers use distro packages (`apt install frr`, etc.). Same area/metrics/loopback plan on every node.

General reference (example multi-node ring, not a fixed site layout):  
[Thunderbolt Net — fabric-proxmox-unraid.md](https://github.com/ibigsnet/ThunderboltNet/blob/main/docs/fabric-proxmox-unraid.md)

## Non-goals for the pair

- FabricRouting must not call into `/plugins/ThunderboltNet/`  
- Thunderbolt Net must not `require` FabricRouting PHP  
- Neither should fail Settings page load if the other is absent  
- FabricRouting does not manage Proxmox (docs/interop only)  
