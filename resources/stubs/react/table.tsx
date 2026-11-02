import { Fragment, useEffect, useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { router } from '@inertiajs/react';
import {
    ArrowDown, ArrowUp, CheckCircle2, ChevronLeft, ChevronRight, ChevronsLeft,
    ChevronsRight, Columns3, Download, Filter, ImageIcon, Layers3, MoreHorizontal,
    RotateCcw, Search, XCircle, GripVertical, Checkbox, Dialog, DialogContent,
    DialogDescription, DialogHeader, DialogTitle, DropdownMenu,
    DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuItem,
    DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger, TableSelect,
    SearchableSelect, cn,
} from './table-ui';
import type { ComponentNode, Scalar, TableColumn, TableRow } from './types';
import {
    badgeClass, columnBadgeTone, defaultHiddenColumns, tableColumns,
    tableColumnsInOrder, tableFilters, tableRows, tableStateUrl, translate,
    safeHref, visibleTableRows,
} from './types';

/**
 * Canonical portable React Tables adapter.
 * PHP owns columns, filters, SQL, pagination limits and authorization.
 * Browser-local UI state is presentation only. No Starter Kit dependency.
 */
type SortDirection = 'asc' | 'desc';
interface Pagination {
    page?: number;
    perPage?: number;
    total?: number | null;
    lastPage?: number | null;
    hasMorePages?: boolean;
    truncated?: boolean;
}

const iconButton = 'inline-flex size-9 shrink-0 items-center justify-center rounded-md text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white focus-visible:outline-2 focus-visible:outline-amber-500 disabled:opacity-40';
const tabButton = 'inline-flex min-h-9 items-center gap-2 rounded-lg px-3 text-sm font-medium transition-colors hover:bg-slate-100 dark:hover:bg-slate-800 aria-selected:bg-slate-100 dark:bg-slate-800 aria-selected:text-slate-950 dark:text-slate-100';
const gradients = [
    'from-lime-300 to-emerald-700', 'from-neutral-400 to-stone-800',
    'from-sky-300 to-blue-800', 'from-amber-200 to-orange-700',
    'from-violet-300 to-fuchsia-800', 'from-red-300 to-rose-700',
    'from-teal-300 to-cyan-800', 'from-slate-300 to-slate-700',
];

function currency(value: Scalar, currencyCode?: string): string {
    const numeric = Number(value);
    if (!Number.isFinite(numeric)) return '—';
    if (!currencyCode) return new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(numeric);
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: currencyCode }).format(numeric);
}

function renderCell(row: TableRow, column: TableColumn) {
    const value = row[column.key];
    if (column.type === 'image') {
        const num = Number(value);
        return <span role="img" aria-label="Demo thumbnail" className={cn(
            'flex size-10 items-center justify-center rounded-lg bg-gradient-to-br text-white/90',
            gradients[Math.abs(Number.isFinite(num) ? num : 0) % gradients.length],
        )}><ImageIcon className="size-4" /></span>;
    }
    if (column.type === 'icon' && column.boolean) {
        const yes = value !== null && value !== undefined
            && value !== false && value !== 0 && value !== '0' && value !== '';
        return yes
            ? <CheckCircle2 role="img" aria-label="Yes" className="size-5 text-emerald-600 dark:text-emerald-400" />
            : <XCircle role="img" aria-label="No" className="size-5 text-rose-600 dark:text-rose-400" />;
    }
    if (column.badge) {
        return <span className={cn(
            'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium capitalize ring-1 ring-inset',
            badgeClass(columnBadgeTone(column, value)),
        )}>{String(value ?? '—')}</span>;
    }
    if (column.currency) return <span className="font-medium tabular-nums">{currency(value, column.currency)}</span>;
    if (column.key === 'name' && typeof value === 'string') {
        const initials = value.split(' ').slice(0, 2).map(part => part[0] ?? '').join('').toUpperCase();
        return <span className="flex items-center gap-2.5"><span aria-hidden="true" className="flex size-8 shrink-0 items-center justify-center rounded-full bg-amber-500/10 text-xs font-bold text-amber-600 dark:text-amber-400">{initials}</span><span>{value}</span></span>;
    }
    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}(T|$)/.test(value)) {
        return <span className="tabular-nums">{value.slice(0, 10)}</span>;
    }
    return <span>{String(value ?? '—')}</span>;
}

function csvCell(value: Scalar): string {
    const raw = String(value ?? '');
    const safe = /^[\t\r\n ]*[=+@-]/.test(raw) ? "'" + raw : raw;
    return '"' + safe.replaceAll('"', '""') + '"';
}

