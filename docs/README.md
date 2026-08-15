# Fabric Routing (FRR) — topic index

Full product intro: [../DOCS.md](../DOCS.md).

**UI:** Settings → Network Settings → **Fabric Routing**  
**Install:** [../RELEASES.md](../RELEASES.md)

## How the pieces fit

```text
User options (channel, daemons)
    → FabricRouting catalog (manifest.json)
    → download + sha256 + installpkg
    → FRR daemons (zebra, fabricd, …)
    → optional Thunderbolt Net OpenFabric policy on Thunderbolt interfaces
```

| You want… | Start here |
|-----------|------------|
| **First-time setup** | **[first-time-setup.md](first-time-setup.md)** |
| Install / update / uninstall | [../DOCS.md](../DOCS.md) · [../RELEASES.md](../RELEASES.md) |
| When to install vs skip | [first-time-setup.md](first-time-setup.md#1-do-you-need-fabric-routing) |
| How packages download and rehydrate | [automation-design.md](automation-design.md) · [boot-lifecycle.md](boot-lifecycle.md) |
| Ethernet / host-wide safety | [scope-and-safety.md](scope-and-safety.md) |
| Pairing with Thunderbolt Net | [integration-thunderboltnet.md](integration-thunderboltnet.md) |
| Supported Unraid versions | [../packages/SUPPORTED.md](../packages/SUPPORTED.md) |
| FRR vs stock Routing Table | [frr-and-unraid-routing.md](frr-and-unraid-routing.md) |
| Two-host lab pattern | [lab-two-node-fabric.md](lab-two-node-fabric.md) |
| Why defaults are what they are | [defaults-rationale.md](defaults-rationale.md) |
| Boot install stuck / Safe Mode | [boot-blocker-plugin-install-stall.md](boot-blocker-plugin-install-stall.md) |

## All topics

| Doc | Contents |
|-----|----------|
| [first-time-setup.md](first-time-setup.md) | Need/skip, settings, first package install, success checks |
| [automation-design.md](automation-design.md) | Catalog / flash cache / package lifecycle |
| [boot-lifecycle.md](boot-lifecycle.md) | Boot plg vs package job vs array-start rehydrate |
| [boot-blocker-plugin-install-stall.md](boot-blocker-plugin-install-stall.md) | Why install stays light; recovery if WebUI never appears |
| [scope-and-safety.md](scope-and-safety.md) | Host-wide FRR vs br0/eth safety |
| [integration-thunderboltnet.md](integration-thunderboltnet.md) | Optional Thunderbolt Net companion |
| [frr-and-unraid-routing.md](frr-and-unraid-routing.md) | FRR vs Unraid Routing Table |
| [lab-two-node-fabric.md](lab-two-node-fabric.md) | Two-host lab pattern (Machine A/B) |
| [defaults-rationale.md](defaults-rationale.md) | Why each default is set |
| [../packages/SUPPORTED.md](../packages/SUPPORTED.md) | Unraid version matrix |
| [../packages/README.md](../packages/README.md) | Package catalog layout |
| [../RELEASES.md](../RELEASES.md) | Install URLs (`stable` / `main` / freezes) |
| [../CONTRIBUTING.md](../CONTRIBUTING.md) | How to contribute |
