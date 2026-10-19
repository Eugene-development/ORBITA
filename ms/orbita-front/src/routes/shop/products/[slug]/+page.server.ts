import { error } from '@sveltejs/kit';
import { CATEGORY_ONE, PRODUCTS } from '#lib/queries.ts';
import { serverGraphql } from '#lib/server/catalog.ts';
import type { CatalogNode, ProductPage } from '#lib/types.ts';
import type { PageServerLoad } from './$types';
export const load: PageServerLoad = async ({ params, url }) => { const { category_one: category } = await serverGraphql<{ category_one: CatalogNode | null }>(CATEGORY_ONE, { slug: params.slug }); if (!category) error(404, 'Категория не найдена.'); const page = Math.max(1, Math.min(10000, Number.parseInt(url.searchParams.get('page') ?? '1', 10) || 1)); const { products } = await serverGraphql<{ products: ProductPage }>(PRODUCTS, { categoryId: category.id, page, first: 24 }); return { category, products }; };
