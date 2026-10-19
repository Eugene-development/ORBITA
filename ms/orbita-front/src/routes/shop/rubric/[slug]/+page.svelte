<script lang="ts">import type { PageProps } from './$types'; import CatalogPage from '#lib/components/CatalogPage.svelte'; let { data }: PageProps = $props();</script>
<CatalogPage node={data.node} children={data.node.rubric ?? []} childRoute="/shop/category" />
