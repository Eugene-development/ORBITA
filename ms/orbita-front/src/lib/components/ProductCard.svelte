<script lang="ts">
  import { getContext } from 'svelte';
  import type { Cart } from '#lib/cart.svelte.ts';
  import type { Product } from '#lib/types.ts';
  import { imageUrl, imageFallback } from '#lib/site.ts';
  import { displayMoney, eligibleProduct } from '#lib/cart-utils.ts';
  let { product, imageBase, featured = false }: { product: Product; imageBase?: string; featured?: boolean } = $props();
  const cart = getContext<Cart>('orbita-cart');
  const available = $derived(eligibleProduct(product));
</script>
<article class="product-card" class:featured-card={featured}>
  <a class="product-card-link" href={`/shop/product/${product.slug}`}>
    <img src={product.image?.url || imageUrl(product.image?.hash, imageBase)} alt={product.image?.alt || product.value} loading="lazy" width="240" height="240" onerror={imageFallback} />
    <h3>{product.value}</h3>
  </a>
  <div class="product-card-bottom">
    <p class="product-price">{product.price ? displayMoney(product.price.value) : 'Цена не указана'}{#if product.unit?.value}<span> / {product.unit.value}</span>{/if}</p>
    {#if !product.is_active}<p class="product-unavailable">Нет в наличии</p>{/if}
    <button type="button" class:in-cart={cart.has(product.id)} class="button product-add" disabled={!available || cart.has(product.id) || !cart.ready} onclick={() => cart.add(product)}>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 3h2l2.5 12h10L21 6H6M9 20h.01M17 20h.01" stroke-linecap="round" stroke-linejoin="round" /></svg>
      {cart.has(product.id) ? 'В корзине' : available ? 'В корзину' : 'Недоступен для заказа'}
    </button>
  </div>
</article>
