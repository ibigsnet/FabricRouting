# HoloX3D host hang + boot blockers — 2026-08-12

**Chassis:** HoloX3D (GENE) Unraid · `192.168.1.4`  
**Purpose:** Capture why iterations were killing the host / stalling array so we stop thrashing and fix UnraidFRR later.

---

## What is blocking us *right now* (this boot)

### Primary: UnraidFRR plugin install never finishes → `emhttp` never starts

Unraid boot order (simplified):

1. Kernel / wifi / services  
2. `/etc/rc.d/rc.local` installs plugins from `/boot/config/plugins/*.plg`  
3. **Only after plugins return** does rc.local run `/boot/config/go`  
4. `go` ends with `/usr/local/sbin/emhttp` → starts `emhttpd` → array autostart (`startArray="yes"`) → libvirt  

**Observed this boot (post hard reset ~17:49):**

| Check | Result |
|--------|--------|
| `startArray` in `disk.cfg` | `"yes"` (config OK) |
| Cache pool `cache.cfg` | Present (4× SanDisk RAID10, UUID `9dc32bdf-…`) |
| `emhttpd` / nginx | **Not running** — never logged `Starting emhttpd` |
| `/mnt/cache`, `/mnt/user` | **DOWN** |
| libvirt | **Down** (normal until array/emhttp) |
| Blocking process | `php -q /usr/local/sbin/plugin install /boot/config/plugins/unraidfrr.plg` (PID stuck **minutes**) |
| Child | `frr_write_companion_marker(); frr_apply();` waiting on **pipe read** |
| Parent | **`/etc/rc.d/rc.local` still running** — waiting on plugin install |

So **auto array start is not broken by unclean shutdown config**. It simply **never gets a chance to run** because rc.local is wedged on UnraidFRR.

**Kernel reset reason (this boot):**

```text
x86/amd: Previous system reset reason [0x00010800]: system reset pin BP_SYS_RST_L was tripped
```

That is a **hardware reset / power cycle**, not a clean Unraid reboot. Hard resets also mean:

- No clean previous-syslog archive to `/boot/logs/` (we only have old diagnostics zips).  
- Btrfs/cache may need a clean emhttp start later; no evidence yet that unclean alone blocked autostart — FRR did.

### Secondary: VFIO never applied this boot

`/boot/config/vfio-pci.cfg` intentionally has **empty early BIND**; bind is done in **`/boot/config/go`** on normal boot.

Because `go` has not run:

- `vfio-pci` module: **not loaded**  
- GPU `01:00.0/.1`: unbound  
- Nobara NVMe `6a:00.0` (9100 `S7YJNJ0Y800227R`): host **`nvme`** (visible as `/dev/nvme1n1`)  
- USB `68` / `6b.4` / `66`: host **`xhci_hcd`**

Do **not** start Nobara until go has run and VFIO is verified. Host-owned 9100 + VM start = bad.

### Host CPU isolation (context for “hangs”, not this boot stall)

```text
isolcpus=1-7,9-15
Cpus_allowed_list for host tasks: 0,8   # one physical core + SMT only
```

Unraid + Wi‑Fi + SSH + QEMU emulator + array all share **one core pair**. That is intentional for guest gaming but makes the host fragile under:

- GPU VFIO assign/deassign storms  
- `virsh destroy` without waiting for full PCI release  
- Rapid rebind of NVMe/USB between host and vfio  
- Large guest start (48G RAM) while host is busy  

**Recommendation (later, after identity work):** leave **two** host cores free (e.g. keep 0/8 **and** 1/9 for Unraid/QEMU iothreads), or pin QEMU emulator threads explicitly off guest vCPUs. Not required to fix FRR; helps host survive iteration.

---

## Why agent actions kept “hanging” Unraid (process faults)

Not a single magic bug — stacked bad ops:

1. **Rushed state machine** — `destroy` → edit → `start` without hard gates (`domstate=shut off`, VFIO driver list stable, array/libvirt up, guest SSH only after ping **and** sshd).  
2. **Hard `virsh destroy`** while guest mid-boot / GPU active → known path to **host network death** (wlan0/host hard hang) → user forced **BP_SYS_RST_L**.  
3. **Mid-session VFIO thrash** — unbind 9100 from vfio, bind `nvme`, mount, mkswap, rebind vfio, start VM in one script with short sleeps. Host lost after `virsh start`.  
4. **Duplicate long SSH wait loops** against a dead host (noise, not root cause).  
5. **Guest hibernate hang** (separate): `resume=UUID=…` stuck at `systemd-hibernate-resume` — fixed on 9100 with `noresume` + mkswap same UUID (**before** last host death). That fix is on disk; confirm after next healthy boot.

