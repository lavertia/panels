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
    locale?: string;
    direction?: 'ltr' | 'rtl';
}

export type TableRow = Record<string, Scalar>;
export type Tone = 'success' | 'warning' | 'danger' | 'info' | 'neutral';

export interface TableColumn {
    key: string;
    label: string;
    sortable: boolean;
    badge: boolean;
    badgeTones: Record<string, Tone>;
    searchable: boolean;
    toggleable: boolean;
    hiddenByDefault: boolean;
    hidden: boolean;
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
            searchable: component.props.searchable === true,
            toggleable: component.props.toggleable === true,
            hiddenByDefault: component.props.hiddenByDefault === true,
            hidden: component.props.hidden === true,
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
    filters: Record<string, string> = {},
): TableRow[] {
    const term = query.trim().toLocaleLowerCase();
    const filtered = rows.filter((row) => {
        const matched = term === '' || columns.some((column) =>
            String(row[column.key] ?? '').toLocaleLowerCase().includes(term),
        );
        const filtered = Object.entries(filters).every(([field, value]) =>
            value === '' || String(row[field] ?? '') === value,
        );
        return matched && filtered;
    });

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


export interface TableFilter {
    key: string;
    label: string;
    options: Record<string, string>;
}

export function tableFilters(node: ComponentNode): TableFilter[] {
    return node.components
        .filter((component) => component.type === 'select-filter')
        .map((component) => ({
            key: String(component.props.name ?? ''),
            label: String(component.props.label ?? component.props.name ?? ''),
            options: isRecord(component.props.options) ? component.props.options as Record<string, string> : {},
        }));
}

export function tableColumnsInOrder(columns: TableColumn[], order: string[], hidden: string[]): TableColumn[] {
    const rank = new Map(order.map((key, index) => [key, index]));
    return columns
        .filter((column) => !column.hidden && !hidden.includes(column.key))
        .sort((a, b) => (rank.get(a.key) ?? columns.indexOf(a)) - (rank.get(b.key) ?? columns.indexOf(b)));
}

export function defaultHiddenColumns(columns: TableColumn[]): string[] {
    return columns.filter((column) => column.hiddenByDefault || column.hidden).map((column) => column.key);
}

export function tableStateUrl(node: ComponentNode, changes: Record<string, string | null>): string {
    const prefix = String(node.props.statePrefix ?? '');
    const url = new URL(window.location.href);

    for (const [key, value] of Object.entries(changes)) {
        const param = prefix + key;

        if (value === null || value === '') {
            url.searchParams.delete(param);
        } else {
            url.searchParams.set(param, value);
        }
    }

    if (!Object.hasOwn(changes, 'page')) {
        url.searchParams.delete(prefix + 'page');
    }

    return url.pathname + url.search + url.hash;
}

export type Locale = 'en' | 'ar' | 'fr';
const translations = {
    en: {
        search: 'Search records...', filters: 'Filters', columns: 'Columns', reset: 'Reset',
        previous: 'Previous', next: 'Next', page: 'Page', of: 'of',
        noResults: 'No matching records', trySearch: 'Try another search term.',
        empty: 'No records have been added yet.', clear: 'Clear search',
        show: 'Showing', records: 'records', moveLeft: 'Move column left',
        moveRight: 'Move column right', preview: 'Local preview',
        submit: 'Save changes', draft: 'Form preview only — no save endpoint',
    },
    ar: {
        search: 'ابحث في السجلات...', filters: 'الفلاتر', columns: 'الأعمدة', reset: 'إعادة ضبط',
        previous: 'السابق', next: 'التالي', page: 'صفحة', of: 'من',
        noResults: 'لا توجد نتائج مطابقة', trySearch: 'جرّب عبارة بحث أخرى.',
        empty: 'لا توجد سجلات بعد.', clear: 'مسح البحث',
        show: 'عرض', records: 'سجلات', moveLeft: 'حرّك العمود للخلف',
        moveRight: 'حرّك العمود للأمام', preview: 'معاينة محلية',
        submit: 'حفظ التغييرات', draft: 'معاينة النموذج فقط — الحفظ غير مفعّل',
    },
    fr: {
        search: 'Rechercher...', filters: 'Filtres', columns: 'Colonnes', reset: 'Réinitialiser',
        previous: 'Précédent', next: 'Suivant', page: 'Page', of: 'sur',
        noResults: 'Aucun résultat', trySearch: 'Essayez une autre recherche.',
        empty: 'Aucun enregistrement.', clear: 'Effacer la recherche',
        show: 'Affichage', records: 'enregistrements', moveLeft: 'Déplacer à gauche',
        moveRight: 'Déplacer à droite', preview: 'Aperçu local',
        submit: 'Enregistrer', draft: 'Aperçu du formulaire — aucun enregistrement',
    },
} as const;

export type TranslationKey = keyof typeof translations.en;
export function translate(locale: unknown, key: TranslationKey): string {
    const selected: Locale = locale === 'ar' || locale === 'fr' ? locale : 'en';
    return translations[selected][key];
}
