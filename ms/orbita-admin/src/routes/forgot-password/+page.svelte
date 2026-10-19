<script lang="ts">
  import { request } from '#lib/api.ts';
  let email = $state(''); let pending = $state(false); let message = $state(''); let failed = $state(false);
  async function send(e: SubmitEvent) { e.preventDefault(); pending = true; failed = false; try { await request('/auth/password/forgot', { method: 'POST', body: JSON.stringify({ email }) }); message = 'Если этот адрес зарегистрирован, на него отправлена инструкция.'; } catch (e) { failed = true; message = (e as Error).message; } finally { pending = false; } }
</script>
<svelte:head><title>Восстановление пароля — Орбита-строй</title></svelte:head>
<main class="container max-w-md py-20"><h1 class="text-3xl font-bold">Восстановление пароля</h1><form class="card mt-8 space-y-5" onsubmit={send}>{#if message}<p role="status" class:notice-error={failed} class="notice">{message}</p>{/if}<label class="label" for="email">Рабочий email</label><input class="field" type="email" id="email" autocomplete="email" required bind:value={email}/><button class="button" disabled={pending}>{pending ? 'Отправляем…' : 'Получить инструкцию'}</button></form><a class="mt-6 inline-block text-brand underline" href="/admin/login">Вернуться ко входу</a></main>
