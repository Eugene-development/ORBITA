import { error } from '@sveltejs/kit';
import sanitizeHtml from 'sanitize-html';
import { demoQuery } from './demo.ts';
export const isDemo = () => {
  if (process.env.APP_ENV === 'production' && process.env.ORBITA_DEMO === 'true') throw new Error('Демонстрационный режим запрещён в production.');
  return process.env.ORBITA_DEMO === 'true';
};
export function cleanHtml(value: string): string {
  return sanitizeHtml(value, { allowedTags: [...sanitizeHtml.defaults.allowedTags, 'img'], allowedAttributes: { a: ['href', 'title'], img: ['src', 'alt', 'width', 'height', 'loading'], '*': ['class'] }, allowedSchemes: ['https', 'http', 'mailto', 'tel'], transformTags: { img: sanitizeHtml.simpleTransform('img', { loading: 'lazy' }) } });
}
function cleanTexts<T>(value: T): T {
  if (Array.isArray(value)) return value.map(cleanTexts) as T;
  if (value && typeof value === 'object') return Object.fromEntries(Object.entries(value).map(([key, item]) => [key, key === 'text' && Array.isArray(item) ? item.map((text) => ({ ...text, value: cleanHtml(text.value ?? '') })) : cleanTexts(item)])) as T;
  return value;
}
export async function serverGraphql<T>(query: string, variables: Record<string, unknown> = {}): Promise<T> {
  if (isDemo()) return demoQuery(query, variables) as T;
  const endpoint = process.env.ORBITA_API_URL_INTERNAL ?? 'http://127.0.0.1:8000/graphql';
  try {
    const url = new URL(endpoint);
    url.searchParams.set('query', query);
    url.searchParams.set('variables', JSON.stringify(variables));
    const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: AbortSignal.timeout(10_000) });
    if (!response.ok) error(503, 'Каталог временно недоступен. Попробуйте обновить страницу.');
    const result = await response.json();
    if (result.errors?.length || !result.data) error(503, 'Не удалось загрузить каталог. Попробуйте позже.');
    return cleanTexts(result.data as T);
  } catch { error(503, 'Каталог временно недоступен. Свяжитесь с нами по телефону 8 (902) 303-36-54.'); }
}
