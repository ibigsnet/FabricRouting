# Contributing to UnraidFRR

## Scope

- Package lifecycle and daemon enablement for FRR on Unraid  
- **Must remain usable without Thunderbolt Net**  
- Never `require` Thunderbolt Net PHP paths  

## Package builds

Helpful contributions: documented Slackware/Unraid-compatible FRR `.txz` builds, CI notes, MANIFEST order, library deps (e.g. libyang).

## With Thunderbolt Net

Cross-plugin behavior is documented in [docs/integration-thunderboltnet.md](docs/integration-thunderboltnet.md). Prefer filesystem detection (`vtysh`) over hard coupling.
