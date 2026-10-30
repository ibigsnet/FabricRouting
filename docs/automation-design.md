# FabricRouting automation design (catalog, flash cache, /etc/frr)

## Goal

Users never download or copy FRR packages by hand, and nothing unverified is ever installed.

1. Install FabricRouting from CA / raw `.plg` (**files only** — seconds; no package download at boot).
2. **Network Settings → Fabric Routing → Download & Install packages**: download, verify, install, build `/etc/frr`, start FRR.
3. **Array start**: reinstall the verified flash cache into RAM, rebuild `/etc/frr`, start FRR (no network).
4. **Apply settings** / companion `frr-reload`: rebuild `/etc/frr`, restart FRR only when something changed (no network).

See [boot-lifecycle.md](boot-lifecycle.md).

---

## Package catalog and trust

`packages/manifest.json` ships **inside** each plugin version. The plugin `.txz` itself is pinned by SHA256 in the `.plg`, so the chain is: `.plg` → plugin `.txz` (sha256) → `manifest.json` → package sha256.

- The catalog is never fetched over the network.
- Every package entry must have a file name `frr-*_unraid.txz` / `libyang-*_unraid.txz`, a 64-hex `sha256` and an https `url`; otherwise the job stops.
- **Package mirror URL** (Advanced) only replaces where files come from (`<mirror>/<file>`). Hashes still come from the bundled catalog, so a mirror cannot change what is installed.
- Downloads use `curl --proto =https` with TLS verification into `/tmp`, are hashed there, copied to flash as `*.part`, hashed again and renamed.

### Selection rules

1. `arch` matches the host (`x86_64` / `aarch64`).
2. Unraid product version is within `[unraid_min, unraid_max]`.
3. `channel` = the chosen package mode (`latest` default, `previous` for rollback).
4. Highest `frr_version` wins.

No match → the status says so and nothing is downloaded.

---

## Flash layout (plugin-owned)

```text
/boot/config/plugins/FabricRouting/
  FabricRouting.cfg     # settings
  companion.json        # marker for Thunderbolt Net (written only when it changes)
  frr.user.conf         # optional: your own FRR config (kept across reboots)
  packages/
    .bundle-id          # which set is cached
    MANIFEST.txt        # "<sha256>  <file>" in install order
    *.txz
```

The log is `/var/log/fabricrouting.log` and the job lock is `/run/fabricrouting.lock` (flock); neither touches the flash.

---

## Job steps

### Download & Install packages (Latest / Previous)

1. Resolve the package set from the bundled catalog.
2. Download any file not already on flash with the right sha256; verify. **Any failure stops the job** (`FAILED`, `_ERROR_`); `MANIFEST.txt` and the previous files stay as they were.
3. Write `MANIFEST.txt` with hashes, delete package files from older sets.
4. Install (step below), then build `/etc/frr` and start/restart FRR.

### Install (job, Flash cache only, array start)

`scripts/frr-install-packages` reads `MANIFEST.txt`, refuses lines without a sha256, checks **every** file before installing anything, ignores files it does not list, and runs `upgradepkg --install-new` for packages not already installed. It exits non-zero on any problem and the job reports failure.

`MANIFEST.txt` files from older plugin versions (names only) are upgraded once by matching the files on flash against the bundled hashes; anything that does not match is refused.

### Config and daemons

Built from flash every time — see [DOCS.md — FRR config files](../DOCS.md#frr-config-files): baseline + route guard, Thunderbolt Net fragment, `frr.user.conf`; `daemons` from the settings (+ `fabricd` when the fragment exists); `vtysh.conf` when missing.

FRR is started with `/usr/sbin/frrinit.sh start`, or `restart` when `/etc/frr` or the packages changed or an enabled daemon is down. `reload` is not used: it needs `frr-reload.py` (Python), which Unraid does not ship.

---

## User-facing options

| Setting | Default | Purpose |
|---------|---------|---------|
| Package choice | Latest (Flash cache only once a cache exists) | Which set the button downloads, or install from flash |
| Install on array start | **Yes** | Reinstall into RAM after reboot |
| Package mirror URL | empty | Alternative file host (hashes stay bundled) |
| zebra / fabricd / staticd | **Yes** | Core + OpenFabric |
| bgpd / ospfd / ospf6d / isisd / bfdd | **No** | Opt-in |
| Start/restart FRR | **Yes** | Bring the stack up, restart on changes |
| Fabric route guard + prefixes | **Yes**, `10.254.0.0/16 10.255.0.0/16`, `fc00::/7` | Limit which OpenFabric routes reach the kernel |

---

## Building packages (maintainer duty)

1. Build libyang + FRR for Unraid's Slackware-class userspace (source tarballs pinned by sha256).
2. Upload the `.txz` files to GitHub Release `pkg-<frrver>`.
3. Add a bundle to `packages/manifest.json` with `url` + `sha256` and the Unraid range.
4. Ship a plugin version; users get the new set with **Download & Install packages**.

---

## Security summary

- HTTPS only, TLS verified, for every package download.
- sha256 from the bundled catalog is required before a file reaches flash and again before every install.
- No remote scripts are run; only verified Slackware packages are installed.
- The settings page never uses the network and never writes the flash.
- Uninstall removes only the packages listed in `MANIFEST.txt` with an `_unraid` suffix.
