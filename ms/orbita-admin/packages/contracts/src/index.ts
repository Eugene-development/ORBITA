import type { Scalars } from './generated.ts';
export type { ProductInput, ContentInput } from './generated.ts';
export type Decimal = Scalars['Decimal']['output'];
export interface Product {
  id: string; value: string; slug: string | null; is_active: boolean;
  price: { value: Decimal } | null; unit: { value: string } | null;
  image: { hash: string; alt: string | null; url?: string | null } | null;
  text: { id?: string; key: string; value: string; slug?: string | null }[];
  property?: { id: string; key: string; value: string; slug?: string | null }[];
  categoryId?: string | null;
}
export interface Employee { id: string; name: string; email: string; role: 'admin' | 'editor'; }
export interface CartItem { productId: string; quantity: Decimal; }
export interface Quote { items: { productId: string; name: string; slug: string; unit: string; quantity: Decimal; unitPrice: Decimal; lineTotal: Decimal }[]; total: Decimal; currency: 'RUB'; quoteHash: string; }
export interface Order { id: string; status: string; paymentStatus: string; total: Decimal; currency: 'RUB'; confirmationUrl: string | null; }
export type { Product as CatalogProduct };
