# Building FRR packages for Unraid

**End users:** ignore this file. Use **Network Settings → Fabric Routing → Apply** (auto-download).

**Maintainers:** full plan → [docs/package-build-plan.md](../docs/package-build-plan.md).

## Short path

1. Build **libyang** + **frr** (with **fabricd**) as Slackware `.txz` for **x86_64**.  
2. Do **not** ship Debian/RPM packages from frrouting.org — use them only for **version pins**.  
3. Upload `.txz` to a GitHub Release `pkg-<frr-version>`.  
4. Add `bundles[]` entries in [manifest.json](manifest.json) with `url` + `sha256` + Unraid version range.  
5. Lab: wipe flash package cache → Apply → confirm download + `vtysh`.

## Lab (current)

- Host: Unraid **7.3.2** x86_64, glibc **2.43** (NIROG).  
- Sacred: `/mnt/cache` BTRFS RAID1 data.  
- Scratch OK: `/mnt/cache/frr-build` (dedicated dir only).

## Status

- Plugin download pipeline: **done**  
- `manifest.json` bundles: **empty** until first `.txz` ships  
