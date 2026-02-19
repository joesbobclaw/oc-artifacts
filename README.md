# OC Artifacts — WordPress Deployment for OpenClaw

A WordPress plugin that lets OpenClaw agents deploy HTML/CSS/JS apps directly to WordPress sites via the REST API, with **proper WordPress script handling**.

## v1.0: How It Works

1. **You POST a single HTML file** — same simple API
2. **Plugin parses the HTML** — extracts `<script>` and `<style>` blocks
3. **Saves JS/CSS as separate files** — in `wp-content/uploads/artifacts/{id}/`
4. **Properly enqueues via WordPress** — `wp_enqueue_script()` / `wp_enqueue_style()`
5. **Sanitizes the HTML body** — via `wp_kses()` with expanded allowed tags
6. **Serves with security headers** — CSP, X-Frame-Options, etc.

**Developer experience is unchanged** — one API call with a single HTML file. The magic happens server-side.

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

## Security

### Access Control
**Only Administrators can create artifacts by default.** This is enforced via custom capabilities.

To allow Editors to create artifacts:
```php
oc_artifacts_grant_to_role('editor');
```

### Script Handling (v1.0+)
- Scripts are extracted and saved as separate files
- Loaded via `wp_enqueue_script()` (WordPress best practice)
- HTML body is sanitized with `wp_kses()`
- No inline `<script>` tags in rendered output

### Content Security Policy
Artifacts are served with CSP headers that:
- ✅ Allow scripts from artifact's upload directory
- ✅ Allow inline styles (for convenience)
- ❌ Block external script loading
- ❌ Block external fetch/XHR (prevents data exfiltration)
- ❌ Block iframe embedding

### Customization Hooks

**Modify CSP headers:**
```php
add_filter('oc_artifacts_csp', function($csp, $post_id) {
    // Allow connecting to a specific API
    return $csp . "; connect-src 'self' https://api.example.com";
}, 10, 2);
```

**Modify allowed HTML tags:**
```php
add_filter('oc_artifacts_allowed_html', function($allowed) {
    // Add custom element
    $allowed['my-component'] = ['class' => true];
    return $allowed;
});
```

## File Structure

```
wp-artifacts/
├── oc-artifacts.php      # Main plugin (post type, API processing)
├── single-artifact.php   # Render template
└── README.md

wp-content/uploads/artifacts/
└── {post_id}/
    ├── style-0.css       # Extracted styles
    ├── script-0.js       # Extracted scripts
    └── ...
```

## Backwards Compatibility

Artifacts created before v1.0 (with raw HTML) still work — the template falls back to legacy rendering mode.

## Examples

- **CyberOps Academy** — Interactive cybersecurity training game
- **Wapuu Run!** — WordPress-themed side-scrolling platformer

## License

MIT — Do whatever you want with it.

## Credits

Built by [Bob](https://bob.newspackstaging.com) 🤖 via OpenClaw.
