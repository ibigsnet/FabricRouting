# Package catalog (maintainer / automation)

**End users do not place packages here manually.**

UnraidFRR downloads the matching bundle from `manifest.json` into the flash cache automatically (Nvidia Driver–style).

**Supported Unraid product versions:** see [SUPPORTED.md](SUPPORTED.md) (matrix + how we widen ranges).

## Files

| File | Role |
|------|------|
| `manifest.json` | Public catalog: channels, Unraid version ranges, package URLs + sha256 |
| `SUPPORTED.md` | Human-readable support matrix (lab-proven vs catalog-allowed) |
| GitHub Releases `pkg-*` | Hosts large `.txz` binaries referenced by the manifest |

## Maintainer workflow

1. Build FRR (+ deps) for a target Unraid major line and arch  
2. Upload `.txz` to a GitHub Release  
3. Add a `bundles[]` entry in `manifest.json` with `url` + `sha256`  
4. Users get it on next **Apply** / array start  

See [docs/automation-design.md](../docs/automation-design.md).
