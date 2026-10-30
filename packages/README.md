# Package catalog

**Normal use:** install Fabric Routing, open **Settings → Network Settings → Fabric Routing**, then **Download & Install packages**. You do not need to put files in this folder by hand.

## Files

| File | Role |
|------|------|
| `manifest.json` | Catalog: channels, Unraid version ranges, package URLs + sha256 |
| `SUPPORTED.md` | Which Unraid product versions the catalog covers |
| GitHub Releases `pkg-*` | Host the large `.txz` binaries referenced by the manifest |

Build scripts for the Slackware-style FRR `.txz` packages are not in this repo.

The catalog is this `manifest.json`, bundled in each plugin version and never fetched at run time. Package files are GitHub Release assets; every entry needs a `sha256`, which is checked before the file reaches flash and again before every install. An optional package mirror (Advanced) only changes where files are downloaded from.

See [docs/automation-design.md](../docs/automation-design.md) for how download, flash cache, and array-start rehydrate work.
