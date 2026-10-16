<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import type { ComponentNode } from './types';
import { safeHref, tableColumns, tableRows } from './types';

defineOptions({ name: 'LavertiaRenderer' });

const props = defineProps<{ node: ComponentNode }>();
const label = computed(() => String(props.node.props.label ?? props.node.props.name ?? ''));
const value = ref(String(props.node.props.value ?? ''));
const href = computed(() => safeHref(props.node.props.href));
const columns = computed(() => tableColumns(props.node));
const rows = computed(() => tableRows(props.node));

watch(() => props.node.props.value, (next) => {
    value.value = String(next ?? '');
});
</script>

<template>
    <div v-if="node.type === 'column' || node.type === 'container'" class="flex min-w-0 flex-col gap-4">
        <LavertiaRenderer v-for="child in node.components" :key="child.key" :node="child" />
    </div>

    <div v-else-if="node.type === 'row'" class="flex min-w-0 flex-wrap gap-4">
        <LavertiaRenderer v-for="child in node.components" :key="child.key" :node="child" />
    </div>

    <section v-else-if="node.type === 'card'" class="min-w-0 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
        <LavertiaRenderer v-for="child in node.components" :key="child.key" :node="child" />
    </section>

    <p v-else-if="node.type === 'text'" class="text-gray-900 dark:text-gray-100">
        {{ String(node.props.content ?? '') }}
    </p>

    <dl v-else-if="node.type === 'infolist'" class="grid min-w-0 gap-3">
        <LavertiaRenderer v-for="child in node.components" :key="child.key" :node="child" />
    </dl>

    <div v-else-if="node.type === 'text-entry'" class="min-w-0">
        <dt class="text-xs text-gray-500">{{ label }}</dt>
        <dd class="text-sm">{{ String(node.props.value ?? '—') }}</dd>
    </div>

    <div v-else-if="node.type === 'text-input'" class="flex min-w-0 flex-col gap-1">
        <label :for="node.key" class="text-sm font-medium">{{ label }}</label>
        <input
            :id="node.key"
            v-model="value"
            type="text"
            :name="String(node.props.name ?? '')"
            :placeholder="String(node.props.placeholder ?? '')"
            class="rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700"
        />
    </div>

    <div v-else-if="node.type === 'table'" class="min-w-0 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
            <thead>
                <tr>
                    <th v-for="column in columns" :key="column.key" scope="col" class="px-3 py-2 text-start font-semibold">
                        {{ column.label }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="(row, index) in rows" :key="String(row.id ?? index)">
                    <td v-for="column in columns" :key="column.key" class="px-3 py-2">
                        {{ String(row[column.key] ?? '') }}
                    </td>
                </tr>
            </tbody>
        </table>
        <p v-if="rows.length === 0" class="px-3 py-4 text-gray-500">No records</p>
    </div>

    <Link
        v-else-if="node.type === 'action' && href?.startsWith('/')"
        :href="href"
        class="inline-flex w-fit rounded-md bg-gray-900 px-3 py-2 text-sm text-white dark:bg-gray-100 dark:text-gray-900"
    >{{ label }}</Link>

    <a
        v-else-if="node.type === 'action' && href"
        :href="href"
        rel="noopener noreferrer"
        class="inline-flex w-fit rounded-md bg-gray-900 px-3 py-2 text-sm text-white dark:bg-gray-100 dark:text-gray-900"
    >{{ label }}</a>

    <span v-else-if="node.type === 'action'" class="text-sm text-gray-500">{{ label }}</span>

    <template v-else-if="node.type === 'text-column'" />

    <p v-else role="alert" class="text-sm text-red-700">Unsupported component: {{ node.type }}</p>
</template>
