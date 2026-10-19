<script lang="ts">
  import { getContext } from 'svelte';
  import { goto } from '$app/navigation';
  import type { PageProps } from './$types';
  import type { Cart } from '#lib/cart.svelte.ts';
  import type { Product, Quote, Order } from '#lib/types.ts';
  import { graphql, post, ApiError } from '#lib/api.ts';
  import { PRODUCT_IDS } from '#lib/queries.ts';
  import { displayMoney, lineTotal, moneyMinor, decimalMoney, fractionalUnit, eligibleProduct } from '#lib/cart-utils.ts';
  import Seo from '#lib/components/Seo.svelte';
  import { checkoutAttempt, clearCheckoutAttempt } from '#lib/checkout.ts';
  let { data }: PageProps = $props();
  const cart = getContext<Cart>('orbita-cart');
  let products = $state<Product[]>([]), loading = $state(false), failure = $state(''), validation = $state<Record<string, string>>({});
  let name = $state(''), phone = $state(''), address = $state(''), comments = $state(''), onlinePayment = $state(false);
  let pending = $state(false), quote = $state<Quote | null>(null), quoteSignature = $state('');
  let loadedIds = '', controller: AbortController | undefined, stableKey = '';
  const signature = $derived(JSON.stringify(cart.items));
  const missing = $derived(cart.items.filter((item) => !products.some((product) => product.id === item.productId && eligibleProduct(product))));
  const estimate = $derived.by(() => { let total = 0n; for (const item of cart.items) { const product = products.find((product) => product.id === item.productId); if (product?.price) total += moneyMinor(lineTotal(product.price.value, item.quantity)); } return decimalMoney(total); });
  async function resolveProducts(ids: string[]) {
    controller?.abort(); controller = new AbortController(); const signal = controller.signal;
    loading = true; failure = '';
    try { const result = data.demo ? data.demoProducts.filter((product) => ids.includes(product.id)) : (await graphql<{ productIds: Product[] }>(PRODUCT_IDS, { ids }, signal)).productIds; if (!signal.aborted) products = result; }
    catch { if (!signal.aborted) failure = 'Не удалось проверить товары. Корзина сохранена. Попробуйте ещё раз.'; }
    finally { if (!signal.aborted) loading = false; }
  }
  $effect(() => { if (!cart.ready) return; const ids = cart.items.map((item) => item.productId); const next = ids.join(','); if (next !== loadedIds) { loadedIds = next; if (ids.length) resolveProducts(ids); else products = []; } });
  $effect(() => { if (signature !== quoteSignature) { quote = null; stableKey = ''; } });
  function updateQuantity(product: Product, event: Event) { const input = event.currentTarget as HTMLInputElement; const valid = cart.quantity(product.id, input.value, product.unit?.value ?? ''); validation = { ...validation, [product.id]: valid ? '' : fractionalUnit(product.unit?.value) ? 'Введите количество больше нуля, до трёх знаков после запятой.' : 'Введите целое количество больше нуля.' }; }
  async function getQuote() {
    if (data.demo) { failure = 'Оформление заказа недоступно в демонстрационном режиме.'; return; }
    pending = true; failure = '';
    try { quote = await post<Quote>('/api/checkout/quote', { items: cart.items }); quoteSignature = signature; stableKey = checkoutAttempt(sessionStorage, `${signature}:${quote!.quoteHash}`); }
    catch (error) { failure = error instanceof Error ? error.message : 'Не удалось проверить заказ. Попробуйте ещё раз.'; }
    finally { pending = false; }
  }
  async function submit(event: SubmitEvent) {
    event.preventDefault(); if (pending || !quote || quoteSignature !== signature) return;
    pending = true; failure = '';
    try {
      const order = await post<Order>('/api/orders', { items: cart.items, quoteHash: quote.quoteHash, customer: { name, phone, address, comments }, onlinePayment, idempotencyKey: stableKey });
      if (!order.id) throw new Error('Сервис не подтвердил заказ. Корзина сохранена. Повторите отправку.');
      const destination = onlinePayment && order.confirmationUrl ? new URL(order.confirmationUrl) : null;
      if (destination && destination.protocol !== 'https:') throw new Error('Некорректная ссылка оплаты. Обратитесь к менеджеру.');
      cart.clear(); clearCheckoutAttempt(sessionStorage); try { sessionStorage.setItem('orbita:last-order', order.id); } catch {}
      if (destination) window.location.assign(destination.href);
      else await goto(`/spasibo?order=${encodeURIComponent(order.id)}`);
    } catch (error) {
      if (error instanceof ApiError && error.status === 409 && error.payload.code === 'QUOTE_CHANGED' && error.payload.quote) { quote = error.payload.quote as unknown as Quote; quoteSignature = signature; stableKey = checkoutAttempt(sessionStorage, `${signature}:${quote!.quoteHash}`); failure = 'Цена или состав заказа изменились. Проверьте обновлённую сумму и отправьте заказ ещё раз.'; }
      else failure = error instanceof Error ? error.message : 'Не удалось отправить заказ. Корзина сохранена.';
    } finally { pending = false; }
  }
