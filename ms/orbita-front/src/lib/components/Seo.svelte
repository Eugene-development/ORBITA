<script lang="ts">
  import { page } from '$app/state';
  let { title, description, image = '' }: { title: string; description: string; image?: string } = $props();
  const canonical = $derived(`${page.data.siteOrigin ?? 'https://орбита-строй.рф'}${page.url.pathname}`);
</script>
<svelte:head>
  <title>{title}</title>
  <meta name="description" content={description} />
  <link rel="canonical" href={canonical} />
  <meta property="og:title" content={title} />
  <meta property="og:description" content={description} />
  <meta property="og:type" content="website" />
  <meta property="og:url" content={canonical} />
  {#if image}<meta property="og:image" content={image} />{/if}
</svelte:head>
