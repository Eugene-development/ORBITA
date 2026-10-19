import type { Handle } from '@sveltejs/kit/hooks';
import { api } from '#lib/server/api.ts';
export const handle: Handle = async ({ event, resolve }) => {
  event.locals.employee = null;
  if (!event.url.pathname.endsWith('/health') && event.request.headers.has('cookie')) {
    try { const res = await api(event, '/auth/me'); if (res.ok) { const data = await res.json(); event.locals.employee = data.user ?? data.employee ?? data; } } catch { /* Protected layout shows a safe login page. */ }
  }
  const response = await resolve(event);
  response.headers.set('Cache-Control', 'private, no-store');
  response.headers.set('X-Robots-Tag', 'noindex, nofollow');
  response.headers.set('Referrer-Policy', 'no-referrer');
  return response;
};
