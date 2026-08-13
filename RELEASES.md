# Fabric Routing (FRR) — releases

## How to install or update

You can get the plugin in **either** of these ways. Both install the same Unraid plugin.

### Option A — Community Applications (recommended for most users)

1. On Unraid, open the **Apps** tab (Community Applications).  
2. Search for **FRR** or **UnraidFRR**.  
3. Open the app and click **Install** (or **Update** if already installed).  
4. Hard-refresh the browser (**Ctrl+Shift+R** / **Cmd+Shift+R**).  
5. Open **Settings → Network Settings → Fabric Routing** → set **Auto-download = Yes** once if needed → **Apply**.

**Support in CA:** use the app’s **Support** / **Project** menu — Support currently goes to [GitHub Issues](https://github.com/ibigsnet/UnraidFRR/issues); Project goes to [GitHub](https://github.com/ibigsnet/UnraidFRR). (Unraid forum thread can replace Support when published, same pattern as Storage Guard / Thunderbolt Net.)

CA is fed from the [unraid-templates](https://github.com/ibigsnet/unraid-templates) repo (`plugins/unraidfrr.xml`); updates may lag a short time after a GitHub push.

### Option B — Plugins → Install Plugin (raw URL)

1. On Unraid: **Plugins → Install Plugin**.  
2. Paste a **raw** `.plg` URL (must end in `.plg` — not a GitHub “blob” page).  
3. Click **Install**.  
4. Hard-refresh the browser, then open **Settings → Network Settings → Fabric Routing**.

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

**Plugins → Fabric Routing (FRR) → Remove** (or remove via CA).

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

### Maintainer checklist (ship a release)

Same discipline as Thunderbolt Net / Storage Guard:

1. **Version** — set `<!ENTITY version "YYYY.MM.DDxx">` (**two-letter** after bare date; never single `a`–`z`).  
2. **CHANGES** — `###&version;` user-facing bullets in the same ship.  
3. **Commit** — prefer `YYYY.MM.DDxx: short description`.  
4. **Diff check** — never push emptied `.plg` / `.page` / large PHP.  
5. **Push `main`** — Latest raw URL serves this tree.  
6. **Tag** — `git tag -a "vYYYY.MM.DDxx" -m "Fabric Routing YYYY.MM.DDxx"` + push tag.  
7. **RELEASES.md** — update Stable baselines row.  
8. **CA** — unraid-templates PluginURL usually tracks `main`; bump Overview only if story changes.  
9. **Lab** — NIROG preferred for install/remove cycles (`docs/install-uninstall-audit.md`).

### Tracks at a glance

```text
develop → bump .plg version + CHANGES → push main (= Latest)
       → git tag vVERSION (= pin / rollback)
       → GitHub Release + RELEASES.md row
```

---

## Stable baselines (Git tags)

| Tag | Plugin version | Notes |
|-----|----------------|--------|
| `main` / `v2026.08.12da` | **2026.08.12da** | Boot = files only; package download only on UI Apply; array start local rehydrate |
| (historical) | 2026.08.12d | Single-letter version (non-standard); progress-frame Apply + catalog fixes |

---

## Links

| | |
|--|--|
| **GitHub repo** | https://github.com/ibigsnet/UnraidFRR |
| **CA templates** | https://github.com/ibigsnet/unraid-templates |
| **Docs** | [DOCS.md](DOCS.md) · [docs/](docs/) |
| **Thunderbolt Net** | https://github.com/ibigsnet/ThunderboltNet |
