# Fabric Routing — install & releases

## Install

### Community Applications (recommended)

1. Unraid **Apps** → search **Fabric Routing** or **FRR**
2. **Install** or **Update**
3. Hard-refresh the browser, then **Settings → Network Settings → Fabric Routing**
4. Choose a package source → **Download & Install packages** → wait for **Done**

CA is fed from [unraid-templates](https://github.com/ibigsnet/unraid-templates). Updates may lag a short time after a GitHub push.

### Manual install (raw plugin URL)

**Plugins → Install Plugin** → paste a **raw** URL ending in `.plg`:

| Channel | Use when | URL |
|---------|----------|-----|
| **Production (`stable`)** | Normal install / CA channel | `https://raw.githubusercontent.com/ibigsnet/FabricRouting/stable/fabricrouting.plg` |
| **Lab (`main`)** | Newest development tree | `https://raw.githubusercontent.com/ibigsnet/FabricRouting/main/fabricrouting.plg` |
| **Recommended freeze** | Known-good pin | `https://raw.githubusercontent.com/ibigsnet/FabricRouting/stable-recommended-2026.08.13ad/fabricrouting.plg` |
| **Pinned version** | Install or roll back to a fixed tag | `https://raw.githubusercontent.com/ibigsnet/FabricRouting/vVERSION/fabricrouting.plg` |

- **`stable`** — what CA installs; production updates.
- **`main`** — lab only; can be ahead of CA.
- **Tags / freezes** — exact trees that never change.

### Recommended freeze

| | |
|--|--|
| **Version** | **2026.08.13ad** |
| **Tag** | [`stable-recommended-2026.08.13ad`](https://github.com/ibigsnet/FabricRouting/releases/tag/stable-recommended-2026.08.13ad) (also `v2026.08.13ad`) |
| **Install** | `https://raw.githubusercontent.com/ibigsnet/FabricRouting/stable-recommended-2026.08.13ad/fabricrouting.plg` |

Catalog FRR packages, array-start rehydrate from flash, standalone of Thunderbolt Net. Legacy **UnraidFRR** flash paths migrate on install.

### After install

- **Download & Install packages** on the Fabric Routing page (progress window until **Done**).
- Optional: [Thunderbolt Net](https://github.com/ibigsnet/ThunderboltNet) for OpenFabric policy on Thunderbolt links.
- Docs: [DOCS.md](DOCS.md)

### Uninstall

**Plugins → Fabric Routing → Remove** (or remove via CA). Stops FRR and removes packages this plugin installed when known; does not change Unraid Network Settings or Thunderbolt Net.

### Roll back

Paste a freeze or `vVERSION` raw `.plg` URL under **Plugins → Install Plugin**, then hard-refresh.

---

## Version numbers

Plugin versions look like `2026.08.14ap` (date + two-letter suffix). Unraid compares them as plain strings for “update available.”

Changelog bullets ship on the **Plugins** page and optionally as [GitHub Releases](https://github.com/ibigsnet/FabricRouting/releases).

---

## Links

| | |
|--|--|
| **GitHub** | https://github.com/ibigsnet/FabricRouting |
| **CA templates** | https://github.com/ibigsnet/unraid-templates |
| **Docs** | [DOCS.md](DOCS.md) · [docs/](docs/) |
| **Thunderbolt Net** | https://github.com/ibigsnet/ThunderboltNet |
