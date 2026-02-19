# OC Artifacts — WordPress Deployment for OpenClaw

A WordPress plugin that lets OpenClaw agents deploy HTML/CSS/JS apps directly to WordPress sites via the REST API.

## Why?

OpenClaw agents can build interactive web apps (games, tools, visualizations). This plugin gives them a place to deploy those apps — your WordPress site — without needing Vercel, GitHub Pages, or any external hosting.

**The workflow:**
1. Agent builds an HTML/CSS/JS app
2. One API call → live on WordPress at `/artifacts/your-app/`
3. No iframe, no external dependencies, full JS execution

## Installation

1. Download or clone this repo
2. Upload the `wp-artifacts` folder to `/wp-content/plugins/`
3. Activate "OC Artifacts" in WordPress admin
4. Go to **Settings → Permalinks** and click **Save Changes** (flushes rewrite rules)

## Usage

### Deploy an artifact via API

```bash
curl -X POST "https://your-site.com/wp-json/wp/v2/artifacts" \
  -u "username:application-password" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "My Cool App",
    "status": "publish",
    "meta": {
      "artifact_html": "<!DOCTYPE html><html>...</html>",
      "artifact_description": "A cool interactive app"
    }
  }'
```

### From OpenClaw

```python
# In your agent's code
import urllib.request, json, base64

site = "https://your-site.com"
creds = base64.b64encode(b"user:app-password").decode()

with open("my-app.html") as f:
    html = f.read()

data = json.dumps({
    "title": "My Cool App",
    "status": "publish",
    "meta": {
        "artifact_html": html,
        "artifact_description": "Built by my OpenClaw agent"
    }
}).encode()

req = urllib.request.Request(
    f"{site}/wp-json/wp/v2/artifacts",
    data=data,
    headers={
        "Authorization": f"Basic {creds}",
        "Content-Type": "application/json"
    }
)

resp = urllib.request.urlopen(req)
result = json.loads(resp.read())
print(f"Deployed: {result.get('link')}")
```

### View artifacts

All artifacts are served at: `https://your-site.com/artifacts/[slug]/`

Archive page: `https://your-site.com/artifacts/`

## Security Considerations

⚠️ **This plugin allows raw HTML/JS execution.** Only users who can edit posts can create artifacts. Consider these hardening steps for production:

1. **Subdomain isolation** — Serve artifacts from a subdomain (e.g., `artifacts.your-site.com`) to isolate cookies/sessions from your main site

2. **Restrict capabilities** — Modify the plugin to use custom capabilities so only admins can create artifacts

3. **Content Security Policy** — Add CSP headers to limit what artifact JS can do

4. **Review before publish** — Set default status to `pending` and require human approval

## Files

- `oc-artifacts.php` — Main plugin file (registers post type + meta)
- `single-artifact.php` — Template that renders the raw HTML

## Examples Built with This

- **CyberOps Academy** — Interactive cybersecurity training game for kids
- **Wapuu Run!** — WordPress-themed side-scrolling platformer

## License

MIT — Do whatever you want with it.

## Credits

Built by [Bob](https://bob.newspackstaging.com) 🤖 via OpenClaw.
