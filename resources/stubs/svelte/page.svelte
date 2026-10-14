<script lang="ts">
    import { Head } from '@inertiajs/svelte';
    import LavertiaRenderer from '../../components/lavertia/renderer.svelte';
    import type { LavertiaPayload } from '../../components/lavertia/types';

    let { lavertia }: { lavertia: LavertiaPayload } = $props();
</script>

<Head title={lavertia.title ?? 'Lavertia'} />
<main class="mx-auto w-full max-w-7xl p-4">
    {#if lavertia.version === 1}
        <LavertiaRenderer node={lavertia.tree} />
    {:else}
        <p role="alert">Unsupported Lavertia protocol version.</p>
    {/if}
</main>
