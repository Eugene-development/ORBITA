<script lang="ts">
  import { request } from '#lib/api.ts';
  let ready = $state(false); $effect(() => { ready = true; });
  let email = $state(''); let password = $state(''); let pending = $state(false); let message = $state('');
  async function login(event: SubmitEvent) { event.preventDefault(); pending = true; message = ''; try { await request('/auth/login', { method: 'POST', body: JSON.stringify({ email, password }) }); window.location.assign('/admin/products'); } catch (e) { message = (e as Error).message; } finally { pending = false; } }
</script>
<svelte:head><title>Вход сотрудников — Орбита-строй</title></svelte:head>
<main class="min-h-screen bg-surface px-4 py-16 sm:py-24">
  <div class="mx-auto max-w-md"><a class="font-bold text-accent" href="/">ОРБИТА-СТРОЙ</a><h1 class="mt-8 text-3xl font-bold">Вход сотрудников</h1><p class="mt-3 text-muted">Управление товарами и просмотр заказов.</p>
    <form class="card mt-8 space-y-5" onsubmit={login}>
      {#if message}<p role="alert" class="notice notice-error">{message}</p>{/if}
      <div><label class="label" for="email">Email</label><input class="field" id="email" type="email" autocomplete="username" required disabled={!ready} bind:value={email}/></div>
      <div><label class="label" for="password">Пароль</label><input class="field" id="password" type="password" autocomplete="current-password" required disabled={!ready} bind:value={password}/></div>
      <button class="button w-full" disabled={pending || !ready}>{pending ? 'Входим…' : 'Войти'}</button>
      <a href="/admin/forgot-password" class="text-sm text-brand underline">Восстановить пароль</a>
    </form>
  </div>
</main>
