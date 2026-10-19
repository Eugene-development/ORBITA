import { CATALOG } from '#lib/queries.ts';
import { serverGraphql, isDemo } from '#lib/server/catalog.ts';
import { demoProducts } from '#lib/server/demo.ts';
import { navigationCatalog } from '#lib/site.ts';
import type { CatalogNode } from '#lib/types.ts';
export async function load() {
  let catalogs: CatalogNode[] = navigationCatalog;
  let catalogUnavailable = false;
  try { catalogs = (await serverGraphql<{ catalog: CatalogNode[] }>(CATALOG)).catalog; } catch { catalogUnavailable = true; }
  return { catalogs, catalogUnavailable, demo: isDemo(), demoProducts: isDemo() ? demoProducts : [], analyticsEnabled: process.env.APP_ENV === 'production' && process.env.ORBITA_ANALYTICS_ENABLED === 'true', siteOrigin: process.env.ORBITA_PUBLIC_URL ?? 'https://орбита-строй.рф', imageBase: process.env.ORBITA_IMAGE_URL ?? 'https://lumen-image-bucket.s3.eu-central-1.amazonaws.com/images/', onlinePayment: process.env.ORBITA_ONLINE_PAYMENT_ENABLED === 'true' };
}
