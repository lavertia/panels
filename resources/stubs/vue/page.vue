<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import LavertiaRenderer from '../../components/lavertia/renderer.vue';
import type { LavertiaPayload } from '../../components/lavertia/types';

defineProps<{ lavertia: LavertiaPayload }>();
</script>

<template>
    <Head :title="lavertia.title ?? 'Lavertia'" />
    <main class="mx-auto w-full max-w-7xl p-4">
        <LavertiaRenderer v-if="lavertia.version === 1" :node="lavertia.tree" />
        <p v-else role="alert">Unsupported Lavertia protocol version.</p>
    </main>
</template>