### Safe iteration rules (mandatory)

| Gate | Required before next step |
|------|---------------------------|
| Host | ping + SSH + `emhttpd` running |
| Storage | `/mnt/cache` and `/mnt/user` mounted |
| Libvirt | `pgrep libvirtd` + `virsh list --all` works |
| VFIO | `6a:00.0` → vfio-pci; GPU → vfio-pci; **no** host `/dev/nvme*S7YJ*` |
| Stop VM | prefer `virsh shutdown`; wait until `shut off` (up to ~90s); only then `destroy` |
| Edit XML | only when `shut off` |
| Start VM | after VFIO verify; wait **patiently** for serial past multi-user / guest ping before identity checks |
| Guest root edits | prefer once guest is up; host-mount 9100 only when VM **shut off** and never race rebind |

**Never** start Nobara while 9100 is host-`nvme`.

---

## UnraidFRR — bug to fix later (custom plugin)

**Plugin:** UnraidFRR (ibigsnet / local work)  
**Plg:** `/boot/config/plugins/unraidfrr.plg`  
**Install path:** `/usr/local/emhttp/plugins/UnraidFRR/`

### Failure mode

During boot plugin install, install script runs roughly:

```php
frr_write_companion_marker(); frr_apply();
```

Process tree (stuck):

```text
rc.local
  └─ plugin install unraidfrr.plg
       └─ bash /tmp/inline12-unraidfrr.sh
            └─ php frr_apply() …  (blocked in anon_pipe_read)
                 └─ sh …
```

Also: on install, plugin-manager **re-downloads** many files from GitHub (`UnraidFRR.page`, packages, scripts). Slow or hanging network during boot makes this worse. Boot must not block forever on package download + `frr_apply`.

### Fix directions (later — do not rush mid-gaming-iter)

1. **Boot must not block on FRR apply**  
   - Install plg should only drop files / packages.  
   - `frr_apply()` only from **array start event** (`event/started`) or UI button, with timeout.  
2. **Never wait unbounded on pipes** in install path; timeout + log + leave emhttp free.  
3. **Offline-capable install** — packages on flash under `packages/`; no mandatory GitHub fetch at boot if files already present.  
4. **Safe mode / disable flag** — e.g. `/boot/config/plugins/UnraidFRR/disable` or rename `.plg` to `.plg.disabled` to recover host without FRR.  
5. **Idempotent apply** — if zebra/watchfrr already up or mgmt iface is wifi-only, do not hang on netlink “Operation not supported” (seen on wlan0/tailscale1).  
6. **Log clearly** — `logger -t UnraidFRR` start/end of apply so syslog shows block.

### Emergency recovery (when FRR blocks array)

```bash
# On Holo SSH — only if emhttpd not running and plugin install stuck:
# 1) Stop the stuck install (accept FRR incomplete this boot)
kill <plugin-install-pid>   # and children if needed
# 2) Let rc.local finish OR run go / emhttp manually:
/usr/local/sbin/emhttp
# 3) WebUI: confirm array/cache start (startArray=yes should then run)
# 4) Optional disable until fixed:
mv /boot/config/plugins/unraidfrr.plg /boot/config/plugins/unraidfrr.plg.disabled
```

Do **not** kill random PHP without checking `pgrep -af unraidfrr|plugin install`.

---

## What’s *not* the array problem

- `disk.cfg` already has `startArray="yes"`.  
- Pool-only array (`SYS_ARRAY_SLOTS="0"`) with cache RAID10 is expected for this box.  
- Hard reset makes logs thinner and is bad for Btrfs, but **current** “array won’t auto-start” = **emhttp never started** due to FRR.

---

## Guest (Nobara) notes (still valid)

- Hibernate unstick applied on 9100: `noresume`, resume= stripped, swap recreated UUID `6098a8cf-ab0f-456d-a808-5fe05e86cc77`.  
- Phase 1 domain: sysinfo AMI + `vm=off` (S8) — re-verify after healthy host+guest boot.  
- Protect serials: 9100 `S7YJNJ0Y800227R`; never wipe; 990s host/cache only.

---

## Next steps (ordered)

1. Documented here (this file).  
2. **Later:** fix UnraidFRR non-blocking install (see above).  
3. Recover this boot: kill stuck FRR install *or* disable plg → finish rc.local/go → emhttp → array → verify VFIO → **patient** Nobara start.  
4. Resume MC/EAC identity only after host stable.  
5. Optional: free a second host core for Unraid resilience.

---

*Captured 2026-08-12 from live Holo SSH after user hard-reset; array/libvirt down; FRR plugin install stuck ~4+ minutes on pipe read.*
