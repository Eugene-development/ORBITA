<script lang="ts">import ProductEditor from '#lib/ProductEditor.svelte'; import type { PageData } from './$types'; let { data }: {data:PageData} = $props();</script>
<svelte:head><title>Создать товар — Орбита-строй</title></svelte:head>
<h1 class="text-3xl font-bold">Создать товар</h1><ProductEditor categories={data.referenceData.categories} units={data.referenceData.units} writesEnabled={data.referenceData.catalogWritesEnabled}/>
