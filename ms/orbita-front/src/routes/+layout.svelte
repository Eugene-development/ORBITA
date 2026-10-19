<script lang="ts">
  import '../app.css';
  import { onMount, setContext } from 'svelte';
  import type { LayoutProps } from './$types';
  import { initializeAnalytics } from '#lib/analytics.ts';
  import { Cart } from '#lib/cart.svelte.ts';
  import { CART_KEY } from '#lib/cart-utils.ts';
  import { navigation, information, promotions, logoUrl } from '#lib/site.ts';
  import Search from '#lib/components/Search.svelte';
  import Footer from '#lib/components/Footer.svelte';
  let { data, children }: LayoutProps = $props();
  const cart = new Cart(); setContext('orbita-cart', cart);
  let searchOpen = $state(false), mobileOpen = $state(false), catalogOpen = $state(false);
  onMount(() => { cart.hydrate(); if (data.analyticsEnabled) initializeAnalytics(); const sync = (event: StorageEvent) => { if (event.key === CART_KEY) cart.sync(event.newValue); }; window.addEventListener('storage', sync); return () => window.removeEventListener('storage', sync); });
</script>
<a class="skip-link" href="#main-content">Перейти к содержимому</a>
{#if data.demo}<div class="demo-banner" role="status">Демонстрационный режим — пример каталога. Заказы не отправляются.</div>{/if}
<div class="announcement"><div class="container"><span class="announcement-city">Дзержинск</span><span class="announcement-copy">Всем скидка 15% на ассортимент бренда UNIS<span class="announcement-contact">, т. 8 (902) 303-36-54</span></span></div></div>
<header class="site-header">
  <div class="contact-bar"><div class="container"><div class="contact-links"><a href="tel:+78314237096">8 (8314) 23-70-96</a><a href="tel:+79023033654">8 (902) 303-36-54</a><a href="tel:+79306888862">8 (930) 688-88-62</a><a href="mailto:info@orbita-stroy.com">info@orbita-stroy.com</a></div><button class="search-trigger" type="button" onclick={() => searchOpen = true}><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="10" cy="10" r="6"/><path d="m15 15 6 6" /></svg>Поиск товара</button></div></div>
  <div class="nav-container container">
    <a href="/" class="brand"><img src={logoUrl} alt="Орбита-Строй — главная страница" width="154" height="64" /></a>
    <nav class="desktop-nav" aria-label="Основная навигация">{#each navigation as item}<a href={item.href}>{item.label}</a>{/each}<details class="info-menu"><summary>Информация</summary><div>{#each information as item}<a href={item.href}>{item.label}</a>{/each}</div></details></nav>
    <div class="desktop-promotions">{#each promotions as item}<a href={item.href}>{item.label}</a>{/each}</div>
    <a class="cart-link" href="/shop/cart" aria-label={`Корзина, товаров: ${cart.count}`}><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 3h2l2.5 12h10L21 6H6M9 20h.01M17 20h.01" stroke-linecap="round" stroke-linejoin="round"/></svg><span>Корзина</span><b>{cart.count}</b></a>
    <button class="menu-trigger" type="button" aria-expanded={mobileOpen} aria-controls="mobile-navigation" onclick={() => mobileOpen = !mobileOpen}><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" /></svg><span>Меню</span></button>
  </div>
  {#if mobileOpen}<nav id="mobile-navigation" class="mobile-navigation container" aria-label="Мобильная навигация">{#each [...navigation, ...information, ...promotions] as item}<a href={item.href} onclick={() => mobileOpen = false}>{item.label}</a>{/each}<a href="/admin" onclick={() => mobileOpen = false}>Вход для сотрудников</a></nav>{/if}
  <div class="catalog-nav container"><p><span>Наши</span> цены сэкономят <span>Ваши</span> деньги</p><button class="catalog-trigger button-secondary" type="button" aria-expanded={catalogOpen} aria-controls="catalog-navigation" onclick={() => catalogOpen = !catalogOpen}>Каталог товаров<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg></button><nav id="catalog-navigation" class:expanded={catalogOpen} aria-label="Каталог">{#each data.catalogs as catalog}<a href={`/shop/rubric/${catalog.slug}`} onclick={() => catalogOpen = false}><svg class="category-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m8.25 4.5 7.5 7.5-7.5 7.5" stroke-linecap="round" stroke-linejoin="round" /></svg>{catalog.value}</a>{/each}</nav></div>
</header>
<main id="main-content">{@render children()}</main>
<Footer demo={data.demo} />
<Search open={searchOpen} onclose={() => searchOpen = false} demo={data.demo} demoProducts={data.demoProducts} />

{#if data.analyticsEnabled}<noscript><img src="https://mc.yandex.ru/watch/82181533" alt="" width="1" height="1" style="position:absolute;left:-9999px" /></noscript>{/if}
