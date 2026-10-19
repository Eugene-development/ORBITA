import type { CartItem, Product } from './types.ts';
import { CART_KEY, readCart, normalizeQuantity, fractionalUnit, eligibleProduct } from './cart-utils.ts';
export class Cart {
  items = $state<CartItem[]>([]);
  ready = $state(false);
  message = $state('');
  count = $derived(this.items.length);
  hydrate() {
    try { this.items = readCart(localStorage.getItem(CART_KEY), localStorage.getItem('inCart')); this.persist(); }
    catch { this.message = 'Браузер не разрешает сохранить корзину. Оставьте эту страницу открытой.'; }
    this.ready = true;
  }
  sync(raw: string | null) { this.items = readCart(raw); }
  persist() { try { localStorage.setItem(CART_KEY, JSON.stringify(this.items)); } catch { this.message = 'Корзина сохранена только до закрытия страницы.'; } }
  has(id: string) { return this.items.some((item) => item.productId === id); }
  add(product: Product) { if (!eligibleProduct(product)) return; if (!this.has(product.id)) this.items = [...this.items, { productId: product.id, quantity: '1' }]; this.persist(); }
  quantity(id: string, value: string, unit: string) { const normalized = normalizeQuantity(value, fractionalUnit(unit)); if (!normalized) return false; this.items = this.items.map((item) => item.productId === id ? { ...item, quantity: normalized } : item); this.persist(); return true; }
  remove(id: string) { this.items = this.items.filter((item) => item.productId !== id); this.persist(); }
  clear() { this.items = []; this.persist(); try { localStorage.removeItem('inCart'); } catch {} }
}
