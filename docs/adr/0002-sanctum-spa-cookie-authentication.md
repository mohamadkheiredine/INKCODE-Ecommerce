# Sanctum SPA cookie authentication instead of bearer tokens

The SPA authenticates with Laravel Sanctum's stateful mode: an HttpOnly session cookie plus the `XSRF-TOKEN` CSRF cookie (`statefulApi()` in `bootstrap/app.php`), rather than a bearer token kept in `localStorage`. This keeps credentials out of JavaScript reach, so an XSS bug cannot steal a session token.

## Consequences

- In production the SPA and API must share a registrable domain (for example `admin.example.com` and `api.example.com`), with `SESSION_DOMAIN=.example.com` and the SPA host listed in `SANCTUM_STATEFUL_DOMAINS`. Locally both run on `localhost` (ports 5173 and 8000).
- Axios must send `withCredentials` and `withXSRFToken`, and call `GET /sanctum/csrf-cookie` before login.
- CORS must allow credentials for the exact SPA origin (`FRONTEND_URL`); a wildcard origin is not allowed.
- Personal access tokens (`HasApiTokens`) stay available for non-browser clients and API tooling such as Postman.
