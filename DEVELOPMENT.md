# FabricRouting — development plan

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
| **2** | First real FRR `.txz` (or documented build) for one Unraid line | **Yes** — [package-build-plan.md](docs/package-build-plan.md) (lab Unraid 7.3.2 x86_64) |
| **3** | Boot reinstall reliability, uninstall options, CA template | Yes |
| **4** | Optional signed download URL from GitHub Releases | Yes |
| **5** | Status: daemon health, vtysh snippet, conflict detect (foreign frr.conf) | Yes |

## Lab checklist (when you re-hook devices)

Mixed fabric testing (any node count): see Thunderbolt Net `docs/fabric-proxmox-unraid.md`.

- [ ] Install FabricRouting alone — Settings page loads; idle with empty packages  
- [ ] br0 still works; Docker still works  
- [ ] Drop packages → Apply → `vtysh -v`  
- [ ] No unexpected LAN routes on `ip route`  
- [ ] Thunderbolt Net sees FRR for OpenFabric  
- [ ] **L2/L3:** Linux/Proxmox ↔ Unraid static TB, then OpenFabric  
- [ ] **L5:** multi-node failover when an alternate path exists  

## Versioning / ship

See [RELEASES.md](RELEASES.md). Tag when packages or behavior are lab-proven; don’t tag every docs commit.
