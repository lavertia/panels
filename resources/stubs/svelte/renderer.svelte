<script lang="ts">
    import { LavertiaRenderer as Renderer } from '@lavertia/svelte-ui';
    import type { ComponentNode } from '@lavertia/ui';

    let { node }: { node: ComponentNode } = $props();
</script>

<Renderer {node} />
