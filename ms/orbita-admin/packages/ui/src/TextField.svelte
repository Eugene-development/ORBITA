<script lang="ts">
  import type { HTMLInputAttributes } from 'svelte/elements';
  let { label, id, value = $bindable(''), ...rest }: HTMLInputAttributes & { label: string; id: string } = $props();
</script>
<label class="label" for={id}>{label}</label>
<input class="field" {id} bind:value {...rest} />
