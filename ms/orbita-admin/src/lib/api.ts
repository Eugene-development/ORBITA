let csrfReady = false;
export async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  const method = options.method || 'GET';
  if (method !== 'GET' && !csrfReady) { const init = await fetch('/auth/csrf', { credentials: 'same-origin', headers: { Accept: 'application/json' } }); if (!init.ok) throw new Error('Не удалось подготовить защищённый запрос'); csrfReady = true; }
  const headers = new Headers(options.headers); headers.set('Accept', 'application/json');
  const xsrf = document.cookie.split(';').map(s => s.trim()).find(s => s.startsWith('XSRF-TOKEN='));
  if (xsrf) headers.set('X-XSRF-TOKEN', decodeURIComponent(xsrf.slice(11)));
  if (options.body && !(options.body instanceof FormData)) headers.set('Content-Type', 'application/json');
  const res = await fetch(path, { ...options, headers, credentials: 'same-origin' });
  const payload = await res.json().catch(() => ({}));
  if (res.status === 419) csrfReady = false;
  if (!res.ok) {
    const errors = payload.errors ? Object.values(payload.errors).flat().join(' ') : '';
    throw new Error(errors || payload.message || (res.status === 401 ? 'Войдите в систему снова' : 'Не удалось выполнить запрос. Повторите попытку.'));
  }
  return payload as T;
}
export async function graphql<T>(query: string, variables: Record<string, unknown> = {}): Promise<T> {
  const payload = await request<{ data: T; errors?: { message: string }[] }>('/graphql', { method: 'POST', body: JSON.stringify({ query, variables }) });
  if (payload.errors?.length) throw new Error(payload.errors.map(e => e.message).join(' '));
  return payload.data;
}
