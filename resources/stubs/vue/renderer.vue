<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import type { ComponentNode, TableColumn } from './types';
import {
    badgeClass,
    columnBadgeTone,
    safeHref,
    statAccentClass,
    tableColumns,
    tableRows,
    visibleTableRows,
    tableFilters,
    tableColumnsInOrder,
    defaultHiddenColumns,
    tableStateUrl,
    translate,
} from './types';

defineOptions({ name: 'LavertiaRenderer' });

const props = defineProps<{ node: ComponentNode }>();
const label = computed(() => String(props.node.props.label ?? props.node.props.name ?? ''));
const title = computed(() => String(props.node.props.title ?? ''));
const input = ref(String(props.node.props.value ?? ''));
const checked = ref(props.node.props.checked === true);
const href = computed(() => safeHref(props.node.props.href));
const columns = computed(() => tableColumns(props.node));

const rows = computed(() => tableRows(props.node));
const filters = computed(() => tableFilters(props.node));
const mode = computed(() => props.node.props.mode === 'eloquent' ? 'eloquent' : 'snapshot');
const tx = (key: Parameters<typeof translate>[1]): string => translate(props.node.props.locale, key);
const search = ref(String(props.node.props.appliedSearch ?? ''));
const sortKey = ref<string | null>(typeof props.node.props.appliedSort === 'string' ? props.node.props.appliedSort : null);
const direction = ref<'asc' | 'desc'>(props.node.props.appliedDirection === 'desc' ? 'desc' : 'asc');
const selectedFilters = ref<Record<string, string>>((props.node.props.appliedFilters as Record<string, string>) ?? {});
const hidden = ref<string[]>(defaultHiddenColumns(tableColumns(props.node)));
const order = ref<string[]>(tableColumns(props.node).map(column => column.key));
const page = ref(1);
const visibleColumns = computed(() => tableColumnsInOrder(columns.value, order.value, hidden.value));
const filtered = computed(() => mode.value === 'eloquent' ? rows.value : visibleTableRows(rows.value, columns.value, search.value, sortKey.value, direction.value, selectedFilters.value));
const lastPage = computed(() => mode.value === 'eloquent'
    ? Number((props.node.props.pagination as { lastPage?: number } | undefined)?.lastPage ?? 1)
    : Math.max(1, Math.ceil(filtered.value.length / 5)));
const currentPage = computed(() => mode.value === 'eloquent'
    ? Number((props.node.props.pagination as { page?: number } | undefined)?.page ?? 1)
    : Math.min(page.value, lastPage.value));
const visible = computed(() => mode.value === 'eloquent' ? filtered.value : filtered.value.slice((currentPage.value - 1) * 5, currentPage.value * 5));
const pagination = computed(() => props.node.props.pagination as { total?: number; nextUrl?: string | null; previousUrl?: string | null } | undefined);
const tableLabel = computed(() => String(props.node.props.label ?? props.node.props.name ?? 'Records'));
function navigate(changes: Record<string, string | null>) {
    router.get(tableStateUrl(props.node, changes), {}, { preserveScroll: true, preserveState: true, replace: true });
}
function setFilter(key: string, value: string) {
    selectedFilters.value = { ...selectedFilters.value, [key]: value };
    if (mode.value === 'eloquent') navigate({ ['filter_' + key]: value || null });
    else page.value = 1;
}
function submitSearch() {
    if (mode.value === 'eloquent') navigate({ search: search.value });
    else page.value = 1;
}
function moveColumn(key: string, step: number) {
    const next = [...order.value];
    const index = next.indexOf(key);
    if (index < 0 || index + step < 0 || index + step >= next.length) return;
    [next[index], next[index + step]] = [next[index + step], next[index]];
    order.value = next;
}
function resetTable() {
    search.value = '';
    selectedFilters.value = {};
    sortKey.value = null;
    direction.value = 'asc';
    page.value = 1;
    order.value = columns.value.map(c => c.key);
    hidden.value = defaultHiddenColumns(columns.value);
    if (mode.value === 'eloquent') {
        const changes: Record<string, string | null> = { search: null, sort: null, direction: null };
        filters.value.forEach(f => { changes['filter_' + f.key] = null; });
        navigate(changes);
    }
}
watch(() => props.node.props.appliedSearch, (next) => {
    if (mode.value === 'eloquent') search.value = String(next ?? '');
});
watch(() => props.node.props.appliedFilters, (next) => {
    if (mode.value === 'eloquent') selectedFilters.value = (next as Record<string, string>) ?? {};
});
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
    const next = sortKey.value === column.key && direction.value === 'asc' ? 'desc' : 'asc';
    sortKey.value = column.key;
    direction.value = next;
    if (mode.value === 'eloquent') navigate({ sort: column.key, direction: next });
    else page.value = 1;
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

