# UnraidFRR — Documentation

**FRRouting for Unraid** as an opt-in, standalone plugin.

## What it is

| | |
|--|--|
| **Name** | UnraidFRR |
| **Does** | Install FRR packages from flash (or future download URL), enable selected daemons (`zebra`, `fabricd`, …), show status |
| **Does not** | Require Thunderbolt Net; configure TB IPs; replace Unraid Network Settings; enable IP forwarding by default; auto-add eth0/br0 to any protocol |

**Scope:** host-wide **routing suite**, not a Thunderbolt driver. Defaults are written so **normal Unraid Ethernet keeps working**; see [docs/scope-and-safety.md](docs/scope-and-safety.md).

Upstream: [FRRouting](https://frrouting.org/) · [fabricd / OpenFabric](https://docs.frrouting.org/en/latest/fabricd.html)

## Why separate from Thunderbolt Net

Same pattern as **Unassigned Devices** (core) vs deeper/optional companions:

- Package install is **invasive** (flash space, boot install, always-on daemons).
- Many users want TB host-net **without** a routing suite.
- FRR is useful for **non-TB** labs too (VLANs, mesh between eth NICs, containers with host routing).

Thunderbolt Net **detects** FRR when present and generates OpenFabric config. UnraidFRR **supplies** FRR. Neither should crash if the other is missing.

## Install / uninstall

See [RELEASES.md](RELEASES.md).

After install: **Settings → FRR (FRRouting)**.

## Settings (product defaults)

| Setting | Default | Meaning |
|---------|---------|---------|
| Install packages on array start | Yes | Run `installpkg` for files in `packages/` if not already live |
| Enable zebra | Yes | Required RIB/kernel interface for other daemons |
| Enable fabricd (OpenFabric) | Yes | Needed for Thunderbolt Net OpenFabric happy path |
| Enable other daemons | No | bgpd, ospfd, isisd, … off unless you opt in |
| Auto-download packages | No | Reserved; only when a trusted package URL exists |

## Standalone behavior (no Thunderbolt Net)

1. Plugin installs → Settings page loads.  
2. No packages → status **Packages missing**; no `installpkg`; no daemon start.  
3. Packages present → array start / Apply installs them; enables daemons per cfg; `vtysh -v` works.  
4. You manage `/etc/frr/frr.conf` yourself (or another tool).  
5. Removing UnraidFRR does not require Thunderbolt Net to be present or absent.

## With Thunderbolt Net

1. Install **UnraidFRR**, get FRR packages working.  
2. Install **ThunderboltNet**, leave OpenFabric **On**.  
3. Thunderbolt Net detects `vtysh`/`fabricd`, writes marked OpenFabric snippets, reloads FRR.  
4. If you remove UnraidFRR later, Thunderbolt Net degrades to static underlay (existing behavior).

Details: [docs/integration-thunderboltnet.md](docs/integration-thunderboltnet.md).

## What “invasive” means (honest)

- Writes under `/boot/config/plugins/UnraidFRR/`
- May run `installpkg` into the Unraid RAM root (re-done each boot if needed)
- Touches `/etc/frr/daemons` and may restart `frr` services
- Does **not** by design rewrite Unraid `network.cfg` or br0

## Packages

See [packages/README.md](packages/README.md). Building FRR for each Unraid/Slackware userspace is the hard part; this plugin is the **lifecycle and UX** shell first.

## Related

- Thunderbolt Net: https://github.com/ibigsnet/ThunderboltNet  
- FRR: https://github.com/FRRouting/frr  
