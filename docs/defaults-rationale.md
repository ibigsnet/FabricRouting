# Defaults rationale (Fabric Routing / FabricRouting)

Why each default is what it is, what happens if you change it, and what we changed after lab use.

---

## Package lifecycle

| Setting | Default | Why | If you flip it |
|---------|---------|-----|----------------|
| **Auto-download packages** | **No** | Avoid surprise multi‑MB network fetch on every Apply. Catalog/network only when the operator opts in. First install: set **Yes** once, Apply, then you may leave **No**. | **Yes** — Apply may download from GitHub Releases (needs internet). **No** — uses flash cache only; fails quietly if cache empty. |
| **Install on array start** | **Yes** | Unraid’s live root is RAM: `installpkg` does not survive reboot. Rehydrate from **flash cache** without downloading. | **No** — after reboot FRR binaries are gone until you Apply (or installpkg manually). |
| **Package channel** | **latest** | Single published bundle today; “previous” reserved for rollbacks when we ship a second channel. | **previous** only matters when the catalog has that channel. |
| **Catalog URL** | empty (official) | Pin to project `manifest.json` on GitHub. | Custom URL only if you host a mirror; HTTPS + sha256 still required. |

### Why not Auto-download = Yes forever?

| Concern | Notes |
|---------|--------|
| Surprise bandwidth / time | FRR package set is tens of MB; Apply should not always hit the network |
| Reproducible hosts | Flash cache is the durable artifact; reboots rehydrate offline |
| First-time still easy | UI docs: turn Yes once, Apply, progress window |
| Security posture | Fewer automatic outbound fetches after packages are known-good |

---

## Daemons

| Setting | Default | Why | If you flip it |
|---------|---------|-----|----------------|
| **zebra** | **Yes** | Core FRR process; without it nothing installs kernel routes. | **No** — fabricd/staticd largely useless. |
| **fabricd** | **Yes** | OpenFabric is the main reason labs install this plugin (Thunderbolt rings, multi-hop, Proxmox peers). | **No** — packages still install; multi-hop fabric protocol off. |
| **staticd** | **Yes** | Cheap; useful if FRR-managed statics appear later. Does not rewrite Unraid Network Settings. | **No** — fine if you only use fabricd + Unraid statics. |
| **bgpd / ospfd / ospf6d / isisd / bfdd** | **No** | LAN-safe: no surprise dynamic protocols on br0/Wi‑Fi. | **Yes** — you must configure them; never auto-enrolls LAN ifaces. |
| **Start FRR on Apply** | **Yes** | After install/rehydrate, bring daemons up so Status is meaningful. | **No** — packages sit idle until you start FRR yourself. |

---

## Thunderbolt Net metric reference (related)

| Setting | Old default | **New default** | Why |
|---------|-------------|-----------------|-----|
| **OpenFabric metric reference** | 100000 (100 G) | **20000 (~20 G)** | Linux Thunderbolt host-net often trains ~20 G each way, not 100 G. Auto metric = ref÷trained; floor 1. With 20 G ref, typical Thunderbolt hops get metric **1**; faster DACs also floor at **1** (do not auto-steal). Prefer **manual** metric if a 100 G DAC must win over 20 G Thunderbolt. |

See Thunderbolt Net `docs/routing-openfabric.md`.

---

## What we deliberately do **not** default to

| Non-default | Reason |
|-------------|--------|
| Auto-enroll br0 / wlan / docker0 | Protect management and Docker |
| ip_forward from this plugin | Unraid LAN behavior stays Unraid’s |
| Download packages at boot | Unraid best practice: boot stays short; network fetch on Apply |
| BGP/OSPF on | Expert opt-in |

---

## Related

- [frr-and-unraid-routing.md](frr-and-unraid-routing.md)  
- [scope-and-safety.md](scope-and-safety.md)  
- [boot-lifecycle.md](boot-lifecycle.md)  
