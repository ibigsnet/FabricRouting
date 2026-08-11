# FRR (FRRouting) — topic index

Full product intro: [../DOCS.md](../DOCS.md).

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
| Install (CA or raw URL) / uninstall | [../DOCS.md](../DOCS.md) · [../RELEASES.md](../RELEASES.md) |
| Automation design (Nvidia-style) | [automation-design.md](automation-design.md) |
| Ethernet / host-wide safety | [scope-and-safety.md](scope-and-safety.md) |
| Pairing with Thunderbolt Net | [integration-thunderboltnet.md](integration-thunderboltnet.md) |
| Maintainer package catalog | [../packages/README.md](../packages/README.md) |

## All topics

| Doc | Contents |
|-----|----------|
| [automation-design.md](automation-design.md) | Full auto download/install lifecycle |
| [scope-and-safety.md](scope-and-safety.md) | Host-wide FRR vs br0/eth safety |
| [integration-thunderboltnet.md](integration-thunderboltnet.md) | Optional TBN companion |
| [../RELEASES.md](../RELEASES.md) | CA + raw install URLs, versioning, tags |
| [../CONTRIBUTING.md](../CONTRIBUTING.md) | How to contribute |
