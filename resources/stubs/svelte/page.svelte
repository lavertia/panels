<script lang="ts">
    import LavertiaRenderer from '../../components/lavertia/renderer.svelte';
    import type { LavertiaPayload } from '../../components/lavertia/types';

    let { lavertia }: { lavertia: LavertiaPayload } = $props();
</script>

<svelte:head>
    <title>{lavertia.title ?? 'Lavertia'}</title>
</svelte:head>
<main class="mx-auto w-full max-w-7xl p-4">
    {#if lavertia.version === 1}
        <LavertiaRenderer node={lavertia.tree} />
    {:else}
        <p role="alert">Unsupported Lavertia protocol version.</p>
    {/if}
</main>
