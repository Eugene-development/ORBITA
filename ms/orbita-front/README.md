# ORBITA public storefront

Svelte 5 runes, SvelteKit 3, TypeScript 6, Vite 8 and Tailwind 4. The storefront includes local versioned snapshots of `@orbita/ui` and API contracts in `packages/`; it builds independently of the admin.

From the repository root:

```sh
npm --prefix ms/orbita-front run dev
npm --prefix ms/orbita-front run check
npm --prefix ms/orbita-front run test
npm --prefix ms/orbita-front run build
```

The development server defaults to `127.0.0.1:4151`. It proxies `/auth`, `/api` and `/graphql` to `ORBITA_API_PROXY` (default `http://127.0.0.1:8000`) and `/admin` to `ORBITA_ADMIN_PROXY` (default `http://127.0.0.1:4152`). Production routing is provided by `prod/nginx/orbita.conf` and the service nginx; the repository gateway is local-only.

## Runtime configuration

- `ORBITA_API_URL_INTERNAL`: the server-only GraphQL endpoint for public SSR reads.
- `ORBITA_PUBLIC_URL`: canonical public origin; default `https://орбита-строй.рф`.
- `ORBITA_IMAGE_URL`: existing public object prefix; default S3 `lumen-image-bucket/images/`.
- `ORBITA_ONLINE_PAYMENT_ENABLED=true`: show the online-payment option only after the merchant is configured.
- `APP_ENV=production` and `ORBITA_ANALYTICS_ENABLED=true`: initialize the existing Metrika and Jivo widgets. Development never initializes them.
- `ORBITA_DEMO=true`: explicit local visual fixtures. This setting is rejected when `APP_ENV=production`. Demo orders and newsletter submissions are disabled.

## Preserved content and addresses

Home section order, logo, cyan/red/slate palette and local insulation banner come from the old storefront. The 18 articles and informational copy are preserved in `src/lib/content`. Existing unfinished documents/certificates/guarantees/price pages retain their original empty content.

The catalog's historical URL mapping is deliberately retained: `/shop/rubric/:slug` resolves Catalog, `/shop/category/:slug` resolves Rubric, `/shop/products/:slug` resolves Category, and `/shop/product/:slug` resolves Product. About/contact/blog, all information pages, discount/sale, cart and `/spasibo` remain available.

Promotion references select current API products by their existing IDs. Production prices are read from the API. `data-imports` journals from the old repository are not copied, imported or replayed.

## Cart and checkout

Cart state is created inside each layout instance, never in an SSR singleton. It persists under `orbita:cart:v1` and migrates old `inCart` IDs once. Quantity and money arithmetic use decimal strings and integer minor units. Checkout revalidates through a server quote, retains a retry key across reloads, keeps the cart on errors and clears it after an order is confirmed by the API. Payment status is read from the session-protected order endpoint; returning from a provider is not treated as proof of payment.

Public GraphQL reads use GET. Writes establish the same-origin session through `/auth/csrf` and send its token. Product HTML is sanitized server-side; repository-owned informational HTML is trusted static content. No browser service token is used.

## Browser verification

`test/smoke.mjs` checks 42 route/status scenarios including product details and health, cart reload, keyboard search, and overflow at 360/390/768/1024/1440 px. It requires an explicitly configured local demo server and a Playwright package available through `PLAYWRIGHT_MODULE`; it uses an installed Chrome by default. `ORBITA_BASE_URL` overrides the local URL. Screenshots are written under `.artifacts`, or disabled with `ORBITA_SCREENSHOTS=false`. The script blocks order/subscription requests and never submits production data.
