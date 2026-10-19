import type { RequestEvent } from '@sveltejs/kit';
export async function api(event: Pick<RequestEvent, 'request' | 'url' | 'fetch'>, path: string, options: RequestInit = {}) {
  const origin = process.env.ORBITA_API_ORIGIN_INTERNAL || 'http://127.0.0.1:8000';
  const cookie = event.request.headers.get('cookie') || '';
  const headers = new Headers(options.headers);
  headers.set('Accept', 'application/json'); headers.set('Cookie', cookie);
  headers.set('Origin', process.env.ORIGIN || event.url.origin);
  const xsrf = cookie.split(';').map(s => s.trim()).find(s => s.startsWith('XSRF-TOKEN='));
  if (xsrf) headers.set('X-XSRF-TOKEN', decodeURIComponent(xsrf.slice('XSRF-TOKEN='.length)));
  return event.fetch(`${origin}${path}`, { ...options, headers });
}
export async function gql<T>(event: Pick<RequestEvent, 'request' | 'url' | 'fetch'>, query: string, variables: Record<string, unknown> = {}): Promise<T> {
  const res = await api(event, '/graphql', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ query, variables }) });
  const payload = await res.json();
  if (!res.ok || payload.errors?.length) throw new Error(payload.errors?.[0]?.message || 'API временно недоступен');
  return payload.data as T;
}
