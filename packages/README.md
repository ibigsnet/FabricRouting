# Package catalog

**Normal use:** install Fabric Routing, open **Settings → Network Settings → Fabric Routing**, then **Download & Install packages**. You do not need to put files in this folder by hand.

## Files

| File | Role |
|------|------|
| `manifest.json` | Catalog: channels, Unraid version ranges, package URLs + sha256 |
| `SUPPORTED.md` | Which Unraid product versions the catalog covers |
| GitHub Releases `pkg-*` | Host the large `.txz` binaries referenced by the manifest |

Build scripts for the Slackware-style FRR `.txz` packages are not in this repo.

Default catalog: this `manifest.json`, bundled in each plugin version. Package files are GitHub Release assets with `sha256`.

See [docs/automation-design.md](../docs/automation-design.md) for how download, flash cache, and array-start rehydrate work.
