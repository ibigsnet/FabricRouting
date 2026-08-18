## 2026.08.17ad

- CA/Support: WIP blurb — Thunderbolt Net OpenFabric focus; eth fabric later.
  Support URL → Thunderbolt Net forum thread until a dedicated FRR thread exists.

## 2026.08.17ac

- Download & Install packages: prefer openBox/logging.htm so the button always opens a progress window (openPlugin silent pid=0 on some Network Settings loads).

## 2026.08.17aa

- **Audit/docs:** SECURITY clarifies no stock UI patch, full flash wipe on uninstall; version bump for suite ship.

# Changelog — FabricRouting

User-facing history for this plugin. The `.plg` file (Community Applications / Plugins page) shows only the **most recent releases**; this file is the complete record.

**Install channels:** production/CA uses branch `stable`; lab uses `main`. See [RELEASES.md](RELEASES.md).

---

###2026.08.16aa
- **Install/upgrade hygiene:** prepare always `removepkg`s prior `FabricRouting-*` / legacy
  `UnraidFRR-*` **plugin** packages and wipes emhttp dirs before the new `.txz`. Does **not**
  touch FRR/libyang packages or the flash `packages/` cache.

###2026.08.15af
- Uninstall: also remove /var/tmp/frr (watchfrr scratch).

###2026.08.15ab
- Changelog: Plugins page shows recent entries only; full history on GitHub <code>CHANGELOG.md</code>.

###2026.08.14ao
- Package job: Done button works again after download/install finishes
  (openPlugin + nchan <code>_DONE_</code> / <code>_ERROR_</code>).

###2026.08.14an
- Packages radio: default <strong>Flash cache only</strong> when packages exist (no surprise download);
  empty flash still defaults to <strong>Latest</strong> so first install works.

###2026.08.14am
- Lead copy: short product blurb; move when-to-use / Thunderbolt split into <strong>What is FRR?</strong>
  (drop “not Routing Table” banner and Need this strip).

###2026.08.14al
- UI: blue <code>inline_help</code> on Advanced core daemons (zebra / fabricd / staticd), optional
  protocols, install-on-start, catalog URL, and start-on-Apply.

###2026.08.14ak
- **Fix:** package job no longer hangs on Step 4/4 — close stdio / hard-timeout frrinit so
  openBox reaches DONE; skip start when zebra already running (packages were already OK).

###2026.08.14aj
- **Package UX v2:** radio pick (Latest / Previous / Flash) + **Download &amp; Install packages**
  via Unraid `openBox` (Nvidia-style log + DONE — do not close early). Settings Apply no longer
  downloads packages. Collapse Advanced daemons. Timeout on `frrinit.sh` start so WebUI cannot hang.

###2026.08.14ai
- Network Settings tab order: `Menu=NetworkSettings:z` so Fabric Routing sorts **after** stock
  Routing Table (far right). Numeric ranks (`:1200`) sort *before* unranked stock tabs with
  SORT_NATURAL — that left FR stuck next to Thunderbolt/tbn. UI copy: packages-only vs TBN policy.

###2026.08.14ah
- Attempted tab pin at `:1200` (still before Interface Extra / Routing Table under SORT_NATURAL).

###2026.08.14ag
- Wording: spell out **Thunderbolt** (not ambiguous “TB”, which can mean terabyte).
- **Rename:** product id **FabricRouting** (public **Fabric Routing**). No “Unraid” in the product name (trademark). Legacy UnraidFRR paths migrated on install.
- Install URL: `fabricrouting.plg` on `ibigsnet/FabricRouting`.

###2026.08.14ab
- **Lab channel:** On branch `main`, PluginURL + raw FILE sources point at `main` (lab uninstall/reinstall testing).
- Branch `stable` remains the CA/production pin Production channel is branch `stable` (CA PluginURL).

###2026.08.14aa
- **License:** GNU GPLv3 or later (copyright ibigs, LLC; Author: RifleJock).
- **Release channel:** PluginURL, raw FILE sources, and package catalog pin to branch `stable`.
- SECURITY.md: idle install, no eth0/br0, packages only on Apply, uninstall scope.

###2026.08.13ab
- Docs: public sanitization — SUPPORTED matrix and lab notes use **Machine A/B** patterns (no personal hostnames/IPs); [lab-two-node-fabric.md](docs/lab-two-node-fabric.md).

###2026.08.13aa
- Fleet standard: companion links use **`ibigsGotoNetTab`** (aliases `frrGotoNetTab` / …); apply-on-load for `ibigsWantTab`; never deep-link standalone CA paths.
- Docs: RELEASES cross-plugin UI link table.

###2026.08.13
- UI: companion “Network Settings → Thunderbolt” under strip, not `/Settings/ThunderboltNet`.
- Versioning: bare `2026.08.13` after historical single-letter 12a–g.

