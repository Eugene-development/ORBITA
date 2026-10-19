import { error } from '@sveltejs/kit';
import { PRODUCT } from '#lib/queries.ts';
import { serverGraphql } from '#lib/server/catalog.ts';
import type { Product } from '#lib/types.ts';
import type { PageServerLoad } from './$types';
export const load: PageServerLoad = async ({ params }) => { const { product_one: product } = await serverGraphql<{ product_one: Product | null }>(PRODUCT, { slug: params.slug }); if (!product) error(404, 'Товар не найден. Возможно, он был удалён из каталога.'); return { product }; };
