# Integration with Thunderbolt Net

UnraidFRR and Thunderbolt Net are **optional companions**. Either can be installed alone.

## Dependency direction

```text
UnraidFRR  ──provides──►  FRR binaries + daemons (vtysh, zebra, fabricd, …)
                                │
                                ▼
ThunderboltNet  ──uses──►  detect FRR; generate OpenFabric conf; reload when present
```

- Thunderbolt Net **never** requires UnraidFRR PHP files.  
- UnraidFRR **never** requires Thunderbolt Net PHP files.  
- Detection is via **filesystem / PATH** (`vtysh`, `fabricd`, `/etc/frr`), plus an optional marker file.

## Marker file (optional, for UI copy)

When UnraidFRR is installed, it maintains:

```text
/boot/config/plugins/UnraidFRR/companion.json
```

Example:

```json
{
  "plugin": "UnraidFRR",
  "provides": ["frr", "fabricd", "zebra"],
  "version": "2026.08.11"
}
```

Thunderbolt Net may show “Install UnraidFRR for packaged FRR” when OpenFabric is On and FRR is missing — using this marker or simple “plugin dir exists” checks. Absence of the marker is fine; `vtysh` detection is enough for apply paths.

## Config ownership

| Path | Owner |
|------|--------|
| FRR packages, UnraidFRR.cfg, install scripts | **UnraidFRR** |
| `/etc/frr/daemons` enable flags | **UnraidFRR** (primary) |
| OpenFabric interface/router stanzas | **Thunderbolt Net** (marked `BEGIN/END ThunderboltNet OpenFabric`) |
| Hand-edited FRR for non-TB use | **User** |

Thunderbolt Net must only rewrite its **marked** blocks (already the design). UnraidFRR must not wipe `frr.conf` wholesale on every boot if a marked TBN block exists — prefer enabling daemons + package install only.

## Install order (recommended)

1. UnraidFRR → packages → Apply / reboot if needed → `vtysh -v` works  
2. Thunderbolt Net → OpenFabric On → Apply → conf generated + fabric reload  

Reverse order is OK: Thunderbolt Net stays in static/degraded mode until FRR appears, then Apply again.

## Uninstall scenarios

| Remove | Expected |
|--------|----------|
| UnraidFRR only | FRR may disappear on next boot (RAM root); TBN shows FRR missing; static TB still works |
| Thunderbolt Net only | FRR keeps running; user keeps full FRR for other uses |
| Both | Clean separation; no cross-require fatals |

## Mixed Proxmox / Debian + Unraid fabrics

Many sites run OpenFabric between **Unraid** and **Proxmox (or other Debian/Linux)** over TB. UnraidFRR only supplies FRR on Unraid; peers use distro packages (`apt install frr`, etc.). Same area/metrics/loopback plan on every node.

General reference (example multi-node ring, not a fixed site layout):  
[Thunderbolt Net — fabric-proxmox-unraid.md](https://github.com/ibigsnet/ThunderboltNet/blob/main/docs/fabric-proxmox-unraid.md)

## Non-goals for the pair

- UnraidFRR must not call into `/plugins/ThunderboltNet/`  
- Thunderbolt Net must not `require` UnraidFRR PHP  
- Neither should fail Settings page load if the other is absent  
- UnraidFRR does not manage Proxmox (docs/interop only)  
