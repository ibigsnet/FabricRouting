**Fabric Routing (FRR)**

Optional FRRouting for Unraid: boot loads the plugin only; **Settings → Network Settings → Fabric Routing → Apply** can download/verify packages for your Unraid version + arch (enable Auto-download when needed); array start rehydrates from flash cache. Standalone: Thunderbolt Net is not required. Does not edit eth0/br0 by default. Pair with Thunderbolt Net for TB multi-host OpenFabric (policy there; packages here).

Install: see [RELEASES.md](RELEASES.md). Lifecycle: [docs/boot-lifecycle.md](docs/boot-lifecycle.md).