<script lang="ts">import type { PageProps } from './$types'; import ContentPage from '#lib/components/ContentPage.svelte'; let { data }: PageProps = $props();</script>
<ContentPage content={{ ...data.content, title: `${data.content.title} — Орбита-Строй` }} />
<div class="container article-return"><a class="button-secondary" href="/blog">Все статьи</a></div>
