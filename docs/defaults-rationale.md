# Defaults rationale (Fabric Routing / FabricRouting)

Why each default is what it is, what happens if you change it, and what we changed after lab use.

---

## Package lifecycle

| Setting | Default | Why | If you flip it |
|---------|---------|-----|----------------|
| **Download** | Explicit **Download & Install packages** button only | Avoid surprise multi‑MB network fetches. Apply settings, boot and array start never download. | — |
| **Install on array start** | **Yes** | Unraid’s live root is RAM: installed packages do not survive reboot. Reinstall the sha256-verified **flash cache** without downloading. | **No** — after reboot FRR binaries are gone until you run Download & Install (Flash cache only). |
| **Package channel** | **latest** | Single published set today; “previous” reserved for rollbacks. | **previous** only matters when the catalog has that channel. |
| **Package mirror URL** | empty | Package URLs come from the bundled catalog (GitHub Release). | A mirror only supplies files; hashes always come from the bundled catalog, so a mirror cannot change what gets installed. |

---

## Daemons

| Setting | Default | Why | If you flip it |
|---------|---------|-----|----------------|
| **zebra** | **Yes** | Core FRR process; without it nothing installs kernel routes. | **No** — fabricd/staticd largely useless. |
| **fabricd** | **Yes** | OpenFabric is the main reason labs install this plugin (Thunderbolt rings, multi-hop, Proxmox peers). | **No** — packages still install; multi-hop fabric protocol off. |
| **staticd** | **Yes** | Cheap; useful if FRR-managed statics appear later. Does not rewrite Unraid Network Settings. | **No** — fine if you only use fabricd + Unraid statics. |
| **bgpd / ospfd / ospf6d / isisd / bfdd** | **No** | LAN-safe: no surprise dynamic protocols on br0/Wi‑Fi. | **Yes** — you must configure them; never auto-enrolls LAN ifaces. |
| **Start/restart FRR** | **Yes** | Bring daemons up after install/rehydrate; restart when `/etc/frr` or packages changed so the switches take effect. | **No** — `/etc/frr` is still written; you start FRR yourself. |
| **Fabric route guard** | **Yes** (`10.254.0.0/16 10.255.0.0/16`, `fc00::/7`) | A fabric peer must not be able to install LAN, default or other routes on this host. Defaults match Thunderbolt Net addressing. | **No** — every OpenFabric route reaches the kernel table. Prefer adding your ranges instead. |

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
| Download packages at boot | Unraid best practice: boot stays short; network fetch only from the package button |
| BGP/OSPF on | Expert opt-in |

---

## Related

- [frr-and-unraid-routing.md](frr-and-unraid-routing.md)  
- [scope-and-safety.md](scope-and-safety.md)  
- [boot-lifecycle.md](boot-lifecycle.md)  
