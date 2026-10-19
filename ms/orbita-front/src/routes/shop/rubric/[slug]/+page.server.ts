import { error } from '@sveltejs/kit';
import { CATALOG_ONE } from '#lib/queries.ts';
import { serverGraphql } from '#lib/server/catalog.ts';
import type { CatalogNode } from '#lib/types.ts';
import type { PageServerLoad } from './$types';
export const load: PageServerLoad = async ({ params }) => { const { catalog_one: node } = await serverGraphql<{ catalog_one: CatalogNode | null }>(CATALOG_ONE, { slug: params.slug }); if (!node) error(404, 'Раздел каталога не найден.'); return { node }; };
