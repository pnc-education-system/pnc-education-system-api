# Security Improvements

## 🚨 Critical Issues Found

### Frontend (6 critical)

| # | Issue | File |
|---|-------|------|
| 1 | All tokens in localStorage — XSS can steal access_token, refresh_token, permissions | `src/stores/auth.ts:36-39` |
| 2 | .env committed to git — API URL + any future secrets exposed in git history | `.env` (tracked) |
| 3 | No Content Security Policy — no defense against XSS or script injection | `index.html` |
| 4 | API over HTTP, not HTTPS — all credentials, tokens sent in plaintext | `.env`, `axios.ts:5` |
| 5 | Vue DevTools in production — exposes full app state, localStorage, components | `vite.config.ts:13` |
| 6 | Client-side permission check only — route guard reads from localStorage, easily bypassed | `src/router/index.ts:154-157` |

### Backend (3 critical)

| # | Issue | File |
|---|-------|------|
| 7 | `Student::insert()` bypasses mass-assignment protection | `StudentImportService.php:190` |
| 8 | JWT_SECRET in .env — if leaked, any JWT can be forged | `.env:68` |
| 9 | PII logged to files — full student records in log (names, emails, phones, DOB) | `storage/logs/laravel.log` |

---

## 🔑 Token Storage Issues

### "Don't store password in localStorage"

✅ Found — currently BOTH `access_token` and `refresh_token` are stored in `localStorage`:

| File | What's stored |
|------|--------------|
| `src/stores/auth.ts:36-37` | `access_token`, `refresh_token` in localStorage |
| `src/stores/auth.ts:39` | `permissions` array in localStorage |
| `src/views/auth/Login.vue:94-97` | Duplicate writes to localStorage (bypasses store) |

**Fix:** Move tokens to httpOnly secure cookies or at minimum use sessionStorage + add CSP headers.

### "Don't store token in browser devtool"

The token appears in devtools because:
1. No CSP — index.html has no Content-Security-Policy, so any script can read localStorage
2. HTTP not HTTPS — token is visible in network tab in plaintext
3. No httpOnly flag — cookies not used, so tokens are accessible to JS

---

## 📋 Plan to Fix (by priority)

### Phase 1 — Quick Wins (30 min each)

- [ ] Remove .env from git — add to .gitignore, git rm --cached .env
- [ ] Disable Vue DevTools in production — `vueDevTools({ enabled: false })` or env guard
- [ ] Remove duplicate localStorage writes in Login.vue:94-97
- [ ] Set APP_DEBUG=false in production .env
- [ ] Disable `SESSION_ENCRYPT=false` → `true` in production

### Phase 2 — Security Headers (1-2 hours)

- [ ] Add CSP to index.html — restrict script-src, object-src, base-uri
- [ ] Add API middleware for security headers — X-Content-Type-Options, Referrer-Policy
- [ ] Restrict CORS from wildcard `*` to specific frontend origin

### Phase 3 — Token Storage (3-4 hours)

- [ ] Replace localStorage with httpOnly + secure cookies for refresh token
- [ ] Keep short-lived JWT in memory (not localStorage) — read from Pinia state only
- [ ] Remove demoLogin() function or gate behind feature flag

### Phase 4 — Backend Hardening (2-3 hours)

- [ ] Fix Student::insert() → use Student::create() to respect $fillable
- [ ] Add rate limiting on import endpoints
- [ ] Stop logging PII — sanitize logs (remove full row data from error logs)
- [ ] Add input re-validation in commit() endpoint
- [ ] Hash password reset tokens before storing in DB
- [ ] Add virus/malware scanning on file uploads
- [ ] Pin maatwebsite/excel version constraint (remove wildcard `*`)
