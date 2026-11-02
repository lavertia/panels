export type Scalar = string | number | boolean | null;

export interface ComponentNode {
    type: string;
    key: string;
    props: Record<string, unknown>;
    components: ComponentNode[];
}

export interface NavigationLink {
    label: string;
    slug: string;
    group: string | null;
    url: string;
    active: boolean;
    icon?: string | null;
    badge?: string | null;
    parentSlug?: string | null;
    descendantActive?: boolean;
}

export interface PanelMenuLink {
    label: string;
    url: string;
    external: boolean;
}

export interface NavigationGroupConfig {
    label: string;
    collapsible: boolean;
    collapsedByDefault: boolean;
    sort: number;
}

export interface NavigationGroupView extends NavigationGroupConfig {
    items: NavigationLink[];
    active: boolean;
}

export function panelNavigationGroups(
    links: NavigationLink[],
    config: NavigationGroupConfig[] = [],
): NavigationGroupView[] {
    const groups: NavigationGroupView[] = [];
    for (const link of links) {
        const label = link.group || 'Lavertia';
        let group = groups.find(item => item.label === label);
        if (!group) {
            const options = config.find(item => item.label === label);
            group = {
                label,
                collapsible: options?.collapsible ?? false,
                collapsedByDefault: options?.collapsedByDefault ?? false,
                sort: options?.sort ?? 0,
                active: false,
                items: [],
            };
            groups.push(group);
        }
        group.items.push(link);
        group.active ||= link.active || link.descendantActive === true;
    }
    return groups;
}

export interface HeaderLink {
    label: string;
    url: string | null;
    active: boolean;
}

export interface ThemeManifest {
    id: string;
    tokens: Record<string, string>;
}

const THEME_TOKENS = new Set([
    'background', 'foreground', 'card', 'card-foreground', 'primary', 'primary-foreground',
    'secondary', 'secondary-foreground', 'accent', 'accent-foreground', 'muted',
    'muted-foreground', 'border', 'input', 'ring', 'sidebar', 'sidebar-foreground',
    'sidebar-primary', 'sidebar-primary-foreground', 'sidebar-accent',
    'sidebar-accent-foreground', 'sidebar-border', 'sidebar-ring',
]);

