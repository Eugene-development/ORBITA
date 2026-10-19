export class ApiError extends Error { constructor(public status: number, public payload: Record<string, unknown>) { super(String(payload.message ?? 'Не удалось выполнить запрос. Попробуйте ещё раз.')); } }
export async function graphql<T>(query: string, variables: Record<string, unknown> = {}, signal?: AbortSignal): Promise<T> {
  const params = new URLSearchParams({ query, variables: JSON.stringify(variables) });
  const response = await fetch(`/graphql?${params}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal });
  const body = await response.json();
  if (!response.ok || body.errors?.length || !body.data) throw new Error('Не удалось загрузить каталог. Попробуйте ещё раз.');
  return body.data as T;
}
export async function post<T>(path: string, payload: unknown): Promise<T> {
  const csrfResponse = await fetch('/auth/csrf', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
  if (!csrfResponse.ok) throw new Error('Не удалось установить безопасное соединение. Обновите страницу.');
  const csrf = await csrfResponse.json();
  const response = await fetch(path, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf.csrf_token }, body: JSON.stringify(payload) });
  const body = await response.json().catch(() => ({ message: 'Сервис временно недоступен. Попробуйте ещё раз.' }));
  if (!response.ok) throw new ApiError(response.status, body);
  return body as T;
}
