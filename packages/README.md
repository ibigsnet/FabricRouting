# Package catalog

**Normal use:** install Fabric Routing, open **Settings → Network Settings → Fabric Routing**, then **Download & Install packages**. You do not need to put files in this folder by hand.

## Files

| File | Role |
|------|------|
| `manifest.json` | Catalog: channels, Unraid version ranges, package URLs + sha256 |
| `SUPPORTED.md` | Which Unraid product versions the catalog covers |
| GitHub Releases `pkg-*` | Host the large `.txz` binaries referenced by the manifest |

Build scripts and container notes for producing Slackware-style FRR `.txz` packages are **not** in this repo (workstation-only). They live locally under `~/.local/share/ibigsnet-notes/plugin-ops/frr-build/`.

Default catalog URL (production channel):

```text
https://raw.githubusercontent.com/ibigsnet/FabricRouting/stable/packages/manifest.json
```

See [docs/automation-design.md](../docs/automation-design.md) for how download, flash cache, and array-start rehydrate work.
