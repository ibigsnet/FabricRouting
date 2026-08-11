# UnraidFRR releases

## Install

### Community Applications

When this plugin is listed in the [ibigsnet/unraid-templates](https://github.com/ibigsnet/unraid-templates) CA repository, open **Apps**, search **UnraidFRR** or **FRR**, and install.

### Raw plugin URL

**Plugins → Install Plugin** → paste a raw `.plg` URL:

| Track | URL |
|-------|-----|
| **Latest (`main`)** | `https://raw.githubusercontent.com/ibigsnet/UnraidFRR/main/unraidfrr.plg` |

Pinned tags (when published):  
`https://raw.githubusercontent.com/ibigsnet/UnraidFRR/vVERSION/unraidfrr.plg`

After install: hard-refresh the browser, then **Settings → FRR (FRRouting)**.

## Versioning

Same lexicographic Unraid rules as StorageGuard / Thunderbolt Net:

- `YYYY.MM.DD` first that calendar day  
- then `aa`, `ab`, … same day  
- No hyphens; two-letter suffixes only after the date  

Bump only `<!ENTITY version>` and `<CHANGES>` in `unraidfrr.plg`.

## Ship checklist

1. Bump version + CHANGES in `unraidfrr.plg`  
2. Push `main`  
3. Tag `vVERSION` when you want a pin  
4. Ensure [unraid-templates](https://github.com/ibigsnet/unraid-templates) `plugins/unraidfrr.xml` points at the desired PluginURL (usually `main`)  
5. Optional: GitHub Release notes  

## Current

| Track | Version | Notes |
|-------|---------|--------|
| `main` | **2026.08.11** | Initial public scaffold: package install hooks, daemon UI, idle without packages |

## Links

| | |
|--|--|
| **GitHub** | https://github.com/ibigsnet/UnraidFRR |
| **CA templates** | https://github.com/ibigsnet/unraid-templates |
| **Docs** | [DOCS.md](DOCS.md) |
| **Thunderbolt Net** | https://github.com/ibigsnet/ThunderboltNet |
