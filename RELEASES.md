# FRR (FRRouting) releases

## How to install or update

You can get the plugin in **either** of these ways. Both install the same Unraid plugin.

### Option A — Community Applications (recommended for most users)

1. On Unraid, open the **Apps** tab (Community Applications).  
2. Search for **FRR** or **UnraidFRR**.  
3. Open the app and click **Install** (or **Update** if already installed).  
4. Hard-refresh the browser (**Ctrl+Shift+R** / **Cmd+Shift+R**).  
5. Open **Settings → FRR (FRRouting)** → **Apply** (auto-download on by default).

**Support in CA:** use the app’s **Support** / **Project** menu — Support currently goes to [GitHub Issues](https://github.com/ibigsnet/UnraidFRR/issues); Project goes to [GitHub](https://github.com/ibigsnet/UnraidFRR). (Unraid forum thread can replace Support when published, same pattern as Storage Guard / Thunderbolt Net.)

CA is fed from the [unraid-templates](https://github.com/ibigsnet/unraid-templates) repo (`plugins/unraidfrr.xml`); updates may lag a short time after a GitHub push.

### Option B — Plugins → Install Plugin (raw URL)

1. On Unraid: **Plugins → Install Plugin**.  
2. Paste a **raw** `.plg` URL (must end in `.plg` — not a GitHub “blob” page).  
3. Click **Install**.  
4. Hard-refresh the browser, then open **Settings → FRR (FRRouting)**.

| Track | When to use | URL |
|-------|-------------|-----|
| **Latest (`main`)** | Always get the newest published tree | `https://raw.githubusercontent.com/ibigsnet/UnraidFRR/main/unraidfrr.plg` |
| **Pinned tag** | Install/rollback to a fixed version | `https://raw.githubusercontent.com/ibigsnet/UnraidFRR/vVERSION/unraidfrr.plg` |

After install, confirm the version under **Plugins**.

### After install

- Leave **Auto-download = Yes** and click **Apply** so the plugin can fetch FRR when a catalog bundle matches your Unraid version.  
- No manual package copy is required.  
- Optional companion: [Thunderbolt Net](https://github.com/ibigsnet/ThunderboltNet) for TB OpenFabric policy.  
- Read [DOCS.md](DOCS.md).

### Uninstall

**Plugins → FRR (FRRouting) → Remove** (or remove via CA).

Removal stops FRR, removes packages we installed (when known via MANIFEST), clears emhttp plugin paths and flash config under `/boot/config/plugins/UnraidFRR`. Does not touch Unraid Network Settings or Thunderbolt Net. Hard-refresh after remove. See [DOCS.md — Uninstall](DOCS.md#uninstall-clean-removal).

---

## Version strings (plugin / Unraid)

Unraid plugin updates use **lexicographic `strcmp()`**, not PHP `version_compare()`. Rules (same as Storage Guard / Thunderbolt Net):

| Form | Meaning |
|------|---------|
| `YYYY.MM.DD` | First ship that calendar day |
| `YYYY.MM.DDaa` | 2nd ship same day, then `ab` … `az`, `ba`, `bb`, … |

**Hard rules:**

- No hyphens in the version string.  
- After the bare date, use **two-letter** suffixes only — never a single `a`–`z` on a new day.  
- Bump only `<!ENTITY version "…">` in `unraidfrr.plg`; asset URLs use `?v=&version;`.  
- Add a `###&version;` block under `<CHANGES>` in the same ship.

---

## Git tags and GitHub Releases

| Artifact | Role |
|----------|------|
| **Plugin version** in `.plg` | What Unraid compares for updates |
| **Git tag** `vVERSION` | Pins the full tree for raw URL install/rollback |
| **GitHub Release** | Optional human notes |

### Maintainer checklist

1. Bump version + CHANGES in `unraidfrr.plg`.  
2. Push `main`.  
3. Tag `vVERSION` when you want a pin.  
4. Confirm [unraid-templates](https://github.com/ibigsnet/unraid-templates) `plugins/unraidfrr.xml` PluginURL is correct (usually `main`).  
5. Publish FRR package bundles in `packages/manifest.json` + Releases when builds are ready.

---

## Stable baselines (Git tags)

| Tag | Plugin version | Notes |
|-----|----------------|--------|
| `main` (Latest) | **2026.08.11ad** | Automated package pipeline; UI version labels; catalog may be empty until builds publish |

---

## Links

| | |
|--|--|
| **GitHub repo** | https://github.com/ibigsnet/UnraidFRR |
| **CA templates** | https://github.com/ibigsnet/unraid-templates |
| **Docs** | [DOCS.md](DOCS.md) · [docs/](docs/) |
| **Thunderbolt Net** | https://github.com/ibigsnet/ThunderboltNet |
