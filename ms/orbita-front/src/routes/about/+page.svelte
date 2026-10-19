<script lang="ts">import type { PageProps } from './$types'; import ContentPage from '#lib/components/ContentPage.svelte'; let { data }: PageProps = $props();</script>
<ContentPage content={data.content} />
