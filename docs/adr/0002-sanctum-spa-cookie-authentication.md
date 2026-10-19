# Sanctum SPA cookie authentication, served on the SPA's own origin

The SPA authenticates with Laravel Sanctum's stateful mode: an HttpOnly session cookie plus the `XSRF-TOKEN` CSRF cookie (`statefulApi()` in `bootstrap/app.php`), rather than a bearer token kept in `localStorage`. This keeps credentials out of JavaScript reach, so an XSS bug cannot steal a session token.

The SPA calls the API on its **own origin** by default (`VITE_API_URL` empty): the Vite dev server forwards `/api`, `/sanctum`, `/docs` and `/storage` to Laravel, and a production host does the same with a reverse proxy. Same-origin requests need no CORS and keep the session cookie first-party in every browser.

## Consequences

- Locally the SPA runs on `localhost:5173` and Laravel on `localhost:8000`; the browser only ever talks to `5173`.
- If the API must live on another domain, set `VITE_API_URL`. The two must then share a registrable domain (for example `admin.example.com` and `api.example.com`), with `SESSION_DOMAIN=.example.com`, the SPA host in `SANCTUM_STATEFUL_DOMAINS`, and CORS allowing credentials for that exact origin (`FRONTEND_URL`).
- Axios sends `withCredentials` and `withXSRFToken`, and calls `GET /sanctum/csrf-cookie` before signing in.
- Personal access tokens (`HasApiTokens`) stay available for non-browser clients and API tooling such as Postman.
