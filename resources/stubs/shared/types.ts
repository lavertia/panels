export type Scalar = string | number | boolean | null;

export interface ComponentNode {
    type: string;
    key: string;
    props: Record<string, unknown>;
    components: ComponentNode[];
}

export type TableRow = Record<string, Scalar>;

export interface LavertiaPayload {
    version: number;
    title?: string;
    tree: ComponentNode;
}

export interface TableColumn {
    key: string;
    label: string;
}

export function tableColumns(node: ComponentNode): TableColumn[] {
    return node.components
        .filter((component) => component.type === 'text-column')
        .map((component) => ({
            key: String(component.props.name ?? ''),
            label: String(component.props.label ?? component.props.name ?? ''),
        }));
}

export function tableRows(node: ComponentNode): TableRow[] {
    return Array.isArray(node.props.rows) ? node.props.rows as TableRow[] : [];
}

export function safeHref(value: unknown): string | null {
    if (typeof value !== 'string') {
        return null;
    }

    if (value.startsWith('/') && !value.startsWith('//') && !value.includes('\\')) {
        return value;
    }

    if (/^https:\/\/[^/]+/i.test(value) && !/[\s\\]/.test(value)) {
        return value;
    }

    return null;
}
