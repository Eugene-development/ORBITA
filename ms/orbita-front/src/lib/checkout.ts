type SessionStorage = Pick<Storage, 'getItem' | 'setItem' | 'removeItem'>;
const attemptKey = 'orbita:checkout-attempt:v1';
export function checkoutAttempt(storage: SessionStorage, signature: string, createKey: () => string = () => crypto.randomUUID()): string {
  try { const existing = JSON.parse(storage.getItem(attemptKey) ?? 'null'); if (existing?.signature === signature && typeof existing.idempotencyKey === 'string') return existing.idempotencyKey; } catch {}
  const idempotencyKey = createKey();
  try { storage.setItem(attemptKey, JSON.stringify({ signature, idempotencyKey })); } catch {}
  return idempotencyKey;
}
export function clearCheckoutAttempt(storage: SessionStorage) { try { storage.removeItem(attemptKey); } catch {} }
