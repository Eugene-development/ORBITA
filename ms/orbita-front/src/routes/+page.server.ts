import promotions from '#lib/content/promotions.json';
import { PRODUCT_IDS } from '#lib/queries.ts';
import { serverGraphql } from '#lib/server/catalog.ts';
import type { Product } from '#lib/types.ts';
export async function load() {
  const homeIds = promotions.homeProducts.flatMap((item) => item?.id ? [item.id] : []);
  const monthIds = promotions.monthProducts.flatMap((item) => item.id && item.link.startsWith('/shop/product/') ? [item.id] : []);
  let products: Product[] = [], unavailable = false;
  try { products = (await serverGraphql<{ productIds: Product[] }>(PRODUCT_IDS, { ids: [...new Set([...homeIds, ...monthIds])] })).productIds; } catch { unavailable = true; }
  const byIds = (ids: string[]) => ids.flatMap((id) => { const product = products.find((item) => item.id === id); return product ? [product] : []; });
  const monthly = promotions.monthProducts.map((offer) => ({ id: offer.id, title: offer.action, href: offer.link, image: offer.img, product: offer.link.startsWith('/shop/product/') ? products.find((product) => product.id === offer.id) ?? null : null }));
  return { featured: byIds(homeIds), monthly, unavailable };
}
