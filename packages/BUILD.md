# Building FRR packages for Unraid

**End users:** ignore this file. Use **Settings → Network Settings → Fabric Routing → Download & Install packages**.

## Short path

1. Build **libyang** + **frr** (with **fabricd**) as Slackware `.txz` for **x86_64**.  
2. Do **not** ship Debian/RPM packages from frrouting.org as the Unraid install path — use them only for version pins.  
3. Upload `.txz` to a GitHub Release `pkg-<frr-version>`.  
4. Add `bundles[]` entries in [manifest.json](manifest.json) with `url` + `sha256` + Unraid product version range.  
5. Test: clear flash package cache → Download & Install packages → confirm `vtysh` works.

## Notes

- Target Unraid’s Slackware-class userspace for the product versions you list in `SUPPORTED.md`.  
- Keep build scratch off production array/cache data.  
- See [SUPPORTED.md](SUPPORTED.md) for the public version matrix.
