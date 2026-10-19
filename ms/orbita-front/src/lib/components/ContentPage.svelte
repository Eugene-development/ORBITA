<script lang="ts">import Seo from './Seo.svelte'; let { content }: { content: { title: string; description: string; html: string } } = $props();</script>
<Seo title={content.title} description={content.description} />
{#if !content.html.includes('<h1')}<h1 class="sr-only">{content.title}</h1>{/if}
<div class="legacy-content">{@html content.html}</div>
