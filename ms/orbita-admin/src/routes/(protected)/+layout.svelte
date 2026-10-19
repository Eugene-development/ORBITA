<script lang="ts">
  import type { Snippet } from 'svelte'; import { request } from '#lib/api.ts'; import type { Employee } from '@orbita/contracts';
  let { data, children }: { data: { employee: Employee }; children: Snippet } = $props();
  let message = $state(''); let pending = $state(false);
  async function logout() { pending = true; try { await request('/auth/logout', { method: 'POST' }); window.location.assign('/admin/login'); } catch (e) { message = (e as Error).message; pending = false; } }
</script>
<div class="min-h-screen bg-surface">
  <header class="border-b border-slate-200 bg-white"><div class="container flex flex-wrap items-center justify-between gap-4 py-4"><a class="font-bold text-accent" href="/admin/products">ОРБИТА-СТРОЙ <span class="ml-2 font-normal text-muted">Управление</span></a><div class="flex items-center gap-3"><span class="text-sm text-muted">{data.employee.name}</span><button class="button button-secondary" onclick={logout} disabled={pending}>Выйти</button></div></div></header>
  <nav aria-label="Управление сайтом" class="border-b border-slate-200 bg-white"><div class="container flex flex-wrap gap-6 py-3"><a class="font-semibold text-brand" href="/admin/products">Товары</a><a class="font-semibold text-brand" href="/admin/orders">Заказы и оплата</a><a class="ml-auto text-muted" href="/">Открыть сайт</a></div></nav>
  <main class="container py-8">{#if message}<p role="alert" class="notice notice-error mb-6">{message}</p>{/if}{@render children()}</main>
</div>
