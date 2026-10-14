<script lang="ts">
    import LavertiaRenderer from '../../components/lavertia/renderer.svelte';
    import type { LavertiaPayload } from '../../components/lavertia/types';

    let { lavertia }: { lavertia: LavertiaPayload } = $props();
</script>

<svelte:head>
    <title>{lavertia.title ?? 'Lavertia'}</title>
</svelte:head>

<main class="min-h-[calc(100vh-8rem)] bg-gray-50/70 px-4 py-6 sm:px-6 lg:px-8 dark:bg-gray-950/40">
    <div class="mx-auto w-full max-w-[1440px]">
        {#if lavertia.version === 1}
            <LavertiaRenderer node={lavertia.tree} />
        {:else}
            <p role="alert" class="text-sm text-rose-700">Unsupported Lavertia protocol version.</p>
        {/if}
    </div>
</main>
