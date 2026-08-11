# UnraidFRR — development plan

Follows the same release discipline as StorageGuard / Thunderbolt Net (`YYYY.MM.DDxx`, tags, no empty-file pushes).

## Product pillars

1. **Standalone** — never require Thunderbolt Net  
2. **Idle-safe** — no packages ⇒ no installpkg / no daemon churn  
3. **LAN-safe defaults** — never auto-enroll eth*/br* into protocols; no ip_forward  
4. **Honest invasive** — package + daemon management is opt-in surface area  
5. **Companion marker** — optional integration without PHP coupling  

## Stages

| Stage | Deliverable | Lab? |
|-------|-------------|------|
| **0** | Scaffold, docs, scope/safety | No |
| **1** | Harden baseline conf, UI warnings, logging | No |
| **2** | First real FRR `.txz` (or documented build) for one Unraid line | **Yes** — install on test Unraid |
| **3** | Boot reinstall reliability, uninstall options, CA template | Yes |
| **4** | Optional signed download URL from GitHub Releases | Yes |
| **5** | Status: daemon health, vtysh snippet, conflict detect (foreign frr.conf) | Yes |

## Lab checklist (when you re-hook devices)

Flagship fabric: **Proxmox A/B/C + Unraid D/E** — see Thunderbolt Net `docs/fabric-proxmox-unraid.md`.

- [ ] Install UnraidFRR alone on Unraid — Settings page loads; idle with empty packages  
- [ ] br0 DHCP/static still works; Docker still works  
- [ ] Drop packages → Apply → `vtysh -v`  
- [ ] No unexpected routes on `ip route` for LAN  
- [ ] Install Thunderbolt Net → OpenFabric status sees FRR  
- [ ] **L2/L3:** Proxmox ↔ Unraid static TB, then OpenFabric adjacency  
- [ ] **L5:** full five-node ring failover (with TBN)  

## Versioning / ship

See [RELEASES.md](RELEASES.md). Tag when packages or behavior are lab-proven; don’t tag every docs commit.