function exportCurrentRows(rows: TableRow[], columns: TableColumn[], name: string): void {
    if (!rows.length) return;
    const content = [
        columns.map(c => csvCell(c.label)).join(','),
        ...rows.map(row => columns.map(c => csvCell(row[c.key])).join(',')),
    ].join('\r\n');
    const blob = new Blob(['\ufeff', content], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = name.replace(/[^a-z0-9_-]/gi, '_') + '-page.csv';
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
}

function toneClass(value: Scalar, config: { field?: string; tones?: Record<string, string> } | null): string {
    const tone = config?.tones?.[String(value ?? '')];
    if (tone === 'success') return 'bg-emerald-500/10 hover:bg-emerald-500/15';
    if (tone === 'warning') return 'bg-amber-500/10 hover:bg-amber-500/15';
    if (tone === 'danger') return 'bg-rose-500/10 hover:bg-rose-500/15';
    if (tone === 'info') return 'bg-sky-500/10 hover:bg-sky-500/15';
    return '';
}

/**
 * Single table renderer for PHP-defined Lavertia Tables.
 * Client controls change only display state; SQL state is sent to Table::getProps.
 */
export function LavertiaTable({ node }: { node: ComponentNode }) {
    const columns = tableColumns(node);
    const filters = tableFilters(node);
    const rows = tableRows(node);
    const mode = node.props.mode === 'eloquent' ? 'eloquent' : 'snapshot';
    const pager = (node.props.pagination ?? {}) as Pagination;
    const prefix = String(node.props.name ?? 'table');
    const title = String(node.props.label ?? 'Records');
    const tx = (key: Parameters<typeof translate>[1]) => translate(node.props.locale, key);
    const [search, setSearch] = useState(String(node.props.appliedSearch ?? ''));
    const [sortKey, setSortKey] = useState<string | null>(
        typeof node.props.appliedSort === 'string' ? node.props.appliedSort : null,
    );
    const [direction, setDirection] = useState<SortDirection>(node.props.appliedDirection === 'desc' ? 'desc' : 'asc');
    const [values, setValues] = useState<Record<string, string>>(
        (node.props.appliedFilters as Record<string, string> | undefined) ?? {},
    );
    const [visibleOrder, setVisibleOrder] = useState(() => columns.map(column => column.key));
    const [hidden, setHidden] = useState(() => defaultHiddenColumns(columns));
    const [filtersOpen, setFiltersOpen] = useState(false);
    const [groupKey, setGroupKey] = useState('none');
    const [groupDirection, setGroupDirection] = useState<SortDirection>('asc');
    const [collapsed, setCollapsed] = useState<string[]>([]);
    const [selected, setSelected] = useState<string[]>([]);
    const [localPage, setLocalPage] = useState(1);
    const configuredOptions = Array.isArray(node.props.paginationOptions)
        ? node.props.paginationOptions.filter((size): size is number =>
            Number.isInteger(size) && size >= 1 && size <= 100)
        : [5, 10, 25, 50, 100];
    const allowedOptions = configuredOptions.length ? configuredOptions : [10];
    const configuredDefault = Number(node.props.defaultPaginationPageOption ?? 10);
    const [localPerPage, setLocalPerPage] = useState(
        allowedOptions.includes(configuredDefault) ? configuredDefault : allowedOptions[0],
    );
    const [localRows, setLocalRows] = useState<TableRow[]>(rows);
    const [reordering, setReordering] = useState(false);
    const [reorderEvents, setReorderEvents] = useState<string[]>([]);
    const [busy, setBusy] = useState(false);
    const [detailRow, setDetailRow] = useState<TableRow | null>(null);

    const paginationEnabled = node.props.paginationEnabled !== false;
    const simplePagination = mode === 'eloquent' && node.props.paginationMode === 'simple' && paginationEnabled;
    const extremeLinks = node.props.extremePaginationLinks !== false;
    const reorderDemo = node.props.demoReorder === true && mode === 'snapshot';
    const paginationSize = mode === 'eloquent' ? Number(pager.perPage ?? 10) : localPerPage;
    const rowTones = node.props.recordTones && typeof node.props.recordTones === 'object'
        ? node.props.recordTones as { field?: string; tones?: Record<string, string> } : null;
    const striped = node.props.striped === true;
    const selectable = node.props.selectable === true;
    const recordActions = node.props.recordActions === true;
    const paginationControls = node.props.paginationControls === true
        || (mode === 'eloquent' && paginationEnabled && node.props.paginationControls !== false);
    const showActionsColumn = recordActions || reorderDemo;
    const searchable = node.props.searchable === true && columns.some(column => column.searchable);
    const columnManager = node.props.columnManager === true && columns.some(column =>
        column.toggleable && !column.hidden);
    const appliedTab = typeof node.props.appliedTab === 'string' ? node.props.appliedTab : '';
    const tabs = node.props.tabs && typeof node.props.tabs === 'object' && !Array.isArray(node.props.tabs)
        ? node.props.tabs as Record<string, string> : {};
    const tabCounts = node.props.tabCounts && typeof node.props.tabCounts === 'object' && !Array.isArray(node.props.tabCounts)
        ? node.props.tabCounts as Record<string, number> : {};
    const groupOptions = node.props.groupOptions && typeof node.props.groupOptions === 'object' && !Array.isArray(node.props.groupOptions)
        ? node.props.groupOptions as Record<string, string> : {};
    const summaryTotals = node.props.summaryTotals && typeof node.props.summaryTotals === 'object' && !Array.isArray(node.props.summaryTotals)
        ? node.props.summaryTotals as Record<string, number> : {};
    const summaryColumns = Array.isArray(node.props.summaryColumns)
        ? node.props.summaryColumns.filter((key): key is string => typeof key === 'string') : [];
    const activeCount = Object.values(values).filter(Boolean).length + (appliedTab ? 1 : 0);

    useEffect(() => {
        setLocalRows(rows);
    }, [node.props.rows]);

    useEffect(() => {
        const interval = Number(node.props.pollIntervalMs ?? 0);
        if (mode !== 'eloquent' || interval < 5000 || interval > 300000) return;
        const timer = window.setInterval(() => {
            if (document.visibilityState === 'visible' && !busy) {
                router.reload();
            }
        }, interval);
        return () => window.clearInterval(timer);
    }, [mode, node.props.pollIntervalMs, busy]);

    useEffect(() => {
        setSearch(String(node.props.appliedSearch ?? ''));
        setSortKey(typeof node.props.appliedSort === 'string' ? node.props.appliedSort : null);
        setDirection(node.props.appliedDirection === 'desc' ? 'desc' : 'asc');
        setValues((node.props.appliedFilters as Record<string, string> | undefined) ?? {});
        setSelected([]);
        setDetailRow(null);
        setBusy(false);
    }, [node.props.appliedSearch, node.props.appliedSort, node.props.appliedDirection, node.props.appliedFilters, node.props.appliedTab, pager.page, pager.perPage]);

    const ordered = tableColumnsInOrder(columns, visibleOrder, hidden);
    // The manager and table header share exactly the same order. Hidden
    // toggleable columns remain listed but cannot be moved until revealed.
    const managerColumns = columns.filter(column => !column.hidden &&
        (column.toggleable || node.props.reorderableColumns === true))
        .sort((a, b) => visibleOrder.indexOf(a.key) - visibleOrder.indexOf(b.key));
    const movableKeys = managerColumns
        .filter(column => !hidden.includes(column.key))
        .map(column => column.key);
    const tableColSpan = ordered.length + Number(selectable) + Number(showActionsColumn);
    const filtered = mode === 'eloquent' ? rows : visibleTableRows(
        reorderDemo ? localRows : rows, columns, search, sortKey, direction, values,
    );
    const totalKnown = mode !== 'eloquent' || (pager.total !== undefined && pager.total !== null);
    const totalRows = mode === 'eloquent' ? Number(pager.total ?? 0) : filtered.length;
    const lastPage = !paginationEnabled ? 1 : mode === 'eloquent'
        ? Math.max(1, Number(pager.lastPage ?? pager.page ?? 1))
        : Math.max(1, Math.ceil(filtered.length / paginationSize));
    const page = mode === 'eloquent' ? (simplePagination
        ? Math.max(1, Number(pager.page ?? 1))
        : Math.min(Math.max(1, Number(pager.page ?? 1)), lastPage))
        : Math.min(localPage, lastPage);
    const shown = mode === 'eloquent' || !paginationEnabled
        ? filtered : filtered.slice((page - 1) * paginationSize, page * paginationSize);
    const hasNext = simplePagination ? pager.hasMorePages === true : page < lastPage;

    const groups = useMemo(() => {
        const result = new Map<string, TableRow[]>();
        for (const row of shown) {
            const label = groupKey === 'none' ? '' : String(row[groupKey] ?? 'Unknown');
            const existing = result.get(label) ?? [];
            result.set(label, [...existing, row]);
        }
        return [...result.entries()].sort((a, b) => groupDirection === 'desc' ? b[0].localeCompare(a[0]) : a[0].localeCompare(b[0]));
    }, [shown, groupKey, groupDirection]);

    const selectedOnPage = shown.filter(row => selected.includes(String(row.id)));
    const selectedAll = shown.length > 0 && selectedOnPage.length === shown.length;
    const first = shown.length ? (page - 1) * paginationSize + 1 : 0;
    const last = shown.length ? first + shown.length - 1 : 0;
    const pages = [...new Set([1, lastPage, page - 2, page - 1, page, page + 1, page + 2]
        .filter(n => n > 0 && n <= lastPage))].sort((a, b) => a - b);

    function navigate(changes: Record<string, string | null>): void {
        setBusy(true);
        router.get(tableStateUrl(node, changes), {}, {
            preserveScroll: true, preserveState: true, replace: true,
            onFinish: () => setBusy(false),
        });
    }
    function changeSort(column: TableColumn): void {
        if (!column.sortable) return;
        const nextDirection = sortKey === column.key && direction === 'asc' ? 'desc' : 'asc';
        setSortKey(column.key);
        setDirection(nextDirection);
        if (mode === 'eloquent') navigate({ sort: column.key, direction: nextDirection });
        else setLocalPage(1);
    }
    function selectFilter(key: string, value: string): void {
        setValues(previous => ({ ...previous, [key]: value }));
        if (mode === 'eloquent') navigate({ ['filter_' + key]: value || null });
        else setLocalPage(1);
    }
    function reset(): void {
        setSearch('');
        setValues({});
        setSortKey(null);
        setDirection('asc');
        setHidden(defaultHiddenColumns(columns));
        setVisibleOrder(columns.map(column => column.key));
        setGroupKey('none');
        setSelected([]);
        setLocalPage(1);
        if (reorderDemo) {
            setLocalRows(rows);
            setReorderEvents([]);
            setReordering(false);
        }
        if (mode === 'eloquent') {
            const parameters: Record<string, string | null> = {
                search: null, sort: null, direction: null, tab: null, page: null, perPage: null,
            };
            filters.forEach(filter => { parameters['filter_' + filter.key] = null; });
            navigate(parameters);
        }
    }
    function moveColumn(key: string, shift: number): void {
        setVisibleOrder(previous => {
            const visible = previous.filter(name => columns.some(column =>
                column.key === name && !column.hidden && !hidden.includes(name)));
            const from = visible.indexOf(key);
            const target = visible[from + shift];
            if (from < 0 || !target) return previous;
            const fromIndex = previous.indexOf(key);
            const toIndex = previous.indexOf(target);
            const next = [...previous];
            [next[fromIndex], next[toIndex]] = [next[toIndex], next[fromIndex]];
            return next;
        });
    }
    function moveDemoRow(id: string, offset: number) {
        if (!reorderDemo || !reordering) return;
        const from = localRows.findIndex(row => String(row.id) === id);
        const to = from + offset;
        if (from < 0 || to < 0 || to >= localRows.length) return;
        const next = [...localRows];
        [next[from], next[to]] = [next[to], next[from]];
        setLocalRows(next);
        setReorderEvents([
            'beforeReorder: ' + localRows.map(row => String(row.id)).slice(0, 8).join(', '),
            'afterReorder: ' + next.map(row => String(row.id)).slice(0, 8).join(', '),
            'Demo only — no database update',
        ]);
    }
    function toggleDemoReorder() {
        if (!reorderDemo) return;
        setReordering(old => !old);
        setSearch('');
        setValues({});
        setSortKey(null);
        setGroupKey('none');
        setLocalPage(1);
    }
    function toggleRow(id: string, checked: boolean): void {
        setSelected(previous => checked ? [...new Set([...previous, id])] : previous.filter(item => item !== id));
    }
    function togglePage(checked: boolean): void {
        const ids = shown.map(row => String(row.id));
        setSelected(previous => checked ? [...new Set([...previous, ...ids])] : previous.filter(item => !ids.includes(item)));
    }
    function setPage(pageNumber: number) {
        if (mode === 'eloquent') navigate({ page: String(pageNumber) });
        else setLocalPage(pageNumber);
    }
    const groupFields = Object.entries(groupOptions).filter(([key]) => columns.some(column => column.key === key));
    const sums = summaryColumns.filter(key => columns.some(column => column.key === key));
    const getColumn = (key: string) => columns.find(column => column.key === key);
    return <section aria-label={title} data-lavertia-table={prefix} aria-busy={busy} className="min-w-0 overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-950 dark:text-slate-100 shadow-sm">
        <header className="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 dark:border-slate-800 px-5 py-4">
            <div className="space-y-1"><h3 className="text-base font-semibold">{title}</h3>
                {typeof node.props.description === 'string' && <p className="max-w-3xl text-sm text-slate-500 dark:text-slate-400">{node.props.description}</p>}
            </div>
            <div className="flex flex-wrap items-center gap-2">
                {Number(node.props.pollIntervalMs ?? 0) > 0 && mode === 'eloquent' &&
                    <span className="rounded-md border border-emerald-500/20 bg-emerald-500/5 px-2 py-1 text-[11px] font-medium text-emerald-700 dark:text-emerald-300">Polling every {Math.round(Number(node.props.pollIntervalMs) / 1000)}s</span>}
                {reorderDemo && <button type="button" aria-pressed={reordering} onClick={toggleDemoReorder}
                    className="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-300 dark:border-slate-700 px-3 text-xs font-semibold hover:bg-slate-100 dark:hover:bg-slate-800">
                    <GripVertical className="size-4" />
                    {reordering ? 'Finish reordering' : String(node.props.demoReorderLabel ?? 'Enable reordering')}
                </button>}
                {recordActions && <span className="rounded-md border border-slate-200 dark:border-slate-800 px-2 py-1 text-[11px] font-medium text-slate-500 dark:text-slate-400">Read-only</span>}
            </div>
        </header>

        {Object.keys(tabs).length > 0 && <div role="tablist" aria-label={title + ' status'} className="flex flex-wrap items-center gap-1 border-b border-slate-200 dark:border-slate-800 px-4 py-2">
            {[['', 'All'], ...Object.entries(tabs)].map(([value, label]) =>
                <button key={value} role="tab" aria-selected={appliedTab === value}
                    type="button" className={tabButton} onClick={() => {
                        if (mode === 'eloquent') navigate({ tab: value || null });
                    }}>
                    {label}
                    <span className="rounded-md border border-slate-200 dark:border-slate-800 px-1.5 py-0.5 text-[11px] tabular-nums text-slate-500 dark:text-slate-400">
                        {value === '' ? Object.values(tabCounts).reduce((n, count) => n + Number(count), 0) : Number(tabCounts[value] ?? 0)}
                    </span>
                </button>)}
        </div>}

        {(groupFields.length > 0 || searchable || filters.length > 0 || columnManager) && <div className="flex flex-wrap items-center gap-2 border-b border-slate-200 dark:border-slate-800 px-4 py-3">
            {groupFields.length > 0 && <>
                <Layers3 className="size-4 text-slate-500 dark:text-slate-400" aria-hidden="true" />
                <span className="text-xs text-slate-500 dark:text-slate-400">Group by</span>
                <TableSelect label="Group by" value={groupKey} size="sm" className="w-40"
                    options={[{ value: 'none', label: 'None' }, ...groupFields.map(([value, label]) => ({ value, label }))]}
                    onValueChange={value => { setGroupKey(value); setCollapsed([]); }} />
                {groupKey !== 'none' && <TableSelect label="Group direction" value={groupDirection} size="sm" className="w-32"
                    options={[{ value: 'asc', label: 'Ascending' }, { value: 'desc', label: 'Descending' }]}
                    onValueChange={v => setGroupDirection(v as SortDirection)} />}
            </>}
            <div className="flex w-full min-w-0 flex-wrap items-center justify-end gap-2 sm:w-auto sm:flex-1">
            {searchable && <form className="flex min-w-0 flex-1 items-center gap-1 sm:flex-none" onSubmit={(event: FormEvent) => {
                event.preventDefault();
                if (mode === 'eloquent') navigate({ search: search.trim() || null });
                else setLocalPage(1);
            }}>
                <div className="relative min-w-0 flex-1 sm:w-56 sm:flex-none">
                    <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-slate-500 dark:text-slate-400" aria-hidden="true" />
                    <input type="search" aria-label={tx('search')} placeholder={tx('search')} value={search}
                        onChange={event => setSearch(event.target.value)}
                        className="h-9 w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 pe-3 ps-9 text-sm outline-none focus-visible:ring-2 focus-visible:ring-amber-500" />
                </div>
                <button className={iconButton} type="submit" aria-label="Search table"><Search className="size-4" /></button>
            </form>}
            {filters.length > 0 && <button type="button" aria-label="Filters" aria-expanded={filtersOpen} onClick={() => setFiltersOpen(v => !v)}
                className={cn(iconButton, filtersOpen && 'bg-slate-100 dark:bg-slate-800 text-slate-950 dark:text-slate-100')}>
                <Filter className="size-4" />
                {activeCount > 0 && <span className="absolute -end-1 -top-1 rounded-full border border-background bg-amber-500 px-1 text-[10px] text-slate-950">{activeCount}</span>}
            </button>}
            {columnManager && <DropdownMenu>
                <DropdownMenuTrigger asChild><button className={iconButton} type="button" aria-label="Manage columns"><Columns3 className="size-4" /></button></DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-64">
                    <DropdownMenuLabel>Columns</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    {managerColumns.map(column =>
                        <div key={column.key} className="flex items-center gap-1">
                            {column.toggleable ? <DropdownMenuCheckboxItem className="min-w-0 flex-1" checked={!hidden.includes(column.key)}
                                disabled={!column.toggleable || (!hidden.includes(column.key) && ordered.length <= 1)}
                                onCheckedChange={checked => setHidden(previous => checked
                                    ? previous.filter(key => key !== column.key)
                                    : [...new Set([...previous, column.key])])}>
                                {column.label}
                            </DropdownMenuCheckboxItem> : <span className="min-w-0 flex-1 truncate px-2 text-sm" title="Fixed column">{column.label}</span>}
                            {node.props.reorderableColumns === true && <div className="flex gap-0.5 pe-1">
                                <button type="button" aria-label={'Move ' + column.label + ' up'}
                                    disabled={hidden.includes(column.key) || movableKeys.indexOf(column.key) <= 0}
                                    className={iconButton} onClick={() => moveColumn(column.key, -1)}>
                                    <ArrowUp className="size-3.5" />
                                </button>
                                <button type="button" aria-label={'Move ' + column.label + ' down'}
                                    disabled={hidden.includes(column.key) || movableKeys.indexOf(column.key) === -1
                                        || movableKeys.indexOf(column.key) === movableKeys.length - 1}
                                    className={iconButton} onClick={() => moveColumn(column.key, 1)}>
                                    <ArrowDown className="size-3.5" />
                                </button>
                            </div>}
                        </div>)}
                    <DropdownMenuSeparator />
                    <button className="w-full rounded px-3 py-2 text-start text-xs text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" onClick={() => {
                        setVisibleOrder(columns.map(column => column.key));
                        setHidden(defaultHiddenColumns(columns));
                    }}>Reset columns</button>
                </DropdownMenuContent>
            </DropdownMenu>}
            </div>
        </div>}

        {filtersOpen && <div className="border-b border-slate-200 dark:border-slate-800 bg-slate-100/40 dark:bg-slate-800/20 px-5 py-4">
            <div className="mb-3 flex items-center justify-between gap-2"><h4 className="text-sm font-semibold">Filters</h4>
                <button type="button" className="inline-flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white" onClick={reset}>
                    <RotateCcw className="size-3.5" /> Reset
                </button></div>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {filters.map(filter => <label key={filter.key} className="min-w-0 space-y-1.5 text-sm">
                    <span className="block font-medium">{filter.label}</span>
                    <SearchableSelect label={filter.label} value={values[filter.key] ?? ''}
                        options={[{ value: '', label: 'All' }, ...Object.entries(filter.options).map(([value, label]) => ({ value, label }))]}
                        onValueChange={value => selectFilter(filter.key, value)} />
                </label>)}
            </div>
        </div>}

        {selectable && selectedOnPage.length > 0 && <div className="flex flex-wrap items-center gap-3 border-b border-slate-200 dark:border-slate-800 bg-amber-500/5 px-5 py-2.5 text-sm">
            <strong>{selectedOnPage.length} selected on this page</strong>
            <button type="button" className="inline-flex items-center gap-1 text-amber-600 dark:text-amber-400 hover:underline"
                onClick={() => exportCurrentRows(selectedOnPage, ordered, prefix)}><Download className="size-4" />Export selected CSV</button>
            <button type="button" className="text-slate-500 dark:text-slate-400 hover:underline" onClick={() => setSelected([])}>Clear</button>
        </div>}

        <div className="overflow-x-auto focus-visible:outline-2 focus-visible:outline-amber-500" tabIndex={0} role="region" aria-label={title + " table, scroll to see more columns"}>
            <table className="w-full min-w-[640px] text-sm">
                <thead className="border-b border-slate-200 dark:border-slate-800 bg-slate-100/60 dark:bg-slate-800/35">
                    <tr>{selectable && <th scope="col" className="w-10 px-4 py-3 text-start">
                        <Checkbox aria-label="Select all rows on this page" checked={selectedAll ? true : selectedOnPage.length > 0 ? 'indeterminate' : false}
                            onCheckedChange={checked => togglePage(checked === true)} />
                    </th>}
                        {ordered.map(column => <th key={column.key} scope="col" aria-sort={column.sortable
                            ? sortKey === column.key ? direction === 'asc' ? 'ascending' : 'descending' : 'none' : undefined}
                            className="whitespace-nowrap px-3 py-3 text-start font-semibold">
                            {column.sortable ? <button type="button" className="inline-flex items-center gap-1.5 focus-visible:outline-2 focus-visible:outline-amber-500"
                                onClick={() => changeSort(column)}>{column.label}
                                {sortKey === column.key ? direction === 'asc' ? <ArrowUp className="size-3.5 text-amber-600 dark:text-amber-400" /> : <ArrowDown className="size-3.5 text-amber-600 dark:text-amber-400" /> : <ArrowDown className="size-3.5 text-slate-500 dark:text-slate-400/60" />}
                            </button> : column.label}
                        </th>)}
                        {showActionsColumn && <th scope="col" className="w-12 px-2 py-3 text-end font-semibold">
                            {recordActions ? 'Actions' : 'Reorder'}
                        </th>}
                    </tr>
                </thead>
                <tbody>
                    {groups.map(([label, groupRows]) => <Fragment key={label}>
                        {groupKey !== 'none' && <tr key={'group-' + label} className="border-b border-slate-200 dark:border-slate-800 bg-slate-100/40 dark:bg-slate-800/25">
                            <td colSpan={tableColSpan} className="px-5 py-2">
                                <button type="button" aria-expanded={!collapsed.includes(label)} onClick={() => setCollapsed(old =>
                                    old.includes(label) ? old.filter(x => x !== label) : [...old, label])}
                                    className="flex w-full items-center gap-2 text-start text-sm font-semibold">
                                    <span>{collapsed.includes(label) ? '▸' : '▾'}</span>{label} <span className="text-xs font-normal text-slate-500 dark:text-slate-400">({groupRows.length} this page)</span>
                                </button></td>
                        </tr>}
                        {!collapsed.includes(label) && groupRows.map((row, index) =>
                            <tr key={String(row.id ?? index)}
                                tabIndex={typeof row.__recordUrl === 'string' ? 0 : undefined}
                                role={typeof row.__recordUrl === 'string' ? 'link' : undefined}
                                aria-label={typeof row.__recordUrl === 'string' ? 'Open ' + String(row.title ?? row.name ?? row.id) : undefined}
                                onClick={event => {
                                    if (typeof row.__recordUrl !== 'string') return;
                                    if ((event.target as Element).closest('button,input,a,[role="checkbox"]')) return;
                                    const href = safeHref(row.__recordUrl);
                                    if (href?.startsWith('/')) router.get(href);
                                }}
                                onKeyDown={event => {
                                    if (typeof row.__recordUrl !== 'string' || (event.key !== 'Enter' && event.key !== ' ')) return;
                                    event.preventDefault();
                                    const href = safeHref(row.__recordUrl);
                                    if (href?.startsWith('/')) router.get(href);
                                }}
                                className={cn(
                                    'border-b border-slate-200/70 dark:border-slate-800/70 transition-colors hover:bg-slate-100/50 dark:bg-slate-800/30',
                                    striped && index % 2 === 1 && 'bg-slate-100/60 dark:bg-slate-800/45',
                                    rowTones && toneClass(row[rowTones.field ?? 'status'], rowTones),
                                    typeof row.__recordUrl === 'string' && 'cursor-pointer focus-visible:outline-2 focus-visible:outline-amber-500',
                                )}>
                                {selectable && <td className="px-4 py-3"><Checkbox aria-label={'Select record ' + String(row.id)}
                                    checked={selected.includes(String(row.id))} onCheckedChange={checked => toggleRow(String(row.id), checked === true)} /></td>}
                                {ordered.map(col => <td key={col.key} className="max-w-72 whitespace-nowrap px-3 py-3 text-start text-sm">
                                    {renderCell(row, col)}
                                </td>)}
                                {showActionsColumn && <td className="px-2 py-2 text-end">
                                    <div className="flex items-center justify-end gap-0.5">
                                    {reordering && <div className="flex gap-0.5">
                                        <button type="button" className={iconButton} aria-label={'Move record ' + String(row.id) + ' up'}
                                            onClick={() => moveDemoRow(String(row.id), -1)}><ArrowUp className="size-4" /></button>
                                        <button type="button" className={iconButton} aria-label={'Move record ' + String(row.id) + ' down'}
                                            onClick={() => moveDemoRow(String(row.id), 1)}><ArrowDown className="size-4" /></button>
                                    </div>}
                                    {recordActions && <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <button type="button" aria-label={'Actions for record ' + String(row.id)} className={iconButton}>
                                                <MoreHorizontal className="size-4" />
                                            </button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem onSelect={() => setDetailRow(row)}>View details</DropdownMenuItem>
                                            <DropdownMenuItem onSelect={() => exportCurrentRows([row], ordered, prefix)}>Export row CSV</DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>}
                                    </div>
                                </td>}
                            </tr>)}
                    </Fragment>)}
                    {shown.length === 0 && <tr><td colSpan={tableColSpan} className="px-5 py-12 text-center text-slate-500 dark:text-slate-400">
                        {tx('noResults')} {mode === 'eloquent' ? 'Try another filter or seed the demo tables.' : ''}
                    </td></tr>}
                </tbody>
                {sums.length > 0 && shown.length > 0 && <tfoot className="border-t border-slate-200 dark:border-slate-800 bg-slate-100/40 dark:bg-slate-800/20 text-sm">
                    <tr><th colSpan={tableColSpan} className="px-5 py-2 text-start">Summary · this page</th></tr>
                    <tr><td colSpan={tableColSpan} className="px-5 pb-2">
                        <div className="flex flex-wrap gap-5">
                            {sums.map(key => <span key={key} className="inline-flex gap-2"><span className="text-slate-500 dark:text-slate-400">{getColumn(key)?.label ?? key}</span>
                                <strong className="tabular-nums">{currency(shown.reduce((total, row) => total + Number(row[key] ?? 0), 0), getColumn(key)?.currency)}</strong></span>)}
                        </div>
                    </td></tr>
                    <tr><th colSpan={tableColSpan} className="px-5 py-2 text-start">All matching records</th></tr>
                    <tr><td colSpan={tableColSpan} className="px-5 pb-3">
                        <div className="flex flex-wrap gap-5">
                            {sums.map(key => <span key={key} className="inline-flex gap-2"><span className="text-slate-500 dark:text-slate-400">{getColumn(key)?.label ?? key}</span>
                                <strong className="tabular-nums">{currency(summaryTotals[key] ?? 0, getColumn(key)?.currency)}</strong></span>)}
                        </div></td></tr>
                </tfoot>}
            </table>
        </div>
        {(paginationControls || (mode === 'snapshot' && paginationEnabled && filtered.length > paginationSize)) && <footer className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 dark:border-slate-800 px-4 py-3 text-xs">
            <p aria-live="polite" className="text-slate-500 dark:text-slate-400">
                {totalKnown ? 'Showing ' + first + ' to ' + last + ' of ' + totalRows.toLocaleString('en-US') + ' results'
                    : 'Showing ' + first + ' to ' + last + ' · total not calculated'}
                {pager.truncated === true && ' · limited to 100 records'}
            </p>
            {paginationEnabled && <>
                <div className="flex items-center gap-2 text-slate-500 dark:text-slate-400">
                    <span>Per page</span>
                    <TableSelect label="Rows per page" size="sm" className="w-20"
                        value={String(paginationSize)}
                        options={allowedOptions.map(size => ({ value: String(size), label: String(size) }))}
                        onValueChange={value => {
                            if (mode === 'eloquent') navigate({ perPage: value, page: null });
                            else { setLocalPerPage(Number(value)); setLocalPage(1); }
                        }} />
                </div>
                <nav aria-label="Table pagination" className="flex items-center overflow-hidden rounded-lg border border-slate-200 dark:border-slate-800">
                    {!simplePagination && extremeLinks &&
                        <button aria-label="First page" type="button" disabled={page <= 1 || busy} className={iconButton}
                            onClick={() => setPage(1)}><ChevronsLeft className="size-4 rtl:rotate-180" /></button>}
                    <button aria-label="Previous page" type="button" disabled={page <= 1 || busy} className={iconButton}
                        onClick={() => setPage(page - 1)}><ChevronLeft className="size-4 rtl:rotate-180" /></button>
                    {!simplePagination && pages.map((pageNumber, index) => <span key={pageNumber} className="inline-flex items-center">
                        {index > 0 && pageNumber > pages[index - 1] + 1 && <span className="px-2 text-slate-500 dark:text-slate-400">…</span>}
                        <button type="button" aria-label={'Page ' + pageNumber} aria-current={page === pageNumber ? 'page' : undefined}
                            className={cn('size-9 text-sm hover:bg-slate-100 dark:hover:bg-slate-800', page === pageNumber && 'bg-slate-100 dark:bg-slate-800 font-semibold text-amber-600 dark:text-amber-400')}
                            onClick={() => setPage(pageNumber)}>{pageNumber}</button>
                    </span>)}
                    <button aria-label="Next page" type="button" disabled={!hasNext || busy} className={iconButton}
                        onClick={() => setPage(page + 1)}><ChevronRight className="size-4 rtl:rotate-180" /></button>
                    {!simplePagination && extremeLinks &&
                        <button aria-label="Last page" type="button" disabled={page >= lastPage || busy} className={iconButton}
                            onClick={() => setPage(lastPage)}><ChevronsRight className="size-4 rtl:rotate-180" /></button>}
                </nav>
            </>}
        </footer>}
        {reorderDemo && reorderEvents.length > 0 && <div role="status" className="border-t border-slate-200 dark:border-slate-800 bg-slate-100/40 dark:bg-slate-800/20 px-5 py-3 text-xs">
            <p className="mb-2 font-semibold">Reordering event log (local only)</p>
            {reorderEvents.map((event, index) => <p key={index} className="font-mono text-slate-500 dark:text-slate-400">{event}</p>)}
        </div>}
        {recordActions && <Dialog open={detailRow !== null} onOpenChange={open => { if (!open) setDetailRow(null); }}>
            <DialogContent className="max-w-xl">
                <DialogHeader>
                    <DialogTitle>{title} · Record details</DialogTitle>
                    <DialogDescription>Read-only data from the active page. No changes are saved.</DialogDescription>
                </DialogHeader>
                <dl className="max-h-[60vh] space-y-2 overflow-y-auto text-sm">
                    {detailRow && columns.filter(column => !column.hidden).map(column => <div key={column.key} className="grid grid-cols-[minmax(0,1fr)_minmax(0,2fr)] gap-3 border-b border-slate-200/50 dark:border-slate-800/50 py-1.5">
                        <dt className="font-medium text-slate-500 dark:text-slate-400">{column.label}</dt>
                        <dd className="min-w-0 break-words">{renderCell(detailRow, column)}</dd>
                    </div>)}
                </dl>
            </DialogContent>
        </Dialog>}
    </section>;
}
