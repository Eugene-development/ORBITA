import { error } from '@sveltejs/kit';
import { RUBRIC_ONE } from '#lib/queries.ts';
import { serverGraphql } from '#lib/server/catalog.ts';
import type { CatalogNode } from '#lib/types.ts';
import type { PageServerLoad } from './$types';
export const load: PageServerLoad = async ({ params }) => { const { rubric_one: node } = await serverGraphql<{ rubric_one: CatalogNode | null }>(RUBRIC_ONE, { slug: params.slug }); if (!node) error(404, 'Рубрика не найдена.'); return { node }; };
