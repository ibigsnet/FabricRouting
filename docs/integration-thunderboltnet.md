# Integration with Thunderbolt Net

FabricRouting and Thunderbolt Net are **optional companions**. Either can be installed alone. They share **files**, never PHP.

## Dependency direction

```text
FabricRouting  ──provides──►  FRR binaries + daemons, /etc/frr (frr.conf, daemons, vtysh.conf)
                                ▲
                                │ fragment file + scripts/frr-reload
ThunderboltNet ──writes──►  /boot/config/plugins/ThunderboltNet/frr/thunderboltnet-openfabric.conf
```

- Thunderbolt Net **never** requires FabricRouting PHP files, and FabricRouting never requires Thunderbolt Net's.
- Detection is via the filesystem (`vtysh`, `fabricd`, `/etc/frr`) plus the optional marker file.

## File contract

| Piece | Owner | Notes |
|-------|-------|-------|
| `/etc/frr/frr.conf`, `/etc/frr/daemons`, `/etc/frr/vtysh.conf` | **FabricRouting** | Rebuilt from flash on Apply, package jobs, array start and `frr-reload` |
| `/boot/config/plugins/ThunderboltNet/frr/thunderboltnet-openfabric.conf` | **Thunderbolt Net** | OpenFabric stanzas for `thunderbolt*` + `lo`; included verbatim between `! BEGIN/END FabricRouting include: ThunderboltNet fragment` |
| `/boot/config/plugins/FabricRouting/frr.user.conf` | **User** | Hand-written FRR config, included last |
| `/usr/local/emhttp/plugins/FabricRouting/scripts/frr-reload` | **FabricRouting** | Hook: rebuild + start/restart; exit 0 = OK |

When the fragment exists and is not empty, FabricRouting also turns `fabricd` on in `daemons`. IP forwarding stays Thunderbolt Net's decision.

**Thunderbolt Net side:** write or delete the fragment, then run `frr-reload` if it exists. Do not edit `/etc/frr/*` directly and do not use `vtysh write memory` (the next rebuild replaces the file).

## Marker file (optional, for UI copy)

```text
/boot/config/plugins/FabricRouting/companion.json
```

```json
{
  "plugin": "FabricRouting",
  "provides": ["frr", "zebra", "fabricd"],
  "version": "FRRouting 10.7.0 …",
  "present": true,
  "fabricd_enabled_cfg": true,
  "reload_hook": "/usr/local/emhttp/plugins/FabricRouting/scripts/frr-reload",
  "fragment_path": "/boot/config/plugins/ThunderboltNet/frr/thunderboltnet-openfabric.conf"
}
```

## Boot order

Array-start rehydrate reads the fragment from flash, so it is in `frr.conf` before FRR starts regardless of which plugin's `started` event runs first.

## Route guard

FabricRouting's default route guard only installs OpenFabric routes in `10.254.0.0/16`, `10.255.0.0/16` and `fc00::/7` — Thunderbolt Net's loopback and link ranges. Custom Thunderbolt Net router-ids or link addresses outside these ranges must be added under **Fabric Routing → Advanced → Guard prefixes**. See [DOCS.md — Fabric route guard](../DOCS.md#fabric-route-guard).

## Install order (recommended)

1. **Network Settings → Fabric Routing** → Download & Install packages → `vtysh -v` works.
2. **Network Settings → Thunderbolt** → OpenFabric On → Apply → fragment written + `frr-reload`.

Reverse order is OK: Thunderbolt Net keeps the fragment on flash; FabricRouting includes it on its first Apply or package job.

## Uninstall scenarios

| Remove | Expected |
|--------|----------|
| **FabricRouting only** | Stops its FRR, removes its own packages and `frr.conf`/`vtysh.conf`, clears its flash folder. The Thunderbolt Net fragment stays on flash. TBN degrades to static underlay. |
| **Thunderbolt Net only** | TBN deletes its fragment and runs `frr-reload`; FRR keeps running without OpenFabric stanzas. |
| **Both** | Order does not matter; neither PHP-requires the other. |

Full FabricRouting removal steps: [DOCS.md — Uninstall](../DOCS.md#uninstall-clean-removal).

## Mixed Proxmox / Debian + Unraid fabrics

FabricRouting only supplies FRR on Unraid; peers use distro packages (`apt install frr`). Same area/metrics/loopback plan on every node, and add the peers' loopback range to the route guard if it differs.

General reference: [Thunderbolt Net — fabric-proxmox-unraid.md](https://github.com/ibigsnet/ThunderboltNet/blob/main/docs/fabric-proxmox-unraid.md)

## Non-goals for the pair

- FabricRouting must not call into `/plugins/ThunderboltNet/` PHP.
- Thunderbolt Net must not `require` FabricRouting PHP.
- Neither should fail Settings page load if the other is absent.
- FabricRouting does not manage Proxmox (docs/interop only).
