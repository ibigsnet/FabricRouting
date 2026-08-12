# FRR package build plan — first Unraid bundle (lab: NIROG)

**Goal:** Ship the first automated UnraidFRR package set so **Fabric Routing → Apply** downloads real `.txz` files, `installpkg`s them, and leaves `vtysh` / `zebra` / `fabricd` usable.

**Lab host:** `192.168.1.3` (NIROG)  
**Do not touch:** `/mnt/cache` BTRFS RAID1 data (appdata, domains, docker.img, etc.). Package work is flash + RAM root only.

---

## Contents

- [Lab facts (gathered)](#lab-facts-gathered)
- [What “done” looks like](#what-done-looks-like)
- [Architecture](#architecture)
- [Phase 0 — Repo scaffolding](#phase-0--repo-scaffolding)
- [Phase 1 — Build environment](#phase-1--build-environment)
- [Phase 2 — First packages (libyang + frr)](#phase-2--first-packages-libyang--frr)
- [Phase 3 — Install on NIROG (no catalog yet)](#phase-3--install-on-nirog-no-catalog-yet)
- [Phase 4 — Catalog + GitHub Release](#phase-4--catalog--github-release)
- [Phase 5 — Plugin E2E + companions](#phase-5--plugin-e2e--companions)
- [Phase 6 — Network matrix (you + optional adapters)](#phase-6--network-matrix-you--optional-adapters)
- [Hard rules / safety](#hard-rules--safety)
- [Version pins (initial)](#version-pins-initial)
- [Decision log](#decision-log)

---

## Lab facts (gathered)

| Item | Value |
|------|--------|
| Host | NIROG @ `192.168.1.3` |
| Unraid | **7.3.2** |
| Arch | **x86_64** |
| Kernel | `6.18.38-Unraid` |
| Userspace | Slackware **15.0+**, **glibc 2.43** |
| CPU / RAM | Ryzen 9 9950X3D (32 threads), **61 GiB** RAM |
| Flash | `/boot` ~8.4 GiB free (enough for package cache) |
| Rootfs | tmpfs-style ~31 GiB free (installpkg target) |
| UnraidFRR | Installed; `auto_download=yes`; log: **catalog empty** |
| Docker | Present (build vehicle) |
| **Cache** | BTRFS 2-device pool `/mnt/cache` (~7.1 TiB used) — **sacred** |
| NIC hardware (PCI) | Intel **I225-V** (1G-class), **Aquantia AQC113** (10G), Intel **AX210** Wi‑Fi 6E, **TB4 Maple Ridge** |
| Interfaces currently up | **`wlan0` = 192.168.1.3/24** only (wired eth / TB host-net not up in `ip` dump) |
| TB | Thunderbolt domain `0-0` present; no `thunderboltN` addresses yet |

**Implication:** Package **build + installpkg** can run over **Wi‑Fi**. Multi-hop / OpenFabric / 10G / TB validation needs **physical wiring** later (see Phase 6).

---

## What “done” looks like

1. Two (or more) **Slackware-style `.txz`** packages: at least **libyang** + **frr** (with **fabricd**).  
2. Built for **x86_64**, installable on Unraid **7.3.x** via `installpkg`.  
3. Hosted on **GitHub Release** (e.g. `pkg-10.x.y`).  
4. `packages/manifest.json` has a **non-empty** `bundles[]` for:
   - `channel: latest`
   - `arch: x86_64`
   - `unraid_min` / `unraid_max` covering **7.3.x** (and likely 7.0–7.3 while we learn glibc range)
5. On NIROG: **Apply** → download → sha256 OK → installpkg → `vtysh -c 'show version'` and fabricd present.  
6. Reboot/array-start rehydrates from flash cache.  
7. Uninstall path still `removepkg`s from MANIFEST.  
8. **br0/wlan**, Docker, **cache pool** unchanged and healthy.

---

## Architecture

```text
                    ┌─────────────────────────────────────┐
  Upstream FRR      │  Source: FRR tag (e.g. frr-10.7.0)  │
  + libyang         │  + libyang release tarball          │
                    └─────────────────┬───────────────────┘
                                      │ build (Docker)
                                      ▼
                    ┌─────────────────────────────────────┐
  Build box         │  Slackware-compatible chroot/image  │
  (Docker on NIROG  │  ./configure --enable-fabricd …     │
   or workstation)  │  make + makepkg → .txz              │
                    └─────────────────┬───────────────────┘
                                      │ upload
                                      ▼
                    ┌─────────────────────────────────────┐
  GitHub            │  Release assets: *.txz              │
                    │  manifest.json → url + sha256       │
                    └─────────────────┬───────────────────┘
                                      │ HTTPS
                                      ▼
                    ┌─────────────────────────────────────┐
  UnraidFRR plugin  │  frr_download_bundle → flash cache  │
                    │  installpkg → RAM root              │
                    │  daemons + start_frr                │
                    └─────────────────────────────────────┘
```

**Not used as install sources:** official FRR `.deb` / `.rpm` catalogs.  
**Used from those catalogs:** version pins, feature expectations, dep names.

**Do not build FRR binaries on Nobara/Fedora host** for shipping — wrong glibc/ABI. Build in a **Slackware-oriented** environment; **test only on Unraid**.

---

## Phase 0 — Repo scaffolding

**Owner:** maintainer (this repo). **Risk:** none on lab data.

| Task | Output |
|------|--------|
| `packages/BUILD.md` | Short maintainer how-to (pointer to this plan) |
| `packages/build/` | Docker-based build scripts + Dockerfiles |
| `packages/build/config.env` | `FRR_VERSION`, `LIBYANG_VERSION`, arch |
| Example empty bundle comments in manifest | Already documented shape |
| CI optional later | Build on tag `pkg-*` only after first manual success |

**Exit:** `podman`/`docker` can run a smoke “hello makepkg” container on NIROG or local with volume mount to `/mnt/user/…` **build work dir on cache is OK for scratch** if under a dedicated path like `/mnt/cache/frr-build` that we create — **do not** write into appdata/domains randomly. Prefer **`/mnt/cache/frr-build`** or **`/tmp/frr-build`** on rootfs for intermediate; final `.txz` copy to flash `packages/` only after success.

Scratch on cache: only a **new directory we own**; never rebalance/format/wipe cache devices.

---

## Phase 1 — Build environment

### Recommended vehicle: Docker on NIROG

Why NIROG:

- Same glibc **2.43** target as production install  
- 61 GiB RAM, fast CPU  
- Docker already installed  
- SSH root available  

Why not build bare on Unraid without a container:

- No full Slackware toolchain by default (no gcc in earlier probe)  
- Contaminating RAM root mid-build is messier than a container  

### Image strategy (ordered preference)

1. **Custom Dockerfile** `FROM` a minimal Slackware 15 / current image **or** Debian bookworm **only if** we static/careful — prefer Slackware userspace for `installpkg` layout.  
2. Practical first path used by many Unraid packagers:  
   - Container with build tools  
   - Install prefix `/usr`  
   - Package with `makepkg` producing standard `/install/doinst.sh` Slackware layout  
3. If Slackware base image is painful: build in container, stage into a DESTDIR tree, then wrap with a small **makepkg** script that produces valid `.txz` (file list + compression).  

### Toolchain inside build image

- gcc/g++, make, autoconf, automake, libtool, pkg-config  
- flex, bison, python3  
- libcap, json-c, readline, ncurses, pam (as needed)  
- **libyang** built first as `.txz` and installpkg’d **inside** the build container before FRR  
- Optional: protobuf-c, c-ares (FRR configure will tell us)

### Hardening the build

- Record `./configure` line in `packages/build/frr-configure.sh`  
- Enable **at least:** `zebra`, **`fabricd` (OpenFabric)**, `staticd`  
- Optional off by default in plugin: bgpd, ospfd — still **compile them in** so later daemon toggles work without rebuild  
- Disable docs/PDF if they pull heavy deps for v1  
- Produce **reproducible-ish** tarball names:  
  `libyang-VERSION-x86_64-1_unraid.txz`  
  `frr-VERSION-x86_64-1_unraid.txz`

**Exit:** container builds both packages; artifacts under `/mnt/cache/frr-build/out/` (or `/tmp/...`).

---

## Phase 2 — First packages (libyang + frr)

### Version pins (starting proposal)

| Package | Pin | Rationale |
|---------|-----|-----------|
| FRR | **10.7.0** (tag `frr-10.7.0`) or **10.6.1** if 10.7 fights deps | Current stable line from upstream releases |
| libyang | Version required by that FRR release notes / configure | FRR will fail configure without matching libyang |

**First ship decision:** Prefer **10.6.1** if we want slightly safer “first blood”; use **10.7.0** if configure is clean. Lab can try 10.7.0 first; fall back if build breaks.

### Build order

1. libyang → `.txz` → install into build env  
2. frr → `.txz` (links against libyang)  
3. `sha256sum` both → record for manifest  

### Configure (FRR) — draft

```bash
./configure \
  --prefix=/usr \
  --sysconfdir=/etc \
  --localstatedir=/var \
  --enable-user=root \
  --enable-group=root \
  --enable-vty-group=root \
  --enable-multipath=64 \
  --enable-shared \
  --enable-fpm \
  --enable-fabricd \
  --enable-staticd \
  --enable-bgpd \
  --enable-ospfd \
  --enable-ospf6d \
  --enable-isisd \
  --enable-bfdd \
  --disable-doc \
  # adjust after first configure probe on Unraid glibc
```

(Exact flags refined in Phase 1 after first configure run.)

### Package contents checklist

- `/usr/lib/frr/*` or `/usr/lib64/frr/*` daemons  
- `vtysh`, `zebra`, **`fabricd`**, `staticd`, …  
- `/etc/frr/` defaults (daemons file template OK)  
- **No** forced `ip_forward=1` in doinst  
- **No** auto-start of bgpd on install (plugin owns `/etc/frr/daemons`)

**Exit:** two `.txz` files + sha256; `installpkg -warn` or dry-run inspection of file lists.

---

## Phase 3 — Install on NIROG (no catalog yet)

**Manual path first** (prove packages before public catalog).

```text
# On NIROG (example)
cp out/*.txz /boot/config/plugins/UnraidFRR/packages/
# Write MANIFEST.txt install order: libyang then frr
installpkg /boot/config/plugins/UnraidFRR/packages/libyang-*.txz
installpkg /boot/config/plugins/UnraidFRR/packages/frr-*.txz
vtysh -c 'show version'
ls /usr/lib/frr/fabricd /usr/sbin/fabricd 2>/dev/null
```

Then **Fabric Routing → Apply** (daemons only / start) without download.

**Verify safety:**

| Check | Expect |
|-------|--------|
| `ip route` | No surprise default via FRR |
| Docker containers | Still run |
| `/mnt/cache` | Untouched, mounts present |
| `wlan0` / management IP | Still `192.168.1.3` |
| `removepkg` | Clean removal test on a **second** attempt after reinstall |

**Exit:** FRR works after manual installpkg; plugin can start daemons.

---

## Phase 4 — Catalog + GitHub Release

1. Create GitHub Release on `ibigsnet/UnraidFRR`:  
   `pkg-10.x.y` (match FRR version)  
2. Upload `.txz` assets  
3. Update `packages/manifest.json`:

```json
"bundles": [
  {
    "channel": "latest",
    "unraid_min": "7.0.0",
    "unraid_max": "7.3.99",
    "arch": "x86_64",
    "frr_version": "10.x.y",
    "label": "FRR 10.x for Unraid 7.x x86_64",
    "packages": [
      {
        "file": "libyang-….txz",
        "url": "https://github.com/ibigsnet/UnraidFRR/releases/download/pkg-10.x.y/libyang-….txz",
        "sha256": "…"
      },
      {
        "file": "frr-….txz",
        "url": "https://github.com/ibigsnet/UnraidFRR/releases/download/pkg-10.x.y/frr-….txz",
        "sha256": "…"
      }
    ]
  }
]
```

4. Bump plugin version if needed (catalog is raw GitHub — users pick up on next Apply without plugin bump, but flash message helps).  
5. Wipe local packages cache on NIROG, **Apply** with auto-download, confirm log shows download not “catalog empty”.

**Exit:** cold Apply on clean packages/ dir fully automates install.

---

## Phase 5 — Plugin E2E + companions

| Test | Pass criteria |
|------|----------------|
| Apply (download path) | Packages in flash cache + live binaries |
| Array stop/start or reboot | `install_on_start` rehydrates FRR |
| Destructive: disable auto-download | No re-fetch; still uses cache |
| Uninstall UnraidFRR | `removepkg` via MANIFEST; no leftover daemons |
| Thunderbolt Net OpenFabric | Chip / status sees FRR; policy can write conf when TB links exist |
| LAN-safe | No OSPF/BGP on br0/wlan unless user enables + conf (defaults off) |

**Exit:** Stage 2–3 of `DEVELOPMENT.md` checked off for single-node.

---

## Phase 6 — Network matrix (you + optional adapters)

Package correctness **does not** require wires. Fabric **behavior** does.

| Link | Hardware on NIROG | Status now | Your action when ready |
|------|-------------------|------------|-------------------------|
| Mgmt | Wi‑Fi AX210 `wlan0` | **Up** @ 192.168.1.3 | Keep for SSH/GUI |
| 1G-class | Intel I225-V | PCI present, **no iface up** | Cable to switch/peer; assign eth in Unraid |
| 10G | Aquantia AQC113 | PCI present, **no iface up** | 10G DAC/fiber/copper to peer |
| TB4 | Maple Ridge dual | Domain present | TB cable + Thunderbolt Net host-net |
| USB NIC | Optional | — | Extra underlay / isolation tests |

**Suggested order after packages work:**

1. Bring **I225** or **10G** up on a private subnet (not wiping cache).  
2. Static L3 to a second Linux/Proxmox box.  
3. Install Thunderbolt Net; TB peer; OpenFabric On.  
4. Multi-hop only when ≥2 paths or ≥3 nodes exist.

**You handle:** physical TB/Ethernet cables, second endpoint.  
**We handle:** packages, plugin, conf templates, remote SSH tests once links show in `ip`.

---

## Hard rules / safety

| Rule | Why |
|------|-----|
| **Never** format, wipe, rebalance, or remove `/mnt/cache` devices | Your RAID1 data |
| Prefer package work on **flash** + **RAM** + optional **`/mnt/cache/frr-build` scratch only** | Isolated from appdata |
| No production FRR policy on `wlan0` for “experiments” without intent | Management path |
| Defaults stay LAN-safe | Scope doc |
| HTTPS + sha256 only in catalog | Automation design |
| Test `removepkg` before advertising | Uninstall honesty |

---

## Version pins (initial)

| Item | Proposal | Locked when |
|------|----------|-------------|
| First FRR | Try **10.7.0**, fallback **10.6.1** | First green build |
| First libyang | Whatever FRR 10.x requires | First green configure |
| Bundle range | `unraid_min=7.0.0` `unraid_max=7.3.99` | After install on 7.3.2 |
| Arch v1 | **x86_64 only** | — |
| aarch64 | Later second bundle | — |

---

## Decision log

| Date | Decision |
|------|----------|
| 2026-08-12 | Lab = NIROG 7.3.2 x86_64 glibc 2.43; catalog empty confirmed |
| 2026-08-12 | Build via Docker on NIROG preferred; no deb/rpm direct install |
| 2026-08-12 | First bundle = libyang + frr w/ fabricd; OpenFabric is the killer feature |
| 2026-08-12 | Cache RAID1 out of scope for all destructive ops |

---

## Immediate next actions (execution order)

1. **Phase 0:** Add `packages/build/` scripts + Dockerfile scaffold in UnraidFRR.  
2. **Phase 1:** On NIROG, create `/mnt/cache/frr-build`, pull base image, prove compile of a tiny C hello + makepkg.  
3. **Phase 2:** libyang → frr build; iterate configure until fabricd links.  
4. **Phase 3:** manual installpkg + plugin Apply (daemons).  
5. **Phase 4:** Release + manifest (public automation).  
6. **Phase 5–6:** E2E + wiring when you’re ready.

**Blocked on you only when:** physical Ethernet/TB/USB NIC testing (Phase 6). Everything through Phase 5 can proceed over current Wi‑Fi management path.
