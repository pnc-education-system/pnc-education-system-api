# Performance Improvements

## 🚀 Backend Performance

| # | Issue | Impact | File |
|---|-------|--------|------|
| 1 | **Row-by-row insert** — `importValidRows()` uses `Student::create()` in a loop instead of bulk `insert()` | Slow for 500+ rows (N queries per insert) | `StudentImportService.php:68-71` |
| 2 | **Loads ALL IDs into memory** — `pluck()` fetches every `student_id_no` and batch ID into an array | Memory exhaustion with large database | `ImportValidationService.php:67-68` |
| 3 | **Validator per row** — creates a new `Validator` instance for every single row | 500 validators allocated for 500 rows | `ImportValidationService.php:115` |
| 4 | **Permission query on every request** — `$user->role->permissions` loaded from DB on every authenticated request | N+1 queries on every route | `PermissionMiddleware.php:32` |
| 5 | **No pagination limits** — `UserController@index` returns all users with no max page limit | Slow response with many users | `UserController.php:19-23` |
| 6 | **Debug logging** — `LOG_LEVEL=debug` writes verbose logs for every operation | Unnecessary disk I/O | `.env:21` |

## 🚀 Frontend Performance

| # | Issue | Impact | File |
|---|-------|--------|------|
| 7 | **No route lazy loading** — all views imported upfront instead of on-demand | Slower initial page load | `src/router/index.ts` |
| 8 | **chart.js bundled always** — heavy charting library loaded even on pages without charts | Unnecessary bundle size | `package.json` |
| 9 | **No debounced search** — `searchQuery` fires API request on every keystroke | Many unnecessary API calls | `EnrollmentsView.vue:211` |

## 📋 Fix Plan

### Already Fixed ✅

| Fix | Detail | 
|-----|--------|
| Bulk insert in commit() | T3.3 `commit()` uses `Student::insert()` (1 query per 100-row chunk) |

### Phase 1 — Quick Wins (30 min each)

- [ ] Add lazy loading to router — use `() => import()` for all views (check which are already lazy)
- [ ] Add 300ms debounce to search inputs in `EnrollmentsView.vue`
- [ ] Set `LOG_LEVEL=warning` in production .env to reduce disk writes

### Phase 2 — Backend Optimization (1-2 hours)

- [ ] Use **chunked `pluck()`** instead of loading all IDs: `Student::pluck('student_id_no')->chunk(1000)`
- [ ] **Cache permissions** in JWT claims at login time (avoid DB query on every request in `PermissionMiddleware`)
- [ ] Add **max page limit** to `UserController@index`: `$users->paginate(min($request->per_page ?? 15, 100))`

### Phase 3 — Import Optimization (2-3 hours)

- [ ] Replace row-by-row `create()` in `importValidRows()` with **chunked bulk `insert()`** (same pattern as `commit()`)
- [ ] **Batch validate** all rows at once instead of creating individual Validator per row — use a single `Validator::make()` with wildcard rules
