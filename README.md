**FRR (FRRouting) for Unraid**

Optional plugin that installs and manages [FRRouting](https://frrouting.org/) on Unraid — zebra, OpenFabric (`fabricd`), and related routing daemons. **Standalone:** Thunderbolt Net is not required.

### What it does

- Installs FRR from Slackware packages you place on the flash (`packages/`)
- Enables selected daemons (`zebra`, `fabricd`, …) and can start the FRR service
- Settings UI: **Settings → FRR (FRRouting)**
- Idle and safe when no packages are present

### What it does not do

- Does not configure Thunderbolt cables or tbn IPs ([Thunderbolt Net](https://github.com/ibigsnet/ThunderboltNet) does that)
- Does not edit Unraid eth0/br0 Network Settings or enable IP forwarding by default
- Does not auto-put LAN bridges into OSPF/BGP/OpenFabric

### Why separate from Thunderbolt Net?

Package install is more invasive (flash packages, boot `installpkg`, always-on daemons). Opt in only if you need a routing suite — e.g. OpenFabric multi-host fabrics, or general FRR use.

| Plugin | Role |
|--------|------|
| **UnraidFRR** (this) | FRR packages + daemon management |
| **[Thunderbolt Net](https://github.com/ibigsnet/ThunderboltNet)** (optional) | TB host-net underlay + OpenFabric policy when FRR is present |

### Install

**Community Applications** (when listed): search **UnraidFRR** / **FRR**.

**Plugins → Install Plugin** → raw URL:

```text
https://raw.githubusercontent.com/ibigsnet/UnraidFRR/main/unraidfrr.plg
```

Then: hard-refresh browser → **Settings → FRR (FRRouting)** → add packages under `/boot/config/plugins/UnraidFRR/packages/` → Apply.

### Docs

- [DOCS.md](DOCS.md) — full guide (what it does, settings, paths, install)  
- [docs/scope-and-safety.md](docs/scope-and-safety.md) — host-wide FRR vs Ethernet safety  
- [docs/integration-thunderboltnet.md](docs/integration-thunderboltnet.md) — pairing with Thunderbolt Net  
- [packages/README.md](packages/README.md) — package drop layout  
- [RELEASES.md](RELEASES.md) — versioning and URLs  