/** A theme package controls presentation only, never routes or permissions. */
export function themeVariables(panel: PanelAppearance | null | undefined): Record<string, string> {
    return Object.fromEntries(
        Object.entries(panel?.theme?.tokens ?? [])
            .filter(([name, color]) => THEME_TOKENS.has(name) && typeof color === 'string'
                && /^#[0-9a-f]{6}$/i.test(color))
            .map(([name, color]) => [`--${name}`, color]),
    );
}

export function shellNavigationSlots(panel: PanelAppearance | null | undefined, position: string): ComponentNode[] {
    return (panel?.shellSlots ?? []).filter(node => node.type === 'navigation-slot'
        && node.props.position === position);
}

export interface PanelAppearance {
    id: string;
    brandName: string;
    brandSubtitle: string;
    brandLogo: string | null;
    brandLogoDisplay: 'full' | 'icon' | 'hidden';
    brandLogoPlacement: 'sidebar' | 'topbar' | 'both';
    tenantSwitcher: boolean;
    tenantSwitcherPlacement: 'sidebar' | 'topbar';
    globalSearch: boolean;
    notifications: boolean;
    footerLinks: PanelMenuLink[];
    userMenuLinks: PanelMenuLink[];
    userMenuSettings: boolean;
    userMenuLogout: boolean;
    primaryColor: string;
    primaryForeground: string;
    sidebarCollapsible: boolean;
    sidebarVariant: 'inset' | 'sidebar' | 'floating';
    themeMode: 'light' | 'dark' | 'system';
    fontFamily: string;
    borderRadius: 'none' | 'sm' | 'md' | 'lg' | 'xl';
    contentWidth: 'sm' | 'md' | 'lg' | 'xl' | '2xl' | 'full';
    sidebarWidth: string;
    navigationPosition: 'sidebar' | 'top' | 'none';
    layout?: 'sidebar' | 'top' | 'full' | 'workspace';
    locale?: string;
    direction?: 'ltr' | 'rtl';
    supportedLocales?: string[];
    languageSwitcher?: boolean;
    navigationGroups?: NavigationGroupConfig[];
    shellSlots?: ComponentNode[];
    theme?: ThemeManifest;
    topbar: boolean;
    breadcrumbs: boolean;
    stickyHeader: boolean;
    mobileNavigation: boolean;
}

export type PagePresentation = {
    variant: 'default' | 'card' | 'flat' | 'split';
    maxWidth: 'sm' | 'md' | 'lg' | 'xl' | '2xl' | 'full';
    padding?: 'compact' | 'normal' | 'spacious';
    ratio?: 'balanced' | 'start-wide' | 'end-wide';
};

export interface PageSeo {
    title: string;
    description: string | null;
    robots: 'index,follow' | 'noindex,follow' | 'noindex,nofollow';
    canonical: string | null;
}

export interface LavertiaPayload {
    navigation?: NavigationLink[];
    panel?: PanelAppearance | null;
    version: number;
    title?: string;
    tree: ComponentNode;
    locale?: string;
    direction?: 'ltr' | 'rtl';
    presentation?: PagePresentation;
    regions?: Record<string, ComponentNode>;
    seo?: PageSeo;
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

/** Validate protocol header links again before rendering in a host adapter. */
export function headerLinks(value: unknown): HeaderLink[] {
    if (!Array.isArray(value)) return [];

    return value.flatMap((item): HeaderLink[] => {
        if (!item || typeof item !== 'object' || typeof item.label !== 'string') return [];
        const url = item.url === null ? null : safeHref(item.url);
        if (url !== null && !url.startsWith('/')) return [];
        if (item.url !== null && url === null) return [];
        return [{ label: item.label, url, active: item.active === true }];
    });
}

/** Preserve the current Inertia URL while changing only the requested language. */
export function localeUrl(currentUrl: string, locale: string): string {
    const [withoutHash, hash] = currentUrl.split('#', 2);
    const [pathname, query] = withoutHash.split('?', 2);
    const params = new URLSearchParams(query ?? '');
    params.set('lang', locale);

    return (pathname.startsWith('/') ? pathname : '/') + '?' + params.toString() + (hash ? '#' + hash : '');
}

/** Localized display name; no hard-coded list of 120+ language names is needed. */
export function localeLabel(code: string, activeLocale: string): string {
    try {
        return new Intl.DisplayNames([activeLocale], { type: 'language' }).of(code) ?? code;
    } catch {
        return code;
    }
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
            options: (isRecord(component.props.options) || Array.isArray(component.props.options))
                ? Object.fromEntries(Object.entries(component.props.options).filter(([, value]) => typeof value === 'string')) as Record<string, string>
                : {},
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
        submit: 'Save changes', saving: 'Saving...', saved: 'Changes saved.', draft: 'Form preview only — no save endpoint',
        addItem: 'Add item', removeItem: 'Remove item', moveUp: 'Move item up', moveDown: 'Move item down',
        collapse: 'Collapse item', expand: 'Expand item', itemLabel: 'Item',
    },
    ar: {
        search: 'ابحث في السجلات...', filters: 'الفلاتر', columns: 'الأعمدة', reset: 'إعادة ضبط',
        previous: 'السابق', next: 'التالي', page: 'صفحة', of: 'من',
        noResults: 'لا توجد نتائج مطابقة', trySearch: 'جرّب عبارة بحث أخرى.',
        empty: 'لا توجد سجلات بعد.', clear: 'مسح البحث',
        show: 'عرض', records: 'سجلات', moveLeft: 'حرّك العمود للخلف',
        moveRight: 'حرّك العمود للأمام', preview: 'معاينة محلية',
        submit: 'حفظ التغييرات', saving: 'جارٍ الحفظ...', saved: 'تم حفظ التغييرات.', draft: 'معاينة النموذج فقط — الحفظ غير مفعّل',
        addItem: 'إضافة عنصر', removeItem: 'حذف العنصر', moveUp: 'تحريك للأعلى', moveDown: 'تحريك للأسفل',
        collapse: 'طي العنصر', expand: 'توسيع العنصر', itemLabel: 'عنصر',
    },
    fr: {
        search: 'Rechercher...', filters: 'Filtres', columns: 'Colonnes', reset: 'Réinitialiser',
        previous: 'Précédent', next: 'Suivant', page: 'Page', of: 'sur',
        noResults: 'Aucun résultat', trySearch: 'Essayez une autre recherche.',
        empty: 'Aucun enregistrement.', clear: 'Effacer la recherche',
        show: 'Affichage', records: 'enregistrements', moveLeft: 'Déplacer à gauche',
        moveRight: 'Déplacer à droite', preview: 'Aperçu local',
        submit: 'Enregistrer', saving: 'Enregistrement...', saved: 'Modifications enregistrées.', draft: 'Aperçu du formulaire — aucun enregistrement',
        addItem: 'Ajouter un élément', removeItem: 'Supprimer l’élément', moveUp: 'Monter', moveDown: 'Descendre',
        collapse: 'Réduire l’élément', expand: 'Développer l’élément', itemLabel: 'Élément',
    },
} as const;

export type TranslationKey = keyof typeof translations.en;
export function translate(locale: unknown, key: TranslationKey): string {
    const selected: Locale = locale === 'ar' || locale === 'fr' ? locale : 'en';
    return translations[selected][key];
}
