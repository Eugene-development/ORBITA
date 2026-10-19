import { error } from '@sveltejs/kit';
import blog from '#lib/content/blog.json';
import type { PageServerLoad } from './$types';
export const load: PageServerLoad = ({ params }) => { const articles = blog.articles as Record<string, { id: string; title: string; description: string; html: string }>; const content = articles[params.id]; if (!content) error(404, 'Статья не найдена.'); return { content }; };
