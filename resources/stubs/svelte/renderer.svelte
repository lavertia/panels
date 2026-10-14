<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import Self from './renderer.svelte';
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

    let { node }: { node: ComponentNode } = $props();
    let input = $state('');
    let checked = $state(false);
    let search = $state('');
    let sortKey = $state<string | null>(null);
    let direction = $state<'asc' | 'desc'>('asc');
    let label = $derived(String(node.props.label ?? node.props.name ?? ''));
    let title = $derived(String(node.props.title ?? ''));
    let href = $derived(safeHref(node.props.href));
    let columns = $derived(tableColumns(node));

    let rows = $derived(tableRows(node));
    let filters = $derived(tableFilters(node));
    let mode = $derived(node.props.mode === 'eloquent' ? 'eloquent' : 'snapshot');
    const tx = (key: Parameters<typeof translate>[1]): string => translate(node.props.locale, key);
    let selectedFilters = $state<Record<string, string>>({});
    let hidden = $state<string[]>(defaultHiddenColumns(tableColumns(node)));
    let order = $state<string[]>(tableColumns(node).map(column => column.key));
    let pageNumber = $state(1);
    let visibleColumns = $derived(tableColumnsInOrder(columns, order, hidden));
    let filtered = $derived(mode === 'eloquent' ? rows : visibleTableRows(rows, columns, search, sortKey, direction, selectedFilters));
    let lastPage = $derived(mode === 'eloquent'
        ? Number((node.props.pagination as { lastPage?: number } | undefined)?.lastPage ?? 1)
        : Math.max(1, Math.ceil(filtered.length / 5)));
    let currentPage = $derived(mode === 'eloquent'
        ? Number((node.props.pagination as { page?: number } | undefined)?.page ?? 1)
        : Math.min(pageNumber, lastPage));
    let visible = $derived(mode === 'eloquent' ? filtered : filtered.slice((currentPage - 1) * 5, currentPage * 5));
    let pagination = $derived(node.props.pagination as { total?: number; nextUrl?: string | null; previousUrl?: string | null } | undefined);

    function navigate(changes: Record<string, string | null>): void {
        router.get(tableStateUrl(node, changes), {}, { preserveScroll: true, preserveState: true, replace: true });
    }

    function setFilter(key: string, value: string): void {
        selectedFilters = { ...selectedFilters, [key]: value };
        if (mode === 'eloquent') navigate({ ['filter_' + key]: value || null });
        else pageNumber = 1;
    }

    function submitSearch(event: SubmitEvent): void {
        event.preventDefault();
        if (mode === 'eloquent') navigate({ search });
        else pageNumber = 1;
    }

    function moveColumn(key: string, step: number): void {
        const next = [...order];
        const index = next.indexOf(key);
        if (index < 0 || index + step < 0 || index + step >= next.length) return;
        [next[index], next[index + step]] = [next[index + step], next[index]];
        order = next;
    }

    function resetTable(): void {
        search = '';
        selectedFilters = {};
        sortKey = null;
        direction = 'asc';
        pageNumber = 1;
        order = columns.map(c => c.key);
        hidden = defaultHiddenColumns(columns);
        if (mode === 'eloquent') {
            const changes: Record<string, string | null> = { search: null, sort: null, direction: null };
            filters.forEach(f => { changes['filter_' + f.key] = null; });
            navigate(changes);
        }
    }

    let actionClass = $derived.by(() => {
        const base = 'inline-flex min-h-9 items-center justify-center rounded-lg px-3.5 py-2 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500';
        const appearance = node.props.appearance;
        const variant = appearance === 'primary'
            ? 'bg-amber-500 text-gray-950 shadow-sm hover:bg-amber-400 dark:bg-amber-400 dark:hover:bg-amber-300'
            : appearance === 'outline'
                ? 'border border-gray-300 bg-transparent text-gray-700 hover:bg-gray-100 dark:border-white/20 dark:text-gray-200 dark:hover:bg-white/10'
                : 'border border-gray-200 bg-white text-gray-800 shadow-sm hover:bg-gray-50 dark:border-white/15 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700';

        return base + ' ' + variant;
    });


    function toggleSort(column: TableColumn): void {
        if (!column.sortable) return;
        const next = sortKey === column.key && direction === 'asc' ? 'desc' : 'asc';
        sortKey = column.key;
        direction = next;
        if (mode === 'eloquent') navigate({ sort: column.key, direction: next });
        else pageNumber = 1;
    }

    $effect(() => {
        input = String(node.props.value ?? '');
        checked = node.props.checked === true;
    });
