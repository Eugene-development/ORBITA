<script lang="ts">import { page } from '$app/state';</script>
<svelte:head><title>{page.status} — Орбита-Строй</title><meta name="robots" content="noindex" /></svelte:head>
<section class="container page-section error-page"><h1>{page.status === 404 ? 'Страница не найдена' : 'Не удалось загрузить страницу'}</h1><p>{page.error?.message ?? 'Попробуйте обновить страницу.'}</p><a class="button" href="/">На главную</a><a class="button-secondary" href="/contact">Связаться с нами</a></section>
