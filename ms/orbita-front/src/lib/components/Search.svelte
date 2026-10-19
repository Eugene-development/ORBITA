<script lang="ts">
  import { tick } from 'svelte';
  import { graphql } from '#lib/api.ts';
  import { PRODUCTS } from '#lib/queries.ts';
  import type { Product, ProductPage } from '#lib/types.ts';
  import { displayMoney } from '#lib/cart-utils.ts';
  let { open, onclose, demoProducts = [], demo = false }: { open: boolean; onclose: () => void; demoProducts?: Product[]; demo?: boolean } = $props();
  let dialog: HTMLDialogElement;
  let input: HTMLInputElement;
  let search = $state('');
  let results = $state<Product[]>([]);
  let loading = $state(false);
  let failure = $state('');
  let page = $state(1);
  let hasMore = $state(false);
  let active = $state(-1);
  let controller: AbortController | undefined;
  async function load(nextPage = 1) {
    controller?.abort(); controller = new AbortController();
    const signal = controller.signal;
    loading = true; failure = '';
    try {
      const result = demo ? { data: demoProducts.filter((item) => item.value.toLocaleLowerCase('ru').includes(search.trim().toLocaleLowerCase('ru'))), paginatorInfo: { hasMorePages: false } } : (await graphql<{ products: ProductPage }>(PRODUCTS, { search: search.trim(), page: nextPage, first: 12 }, signal)).products;
      if (signal.aborted) return;
      results = nextPage === 1 ? result.data : [...results, ...result.data]; page = nextPage; hasMore = result.paginatorInfo.hasMorePages; active = -1;
    } catch { if (!signal.aborted) failure = 'Не удалось выполнить поиск. Попробуйте ещё раз.'; }
    finally { if (!signal.aborted) loading = false; }
  }
  $effect(() => {
    if (open && dialog && !dialog.open) { dialog.showModal(); tick().then(() => input?.focus()); }
    if (!open && dialog?.open) dialog.close();
  });
  $effect(() => {
    const term = search.trim();
    results = []; failure = ''; hasMore = false; controller?.abort(); loading = false;
    if (!open || term.length < 3) return;
    const timer = setTimeout(() => load(), 250);
    return () => { clearTimeout(timer); controller?.abort(); };
  });
  function keydown(event: KeyboardEvent) {
    if (event.key === 'Escape') { event.preventDefault(); onclose(); return; }
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') { event.preventDefault(); active = Math.max(0, Math.min(results.length - 1, active + (event.key === 'ArrowDown' ? 1 : -1))); }
    if (event.key === 'Enter' && active >= 0 && results[active]) { event.preventDefault(); window.location.href = `/shop/product/${results[active].slug}`; }
  }
</script>
<dialog bind:this={dialog} class="search-dialog" aria-labelledby="search-title" onclose={onclose} oncancel={onclose}>
  <header><h2 id="search-title">Поиск товара</h2><button class="icon-button" type="button" aria-label="Закрыть поиск" onclick={onclose}><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg></button></header>
  <label class="label" for="product-search">Наименование товара</label>
  <input bind:this={input} bind:value={search} onkeydown={keydown} id="product-search" class="field" type="search" placeholder="Введите не менее трёх символов" autocomplete="off" role="combobox" aria-expanded={results.length > 0} aria-autocomplete="list" aria-controls="search-options" aria-activedescendant={active >= 0 ? `search-result-${active}` : undefined} />
  <div class="search-results" id="search-results">
    {#if search.trim().length < 3}<p class="search-hint">Например, «цемент» или «утеплитель».</p>{/if}
    {#if loading}<p role="status">Ищем товары…</p>{/if}
    {#if failure}<p class="notice notice-error" role="alert">{failure}</p><button class="button-secondary" type="button" onclick={() => load()}>Повторить поиск</button>{/if}
    {#if !loading && !failure && search.trim().length >= 3 && results.length === 0}<p role="status">Товары не найдены. Попробуйте другое название.</p>{/if}
    <ul id="search-options" role="listbox" aria-label="Найденные товары">{#each results as product, i (product.id)}<li role="presentation"><a role="option" aria-selected={active === i} id={`search-result-${i}`} class:active={active === i} href={`/shop/product/${product.slug}`} onclick={onclose}><span>{product.value}</span><strong>{product.price ? displayMoney(product.price.value) : 'Уточните цену'}{#if product.unit?.value} / {product.unit.value}{/if}</strong></a></li>{/each}</ul>
    {#if hasMore}<button class="button-secondary" type="button" disabled={loading} onclick={() => load(page + 1)}>Показать ещё</button>{/if}
  </div>
</dialog>
