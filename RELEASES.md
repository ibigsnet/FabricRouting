# UnraidFRR releases

## Install

**Plugins → Install Plugin** → paste raw `.plg` URL:

| Track | URL |
|-------|-----|
| **Latest (`main`)** | `https://raw.githubusercontent.com/ibigsnet/UnraidFRR/main/unraidfrr.plg` |

(Publish the GitHub repo first; pin tags later with `vYYYY.MM.DDxx`.)

## Versioning

Same lexicographic Unraid rules as StorageGuard / Thunderbolt Net:

- `YYYY.MM.DD` then `aa`, `ab`, … same day  
- No hyphens; two-letter suffixes only after the date  

## Ship checklist

1. Bump `<!ENTITY version>` + `<CHANGES>` in `unraidfrr.plg`  
2. Push `main`  
3. Tag `vVERSION` when you want a pin  
4. Optional: CA template in `unraid-templates`  

## Related plugins

| Plugin | Repo |
|--------|------|
| UnraidFRR | https://github.com/ibigsnet/UnraidFRR |
| Thunderbolt Net | https://github.com/ibigsnet/ThunderboltNet |
| Storage Guard | https://github.com/ibigsnet/StorageGuard |
