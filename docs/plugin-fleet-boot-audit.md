# Plugin fleet — boot / install audit (2026-08-12)

Scope: plugins under `rifle/projects` we maintain (ibigsnet).

| Plugin | Boot plg heavy work? | Array start | Version form | Action |
|--------|----------------------|-------------|--------------|--------|
| **FabricRouting** | Was bad (frr_apply download). **Fixed 2026.08.12da** | Local rehydrate only | 12da two-letter | Done |
| **ThunderboltNet** | Light (file deploy only) | Dashboard ports + OpenFabric apply (fast, no big download) | 2026.08.11ap | OK; optional later: detach long OpenFabric if ever slow |
| **NbdExport** | Light | Companion marker; rehydrate default **off** | 2026.08.11bm | OK (safety-first) |
| **StorageGuard** | Optional curl refresh of a few small assets in finish | None | 2026.07.29aa | Low risk; prefer flash/LOCAL if curl ever hangs on bad net |
| **UsbMap** | Scaffold only | — | — | N/A |

## Community / Unraid norms applied

- Boot `plugin install` must not hang `rc.local` (forum: stuck “Installing plugins”).
- Large packages: download to flash once (UI Apply), rehydrate from cache (array start).
- Event scripts: keep short or background (`at` / `nohup`).
- Version: `YYYY.MM.DDaa` two-letter after bare date (StorageGuard/ThunderboltNet RELEASES).

No emergency changes required for TBN/NBD/SG for this incident.
