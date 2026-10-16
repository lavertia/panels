<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import Self from './renderer.svelte';
    import type { ComponentNode } from './types';
    import { safeHref, tableColumns, tableRows } from './types';

    let { node }: { node: ComponentNode } = $props();
    let input = $state('');
    let label = $derived(String(node.props.label ?? node.props.name ?? ''));
    let href = $derived(safeHref(node.props.href));
    let columns = $derived(tableColumns(node));
    let rows = $derived(tableRows(node));

    $effect(() => {
        input = String(node.props.value ?? '');
    });
</script>

{#if node.type === 'column' || node.type === 'container'}
    <div class="flex min-w-0 flex-col gap-4">
        {#each node.components as child (child.key)}
            <Self node={child} />
        {/each}
    </div>
{:else if node.type === 'row'}
    <div class="flex min-w-0 flex-wrap gap-4">
        {#each node.components as child (child.key)}
            <Self node={child} />
        {/each}
    </div>
{:else if node.type === 'card'}
    <section class="min-w-0 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
        {#each node.components as child (child.key)}
            <Self node={child} />
        {/each}
    </section>
{:else if node.type === 'text'}
    <p class="text-gray-900 dark:text-gray-100">{String(node.props.content ?? '')}</p>
{:else if node.type === 'infolist'}
    <dl class="grid min-w-0 gap-3">
        {#each node.components as child (child.key)}
            <Self node={child} />
        {/each}
    </dl>
{:else if node.type === 'text-entry'}
    <div class="min-w-0">
        <dt class="text-xs text-gray-500">{label}</dt>
        <dd class="text-sm">{String(node.props.value ?? '—')}</dd>
    </div>
{:else if node.type === 'text-input'}
    <div class="flex min-w-0 flex-col gap-1">
        <label for={node.key} class="text-sm font-medium">{label}</label>
        <input
            id={node.key}
            bind:value={input}
            type="text"
            name={String(node.props.name ?? '')}
            placeholder={String(node.props.placeholder ?? '')}
            class="rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700"
        />
    </div>
{:else if node.type === 'table'}
    <div class="min-w-0 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
            <thead>
                <tr>
                    {#each columns as column (column.key)}
                        <th scope="col" class="px-3 py-2 text-start font-semibold">{column.label}</th>
                    {/each}
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                {#each rows as row, index (String(row.id ?? index))}
                    <tr>
                        {#each columns as column (column.key)}
                            <td class="px-3 py-2">{String(row[column.key] ?? '')}</td>
                        {/each}
                    </tr>
                {/each}
            </tbody>
        </table>
        {#if rows.length === 0}
            <p class="px-3 py-4 text-gray-500">No records</p>
        {/if}
    </div>
{:else if node.type === 'action' && href?.startsWith('/')}
    <Link {href} class="inline-flex w-fit rounded-md bg-gray-900 px-3 py-2 text-sm text-white dark:bg-gray-100 dark:text-gray-900">{label}</Link>
{:else if node.type === 'action' && href}
    <a {href} rel="noopener noreferrer" class="inline-flex w-fit rounded-md bg-gray-900 px-3 py-2 text-sm text-white dark:bg-gray-100 dark:text-gray-900">{label}</a>
{:else if node.type === 'action'}
    <span class="text-sm text-gray-500">{label}</span>
{:else if node.type === 'text-column'}
    <!-- Table columns are data descriptors rendered by the table. -->
{:else}
    <p role="alert" class="text-sm text-red-700">Unsupported component: {node.type}</p>
{/if}