</script>
<Seo title="Корзина — Орбита-Строй" description="Корзина интернет-магазина стройматериалов Орбита-Строй" />
<svelte:head><meta name="robots" content="noindex" /></svelte:head>
<section class="container page-section"><h1 class="page-title">Ваша корзина</h1><p class="page-intro">После отправки заказа с вами свяжется менеджер.</p>{#if cart.message}<p class="notice" role="status">{cart.message}</p>{/if}{#if failure}<p class="notice notice-error" role="alert">{failure}</p>{/if}{#if !cart.ready || loading}<p role="status">Проверяем товары в корзине…</p>{/if}{#if cart.ready && cart.items.length === 0}<div class="notice"><p>Корзина пуста. Ждём вас снова за покупками.</p><a href="/shop/rubric/stroymaterialy" class="button-secondary">Выбрать товары</a></div>{:else if cart.ready}<div class="cart-items">{#each cart.items as item (item.productId)}{@const product = products.find((product) => product.id === item.productId)}<article class="cart-row">{#if product}<div><a href={`/shop/product/${product.slug}`}>{product.value}</a><p>{quote?.items.find((line) => line.productId === item.productId)?.unitPrice ? displayMoney(quote.items.find((line) => line.productId === item.productId)!.unitPrice) : product.price ? displayMoney(product.price.value) : 'Цена не указана'} / {product.unit?.value}</p></div><div><label class="label" for={`quantity-${product.id}`}>Количество, {product.unit?.value}</label><input class="field quantity-field" id={`quantity-${product.id}`} type="text" inputmode={fractionalUnit(product.unit?.value) ? 'decimal' : 'numeric'} value={item.quantity} oninput={(event) => updateQuantity(product, event)} aria-invalid={!!validation[product.id]} aria-describedby={validation[product.id] ? `quantity-error-${product.id}` : undefined} disabled={pending} />{#if validation[product.id]}<p class="field-error" id={`quantity-error-${product.id}`}>{validation[product.id]}</p>{/if}</div><strong>{quote?.items.find((line) => line.productId === item.productId)?.lineTotal ? displayMoney(quote.items.find((line) => line.productId === item.productId)!.lineTotal) : product.price ? displayMoney(lineTotal(product.price.value, item.quantity)) : '—'}</strong>{:else}<div><strong>Товар #{item.productId}</strong><p>{loading ? 'Проверяем доступность…' : 'Товар удалён или недоступен. Удалите его из корзины.'}</p></div>{/if}<button class="icon-button remove-item" type="button" disabled={pending} aria-label={`Удалить ${product?.value ?? `товар ${item.productId}`} из корзины`} onclick={() => { cart.remove(item.productId); validation = Object.fromEntries(Object.entries(validation).filter(([id]) => id !== item.productId)); }}><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg></button></article>{/each}</div><div class="cart-summary"><strong>{quote ? 'Итого по проверенному заказу' : 'Предварительная сумма'}: {displayMoney(quote?.total ?? estimate)}</strong><p>Доставка и услуги грузчиков оплачиваются отдельно по согласованию с менеджером.</p>{#if missing.length && !loading}<p class="notice notice-error">Удалите недоступные товары, чтобы оформить заказ.</p>{/if}{#if !quote}<button class="button" type="button" disabled={pending || loading || !!failure || !!missing.length || Object.values(validation).some(Boolean)} onclick={getQuote}>{pending ? 'Проверяем заказ…' : 'Проверить цены и оформить заказ'}</button>{/if}{#if failure && !quote && !loading}<button class="button-secondary" type="button" onclick={() => { failure = ''; resolveProducts(cart.items.map((item) => item.productId)); }}>Проверить товары снова</button>{/if}</div>{#if quote}<form class="checkout-form card" onsubmit={submit}><h2>Дополнительная информация</h2><div class="checkout-fields"><div><label class="label" for="customer-name">Ваше имя *</label><input class="field" id="customer-name" name="name" required maxlength="120" autocomplete="name" bind:value={name} disabled={pending} /></div><div><label class="label" for="customer-phone">Телефон *</label><input class="field" id="customer-phone" name="phone" type="tel" required maxlength="40" autocomplete="tel" bind:value={phone} disabled={pending} /></div><div><label class="label" for="customer-address">Адрес доставки</label><input class="field" id="customer-address" name="address" maxlength="500" autocomplete="street-address" bind:value={address} disabled={pending} /></div><div><label class="label" for="customer-comments">Комментарий</label><textarea class="field" id="customer-comments" name="comments" rows="3" maxlength="2000" bind:value={comments} disabled={pending}></textarea></div></div>{#if data.onlinePayment}<label class="payment-option"><input type="checkbox" bind:checked={onlinePayment} disabled={pending} />Оплатить картой онлайн</label>{#if onlinePayment}<p>После создания заказа вы перейдёте на защищённую страницу оплаты.</p>{/if}{:else}<p>Способ оплаты согласуйте с менеджером после отправки заказа.</p>{/if}<button class="button" type="submit" disabled={pending}>{pending ? 'Отправляем заказ…' : onlinePayment ? 'Создать заказ и перейти к оплате' : 'Отправить менеджеру'}</button></form>{/if}{/if}</section>
