<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import LavertiaRenderer from '../../components/lavertia/renderer.vue';
import type { LavertiaPayload } from '../../components/lavertia/types';

defineProps<{ lavertia: LavertiaPayload }>();
</script>

<template>
    <Head :title="lavertia.title ?? 'Lavertia'" />
    <main :lang="lavertia.locale ?? 'en'" :dir="lavertia.direction ?? 'ltr'" class="min-h-[calc(100vh-8rem)] bg-gray-50/70 px-4 py-6 sm:px-6 lg:px-8 dark:bg-gray-950/40">
        <div class="mx-auto flex w-full max-w-[1440px] flex-col gap-6 lg:flex-row">
            <aside v-if="lavertia.version === 1 && lavertia.navigation?.length" class="w-full shrink-0 lg:w-60">
                <nav aria-label="Panel navigation" class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-gray-900/70">
                    <ul class="space-y-1">
                        <li v-for="(item, index) in lavertia.navigation" :key="item.slug">
                            <p v-if="item.group && (index === 0 || lavertia.navigation?.[index - 1]?.group !== item.group)"
                                class="px-3 pb-1 pt-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                {{ item.group }}
                            </p>
                            <Link :href="item.url" :aria-current="item.active ? 'page' : undefined"
                                :class="[
                                    'block rounded-lg px-3 py-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500',
                                    item.active
                                        ? 'bg-amber-100 text-amber-950 dark:bg-amber-400/20 dark:text-amber-200'
                                        : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800',
                                ]">
                                {{ item.label }}
                            </Link>
                        </li>
                    </ul>
                </nav>
            </aside>
            <div class="min-w-0 flex-1">
                <LavertiaRenderer v-if="lavertia.version === 1" :node="lavertia.tree" />
                <p v-else role="alert" class="text-sm text-rose-700">Unsupported Lavertia protocol version.</p>
            </div>
        </div>
    </main>
</template>
