# WordPress Tools — Feature Wishlist

Tracked ideas and future feature requests for upcoming versions of the plugin. Items here are not committed to any release — they're candidates for planning discussions.

---

## Staging / Dev Domain Protections

### Force No-Index on Staging and Dev Domains

Automatically inject `noindex, nofollow` meta tags and `X-Robots-Tag` headers on all requests made to `*.829dev.com` and `*.829stage.com` domains, preventing staging and dev environments from being indexed by search engines.

**Behavior**
- Active by default with no permanent off switch
- Settings page provides a timed disable option (1 hour maximum)
- After the 1-hour window expires, no-index enforcement automatically resumes
- No option to disable permanently

**Implementation notes**
- Hook into `wp_head` for the meta tag and `send_headers` (or `wp_headers`) for the HTTP header
- Store the disable expiry as a transient or option with a timestamp; check on each request
- Settings UI should display the expiry time when disabled
