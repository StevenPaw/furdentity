# Furdentity

Headless [Silverstripe](https://silverstripe.org/) backend (CMS **without** pages)
with a standalone [Vue 3](https://vuejs.org/) single-page frontend, built with
[Vite](https://vite.dev/) and served from the same repository.

## Architecture

| Part | Location | Notes |
|--|--|--|
| CMS / backend | `app/` | `silverstripe/admin` + asset admin, no `silverstripe/cms` / SiteTree. Themed with `stevenpaw/silverstripe-cms-backend-theme`. |
| Vue 3 SPA | `frontend/` | Built to `public/frontend/`, served for every non-API route by `App\Control\FrontendController`. |
| Routing | `app/_config/routes.yml` | Fixed routes only. |

### APIs

| API | Prefix | Auth | Purpose |
|--|--|--|--|
| Public | `/api/v1/public` | `X-Api-Key` header (accepted keys via `API_PUBLIC_KEYS`; open while empty) | Read-only data for third parties |
| Internal | `/api/v1/internal` | `Authorization: Bearer <jwt>` | Feeds the own Vue frontend |
| Auth | `/api/v1/auth` | – | `POST /login`, `POST /refresh` – issues JWTs for the internal API |

Endpoints in place today: `GET /public/ping`, `GET /public/profiles`,
`GET /internal/me`, `GET /internal/profiles`, `POST /auth/login`, `POST /auth/refresh`.

## Setup

1. Copy `.env.example` to `.env` and set `JWT_SIGNING_KEY`
   (`php -r "echo bin2hex(random_bytes(32));"`).
2. `ddev start` – runs `composer install`, `yarn install`, `yarn build` and
   `sake dev/build` automatically.
3. Backend: <https://furdentity.ddev.site/admin> (`admin` / `password`).
4. Frontend (production build): <https://furdentity.ddev.site/>

## Development

- `ddev start`, then `yarn dev` for the hot-reloading frontend on
  <https://furdentity.ddev.site:5173> (proxies `/api` to the DDEV site).
- After changing PHP models: `ddev exec vendor/bin/sake dev/build`.

## Build

`yarn build` → output in `public/frontend/`.

## Tooling

- `composer phpstan` – static analysis
- `composer lint` / `composer fix` – PHP_CodeSniffer
- `composer rector-dry` – Rector (targets PHP 8.5 + Silverstripe 6.2)

## Notes

`App\Model\Profile` + `App\Admin\ProfileAdmin` are example scaffolding so the CMS
and APIs have real data. Replace them with the real furry-identity models.
