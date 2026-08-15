# Contributing to Fabric Routing

Thanks for helping with FRR packaging and lifecycle on Unraid.

## Scope

- Package catalog, download/install, and daemon enablement for FRR  
- Must remain usable **without** Thunderbolt Net  
- Never hard-`require` Thunderbolt Net PHP  

## Helpful contributions

- Slackware/Unraid-compatible FRR `.txz` builds and catalog entries (`packages/`)  
- Docs under [docs/](docs/)  
- Bug reports with Unraid product version + arch  

Cross-plugin behavior: [docs/integration-thunderboltnet.md](docs/integration-thunderboltnet.md).

## Branches

| Branch | Role |
|--------|------|
| `main` | Development |
| `stable` | Production / Community Applications |

Install channels: [RELEASES.md](RELEASES.md).

## License

By contributing, you agree that your contributions are licensed under the **GNU GPLv3 or later**. Copyright for the project is held by **ibigs, LLC**.