###2026.08.12g
- Docs: first-time-setup.md — need/skip, settings guide, first Apply checklist; linked from DOCS + UI.
- UI: companion link (buggy) pointed at /Settings/ThunderboltNet; fixed in 2026.08.13.
- Defaults: Auto-download **No** (opt-in network fetch); first-time Yes → Apply still works.
- UI: beginner “What is FRR?” section; clearer package/daemon help; no public Nvidia branding.
- Docs: defaults-rationale.md; sanitize Nvidia comparisons in public docs.
- **Boot / plg install is files-only** — no package download, no frr_apply (sync or background). Unraid re-runs every .plg at boot; heavy work there could block rc.local → emhttp → array.
- **Network download only on Settings → Fabric Routing → Apply** (auto_download). Network package fetch only on Settings → Apply (flash cache + rehydrate on array start).
- **Array start** rehydrates packages **already on flash** into RAM (`frr_rehydrate_local` / local_only); never fetches catalog. Detached via `at`/`nohup` so emhttp events stay non-blocking.
- Docs: boot-lifecycle.md; install-uninstall-audit + automation-design aligned with Unraid plugin best practice.
- Versioning comment aligned with StorageGuard / Thunderbolt Net (two-letter suffixes only).

###2026.08.12d
- Apply: progress-frame messages (do not close — same UX as other Unraid Settings progress dialogs); single-flight lock.
- Catalog: do not keep empty-bundle cache for a full hour (fixes stale “No match yet”).
- Status: flash package names; no-match help + SUPPORTED link; fix plugin version detection path.
- Docs: SUPPORTED matrix — lab-confirmed vs suggested-compatible Unraid versions.
- Docs: frr-and-unraid-routing.md — stock Routing Table vs FRR; what Unraid can/cannot leverage.
- Docs: scope-and-safety + DOCS cross-links for routing-table impact.
- Uninstall: stop via /usr/sbin/frrinit.sh; kill mgmtd; always removepkg-sweep frr-* and libyang-*.
- Docs: packages/SUPPORTED.md — Unraid product version matrix (lab-proven 7.3.x x86_64).
- Docs: product-roadmap.md — interfaces/metrics UI, CA/forum, ambitions (Proxmox, multi-node AI).
- First FRR package set: libyang 2.1.148 + frr 10.7.0 (fabricd) for Unraid 7.x x86_64 — GitHub Release pkg-10.7.0 + manifest catalog.
- frr_try_start: prefer /usr/sbin/frrinit.sh (our packages).
- Docs: two-host lab fabric pattern; package build plan; packages/build scripts.
- Docs: Contents/TOC on DOCS.md.
- UI: collapsible About; tighter companion strip (less wall of text).
- UI: sectioned Status / Packages / Core daemons / Optional protocols (NBD-style density); less wall of text.
- Public name: **Fabric Routing** (Network Settings tab) · **Fabric Routing (FRR)** (CA / Plugins blurb). Plugin id FabricRouting.
- Note: single-letter 12d was non-standard; use two-letter suffixes going forward.

###2026.08.11ag
- Network Settings tab **Fabric Routing** (with Routing Table / Interface Rules), not System Settings tile.
- Title explains FRR packages vs stock Routing Table; plugin id stays FabricRouting.

###2026.08.11af
- Fix Settings placement: **System Settings tile** (`Menu="OtherSettings"`), not embedded section on /Settings (`Settings:50` was wrong).
- Uninstall: also remove any stray FabricRouting/FRR .page copies under webGui/dynamix; clear /tmp plugin remnants.

###2026.08.11ae
- Docs/UI: when to install (rings, multi-hop, Proxmox+Unraid) vs skip (single static Thunderbolt cable); roles vs Thunderbolt Net OpenFabric policy.

###2026.08.11ad
- UI: status labels distinguish plugin version / Unraid product / FRR package set; USB4STREAM is kernel (not Unraid 7.2 product); companion strip wording.
- Catalog matching uses Unraid product version + arch only (not uname -r).

###2026.08.11ac
- Docs: DOCS/RELEASES/docs index aligned with Storage Guard + Thunderbolt Net (CA + raw install, uninstall).
- Uninstall: stop FRR, removepkg managed packages, clear emhttp + flash config — no leftover hooks; does not touch network.cfg or Thunderbolt Net.
- Install finish message: automated Apply path (no manual package drop).

###2026.08.11ab
- Package catalog: manifest + sha256 + installpkg + array-start rehydrate from flash.
- UI: package channel, auto-download default Yes; status shows Unraid version/arch/bundle match.
- Docs: automation-design.md; DOCS/README updated for full control by the plugin.

###2026.08.11aa
- README: short Unraid Plugins-list blurb (same format as Storage Guard / Thunderbolt Net); long docs stay in DOCS.md.

###2026.08.11
- Initial public release: standalone FRR package install hooks, daemon toggles (zebra/fabricd default on), Settings → FRR UI, array-start install, companion marker for Thunderbolt Net.
- Docs: detailed what-it-does guide, scope/safety (host-wide routing, not Thunderbolt-only), LAN-safe defaults, Thunderbolt Net integration.
- Idle and safe when packages/ is empty. Does not require Thunderbolt Net. CA listing via ibigsnet/unraid-templates.
