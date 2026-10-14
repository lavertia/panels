<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import type { ComponentNode, TableColumn } from './types';
import {
    badgeClass,
    columnBadgeTone,
    safeHref,
    statAccentClass,
    tableColumns,
    tableRows,
    visibleTableRows,
} from './types';

defineOptions({ name: 'LavertiaRenderer' });

const props = defineProps<{ node: ComponentNode }>();
const label = computed(() => String(props.node.props.label ?? props.node.props.name ?? ''));
const title = computed(() => String(props.node.props.title ?? ''));
const input = ref(String(props.node.props.value ?? ''));
const href = computed(() => safeHref(props.node.props.href));
const columns = computed(() => tableColumns(props.node));
const rows = computed(() => tableRows(props.node));
const search = ref('');
const sortKey = ref<string | null>(null);
const direction = ref<'asc' | 'desc'>('asc');
const visible = computed(() => visibleTableRows(rows.value, columns.value, search.value, sortKey.value, direction.value));
const actionClass = computed(() => {
    const base = 'inline-flex min-h-9 items-center justify-center rounded-lg px-3.5 py-2 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500';
    const appearance = props.node.props.appearance;
    const variant = appearance === 'primary'
        ? 'bg-amber-500 text-gray-950 shadow-sm hover:bg-amber-400 dark:bg-amber-400 dark:hover:bg-amber-300'
        : appearance === 'outline'
            ? 'border border-gray-300 bg-transparent text-gray-700 hover:bg-gray-100 dark:border-white/20 dark:text-gray-200 dark:hover:bg-white/10'
            : 'border border-gray-200 bg-white text-gray-800 shadow-sm hover:bg-gray-50 dark:border-white/15 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700';

    return base + ' ' + variant;
});

function toggleSort(column: TableColumn): void {
    if (!column.sortable) return;

    if (sortKey.value === column.key) {
        direction.value = direction.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = column.key;
        direction.value = 'asc';
    }
}

watch(() => props.node.props.value, (next) => {
    input.value = String(next ?? '');
});
</script>

