# Shopify App – Symfony Template

A production-oriented [Symfony](https://symfony.com/) template for building [Shopify apps](https://shopify.dev/docs/apps) using the official [shopify-app-php](https://github.com/Shopify/shopify-app-php) package. Uses **offline-only** access tokens, encrypted token storage, GDPR webhooks, and automatic webhook registration.

## Requirements

- PHP 8.4+
- [Composer](https://getcomposer.org/)
- A database (PostgreSQL, MySQL, or SQLite for local dev) – used for access token storage via Doctrine ORM
- [Shopify CLI](https://shopify.dev/docs/api/shopify-cli#installation) (for local development)
- A [Shopify Partner](https://partners.shopify.com/) app

## Installation

1. **Install dependencies**

   ```bash
   composer install
   ```

2. **Configure environment**

   Copy `.env.example` to `.env` and set at least `APP_SECRET` and `DATABASE_URL`:

   ```bash
   cp .env.example .env
   ```

   - **DATABASE_URL** – Use PostgreSQL or MySQL in production; SQLite is fine for local dev (see `.env.example`).
   - **DEFAULT_URI** – Base URL of your app (e.g. `https://your-app.com`). Used for webhook callback URLs and must match your Shopify app’s application URL in production.
   - **SHOPIFY_API_KEY** / **SHOPIFY_API_SECRET** – For local development, the Shopify CLI injects these when you run `shopify app dev`. Set them explicitly for production.
   - **SHOPIFY_TOKEN_ENCRYPTION_KEY** – (Optional) Base64-encoded 32-byte key for encrypting access tokens at rest. Generate with `openssl rand -base64 32`. If unset, tokens are stored in plaintext (not recommended for production).

3. **Install Shopify CLI** (if not already installed)

   ```bash
   npm install -g @shopify/cli@latest
   ```

4. **Configure the Shopify app**

   - In the project root, ensure `shopify.app.toml` and `shopify.web.toml` exist (they are provided).
   - Run once to link and populate config from your Partner Dashboard:

   ```bash
   shopify app dev --reset
   ```

   Only use `--reset` the first time. This will set `client_id` and other values in `shopify.app.toml` and provide env vars for the dev server.

5. **Database and migrations**

   Run migrations to create the `shopify_access_token` table:

   ```bash
   php bin/console doctrine:migrations:migrate --no-interaction
   ```

## Running the app

- **With Shopify CLI (recommended for development)**

  ```bash
  shopify app dev
  ```

  This starts the Symfony server (via `shopify.web.toml`’s `dev` command) and tunnels your app so you can install it in a development store.

- **Standalone (e.g. production or local without CLI)**

  ```bash
  symfony server:start --port=8000 --allow-http
  # or
  php -S localhost:8000 -t public
  ```

  Set `SHOPIFY_API_KEY`, `SHOPIFY_API_SECRET`, and `DATABASE_URL` in your environment (or `.env`).

## What’s included

- **App Home** (`/`) – Embedded app entry point. Verifies the request with `verifyAppHomeReq`, exchanges or refreshes an **offline** access token, registers webhooks on first success (see below), and renders a minimal page with [App Bridge](https://shopify.dev/docs/api/app-bridge) and [Polaris](https://shopify.dev/docs/api/app-home/using-polaris-components).
- **Patch ID token** (`/auth/patch-id-token`) – Route for the [patch ID token](https://shopify.dev/docs/apps/build/security/set-up-iframe-protection) flow used by App Home.
- **Webhooks**
  - `POST /webhooks/app/uninstalled` – Verifies with `verifyWebhookReq`, deletes stored tokens for the shop.
  - `POST /webhooks/customers/data_request` – GDPR: customer data request (verify, respond 200).
  - `POST /webhooks/customers/redact` – GDPR: customer redact (verify, respond 200; implement data deletion if you store customer PII).
  - `POST /webhooks/shop/redact` – GDPR: shop redact (verify, respond 200).

**Token storage:** Access tokens are stored in the **database** via Doctrine ORM (`shopify_access_token` table). If `SHOPIFY_TOKEN_ENCRYPTION_KEY` is set, `token` and `refreshToken` are encrypted at rest (libsodium, prefix `enc:v1:`); existing plaintext values are still read for backward compatibility.

**Webhook registration:** On the first successful token exchange or refresh in App Home, the app registers the `app/uninstalled` webhook via the Admin GraphQL API using `DEFAULT_URI` for the callback URL. A hash of the webhook config is stored so registration is not repeated. Mandatory GDPR webhooks (`customers/data_request`, `customers/redact`, `shop/redact`) cannot be created via the GraphQL API and are configured only in `shopify.app.toml` (they apply to all shops when you sync app config via CLI or Partner Dashboard); replace `YOUR_APP_URL` in that file with your production URL (or match `application_url`).

## Production

- Set **APP_ENV=prod** and **APP_DEBUG=0**.
- Use a real database (PostgreSQL or MySQL) and run migrations on deploy: `php bin/console doctrine:migrations:migrate --no-interaction`.
- Set **DEFAULT_URI** to your production app URL (e.g. `https://your-app.com`). This is used for webhook callbacks and must match your Shopify app’s application URL.
- Set **SHOPIFY_API_KEY** and **SHOPIFY_API_SECRET** (and optionally **SHOPIFY_OLD_API_SECRET** for secret rotation) via environment or [Symfony secrets](https://symfony.com/doc/current/configuration/secrets.html).
- Set **SHOPIFY_TOKEN_ENCRYPTION_KEY** (base64 32-byte key) so tokens are encrypted at rest.
- In `shopify.app.toml`, replace `YOUR_APP_URL` in the webhook subscription URIs with your production URL, and ensure **API version** and **scopes** match your app (see [Shopify API versioning](https://shopify.dev/docs/api/usage/versioning)); the template uses `2026-01` and minimal scopes by default.
- Warm the production cache: `php bin/console cache:clear --env=prod`.
- Logging: Monolog is configured; Shopify verify/exchange/webhook results are logged with `code` and `shop` for debugging and monitoring.

## Project structure (Shopify-related)

- `config/services.yaml` – Binds Shopify env vars, `DEFAULT_URI` for `WebhookRegistrar`, and `SHOPIFY_TOKEN_ENCRYPTION_KEY` for `AccessTokenCrypto`; wires token storage to `DoctrineAccessTokenStorage`.
- `src/Entity/ShopifyAccessToken.php` – Doctrine entity for stored tokens (includes `webhooks_config_hash` and `webhooks_registered_at`).
- `src/Shopify/` – `ShopifyAppFactory`, `RequestResponseHelper`, `AccessTokenCrypto`, `AccessTokenStorageInterface`, `DoctrineAccessTokenStorage`, `WebhookRegistrar`.
- `src/Controller/` – `AppHomeController`, `AuthController`, `WebhookController` (app/uninstalled + GDPR endpoints).
- `migrations/` – Doctrine migrations (`shopify_access_token` table and webhook registration fields).
- `shopify.app.toml` – API version `2026-01`, minimal scopes, and GDPR webhook subscription URIs (replace `YOUR_APP_URL`).

## References

- [shopify-app-php README](https://github.com/Shopify/shopify-app-php/blob/main/README.md) – Package usage, request verification, token exchange, GraphQL, webhooks.
- [Shopify App configuration](https://shopify.dev/docs/apps/build/configuration) – `shopify.app.toml` and app setup.
- [Shopify CLI](https://shopify.dev/docs/api/shopify-cli) – Commands and `shopify.web.toml`.
