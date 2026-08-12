# Lab fabric: NIROG + HoloX3D

## Hosts

| Role | Hostname | Mgmt (Wi‑Fi — do not reconfigure) | Fabric underlay (2.5G eth0) |
|------|----------|-----------------------------------|----------------------------|
| Primary build / risk | **NIROG** | `192.168.1.3` | `192.168.254.3/24` @ **2500 Mb/s** |
| Secondary peer / light | **HoloX3D** | `192.168.1.4` | `192.168.254.4/24` @ **2500 Mb/s** |

Both: Unraid **7.3.2**, x86_64, kernel `6.18.38-Unraid`.

## Hard rules

1. **Never** change `wlan0` / wireless / default route via `192.168.1.1` — that is SSH/GUI mgmt (and HoloX3D hosts the Nobara VM).  
2. Fabric experiments stay on **`eth0` / `192.168.254.0/24`** (and later Thunderbolt host-net).  
3. **NIROG** `/mnt/cache` BTRFS RAID1 data is sacred — no format/wipe; package builds use `/tmp/frr-build` on rootfs.  
4. Riskier steps (build, first `installpkg`, FRR experiments) prefer **NIROG**. HoloX3D: plugin install + light peer tests.

## Plugin / FRR state (after first package ship)

| Plugin | NIROG | HoloX3D |
|--------|-------|---------|
| UnraidFRR (Fabric Routing) | Yes | Yes |
| Thunderbolt Net | Yes | Not yet |
| NbdExport | Yes | Not yet |
| FRR **10.7.0** live | **Yes** (zebra/fabricd/staticd) | **Yes** |
| OpenFabric on eth0 | **Adjacency Up** ↔ HoloX3D | **Adjacency Up** ↔ NIROG |

**Catalog:** GitHub Release [`pkg-10.7.0`](https://github.com/ibigsnet/UnraidFRR/releases/tag/pkg-10.7.0) + `packages/manifest.json` bundle for Unraid 7.0–7.3 x86_64.

**Mgmt check:** default route still via **wlan0** on both hosts after FRR start.

## Connectivity checks

```bash
# From either host
ping -c 2 192.168.254.3
ping -c 2 192.168.254.4
ethtool eth0 | grep Speed   # expect 2500Mb/s
```

## Build location (NIROG)

```text
/tmp/frr-build/          # rootfs scratch (not cache)
  src/ out/ scripts/
  Dockerfile.toolchain
  build-in-container.sh
  config.env
```

Docker image target: `unraid-frr-toolchain:15.0` (Slackware 15 + gcc/cmake/…).

## After packages exist

1. Copy `.txz` → `/boot/config/plugins/UnraidFRR/packages/` + MANIFEST  
2. `installpkg` on NIROG first; smoke `vtysh`  
3. Repeat light install on HoloX3D  
4. Publish Release + `manifest.json`  
5. OpenFabric/static tests on `192.168.254.0/24` (not Wi‑Fi)

## Physical notes

- Direct **2.5G ↔ 2.5G** cable between eth0s is up.  
- TB4 / 10G Aquantia available later; not required for first FRR package set.
