/**
 * Frontend-neutral Lavertia form primitives.
 * No HTTP requests are made here. Laravel validates and persists the payload.
 */
import type { ComponentNode } from './types';

export type FormValue = string | File | FormValue[] | { [key: string]: FormValue };
export type FormPayload = Record<string, FormValue>;

export interface LocalRepeaterRow {
    key: string;
    values: Record<string, unknown>;
}

/** Unsaved local edits take priority over incoming server props. */
export function reconcileField<T>(
    current: T,
    incoming: T,
    dirty: boolean,
): { value: T; dirty: boolean } {
    return dirty && !Object.is(current, incoming)
        ? { value: current, dirty: true }
        : { value: incoming, dirty: false };
}

function record(value: unknown): Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value)
        ? value as Record<string, unknown>
        : {};
}

function repeaterBounds(node: ComponentNode): { min: number; max: number; initial: number } {
    const integer = (value: unknown, fallback: number): number =>
        typeof value === 'number' && Number.isSafeInteger(value) ? value : fallback;
    const max = Math.min(500, Math.max(1, integer(node.props.maxItems, 100)));
    const min = Math.min(max, Math.max(0, integer(node.props.minItems, 0)));
    const initial = Math.max(min, Math.min(max, integer(node.props.defaultItems, 1)));
    return { min, max, initial };
}

export function initialRepeaterRows(node: ComponentNode): LocalRepeaterRow[] {
    const { min, max, initial } = repeaterBounds(node);
    const defaults = Array.isArray(node.props.defaultRows)
        ? node.props.defaultRows.slice(0, max)
        : [];
    const count = Math.max(min, defaults.length || initial);

    return Array.from({ length: Math.min(count, max) }, (_, index) => ({
        // Stable on the initial SSR render and during hydration.
        key: 'initial-' + index,
        values: record(defaults[index]),
    }));
}

export function addRepeaterRow(
    node: ComponentNode,
    rows: LocalRepeaterRow[],
): LocalRepeaterRow[] {
    if (node.props.addable === false || rows.length >= repeaterBounds(node).max) return rows;

    return [...rows, { key: 'local-' + crypto.randomUUID(), values: {} }];
}

export function removeRepeaterRow(
    node: ComponentNode,
    rows: LocalRepeaterRow[],
    key: string,
): LocalRepeaterRow[] {
    if (node.props.deletable === false || rows.length <= repeaterBounds(node).min) return rows;

    return rows.filter((row) => row.key !== key);
}

export function moveRepeaterRow(
    node: ComponentNode,
    rows: LocalRepeaterRow[],
    key: string,
    offset: -1 | 1,
): LocalRepeaterRow[] {
    if (node.props.reorderable === false) return rows;

    const index = rows.findIndex((row) => row.key === key);
    const destination = index + offset;
    if (index < 0 || destination < 0 || destination >= rows.length) return rows;

    const next = [...rows];
    [next[index], next[destination]] = [next[destination], next[index]];

    return next;
}

export function canAddRepeaterRow(node: ComponentNode, count: number): boolean {
    return node.props.addable !== false && count < repeaterBounds(node).max;
}

export function canRemoveRepeaterRow(node: ComponentNode, count: number): boolean {
    return node.props.deletable !== false && count > repeaterBounds(node).min;
}

/**
 * Clone a child schema field for a repeater row. Keys follow row identity,
 * not the index, so reordering preserves mounted input state in all adapters.
 */
export function repeaterFieldNode(
    field: ComponentNode,
    row: LocalRepeaterRow,
    index: number,
    parentPath: string,
): ComponentNode {
    const originalName = String(field.props.name ?? '');
    const props: Record<string, unknown> = {
        ...field.props,
        name: parentPath + '[' + index + '][' + originalName + ']',
    };

    if (Object.hasOwn(row.values, originalName)) {
        const value = row.values[originalName];

        if (field.type === 'checkbox') {
            props.checked = value === true || value === 1 || value === '1';
        } else if (field.type === 'repeater' && Array.isArray(value)) {
            props.defaultRows = value;
        } else if (value !== null && typeof value !== 'object') {
            props.value = String(value);
        }
    }

    return {
        ...field,
        key: field.key + ':' + row.key,
        props,
    };
}

const FORBIDDEN = new Set(['__proto__', 'constructor', 'prototype']);

/**
 * Convert bracket/dot names to safe nested objects and arrays. Discard unsafe
 * paths rather than writing prototype properties. The backend still validates.
 */
function pathSegments(path: string): string[] | null {
    if (!/^[A-Za-z][A-Za-z0-9_.-]*(?:\[[A-Za-z0-9_.-]+\])*$/.test(path)) {
        return null;
    }

    const segments = path.replace(/\[([^\]]+)\]/g, '.$1').split('.');
    if (segments.length > 16 || segments.some((part) => part.length === 0 || FORBIDDEN.has(part))) {
        return null;
    }

    return segments;
}

function assignPath(target: FormPayload, path: string, value: FormValue): void {
    const segments = pathSegments(path);
    if (segments === null) return;

    let current: Record<string, FormValue> | FormValue[] = target;

    for (let index = 0; index < segments.length; index++) {
        const key = segments[index];
        const last = index === segments.length - 1;
        const numeric = /^(0|[1-9][0-9]*)$/.test(key);

        if (numeric && Number(key) > 499) return;
        if (Array.isArray(current) && !numeric) return;

        const container = current as unknown as Record<string, FormValue>;

        if (last) {
            container[key] = value;
            return;
        }

        const nextIsIndex = /^(0|[1-9][0-9]*)$/.test(segments[index + 1]);
        let next = container[key];

        if (!next || typeof next !== 'object' || Array.isArray(next) !== nextIsIndex) {
            next = nextIsIndex ? [] : {};
            container[key] = next;
        }

        current = next as Record<string, FormValue> | FormValue[];
    }
}

/**
 * Empty repeaters must submit an empty array, including after deleting all
 * rows. Repeater paths are taken from DOM attributes, not extra hidden inputs.
 */
export function formDataToPayload(
    data: FormData,
    repeaterPaths: string[] = [],
): FormPayload {
    const payload: FormPayload = {};

    for (const path of repeaterPaths) {
        assignPath(payload, path, []);
    }

    for (const [name, value] of data.entries()) {
        assignPath(payload, name, value);
    }

    return payload;
}
