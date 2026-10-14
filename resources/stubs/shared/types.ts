export type Scalar = string | number | boolean | null;

export interface ComponentNode {
    type: string;
    key: string;
    props: Record<string, unknown>;
    components: ComponentNode[];
}

export interface LavertiaPayload {
    version: number;
    title?: string;
    tree: ComponentNode;
}

export type TableRow = Record<string, Scalar>;
export type Tone = 'success' | 'warning' | 'danger' | 'info' | 'neutral';

export interface TableColumn {
    key: string;
    label: string;
    sortable: boolean;
    badge: boolean;
    badgeTones: Record<string, Tone>;
}

const badgeClasses: Record<Tone, string> = {
    success: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/20',
    warning: 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/20',
    danger: 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-400/20',
    info: 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-400/20',
    neutral: 'bg-gray-100 text-gray-700 ring-gray-500/20 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-500/30',
};

const statAccentClasses: Record<string, string> = {
    primary: 'bg-amber-400 ring-amber-500/20',
    success: 'bg-emerald-400 ring-emerald-500/20',
    warning: 'bg-orange-400 ring-orange-500/20',
    neutral: 'bg-gray-400 ring-gray-500/20',
};

export function badgeClass(tone: unknown): string {
    const value = typeof tone === 'string' && tone in badgeClasses ? tone as Tone : 'neutral';
    return badgeClasses[value];
}

export function statAccentClass(tone: unknown): string {
    return typeof tone === 'string' && tone in statAccentClasses
        ? statAccentClasses[tone]
        : statAccentClasses.neutral;
}

export function tableColumns(node: ComponentNode): TableColumn[] {
    return node.components
        .filter((component) => component.type === 'text-column')
        .map((component) => ({
            key: String(component.props.name ?? ''),
            label: String(component.props.label ?? component.props.name ?? ''),
            sortable: component.props.sortable === true,
            badge: component.props.badge === true,
            badgeTones: isRecord(component.props.badgeTones)
                ? component.props.badgeTones as Record<string, Tone>
                : {},
        }));
}

export function tableRows(node: ComponentNode): TableRow[] {
    return Array.isArray(node.props.rows) ? node.props.rows as TableRow[] : [];
}

export function visibleTableRows(
    rows: TableRow[],
    columns: TableColumn[],
    query: string,
    sortKey: string | null,
    direction: 'asc' | 'desc',
): TableRow[] {
    const term = query.trim().toLocaleLowerCase();
    const filtered = term === ''
        ? [...rows]
        : rows.filter((row) =>
            columns.some((column) => String(row[column.key] ?? '').toLocaleLowerCase().includes(term)),
        );

    if (sortKey && columns.some((column) => column.key === sortKey && column.sortable)) {
        const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });

        filtered.sort((a, b) => {
            const comparison = collator.compare(String(a[sortKey] ?? ''), String(b[sortKey] ?? ''));
            return direction === 'asc' ? comparison : -comparison;
        });
    }

    return filtered;
}

export function columnBadgeTone(column: TableColumn, value: Scalar): Tone {
    const candidate = column.badgeTones[String(value ?? '')];
    return candidate === 'success' || candidate === 'warning' || candidate === 'danger'
        || candidate === 'info' || candidate === 'neutral'
        ? candidate
        : 'neutral';
}

export function safeHref(value: unknown): string | null {
    if (typeof value !== 'string') {
        return null;
    }

    if (value.startsWith('/') && !value.startsWith('//') && !value.includes('\\')) {
        return value;
    }

    try {
        const url = new URL(value);
        if (url.protocol === 'https:' && !url.username && !url.password && !/\s|\\/.test(value)) {
            return value;
        }
    } catch {
        return null;
    }

    return null;
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}
