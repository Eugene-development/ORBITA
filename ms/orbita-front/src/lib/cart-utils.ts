import type { CartItem, Product } from './types.ts';
export const CART_KEY = 'orbita:cart:v1';
export function normalizeQuantity(value: string, fractional = true): string | null {
  const raw = String(value).trim().replace(',', '.');
  if (!/^\d+(?:\.\d{1,3})?$/.test(raw)) return null;
  const [whole, fraction = ''] = raw.split('.');
  const milli = BigInt(whole) * 1000n + BigInt(fraction.padEnd(3, '0'));
  if (milli <= 0n || milli > 1_000_000_000n || (!fractional && milli % 1000n !== 0n)) return null;
  const suffix = fraction.replace(/0+$/, '');
  return `${BigInt(whole)}${suffix ? `.${suffix}` : ''}`;
}
const measuredUnits = new Set(['кг','г','т','тонна','тонн','kg','g','м','п.м.','п.м','м.п.','м.п','мп','пог.м','пог.м.','погонныйметр','m','м2','м²','кв.м','кв.м.','m2','м3','м³','куб.м','куб.м.','m3','л','литр','l']);
export function fractionalUnit(unit = ''): boolean { return measuredUnits.has(unit.toLocaleLowerCase('ru').replace(/\s/g, '')); }
export function moneyMinor(value: string): bigint {
  if (!/^\d+(?:\.\d{1,2})?$/.test(value)) throw new Error('Некорректная цена');
  const [whole, fraction = ''] = value.split('.');
  return BigInt(whole) * 100n + BigInt(fraction.padEnd(2, '0'));
}
export function decimalMoney(minor: bigint): string { return `${minor / 100n}.${(minor % 100n).toString().padStart(2, '0')}`; }
export function lineTotal(price: string, quantity: string): string {
  const normalized = normalizeQuantity(quantity);
  if (!normalized) throw new Error('Некорректное количество');
  const [whole, fraction = ''] = normalized.split('.');
  return decimalMoney((moneyMinor(price) * (BigInt(whole) * 1000n + BigInt(fraction.padEnd(3, '0'))) + 500n) / 1000n);
}
export function displayMoney(value: string): string {
  const [whole, fraction = '00'] = value.split('.');
  return `${whole.replace(/\B(?=(\d{3})+(?!\d))/g, ' ')}${fraction === '00' ? '' : `,${fraction.padEnd(2, '0')}`} ₽`;
}
export function readCart(raw: string | null, legacy: string | null = null): CartItem[] {
  try {
    const value: unknown = raw ? JSON.parse(raw) : legacy ? JSON.parse(legacy) : [];
    if (!Array.isArray(value)) return [];
    const items = value.map((item: unknown) => typeof item === 'string' || typeof item === 'number' ? { productId: String(item), quantity: '1' } : item).filter((item): item is CartItem => !!item && typeof item === 'object' && 'productId' in item && 'quantity' in item && typeof item.productId === 'string' && typeof item.quantity === 'string' && /^\d+$/.test(item.productId) && !!normalizeQuantity(item.quantity));
    return Array.from(new Map(items.map((item) => [item.productId, { ...item, quantity: normalizeQuantity(item.quantity)! }])).values());
  } catch { return []; }
}
export function eligibleProduct(product: Product): boolean { try { return !!product.is_active && !!product.price && moneyMinor(product.price.value) > 0n; } catch { return false; } }
