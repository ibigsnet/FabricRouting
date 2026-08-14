# Fabric Routing (FRR) — releases

## How to install or update

You can get the plugin in **either** of these ways. Both install the same Unraid plugin.

### Option A — Community Applications (recommended for most users)

1. On Unraid, open the **Apps** tab (Community Applications).  
2. Search for **FRR** or **FabricRouting**.  
3. Open the app and click **Install** (or **Update** if already installed).  
4. Hard-refresh the browser (**Ctrl+Shift+R** / **Cmd+Shift+R**).  
5. Open **Settings → Network Settings → Fabric Routing** → set **Auto-download = Yes** once if needed → **Apply**.

**Support in CA:** use the app’s **Support** / **Project** menu — Support currently goes to [GitHub Issues](https://github.com/ibigsnet/FabricRouting/issues); Project goes to [GitHub](https://github.com/ibigsnet/FabricRouting). (Unraid forum thread can replace Support when published, same pattern as Storage Guard / Thunderbolt Net.)

CA is fed from the [unraid-templates](https://github.com/ibigsnet/unraid-templates) repo (`plugins/fabricrouting.xml`); updates may lag a short time after a GitHub push.

### Option B — Plugins → Install Plugin (raw URL)

1. On Unraid: **Plugins → Install Plugin**.  
2. Paste a **raw** `.plg` URL (must end in `.plg` — not a GitHub “blob” page).  
3. Click **Install**.  
4. Hard-refresh the browser, then open **Settings → Network Settings → Fabric Routing**.

| Track | When to use | URL |
|-------|-------------|-----|
| **Production (`stable`)** | CA / end-user channel | `https://raw.githubusercontent.com/ibigsnet/FabricRouting/stable/fabricrouting.plg` |
| **Recommended freeze** | Known-good FabricRouting line | `https://raw.githubusercontent.com/ibigsnet/FabricRouting/stable-recommended-2026.08.13ad/fabricrouting.plg` |
| **Pinned tag** | Install/rollback to a fixed version | `https://raw.githubusercontent.com/ibigsnet/FabricRouting/vVERSION/fabricrouting.plg` |

### Recommended freeze (2026-08-13)

| | |
|--|--|
| **Label** | **Recommended** (fleet freeze; product id **FabricRouting**) |
| **Plugin version** | **`2026.08.13ad`** |
| **Tag** | [`stable-recommended-2026.08.13ad`](https://github.com/ibigsnet/FabricRouting/releases/tag/stable-recommended-2026.08.13ad) |
| **Also** | `v2026.08.13ad` |
| **Install / rollback** | `https://raw.githubusercontent.com/ibigsnet/FabricRouting/stable-recommended-2026.08.13ad/fabricrouting.plg` |

Includes: trademark-safe **FabricRouting** id (not UnraidFRR), FRR catalog packages, boot = files only, package download only on Apply, array local rehydrate, public doc sanitization, Thunderbolt wording. Legacy UnraidFRR flash paths migrate on install. **`main` may move ahead** after this pin.

After install, confirm the version under **Plugins**.

### After install

- Leave **Auto-download = Yes** and click **Apply** so the plugin can fetch FRR when a catalog bundle matches your Unraid version.  
- No manual package copy is required.  
- Optional companion: [Thunderbolt Net](https://github.com/ibigsnet/ThunderboltNet) for Thunderbolt OpenFabric policy.  
- Read [DOCS.md](DOCS.md).

### Uninstall

**Plugins → Fabric Routing (FRR) → Remove** (or remove via CA).

Removal stops FRR, removes packages we installed (when known via MANIFEST), clears emhttp plugin paths and flash config under `/boot/config/plugins/FabricRouting`. Does not touch Unraid Network Settings or Thunderbolt Net. Hard-refresh after remove. See [DOCS.md — Uninstall](DOCS.md#uninstall-clean-removal).

---

## Version strings

Unraid plugin updates use lexicographic `strcmp()` (not PHP `version_compare`).

| Form | Meaning |
|------|---------|
| `YYYY.MM.DD` | First ship that calendar day (lab wall clock) |
| `YYYY.MM.DDaa` | Further ships same day (`ab` … `az`, `ba`, …) |

- No hyphens. After the bare date, **two-letter** suffixes only (never single `a`–`z`).
- Bump only `<!ENTITY version "…">` in the `.plg`; assets use `?v=&version;`.
- Add a `###&version;` entry under `<CHANGES>` in the same ship.
- Versions only move forward for existing installs (`strcmp`); do not rewind a mistaken future date.

### Cross-plugin UI links (fleet standard)

Same rules as Thunderbolt Net / NBD Export:

| Do | Don’t |
|----|--------|
| `/Settings/NetworkSettings` + `ibigsGotoNetTab('Thunderbolt')` | `/Settings/ThunderboltNet` |
| `/Settings/NetworkSettings` + `ibigsGotoNetTab('Fabric Routing')` | `/Settings/FabricRouting` |

Canonical JS: **`ibigsGotoNetTab(needle, event)`** (aliases: `tbnGotoNetTab`, `frrGotoNetTab`, `nbdGotoNetTab`).

**Network Settings tab rank:** `Menu="NetworkSettings:z"` on `FabricRouting.page`. Unraid keys tabs as `{rank}{pageName}` and sorts with `SORT_NATURAL`. Numeric ranks (Thunderbolt `:1100`, tbn `:1110+`) come *before* unranked stock tabs (Routing Table). Rank `z` places Fabric Routing **after** Routing Table (far right). Do not use `:1200` for that goal.

**Package install UX (v2):** radios + **Download & Install packages** → Unraid `openBox` / `logging.htm` (same family as Nvidia Driver and plugin install). Settings **Apply** does not download. Job script: `scripts/frr-packages-job`.  
Storage: `sessionStorage.ibigsWantTab` (+ legacy `tbnWantTab`).

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
9. **Lab** — use a non-production host for install/remove cycles (`docs/install-uninstall-audit.md`).

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
| **[`stable-recommended-2026.08.13ad`](https://github.com/ibigsnet/FabricRouting/releases/tag/stable-recommended-2026.08.13ad)** | **2026.08.13ad** | **Current recommended freeze** — FabricRouting rename + catalog FRR |
| `v2026.08.13ad` | **2026.08.13ad** | Same tree as the freeze (version tag) |
| (historical) | 2026.08.12d | Single-letter version (non-standard); progress-frame Apply + catalog fixes |

---

## Links

| | |
|--|--|
| **GitHub repo** | https://github.com/ibigsnet/FabricRouting |
| **CA templates** | https://github.com/ibigsnet/unraid-templates |
| **Docs** | [DOCS.md](DOCS.md) · [docs/](docs/) |
| **Thunderbolt Net** | https://github.com/ibigsnet/ThunderboltNet |
