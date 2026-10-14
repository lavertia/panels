import { useEffect, useState } from 'react';
import { Link } from '@inertiajs/react';
import type { ComponentNode, TableColumn, TableRow } from './types';
import {
    badgeClass,
    columnBadgeTone,
    safeHref,
    statAccentClass,
    tableColumns,
    tableRows,
    visibleTableRows,
} from './types';

type SortDirection = 'asc' | 'desc';

function TablePreview({ node }: { node: ComponentNode }) {
    const [query, setQuery] = useState('');
    const [sortKey, setSortKey] = useState<string | null>(null);
    const [direction, setDirection] = useState<SortDirection>('asc');
    const columns = tableColumns(node);
    const rows = tableRows(node);
    const visible = visibleTableRows(rows, columns, query, sortKey, direction);
    const title = String(node.props.label ?? node.props.name ?? 'Records');
    const searchable = node.props.searchable === true;

    function toggleSort(column: TableColumn) {
        if (!column.sortable) return;

        if (sortKey === column.key) {
            setDirection((current) => current === 'asc' ? 'desc' : 'asc');
        } else {
            setSortKey(column.key);
            setDirection('asc');
        }
    }

    function cell(row: TableRow, column: TableColumn) {
        const value = row[column.key];

        if (column.badge) {
            return (
                <span className={'inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset ' + badgeClass(columnBadgeTone(column, value))}>
                    {String(value ?? '—')}
                </span>
            );
        }

        return <span className="text-gray-800 dark:text-gray-200">{String(value ?? '—')}</span>;
    }

    return (
        <section className="min-w-0 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900/70" aria-label={title}>
            <header className="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/10">
                <div>
                    <h3 className="text-sm font-semibold text-gray-950 dark:text-white">{title}</h3>
                    {node.props.description && <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{String(node.props.description)}</p>}
                </div>

                {searchable && (
                    <label className="flex w-full items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm transition-colors focus-within:border-amber-500 focus-within:ring-2 focus-within:ring-amber-500/20 sm:max-w-xs dark:border-white/15 dark:bg-gray-950">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" className="size-4 shrink-0 text-gray-400" stroke="currentColor" strokeWidth="1.8">
                            <circle cx="11" cy="11" r="7" /><path d="m16 16 4 4" />
                        </svg>
                        <span className="sr-only">Search {title}</span>
                        <input
                            aria-label={'Search ' + title}
                            type="search"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Search records..."
                            className="min-w-0 flex-1 bg-transparent text-gray-900 outline-none placeholder:text-gray-400 dark:text-gray-100"
                        />
                    </label>
                )}
            </header>

            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200 text-start text-sm dark:divide-white/10">
                    <thead className="bg-gray-50/80 dark:bg-white/[0.03]">
                        <tr>
                            {columns.map((column) => (
                                <th
                                    key={column.key}
                                    scope="col"
                                    aria-sort={column.sortable
                                        ? sortKey === column.key
                                            ? direction === 'asc' ? 'ascending' : 'descending'
                                            : 'none'
                                        : undefined}
                                    className="whitespace-nowrap px-5 py-3 text-start text-xs font-semibold text-gray-600 dark:text-gray-300"
                                >
                                    {column.sortable ? (
                                        <button
                                            type="button"
                                            onClick={() => toggleSort(column)}
                                            className="inline-flex min-h-6 items-center gap-1.5 rounded text-start transition-colors hover:text-amber-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500 dark:hover:text-amber-400"
                                        >
                                            {column.label}
                                            <span aria-hidden="true" className="text-gray-400">
                                                {sortKey === column.key ? direction === 'asc' ? '↑' : '↓' : '↕'}
                                            </span>
                                        </button>
                                    ) : column.label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 bg-white dark:divide-white/[0.07] dark:bg-transparent">
                        {visible.map((row, index) => (
                            <tr key={String(row.id ?? index)} className="transition-colors hover:bg-gray-50/70 dark:hover:bg-white/[0.035]">
                                {columns.map((column) => (
                                    <td key={column.key} className="whitespace-nowrap px-5 py-3.5">
                                        {cell(row, column)}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
                {visible.length === 0 && (
                    <div className="flex flex-col items-center gap-2 px-6 py-12 text-center">
                        <p className="text-sm font-semibold text-gray-800 dark:text-gray-100">No matching records</p>
                        <p className="text-xs text-gray-500 dark:text-gray-400">
                            {query ? 'Try another search term.' : 'No records have been added yet.'}
                        </p>
                        {query && <button type="button" onClick={() => setQuery('')} className="mt-1 rounded-md px-3 py-1.5 text-sm font-medium text-amber-700 hover:bg-amber-50 focus-visible:outline-2 focus-visible:outline-amber-500 dark:text-amber-400 dark:hover:bg-amber-500/10">Clear search</button>}
                    </div>
                )}
            </div>

            <footer className="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 bg-gray-50/50 px-5 py-3 text-xs text-gray-500 dark:border-white/10 dark:bg-white/[0.02] dark:text-gray-400">
                <span aria-live="polite">Showing {visible.length} of {rows.length} records</span>
                <span>Local preview · up to 100 records</span>
            </footer>
        </section>
    );
}

function ActionLink({ node }: { node: ComponentNode }) {
    const label = String(node.props.label ?? node.props.name ?? 'Action');
    const href = safeHref(node.props.href);
    const appearance = node.props.appearance;
    const base = 'inline-flex min-h-9 items-center justify-center rounded-lg px-3.5 py-2 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500';
    const variant = appearance === 'primary'
        ? 'bg-amber-500 text-gray-950 shadow-sm hover:bg-amber-400 dark:bg-amber-400 dark:hover:bg-amber-300'
        : appearance === 'outline'
            ? 'border border-gray-300 bg-transparent text-gray-700 hover:bg-gray-100 dark:border-white/20 dark:text-gray-200 dark:hover:bg-white/10'
            : 'border border-gray-200 bg-white text-gray-800 shadow-sm hover:bg-gray-50 dark:border-white/15 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700';
    const classes = base + ' ' + variant;

    if (!href) return <span className="text-sm text-gray-500">{label}</span>;
    if (href.startsWith('/')) return <Link href={href} className={classes}>{label}</Link>;

    return <a href={href} className={classes} rel="noopener noreferrer">{label}</a>;
}

export default function LavertiaRenderer({ node }: { node: ComponentNode }) {
    const [input, setInput] = useState(String(node.props.value ?? ''));

    useEffect(() => {
        setInput(String(node.props.value ?? ''));
    }, [node.props.value]);

    const children = node.components.map((child) => <LavertiaRenderer key={child.key} node={child} />);

    switch (node.type) {
        case 'container':
        case 'column':
            return <div className="flex min-w-0 flex-col gap-6">{children}</div>;

        case 'row':
            return <div className="flex min-w-0 flex-wrap items-start gap-4">{children}</div>;

        case 'page-header':
            return (
                <header className="flex flex-col gap-4 border-b border-gray-200 pb-6 sm:flex-row sm:items-end sm:justify-between dark:border-white/10">
                    <div className="min-w-0">
                        {node.props.eyebrow && <p className="mb-2 text-xs font-bold uppercase tracking-widest text-amber-700 dark:text-amber-400">{String(node.props.eyebrow)}</p>}
                        <h1 className="text-2xl font-bold tracking-tight text-gray-950 sm:text-3xl dark:text-white">{String(node.props.title ?? 'Overview')}</h1>
                        {node.props.description && <p className="mt-2 max-w-2xl text-sm leading-6 text-gray-500 dark:text-gray-400">{String(node.props.description)}</p>}
                    </div>
                    {children.length > 0 && <div className="flex shrink-0 flex-wrap items-center gap-2">{children}</div>}
                </header>
            );

        case 'stats-grid':
            return <div className="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">{children}</div>;

        case 'stat':
            return (
                <article className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900/70">
                    <div className="flex items-center justify-between gap-3">
                        <p className="text-sm font-medium text-gray-500 dark:text-gray-400">{String(node.props.label ?? '')}</p>
                        <span aria-hidden="true" className={'size-2.5 rounded-full ring-4 ' + statAccentClass(node.props.tone)} />
                    </div>
                    <p className="mt-3 text-3xl font-bold tracking-tight text-gray-950 dark:text-white">{String(node.props.value ?? '—')}</p>
                    {node.props.description && <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">{String(node.props.description)}</p>}
                </article>
            );

        case 'section':
            return (
                <section className="space-y-4" aria-label={String(node.props.title ?? 'Section')}>
                    <div>
                        <h2 className="text-base font-semibold text-gray-950 dark:text-white">{String(node.props.title ?? '')}</h2>
                        {node.props.description && <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{String(node.props.description)}</p>}
                    </div>
                    <div className="flex min-w-0 flex-col gap-4">{children}</div>
                </section>
            );

        case 'card':
            return <section className="flex min-w-0 flex-col gap-5 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900/70">{children}</section>;

        case 'text':
            return <p className="text-sm leading-6 text-gray-700 dark:text-gray-300">{String(node.props.content ?? '')}</p>;

        case 'infolist':
            return (
                <dl className="grid min-w-0 grid-cols-1 gap-x-8 gap-y-5 sm:grid-cols-2">
                    {children}
                </dl>
            );

        case 'text-entry':
            return (
                <div className="min-w-0">
                    <dt className="text-xs font-medium text-gray-500 dark:text-gray-400">{String(node.props.label ?? node.props.name ?? '')}</dt>
                    <dd className="mt-1 break-words text-sm font-semibold text-gray-900 dark:text-gray-100">{String(node.props.value ?? '—')}</dd>
                </div>
            );

        case 'text-input':
            return (
                <div className="flex min-w-0 flex-col gap-1.5">
                    <label htmlFor={node.key} className="text-sm font-semibold text-gray-800 dark:text-gray-200">{String(node.props.label ?? node.props.name ?? '')}</label>
                    <input
                        id={node.key}
                        name={String(node.props.name ?? '')}
                        type="text"
                        value={input}
                        placeholder={String(node.props.placeholder ?? '')}
                        onChange={(event) => setInput(event.target.value)}
                        aria-describedby={node.props.helperText ? node.key + '-help' : undefined}
                        className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20 dark:border-white/20 dark:bg-gray-950 dark:text-gray-100"
                    />
                    {node.props.helperText && <p id={node.key + '-help'} className="text-xs text-gray-500 dark:text-gray-400">{String(node.props.helperText)}</p>}
                </div>
            );

        case 'table':
            return <TablePreview node={node} />;

        case 'action':
            return <ActionLink node={node} />;

        case 'text-column':
            return null;

        default:
            return <p role="alert" className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900/50 dark:bg-rose-900/20 dark:text-rose-300">Unsupported component: {node.type}</p>;
    }
}
