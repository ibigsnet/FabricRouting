# Boot install must stay light

Unraid installs plugins from `/boot/config/plugins/*.plg` during boot **before** `emhttp` starts. If a plugin install script **blocks** (long network download, package apply, or a hang), the WebUI and array autostart wait.

## Rule

| Do in `.plg` install | Do **not** do in `.plg` install |
|----------------------|----------------------------------|
| Copy plugin files to emhttp | Download large packages from the network |
| Create default config if missing | Call FRR apply / start daemons |
| Register UI / event hooks | Block on remote HTTP for minutes |

Heavy work belongs behind an explicit **Download & Install packages** (or similar) control in the WebUI, with a visible log, after the array is up.

## If the host never reaches the WebUI after a plugin update

1. Boot **Safe Mode** (no plugins), or  
2. From the console, remove or rename the stuck plugin’s `.plg` under `/boot/config/plugins/`, then reboot.  
3. Fix the plugin so install does not block; reinstall when ready.

## Related

- [boot-lifecycle.md](boot-lifecycle.md)  
- [scope-and-safety.md](scope-and-safety.md)  

