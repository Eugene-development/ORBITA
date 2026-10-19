<script lang="ts">
  import { onMount, getContext } from 'svelte';
  import type { Cart } from '#lib/cart.svelte.ts';
  import type { PageProps } from './$types';
  import Seo from '#lib/components/Seo.svelte';
  import ProductCard from '#lib/components/ProductCard.svelte';
  import tinting from '#lib/content/tinting.html?raw';
  import testimonials from '#lib/content/testimonials.html?raw';
  import message from '#lib/content/message.html?raw';
  import article from '#lib/content/article.html?raw';
  import { displayMoney, eligibleProduct } from '#lib/cart-utils.ts';
  import { imageUrl } from '#lib/site.ts';
  let { data }: PageProps = $props();
  const cart = getContext<Cart>('orbita-cart');
  let slide = $state(0), paused = $state(false);
  onMount(() => { if (matchMedia('(prefers-reduced-motion: reduce)').matches) return; const timer = setInterval(() => { if (!paused && !document.hidden && data.monthly.length > 1) slide = (slide + 1) % data.monthly.length; }, 6000); return () => clearInterval(timer); });
  const monthProduct = $derived(data.monthly[slide]);
</script>
<Seo title={'Стройматериалы в Дзержинске | Интернет-магазин «Орбита-Строй»'} description="База Орбита Строй — качественные стройматериалы в Дзержинске. Широкий ассортимент, низкие цены и доставка со склада." />
<section class="shock-section container"><h2>Шок цена!!!</h2><p>Воспользуйтесь нашими спецпредложениями и получите значительную выгоду при покупке стройматериалов в этом сезоне.</p><img src="/images/banner_tehnonikol_utepliteli_1920x720.webp" alt="Специальные цены на утеплители Технониколь" width="1920" height="720" fetchpriority="high" /></section>
<section class="container featured-section"><h1 class="page-title">Стройматериалы в Дзержинске</h1><p class="page-intro">Цены на стройматериалы, которые вас приятно удивят. Наличие товара и условия доставки уточняйте у наших менеджеров.</p>{#if data.unavailable}<p class="notice" role="status">Каталог временно недоступен. Уточните наличие и цены по телефону <a href="tel:+79023033654">8 (902) 303-36-54</a>.</p>{/if}<div class="product-grid featured-grid">{#each data.featured as product (product.id)}<ProductCard {product} imageBase={data.imageBase} featured />{/each}</div></section>
{#if monthProduct}
<section class="monthly-section" aria-label="Специальные предложения" aria-roledescription="карусель">
  <div class="container monthly-content">
    <div class="monthly-copy"><h2>{monthProduct.product?.value || monthProduct.title}</h2><p class="monthly-price">{#if monthProduct.product?.price}Цена - {displayMoney(monthProduct.product.price.value)}/{monthProduct.product.unit?.value}{:else}Уточните цену{/if}</p><div class="monthly-actions">{#if monthProduct.href.startsWith('/shop/product/')}<button type="button" class="button monthly-add" disabled={!monthProduct.product || !eligibleProduct(monthProduct.product) || cart.has(monthProduct.product.id) || !cart.ready} onclick={() => { if (monthProduct.product) cart.add(monthProduct.product); }}>{monthProduct.product && cart.has(monthProduct.product.id) ? 'В корзине' : 'В корзину'}</button>{/if}<a class="button-secondary" href={monthProduct.href}>Подробнее</a></div></div>
    <img src={monthProduct.product?.image?.url || (monthProduct.product?.image?.hash ? imageUrl(monthProduct.product.image.hash, data.imageBase) : monthProduct.image)} alt={monthProduct.product?.image?.alt || monthProduct.title} loading="lazy" />
  </div>
  <div class="container carousel-controls">{#each data.monthly as offer, index (offer.id)}<button type="button" class="carousel-dot" aria-label={`Предложение ${index + 1} из ${data.monthly.length}`} aria-pressed={slide === index} onclick={() => slide = index}><svg viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="4" /></svg></button>{/each}<button class="carousel-pause" type="button" aria-label={paused ? 'Продолжить смену предложений' : 'Остановить смену предложений'} aria-pressed={paused} onclick={() => paused = !paused}><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">{#if paused}<path d="m8 5 11 7-11 7z" />{:else}<path d="M7 5h4v14H7zM14 5h4v14h-4z" />{/if}</svg></button></div>
</section>
{/if}
<div class="legacy-content home-copy">{@html tinting}{@html testimonials}{@html message}{@html article}</div>
