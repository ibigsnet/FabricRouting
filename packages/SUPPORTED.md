# Supported Unraid versions (package catalog)

The plugin matches **Unraid product version + arch** from `packages/manifest.json` (`unraid_min` / `unraid_max` / `arch`). It does **not** key off `uname -r` (kernel).

Two kinds of rows in the matrix:

| Status | Meaning |
|--------|---------|
| **Lab-confirmed** | We installed the bundle on real hardware, ran `vtysh`, and exercised basic fabric (or at least install + daemons). |
| **Suggested (compatible)** | Same catalog range / same binary we *expect* to work (same major Unraid line + arch, similar userspace). Not separately lab-signed. Report issues if it fails. |
| **Not in catalog** | Apply will not download packages. Need a new build or explicit range before support claims. |

---

## Matrix (current)

| Unraid product | Arch | FRR package set | Status |
|----------------|------|-----------------|--------|
| **7.3.2** | **x86_64** | FRR **10.7.0** + libyang **2.1.148** | **Lab-confirmed** — NIROG + HoloX3D (install, fabricd, OpenFabric on eth0) |
| **7.3.x** (other 7.3 builds) | **x86_64** | same bundle | **Suggested** — same product line as lab |
| **7.0.x – 7.2.x** | **x86_64** | same bundle | **Suggested** — catalog range; similar 7.x userspace; not separately lab-signed |
| 6.12.x | x86_64 | — | **Not in catalog** |
| any | **aarch64** | — | **Not in catalog** |

**Catalog entry today:** `unraid_min=7.0.0` … `unraid_max=7.3.99`, `arch=x86_64`, channel `latest`.

---

## How we decide “suggested”

Rough compatibility (not a guarantee):

- Same **major Unraid product family** as a lab-confirmed host (here: **7.x**)  
- Same **arch** (`x86_64`)  
- Packages are Slackware-style `.txz` built against a **Slackware 15–class** toolchain; Unraid 7.x glibc has been fine in lab  
- Kernel version does **not** select the package (USB4STREAM / kernel modules are unrelated)

We still prefer **narrow lab-confirmed rows** in this doc even when the catalog range is wider, so users know what was actually tested.

---

## Why map at all?

| Risk if we don’t | Mitigation |
|------------------|------------|
| Claim “all Unraid” with one binary | Product-version ranges + arch |
| 6.12 user gets a 7.x-oriented binary | No bundle → no download |
| Wrong arch | `arch` on every bundle |
| “It needs kernel 6.18” confusion | Docs + UI: **product version**, not kernel |

---

## How to promote a version to lab-confirmed

1. Install Fabric Routing on that Unraid product + arch.  
2. Apply → packages install → `vtysh -c 'show version'`.  
3. Optional: OpenFabric or static underlay smoke test.  
4. Update this matrix (**Lab-confirmed** + host notes).  
5. Only then market that exact version as tested.

Widening **catalog** range without lab: allowed for **Suggested**, with clear wording.

---

## Related

- [manifest.json](manifest.json) — machine-readable ranges  
- [README.md](README.md) — maintainer build/release  
- [../docs/frr-and-unraid-routing.md](../docs/frr-and-unraid-routing.md) — FRR vs stock Routing Table  
