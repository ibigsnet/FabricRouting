# Boot / install lifecycle (Unraid best practice)

## Why `plugin install` runs every boot

Unraid is a **RAM OS**. The plugin manager **re-installs** every `.plg` under `/boot/config/plugins/` on each boot so files under `/usr/local/emhttp/plugins/` exist again. That path must stay **fast, offline and non-blocking**.

## What FabricRouting does

| Phase | What runs | Network? | Blocks boot/emhttp? |
|-------|-----------|----------|---------------------|
| **Boot `plugin install`** | Installs the plugin `.txz` cached on flash (SHA256-pinned in the `.plg`), chmod, default cfg. If FRR is already running (plugin update on a live system), `frr-reload` runs in the background | Only when the cached plugin `.txz` is missing (Unraid's normal plugin download). **Never FRR packages** | Seconds |
| **Array `event/started`** | `frr_rehydrate_local()`: install verified packages from flash, rebuild `/etc/frr` (baseline + Thunderbolt Net fragment + `frr.user.conf`), start FRR | **No** | Detached (`at` / `nohup`) |
| **Apply settings** | Install verified packages from flash (when Install on array start is Yes), rebuild `/etc/frr`, restart FRR if something changed | **No** | Progress frame |
| **Download & Install packages** | Download + verify, install, rebuild `/etc/frr`, start/restart | **Yes** (only here) | Progress window |
| **`scripts/frr-reload`** | Rebuild `/etc/frr`, start/restart if needed (companion hook) | **No** | Waits ≤120 s for the job lock |

If GitHub is unreachable at boot nothing changes: the FRR packages and the plugin `.txz` are already on flash.

## Ordering with Thunderbolt Net

Array-start rehydrate reads the Thunderbolt Net fragment from **flash**, so the OpenFabric config is in `frr.conf` before FRR starts, whichever plugin's event runs first. When Thunderbolt Net changes its fragment later it runs `frr-reload`, which waits for a running rehydrate to finish.

## Operator notes

- First time after install: **Download & Install packages**.
- After reboot: array start reinstalls from flash when `install_on_start="yes"`.
- Set `install_on_start="no"` to skip reinstall at array start (the package button still installs).
- Log: `/var/log/fabricrouting.log` (RAM).

See also: [automation-design.md](automation-design.md), [boot-blocker-plugin-install-stall.md](boot-blocker-plugin-install-stall.md), [../RELEASES.md](../RELEASES.md).
