export interface CatalogNode { id: string; value: string; slug: string; text?: { key: string; value: string }[]; parentable?: CatalogNode | null; rubric?: CatalogNode[]; category?: CatalogNode[]; product?: Product[] }
export interface Product { id: string; value: string; slug: string; is_active: boolean; price?: { value: string } | null; unit?: { value: string } | null; image?: { hash: string; alt?: string | null; url?: string | null } | null; text?: { key: string; value: string }[]; property?: { id: string; slug?: string | null; value: string }[]; parentable?: CatalogNode | null }
export interface CartItem { productId: string; quantity: string }
export interface ProductPage { data: Product[]; paginatorInfo: { currentPage: number; lastPage: number; total: number; hasMorePages: boolean } }
export interface QuoteItem { productId: string; name: string; slug: string; unit: string; quantity: string; unitPrice: string; lineTotal: string }
export interface Quote { items: QuoteItem[]; total: string; currency: 'RUB'; quoteHash: string }
export interface Order { id: string; status: string; paymentStatus: string; total: string; currency: string; confirmationUrl: string | null }
