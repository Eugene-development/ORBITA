import promotions from '#lib/content/promotions.json';
import { PRODUCT_IDS } from '#lib/queries.ts';
import { serverGraphql } from '#lib/server/catalog.ts';
import type { Product } from '#lib/types.ts';
export async function load() { let products: Product[] = [], unavailable = false; const ids = promotions.discountProducts.flatMap((item) => item.id ? [item.id] : []); try { products = (await serverGraphql<{ productIds: Product[] }>(PRODUCT_IDS, { ids })).productIds; } catch { unavailable = true; } return { products, unavailable }; }
