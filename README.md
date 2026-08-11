**FRR (FRRouting) for Unraid**

Optional companion plugin that installs and manages [FRRouting](https://frrouting.org/) on Unraid — the routing suite used for **OpenFabric** (`fabricd`), OSPF, BGP, and related protocols.

This plugin is **standalone**. You do **not** need Thunderbolt Net. It only installs/enables FRR when you choose, and it must not depend on any TB plugin paths.

### Why a separate plugin?

Installing a full routing stack is more invasive than a Settings UI for Thunderbolt links: packages on the flash, boot-time `installpkg`, daemons, config under `/etc/frr`. Splitting it (same idea as Unassigned Devices vs Plus) lets users opt in.

| Plugin | Role |
|--------|------|
| **UnraidFRR** (this) | Obtain/install FRR packages, enable daemons, basic status |
| **[ThunderboltNet](https://github.com/ibigsnet/ThunderboltNet)** (optional) | TB host-net underlay + OpenFabric *policy* when FRR is present |

### Install

**Plugins → Install Plugin** → raw URL:

`https://raw.githubusercontent.com/ibigsnet/UnraidFRR/main/unraidfrr.plg`

(After the repo is published on GitHub.)

### Scope (important)

UnraidFRR is **not Thunderbolt-only**. It manages **FRRouting on the Unraid host** (same class of software as FRR on any Linux router).  

**Defaults are LAN-safe:** no auto-config of eth0/br0 into OSPF/BGP/OpenFabric, no Unraid `network.cfg` edits, no IP forwarding toggle.  
**Risk appears if you** (or another tool) put LAN interfaces into a routing protocol. Details: [docs/scope-and-safety.md](docs/scope-and-safety.md).

### Safety

- Default: **idle** until packages exist on flash (no installpkg / no start).
- Uninstall removes plugin files; flash config/packages kept by default.
- Does not modify Thunderbolt Net, eth0/br0 Unraid Network Settings UI, or Docker networking by itself.
- IP forwarding is **not** enabled by this plugin alone (Thunderbolt Net may enable it when OpenFabric is active with FRR present).
### Packages

Unraid is Slackware-based. Place compatible `.txz` / `.tgz` builds under:

`/boot/config/plugins/UnraidFRR/packages/`

See [packages/README.md](packages/README.md). Until packages exist for your Unraid version, the plugin reports **packages missing** and does nothing harmful.

### Docs

- [DOCS.md](DOCS.md) — full guide  
- [docs/integration-thunderboltnet.md](docs/integration-thunderboltnet.md) — optional pairing with Thunderbolt Net  
- [RELEASES.md](RELEASES.md) — install URLs and versioning  