</script>

{#if node.type === 'column' || node.type === 'container'}
    <div class="flex min-w-0 flex-col gap-6">
        {#each node.components as child (child.key)}
            <Self node={child} />
        {/each}
    </div>
{:else if node.type === 'row'}
    <div class="flex min-w-0 flex-wrap items-start gap-4">
        {#each node.components as child (child.key)}
            <Self node={child} />
        {/each}
    </div>
{:else if node.type === 'page-header'}
    <header class="flex flex-col gap-4 border-b border-gray-200 pb-6 sm:flex-row sm:items-end sm:justify-between dark:border-white/10">
        <div class="min-w-0">
            {#if node.props.eyebrow}
                <p class="mb-2 text-xs font-bold uppercase tracking-widest text-amber-700 dark:text-amber-400">{String(node.props.eyebrow)}</p>
            {/if}
            <h1 class="text-2xl font-bold tracking-tight text-gray-950 sm:text-3xl dark:text-white">{title || 'Overview'}</h1>
            {#if node.props.description}
                <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-500 dark:text-gray-400">{String(node.props.description)}</p>
            {/if}
        </div>
        {#if node.components.length > 0}
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                {#each node.components as child (child.key)}
                    <Self node={child} />
                {/each}
            </div>
        {/if}
    </header>
{:else if node.type === 'stats-grid'}
    <div class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {#each node.components as child (child.key)}
            <Self node={child} />
        {/each}
    </div>
{:else if node.type === 'stat'}
    <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900/70">
        <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{label}</p>
            <span aria-hidden="true" class={'size-2.5 rounded-full ring-4 ' + statAccentClass(node.props.tone)}></span>
        </div>
        <p class="mt-3 text-3xl font-bold tracking-tight text-gray-950 dark:text-white">{String(node.props.value ?? '—')}</p>
        {#if node.props.description}
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{String(node.props.description)}</p>
        {/if}
    </article>
{:else if node.type === 'section'}
    <section class="space-y-4" aria-label={title || 'Section'}>
        <div>
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">{title}</h2>
            {#if node.props.description}
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{String(node.props.description)}</p>
            {/if}
        </div>
        <div class="flex min-w-0 flex-col gap-4">
            {#each node.components as child (child.key)}
                <Self node={child} />
            {/each}
        </div>
    </section>
{:else if node.type === 'card'}
    <section class="flex min-w-0 flex-col gap-5 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900/70">
        {#each node.components as child (child.key)}
            <Self node={child} />
        {/each}
    </section>
{:else if node.type === 'text'}
    <p class="text-sm leading-6 text-gray-700 dark:text-gray-300">{String(node.props.content ?? '')}</p>
{:else if node.type === 'infolist'}
    <dl class="grid min-w-0 grid-cols-1 gap-x-8 gap-y-5 sm:grid-cols-2">
        {#each node.components as child (child.key)}
            <Self node={child} />
        {/each}
    </dl>
{:else if node.type === 'text-entry'}
    <div class="min-w-0">
        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{label}</dt>
        <dd class="mt-1 break-words text-sm font-semibold text-gray-900 dark:text-gray-100">{String(node.props.value ?? '—')}</dd>
    </div>
{:else if node.type === 'form'}
    <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-gray-900/70">
        <h3 class="text-sm font-semibold">{label}</h3>
        <div class="grid gap-4 sm:grid-cols-2">
            {#each node.components as child (child.key)}
                <Self node={child} />
            {/each}
        </div>
        <p role="status" class="text-xs text-gray-500">Form preview only — no save endpoint configured.</p>
        <button type="button" disabled class="cursor-not-allowed rounded-md bg-gray-200 px-4 py-2 text-sm opacity-60 dark:bg-gray-800">Save (unavailable)</button>
    </section>
{:else if node.type === 'select'}
    <label class="flex flex-col gap-1.5 text-sm font-semibold">
        {label}
        <select bind:value={input} required={node.props.required === true} class="rounded-lg border border-gray-300 bg-white px-3 py-2.5 font-normal dark:border-white/20 dark:bg-gray-950">
            <option value="">—</option>
            {#each Object.entries((node.props.options as Record<string, string>) ?? {}) as [value, option] (value)}
                <option {value}>{option}</option>
            {/each}
        </select>
    </label>
{:else if node.type === 'textarea'}
    <label class="flex flex-col gap-1.5 text-sm font-semibold">
        {label}
        <textarea bind:value={input} rows={Number(node.props.rows ?? 3)} class="rounded-lg border border-gray-300 bg-white px-3 py-2.5 font-normal dark:border-white/20 dark:bg-gray-950"></textarea>
    </label>
{:else if node.type === 'checkbox'}
    <label class="flex items-center gap-2 text-sm font-semibold">
        <input type="checkbox" bind:checked />{label}
    </label>
{:else if node.type === 'text-input'}
    <div class="flex min-w-0 flex-col gap-1.5">
        <label for={node.key} class="text-sm font-semibold text-gray-800 dark:text-gray-200">{label}</label>
        <input
            id={node.key}
            bind:value={input}
            type="text"
            name={String(node.props.name ?? '')}
            placeholder={String(node.props.placeholder ?? '')}
            aria-describedby={node.props.helperText ? node.key + '-help' : undefined}
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20 dark:border-white/20 dark:bg-gray-950 dark:text-gray-100"
        />
        {#if node.props.helperText}
            <p id={node.key + '-help'} class="text-xs text-gray-500 dark:text-gray-400">{String(node.props.helperText)}</p>
        {/if}
    </div>

{:else if node.type === 'table'}
    <section class="min-w-0 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900/70" aria-label={label}>
        <header class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 dark:border-white/10">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{label}</h3>
                    {#if node.props.description}
                        <p class="mt-1 text-xs text-gray-500">{String(node.props.description)}</p>
                    {/if}
                </div>
                <div class="flex flex-wrap gap-2">
                    {#if node.props.columnManager !== false}
                        <details class="relative">
                            <summary class="cursor-pointer rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold focus-visible:outline-2 focus-visible:outline-amber-500 dark:border-white/20">{tx('columns')}</summary>
                            <div class="absolute end-0 z-20 mt-2 w-64 space-y-2 rounded-lg border border-gray-200 bg-white p-3 shadow-xl dark:border-gray-700 dark:bg-gray-900">
                                {#each columns.filter(c => !c.hidden) as column (column.key)}
                                    <div class="flex items-center gap-2 text-xs">
                                        <label class="flex min-w-0 flex-1 items-center gap-2">
                                            <input type="checkbox" checked={!hidden.includes(column.key)}
                                                disabled={!column.toggleable || (!hidden.includes(column.key) && visibleColumns.length <= 1)}
                                                onchange={(event) => hidden = event.currentTarget.checked ? hidden.filter(key => key !== column.key) : [...hidden, column.key]} />
                                            <span class="truncate">{column.label}</span>
                                        </label>
                                        {#if node.props.reorderableColumns === true}
                                            <button type="button" aria-label={tx('moveLeft') + ': ' + column.label} disabled={order.indexOf(column.key) === 0} onclick={() => moveColumn(column.key, -1)} class="rounded p-1 disabled:opacity-30">↑</button>
                                            <button type="button" aria-label={tx('moveRight') + ': ' + column.label} disabled={order.indexOf(column.key) === order.length - 1} onclick={() => moveColumn(column.key, 1)} class="rounded p-1 disabled:opacity-30">↓</button>
                                        {/if}
                                    </div>
                                {/each}
                                <button type="button" onclick={resetTable} class="w-full rounded bg-gray-100 px-2 py-1.5 text-xs dark:bg-gray-800">{tx('reset')}</button>
                            </div>
                        </details>
                    {/if}
                    {#if node.props.searchable === true}
                        <form onsubmit={submitSearch} class="flex gap-1">
                            <input bind:value={search} type="search" aria-label={tx('search')} placeholder={tx('search')}
                                class="w-44 rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-amber-500 dark:border-white/20" />
                            {#if mode === 'eloquent'}
                                <button type="submit" class="rounded-lg bg-amber-500 px-3 text-xs font-semibold text-black">{tx('search')}</button>
                            {/if}
                        </form>
                    {/if}
                </div>
            </div>
            {#if filters.length}
                <div class="flex flex-wrap items-end gap-2">
                    {#each filters as filter (filter.key)}
                        <label class="flex flex-col gap-1 text-xs text-gray-500">
                            {filter.label}
                            <select value={selectedFilters[filter.key] ?? ''} onchange={(event) => setFilter(filter.key, event.currentTarget.value)}
                                class="min-w-32 rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm text-gray-900 dark:border-white/20 dark:bg-gray-950 dark:text-white">
                                <option value="">{tx('filters')}: {filter.label}</option>
                                {#each Object.entries(filter.options) as [value, option] (value)}
                                    <option {value}>{option}</option>
                                {/each}
                            </select>
                        </label>
                    {/each}
                    <button type="button" onclick={resetTable} class="rounded-md border border-gray-300 px-3 py-2 text-xs dark:border-white/20">{tx('reset')}</button>
                </div>
            {/if}
        </header>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50/80 dark:bg-white/[0.03]">
                    <tr>
                        {#each visibleColumns as column (column.key)}
                            <th scope="col" aria-sort={column.sortable ? (sortKey === column.key ? (direction === 'asc' ? 'ascending' : 'descending') : 'none') : undefined}
                                class="whitespace-nowrap px-5 py-3 text-start text-xs font-semibold text-gray-600 dark:text-gray-300">
                                {#if column.sortable}
                                    <button type="button" onclick={() => toggleSort(column)} class="rounded focus-visible:outline-2 focus-visible:outline-amber-500">{column.label} ↕</button>
                                {:else}
                                    {column.label}
                                {/if}
                            </th>
                        {/each}
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/[0.07]">
                    {#each visible as row, index (String(row.id ?? index))}
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.035]">
                            {#each visibleColumns as column (column.key)}
                                <td class="whitespace-nowrap px-5 py-3.5">
                                    {#if column.badge}
                                        <span class={'inline-flex rounded-md px-2 py-1 text-xs ring-1 ring-inset ' + badgeClass(columnBadgeTone(column, row[column.key]))}>{String(row[column.key] ?? '—')}</span>
                                    {:else}
                                        <span>{String(row[column.key] ?? '—')}</span>
                                    {/if}
                                </td>
                            {/each}
                        </tr>
                    {/each}
                </tbody>
            </table>
            {#if visible.length === 0}
                <p class="px-5 py-12 text-center text-sm text-gray-500">{tx('noResults')}</p>
            {/if}
        </div>
        <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 px-5 py-3 text-xs dark:border-white/10">
            <span aria-live="polite">{tx('show')} {mode === 'eloquent' ? pagination?.total ?? 0 : filtered.length} {tx('records')}</span>
            <nav aria-label={tx('page')} class="flex items-center gap-2">
                {#if mode === 'eloquent'}
                    {#if pagination?.previousUrl}
                        <Link href={pagination.previousUrl} preserveScroll class="rounded border px-2 py-1.5">{tx('previous')}</Link>
                    {:else}
                        <span class="opacity-40">{tx('previous')}</span>
                    {/if}
                    <span>{tx('page')} {currentPage} {tx('of')} {lastPage}</span>
                    {#if pagination?.nextUrl}
                        <Link href={pagination.nextUrl} preserveScroll class="rounded border px-2 py-1.5">{tx('next')}</Link>
                    {:else}
                        <span class="opacity-40">{tx('next')}</span>
                    {/if}
                {:else}
                    <button type="button" disabled={currentPage <= 1} onclick={() => pageNumber = currentPage - 1} class="rounded border px-2 py-1.5 disabled:opacity-40">{tx('previous')}</button>
                    <span>{tx('page')} {currentPage} {tx('of')} {lastPage}</span>
                    <button type="button" disabled={currentPage >= lastPage} onclick={() => pageNumber = currentPage + 1} class="rounded border px-2 py-1.5 disabled:opacity-40">{tx('next')}</button>
                {/if}
            </nav>
        </footer>
    </section>
{:else if node.type === 'action' && href?.startsWith('/')}
    <Link {href} class={actionClass}>{label}</Link>
{:else if node.type === 'action' && href}
    <a {href} rel="noopener noreferrer" class={actionClass}>{label}</a>
{:else if node.type === 'action'}
    <span class="text-sm text-gray-500">{label}</span>
{:else if node.type === 'text-column'}
    <!-- Table column descriptors are displayed by the table component. -->
{:else}
    <p role="alert" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900/50 dark:bg-rose-900/20 dark:text-rose-300">
        Unsupported component: {node.type}
    </p>
{/if}
