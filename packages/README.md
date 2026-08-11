# FRR packages for Unraid

Drop Slackware-compatible FRR package files here on the **flash**:

```text
/boot/config/plugins/UnraidFRR/packages/
```

The plugin copies or reads from that path at array start (see `scripts/frr-install-packages`).

## Expected layout (on flash after install)

```text
/boot/config/plugins/UnraidFRR/packages/
  frr-*.txz          # or .tgz — exact names depend on the build
  (optional deps)
  MANIFEST.txt       # optional: one package file name per line, install order
```

## Status of official builds

Unraid does **not** ship FRR. Community or project-maintained builds must match:

- Unraid major line (e.g. 6.12 / 7.x) userspace  
- x86_64  
- Libraries expected by that FRR build  

Until **ibigsnet/UnraidFRR** GitHub Releases host tested `.txz` files:

1. Build FRR for Slackware/Unraid yourself, or  
2. Use a known-good third-party package **at your own risk**, or  
3. Leave this folder empty — the plugin stays idle (safe).

When releases exist, optional `package_base_url` in UnraidFRR.cfg may download into this directory (default **off** until URLs are trusted).

## MANIFEST.txt example

```text
# install order
libyang-2.x.txz
frr-10.x.txz
```

If missing, the install script installs every `*.txz` / `*.tgz` in name sort order.

## Verify after install

```bash
vtysh -v
pgrep -a zebra
pgrep -a fabricd
ls /etc/frr
```