<section v-else-if="node.type === 'form'" class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-gray-900/70">
        <h3 class="text-sm font-semibold">{{ label }}</h3>
        <div class="grid gap-4 sm:grid-cols-2"><LavertiaRenderer v-for="child in node.components" :key="child.key" :node="child" /></div>
        <p role="status" class="text-xs text-gray-500">Form preview only — no save endpoint configured.</p>
        <button type="button" disabled class="cursor-not-allowed rounded-md bg-gray-200 px-4 py-2 text-sm opacity-60 dark:bg-gray-800">Save (unavailable)</button>
    </section>
    <label v-else-if="node.type === 'select'" class="flex flex-col gap-1.5 text-sm font-semibold">
        {{ label }}
        <select v-model="input" :required="node.props.required === true" class="rounded-lg border border-gray-300 bg-white px-3 py-2.5 font-normal dark:border-white/20 dark:bg-gray-950">
            <option value="">—</option>
            <option v-for="(text, value) in (node.props.options as Record<string, string> ?? {})" :key="value" :value="value">{{ text }}</option>
        </select>
    </label>
    <label v-else-if="node.type === 'textarea'" class="flex flex-col gap-1.5 text-sm font-semibold">
        {{ label }}
        <textarea v-model="input" :rows="Number(node.props.rows ?? 3)" class="rounded-lg border border-gray-300 bg-white px-3 py-2.5 font-normal dark:border-white/20 dark:bg-gray-950" />
    </label>
    <label v-else-if="node.type === 'checkbox'" class="flex items-center gap-2 text-sm font-semibold">
        <input v-model="checked" type="checkbox" />{{ label }}
    </label>

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


    <section v-else-if="node.type === 'table'" class="min-w-0 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900/70" :aria-label="tableLabel">
        <header class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 dark:border-white/10">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ tableLabel }}</h3>
                    <p v-if="node.props.description" class="mt-1 text-xs text-gray-500">{{ String(node.props.description) }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <details v-if="node.props.columnManager !== false" class="relative">
                        <summary class="cursor-pointer rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold focus-visible:outline-2 focus-visible:outline-amber-500 dark:border-white/20">{{ tx('columns') }}</summary>
                        <div class="absolute end-0 z-20 mt-2 w-64 space-y-2 rounded-lg border border-gray-200 bg-white p-3 shadow-xl dark:border-gray-700 dark:bg-gray-900">
                            <div v-for="column in columns.filter(c => !c.hidden)" :key="column.key" class="flex items-center gap-2 text-xs">
                                <label class="flex min-w-0 flex-1 items-center gap-2">
                                    <input type="checkbox" :checked="!hidden.includes(column.key)" :disabled="!column.toggleable || (!hidden.includes(column.key) && visibleColumns.length <= 1)"
                                        @change="(event) => { hidden = (event.target as HTMLInputElement).checked ? hidden.filter(key => key !== column.key) : [...hidden, column.key] }" />
                                    <span class="truncate">{{ column.label }}</span>
                                </label>
                                <template v-if="node.props.reorderableColumns === true">
                                    <button type="button" :aria-label="tx('moveLeft') + ': ' + column.label" :disabled="order.indexOf(column.key) === 0" @click="moveColumn(column.key, -1)" class="rounded p-1 disabled:opacity-30">↑</button>
                                    <button type="button" :aria-label="tx('moveRight') + ': ' + column.label" :disabled="order.indexOf(column.key) === order.length - 1" @click="moveColumn(column.key, 1)" class="rounded p-1 disabled:opacity-30">↓</button>
                                </template>
                            </div>
                            <button type="button" @click="resetTable" class="w-full rounded bg-gray-100 px-2 py-1.5 text-xs dark:bg-gray-800">{{ tx('reset') }}</button>
                        </div>
                    </details>
                    <form v-if="node.props.searchable === true" @submit.prevent="submitSearch" class="flex gap-1">
                        <input v-model="search" type="search" :aria-label="tx('search')" :placeholder="tx('search')"
                            class="w-44 rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-amber-500 dark:border-white/20" />
                        <button v-if="mode === 'eloquent'" type="submit" class="rounded-lg bg-amber-500 px-3 text-xs font-semibold text-black">{{ tx('search') }}</button>
                    </form>
                </div>
            </div>
            <div v-if="filters.length" class="flex flex-wrap items-end gap-2">
                <label v-for="filter in filters" :key="filter.key" class="flex flex-col gap-1 text-xs text-gray-500">
                    {{ filter.label }}
                    <select :value="selectedFilters[filter.key] ?? ''" @change="setFilter(filter.key, ($event.target as HTMLSelectElement).value)"
                        class="min-w-32 rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm text-gray-900 dark:border-white/20 dark:bg-gray-950 dark:text-white">
                        <option value="">{{ tx('filters') }}: {{ filter.label }}</option>
                        <option v-for="(text, value) in filter.options" :key="value" :value="value">{{ text }}</option>
                    </select>
                </label>
                <button type="button" @click="resetTable" class="rounded-md border border-gray-300 px-3 py-2 text-xs dark:border-white/20">{{ tx('reset') }}</button>
            </div>
        </header>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50/80 dark:bg-white/[0.03]">
                    <tr>
                        <th v-for="column in visibleColumns" :key="column.key" scope="col"
                            :aria-sort="column.sortable ? (sortKey === column.key ? (direction === 'asc' ? 'ascending' : 'descending') : 'none') : undefined"
                            class="whitespace-nowrap px-5 py-3 text-start text-xs font-semibold text-gray-600 dark:text-gray-300">
                            <button v-if="column.sortable" type="button" @click="toggleSort(column)" class="rounded focus-visible:outline-2 focus-visible:outline-amber-500">{{ column.label }} ↕</button>
                            <template v-else>{{ column.label }}</template>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/[0.07]">
                    <tr v-for="(row, index) in visible" :key="String(row.id ?? index)" class="hover:bg-gray-50 dark:hover:bg-white/[0.035]">
                        <td v-for="column in visibleColumns" :key="column.key" class="whitespace-nowrap px-5 py-3.5">
                            <span v-if="column.badge" :class="badgeClass(columnBadgeTone(column, row[column.key]))" class="inline-flex rounded-md px-2 py-1 text-xs ring-1 ring-inset">{{ String(row[column.key] ?? '—') }}</span>
                            <span v-else>{{ String(row[column.key] ?? '—') }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="visible.length === 0" class="px-5 py-12 text-center text-sm text-gray-500">{{ tx('noResults') }}</p>
        </div>
        <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 px-5 py-3 text-xs dark:border-white/10">
            <span aria-live="polite">{{ tx('show') }} {{ mode === 'eloquent' ? pagination?.total ?? 0 : filtered.length }} {{ tx('records') }}</span>
            <nav :aria-label="tx('page')" class="flex items-center gap-2">
                <template v-if="mode === 'eloquent'">
                    <Link v-if="pagination?.previousUrl" :href="pagination.previousUrl" preserve-scroll class="rounded border px-2 py-1.5">{{ tx('previous') }}</Link>
                    <span v-else class="opacity-40">{{ tx('previous') }}</span>
                    <span>{{ tx('page') }} {{ currentPage }} {{ tx('of') }} {{ lastPage }}</span>
                    <Link v-if="pagination?.nextUrl" :href="pagination.nextUrl" preserve-scroll class="rounded border px-2 py-1.5">{{ tx('next') }}</Link>
                    <span v-else class="opacity-40">{{ tx('next') }}</span>
                </template>
                <template v-else>
                    <button type="button" :disabled="currentPage <= 1" @click="page = currentPage - 1" class="rounded border px-2 py-1.5 disabled:opacity-40">{{ tx('previous') }}</button>
                    <span>{{ tx('page') }} {{ currentPage }} {{ tx('of') }} {{ lastPage }}</span>
                    <button type="button" :disabled="currentPage >= lastPage" @click="page = currentPage + 1" class="rounded border px-2 py-1.5 disabled:opacity-40">{{ tx('next') }}</button>
                </template>
            </nav>
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
