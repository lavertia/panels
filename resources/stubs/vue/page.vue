<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import LavertiaRenderer from '../../components/lavertia/renderer.vue';
import type { LavertiaPayload } from '../../components/lavertia/types';

defineProps<{ lavertia: LavertiaPayload }>();
</script>

<template>
    <Head :title="lavertia.title ?? 'Lavertia'" />
    <main class="min-h-[calc(100vh-8rem)] bg-gray-50/70 px-4 py-6 sm:px-6 lg:px-8 dark:bg-gray-950/40">
        <div class="mx-auto w-full max-w-[1440px]">
            <LavertiaRenderer v-if="lavertia.version === 1" :node="lavertia.tree" />
            <p v-else role="alert" class="text-sm text-rose-700">Unsupported Lavertia protocol version.</p>
        </div>
    </main>
</template>
