<script lang="ts">
  import type { Snippet } from 'svelte';
  import type { HTMLButtonAttributes } from 'svelte/elements';
  let { children, variant = 'primary', class: className = '', ...rest }: HTMLButtonAttributes & { children?: Snippet; variant?: 'primary' | 'secondary' | 'danger' } = $props();
</script>
<button class={`button ${variant === 'primary' ? '' : `button-${variant}`} ${className}`} {...rest}>{@render children?.()}</button>
