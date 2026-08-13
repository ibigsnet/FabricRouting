# Fabric Routing (FRR) — topic index

Full product intro: [../DOCS.md](../DOCS.md).

**UI:** Settings → Network Settings → **Fabric Routing**  
**Plugin id / install:** UnraidFRR · `unraidfrr.plg`

## How the pieces fit

```text
User options (channel, daemons)
    → UnraidFRR catalog (manifest.json)
    → auto-download + sha256 + installpkg
    → FRR daemons (zebra, fabricd, …)
    → optional Thunderbolt Net OpenFabric policy on TB ifaces
```

| You want… | Start here |
|-----------|------------|
| **First-time setup** (settings + first Apply) | **[first-time-setup.md](first-time-setup.md)** |
| Install (CA or raw URL) / uninstall | [../DOCS.md](../DOCS.md) · [../RELEASES.md](../RELEASES.md) |
| When to install vs skip | [first-time-setup.md](first-time-setup.md#1-do-you-need-fabric-routing) · [../DOCS.md](../DOCS.md#when-do-i-need-this) |
| Automation design (catalog / flash cache) | [automation-design.md](automation-design.md) |
| Ethernet / host-wide safety | [scope-and-safety.md](scope-and-safety.md) |
| Pairing with Thunderbolt Net | [integration-thunderboltnet.md](integration-thunderboltnet.md) |
| Maintainer package catalog | [../packages/README.md](../packages/README.md) |
| Supported Unraid versions (lab vs suggested) | [../packages/SUPPORTED.md](../packages/SUPPORTED.md) |
| FRR vs stock Routing Table / what Unraid can leverage | [frr-and-unraid-routing.md](frr-and-unraid-routing.md) |
| Scope & LAN safety | [scope-and-safety.md](scope-and-safety.md) |
| Product roadmap / ambitions | [product-roadmap.md](product-roadmap.md) |
| Two-host lab pattern (anonymized) | [lab-two-node-fabric.md](lab-two-node-fabric.md) |
| Defaults rationale | [defaults-rationale.md](defaults-rationale.md) |

## All topics

| Doc | Contents |
|-----|----------|
| [first-time-setup.md](first-time-setup.md) | Onboarding: need/skip, settings, first Apply, success checks |
| [automation-design.md](automation-design.md) | Catalog / flash cache / Apply lifecycle |
| [scope-and-safety.md](scope-and-safety.md) | Host-wide FRR vs br0/eth safety |
| [integration-thunderboltnet.md](integration-thunderboltnet.md) | Optional TBN companion |
| [../RELEASES.md](../RELEASES.md) | CA + raw install URLs, versioning, tags |
| [boot-lifecycle.md](boot-lifecycle.md) | Boot plg vs UI Apply vs array-start rehydrate |
| [../CONTRIBUTING.md](../CONTRIBUTING.md) | How to contribute |
| [plugin-fleet-boot-audit.md](plugin-fleet-boot-audit.md) | Boot/install audit across UnraidFRR + siblings |
| [lab-two-node-fabric.md](lab-two-node-fabric.md) | Two-host lab pattern (Machine A/B; no personal hostnames) |
| [boot-blocker-plugin-install-stall.md](boot-blocker-plugin-install-stall.md) | Why heavy work must not run in `.plg` at boot |
