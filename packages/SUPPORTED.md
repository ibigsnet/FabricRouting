# Supported Unraid versions (package catalog)

**Yes — we map supported versions explicitly.** The plugin matches **Unraid product version + arch** from `packages/manifest.json` (`unraid_min` / `unraid_max` / `arch`). It does **not** key off the Linux kernel string.

## Matrix (current)

| Unraid product | Arch | FRR bundle | Status |
|----------------|------|------------|--------|
| **7.3.x** | **x86_64** | 10.7.0 + libyang 2.1.148 | **Lab-proven** (NIROG / HoloX3D **7.3.2**) |
| **7.0.x – 7.2.x** | **x86_64** | same bundle (catalog range) | **Catalog-allowed**; not separately lab-signed yet |
| 6.12.x | x86_64 | — | **Not in catalog** until a dedicated build is tested |
| any | **aarch64** | — | **Not yet** |

Catalog range today: `unraid_min=7.0.0` … `unraid_max=7.3.99`, `arch=x86_64`.

## Why map at all?

| Risk if we don’t | Mitigation |
|------------------|------------|
| Claim “all Unraid” with one binary | glibc / userspace drift between 6.12 and 7.x |
| User on 6.12 installs 7.x-built FRR | Catalog refuses (no matching bundle) |
| Silent wrong-arch install | `arch` field in every bundle |
| Kernel confusion | Docs: packages track **product version**, not `uname -r` |

## How to add a version

1. Lab install on that Unraid product + arch.  
2. Build or re-validate `.txz` (same or new FRR pin).  
3. Add or widen a `bundles[]` entry in `manifest.json`.  
4. Update this table + `DOCS.md` / CA Overview.  
5. Prefer **narrow ranges** until proven (`7.3.0`–`7.3.99`) rather than `6.0`–`99`.

## Policy

- **Lab-proven** row = we ran install + `vtysh` + basic fabric on real hardware.  
- **Catalog-allowed** = range includes it for convenience; file bugs if 7.0/7.1 differ.  
- **Not listed** = do not expect Apply to download packages.

Plugin UI always shows **Unraid product** + **arch** + **catalog match / no match** so users see exactly why download did or did not run.
