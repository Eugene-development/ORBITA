import { error } from '@sveltejs/kit';
import pages from '#lib/content/pages.json';
import promotions from '#lib/content/promotions.json';
import type { PageServerLoad } from './$types';
export const load: PageServerLoad = ({ params }) => {
  if (params.slug === 'action') return { content: null, actions: promotions.factory?.actions ?? [] };
  const content = (pages as Record<string, { title: string; description: string; html: string }>)[params.slug];
  if (!content || ['about', 'contact'].includes(params.slug)) error(404, 'Страница не найдена.');
  return { content, actions: [] };
};
