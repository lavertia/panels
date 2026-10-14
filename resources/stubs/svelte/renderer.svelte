<script lang="ts">
    import { Link } from '@inertiajs/svelte';
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
    } from './types';

    let { node }: { node: ComponentNode } = $props();
    let input = $state('');
    let search = $state('');
    let sortKey = $state<string | null>(null);
    let direction = $state<'asc' | 'desc'>('asc');
    let label = $derived(String(node.props.label ?? node.props.name ?? ''));
    let title = $derived(String(node.props.title ?? ''));
    let href = $derived(safeHref(node.props.href));
    let columns = $derived(tableColumns(node));
    let rows = $derived(tableRows(node));
    let visible = $derived(visibleTableRows(rows, columns, search, sortKey, direction));
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

        if (sortKey === column.key) {
            direction = direction === 'asc' ? 'desc' : 'asc';
        } else {
            sortKey = column.key;
            direction = 'asc';
        }
    }

    $effect(() => {
        input = String(node.props.value ?? '');
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
        <header class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/10">
            <div>
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{label}</h3>
                {#if node.props.description}
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{String(node.props.description)}</p>
                {/if}
            </div>
            {#if node.props.searchable === true}
                <label class="flex w-full items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm transition-colors focus-within:border-amber-500 focus-within:ring-2 focus-within:ring-amber-500/20 sm:max-w-xs dark:border-white/15 dark:bg-gray-950">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" class="size-4 shrink-0 text-gray-400" stroke="currentColor" stroke-width="1.8">
                        <circle cx="11" cy="11" r="7" /><path d="m16 16 4 4" />
                    </svg>
                    <span class="sr-only">Search {label}</span>
                    <input
                        bind:value={search}
                        aria-label={'Search ' + label}
                        type="search"
                        placeholder="Search records..."
                        class="min-w-0 flex-1 bg-transparent text-gray-900 outline-none placeholder:text-gray-400 dark:text-gray-100"
                    />
                </label>
            {/if}
        </header>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-start text-sm dark:divide-white/10">
                <thead class="bg-gray-50/80 dark:bg-white/[0.03]">
                    <tr>
                        {#each columns as column (column.key)}
                            <th
                                scope="col"
                                aria-sort={column.sortable ? (sortKey === column.key ? (direction === 'asc' ? 'ascending' : 'descending') : 'none') : undefined}
                                class="whitespace-nowrap px-5 py-3 text-start text-xs font-semibold text-gray-600 dark:text-gray-300"
                            >
                                {#if column.sortable}
                                    <button
                                        type="button"
                                        onclick={() => toggleSort(column)}
                                        class="inline-flex min-h-6 items-center gap-1.5 rounded text-start transition-colors hover:text-amber-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500 dark:hover:text-amber-400"
                                    >
                                        {column.label}
                                        <span aria-hidden="true" class="text-gray-400">{sortKey === column.key ? (direction === 'asc' ? '↑' : '↓') : '↕'}</span>
                                    </button>
                                {:else}
                                    {column.label}
                                {/if}
                            </th>
                        {/each}
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white dark:divide-white/[0.07] dark:bg-transparent">
                    {#each visible as row, index (String(row.id ?? index))}
                        <tr class="transition-colors hover:bg-gray-50/70 dark:hover:bg-white/[0.035]">
                            {#each columns as column (column.key)}
                                <td class="whitespace-nowrap px-5 py-3.5">
                                    {#if column.badge}
                                        <span class={'inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset ' + badgeClass(columnBadgeTone(column, row[column.key]))}>
                                            {String(row[column.key] ?? '—')}
                                        </span>
                                    {:else}
                                        <span class="text-gray-800 dark:text-gray-200">{String(row[column.key] ?? '—')}</span>
                                    {/if}
                                </td>
                            {/each}
                        </tr>
                    {/each}
                </tbody>
            </table>
            {#if visible.length === 0}
                <div class="flex flex-col items-center gap-2 px-6 py-12 text-center">
                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">No matching records</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{search ? 'Try another search term.' : 'No records have been added yet.'}</p>
                    {#if search}
                        <button
                            type="button"
                            onclick={() => search = ''}
                            class="mt-1 rounded-md px-3 py-1.5 text-sm font-medium text-amber-700 hover:bg-amber-50 focus-visible:outline-2 focus-visible:outline-amber-500 dark:text-amber-400 dark:hover:bg-amber-500/10"
                        >Clear search</button>
                    {/if}
                </div>
            {/if}
        </div>
        <footer class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 bg-gray-50/50 px-5 py-3 text-xs text-gray-500 dark:border-white/10 dark:bg-white/[0.02] dark:text-gray-400">
            <span aria-live="polite">Showing {visible.length} of {rows.length} records</span>
            <span>Local preview · up to 100 records</span>
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