<template>
    <div v-if="node.type === 'column' || node.type === 'container'" class="flex min-w-0 flex-col gap-6">
        <LavertiaRenderer v-for="child in node.components" :key="child.key" :node="child" />
    </div>

    <div v-else-if="node.type === 'row'" class="flex min-w-0 flex-wrap items-start gap-4">
        <LavertiaRenderer v-for="child in node.components" :key="child.key" :node="child" />
    </div>

    <header v-else-if="node.type === 'page-header'" class="flex flex-col gap-4 border-b border-gray-200 pb-6 sm:flex-row sm:items-end sm:justify-between dark:border-white/10">
        <div class="min-w-0">
            <p v-if="node.props.eyebrow" class="mb-2 text-xs font-bold uppercase tracking-widest text-amber-700 dark:text-amber-400">
                {{ String(node.props.eyebrow) }}
            </p>
            <h1 class="text-2xl font-bold tracking-tight text-gray-950 sm:text-3xl dark:text-white">
                {{ title || 'Overview' }}
            </h1>
            <p v-if="node.props.description" class="mt-2 max-w-2xl text-sm leading-6 text-gray-500 dark:text-gray-400">
                {{ String(node.props.description) }}
            </p>
        </div>
        <div v-if="node.components.length" class="flex shrink-0 flex-wrap items-center gap-2">
            <LavertiaRenderer v-for="child in node.components" :key="child.key" :node="child" />
        </div>
    </header>

    <div v-else-if="node.type === 'stats-grid'" class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <LavertiaRenderer v-for="child in node.components" :key="child.key" :node="child" />
    </div>

    <article v-else-if="node.type === 'stat'" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900/70">
        <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ label }}</p>
            <span aria-hidden="true" :class="statAccentClass(node.props.tone)" class="size-2.5 rounded-full ring-4" />
        </div>
        <p class="mt-3 text-3xl font-bold tracking-tight text-gray-950 dark:text-white">
            {{ String(node.props.value ?? '—') }}
        </p>
        <p v-if="node.props.description" class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            {{ String(node.props.description) }}
        </p>
    </article>

    <section v-else-if="node.type === 'section'" class="space-y-4" :aria-label="title || 'Section'">
        <div>
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ title }}</h2>
            <p v-if="node.props.description" class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ String(node.props.description) }}</p>
        </div>
        <div class="flex min-w-0 flex-col gap-4">
            <LavertiaRenderer v-for="child in node.components" :key="child.key" :node="child" />
        </div>
    </section>

    <section v-else-if="node.type === 'card'" class="flex min-w-0 flex-col gap-5 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900/70">
        <LavertiaRenderer v-for="child in node.components" :key="child.key" :node="child" />
    </section>

    <p v-else-if="node.type === 'text'" class="text-sm leading-6 text-gray-700 dark:text-gray-300">
        {{ String(node.props.content ?? '') }}
    </p>

    <dl v-else-if="node.type === 'infolist'" class="grid min-w-0 grid-cols-1 gap-x-8 gap-y-5 sm:grid-cols-2">
        <LavertiaRenderer v-for="child in node.components" :key="child.key" :node="child" />
    </dl>

    <div v-else-if="node.type === 'text-entry'" class="min-w-0">
        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ label }}</dt>
        <dd class="mt-1 break-words text-sm font-semibold text-gray-900 dark:text-gray-100">{{ String(node.props.value ?? '—') }}</dd>
    </div>

    <div v-else-if="node.type === 'text-input'" class="flex min-w-0 flex-col gap-1.5">
        <label :for="node.key" class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ label }}</label>
        <input
            :id="node.key"
            v-model="input"
            type="text"
            :name="String(node.props.name ?? '')"
            :placeholder="String(node.props.placeholder ?? '')"
            :aria-describedby="node.props.helperText ? node.key + '-help' : undefined"
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20 dark:border-white/20 dark:bg-gray-950 dark:text-gray-100"
        />
        <p v-if="node.props.helperText" :id="node.key + '-help'" class="text-xs text-gray-500 dark:text-gray-400">
            {{ String(node.props.helperText) }}
        </p>
    </div>

    <section v-else-if="node.type === 'table'" class="min-w-0 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900/70" :aria-label="label">
        <header class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/10">
            <div>
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ label }}</h3>
                <p v-if="node.props.description" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ String(node.props.description) }}
                </p>
            </div>
            <label v-if="node.props.searchable === true" class="flex w-full items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm transition-colors focus-within:border-amber-500 focus-within:ring-2 focus-within:ring-amber-500/20 sm:max-w-xs dark:border-white/15 dark:bg-gray-950">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" class="size-4 shrink-0 text-gray-400" stroke="currentColor" stroke-width="1.8">
                    <circle cx="11" cy="11" r="7" /><path d="m16 16 4 4" />
                </svg>
                <span class="sr-only">Search {{ label }}</span>
                <input
                    v-model="search"
                    :aria-label="'Search ' + label"
                    type="search"
                    placeholder="Search records..."
                    class="min-w-0 flex-1 bg-transparent text-gray-900 outline-none placeholder:text-gray-400 dark:text-gray-100"
                />
            </label>
        </header>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-start text-sm dark:divide-white/10">
                <thead class="bg-gray-50/80 dark:bg-white/[0.03]">
                    <tr>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            scope="col"
                            :aria-sort="column.sortable ? (sortKey === column.key ? (direction === 'asc' ? 'ascending' : 'descending') : 'none') : undefined"
                            class="whitespace-nowrap px-5 py-3 text-start text-xs font-semibold text-gray-600 dark:text-gray-300"
                        >
                            <button
                                v-if="column.sortable"
                                type="button"
                                @click="toggleSort(column)"
                                class="inline-flex min-h-6 items-center gap-1.5 rounded text-start transition-colors hover:text-amber-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500 dark:hover:text-amber-400"
                            >
                                {{ column.label }}
                                <span aria-hidden="true" class="text-gray-400">
                                    {{ sortKey === column.key ? (direction === 'asc' ? '↑' : '↓') : '↕' }}
                                </span>
                            </button>
                            <template v-else>{{ column.label }}</template>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white dark:divide-white/[0.07] dark:bg-transparent">
                    <tr v-for="(row, index) in visible" :key="String(row.id ?? index)" class="transition-colors hover:bg-gray-50/70 dark:hover:bg-white/[0.035]">
                        <td v-for="column in columns" :key="column.key" class="whitespace-nowrap px-5 py-3.5">
                            <span
                                v-if="column.badge"
                                :class="badgeClass(columnBadgeTone(column, row[column.key]))"
                                class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset"
                            >{{ String(row[column.key] ?? '—') }}</span>
                            <span v-else class="text-gray-800 dark:text-gray-200">{{ String(row[column.key] ?? '—') }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-if="visible.length === 0" class="flex flex-col items-center gap-2 px-6 py-12 text-center">
                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">No matching records</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ search ? 'Try another search term.' : 'No records have been added yet.' }}</p>
                <button v-if="search" type="button" @click="search = ''" class="mt-1 rounded-md px-3 py-1.5 text-sm font-medium text-amber-700 hover:bg-amber-50 focus-visible:outline-2 focus-visible:outline-amber-500 dark:text-amber-400 dark:hover:bg-amber-500/10">Clear search</button>
            </div>
        </div>
        <footer class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 bg-gray-50/50 px-5 py-3 text-xs text-gray-500 dark:border-white/10 dark:bg-white/[0.02] dark:text-gray-400">
            <span aria-live="polite">Showing {{ visible.length }} of {{ rows.length }} records</span>
            <span>Local preview · up to 100 records</span>
        </footer>
    </section>

    <Link v-else-if="node.type === 'action' && href?.startsWith('/')" :href="href" :class="actionClass">{{ label }}</Link>

    <a v-else-if="node.type === 'action' && href" :href="href" :class="actionClass" rel="noopener noreferrer">{{ label }}</a>

    <span v-else-if="node.type === 'action'" class="text-sm text-gray-500">{{ label }}</span>

    <template v-else-if="node.type === 'text-column'" />

    <p v-else role="alert" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900/50 dark:bg-rose-900/20 dark:text-rose-300">
        Unsupported component: {{ node.type }}
    </p>
</template>
