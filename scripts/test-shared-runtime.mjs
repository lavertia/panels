import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import ts from 'typescript';

// Execute the exact TypeScript utility module shipped to all three adapters.
// The module must stay frontend-neutral and contain no browser-only top-level imports.
const source = readFileSync(resolve('resources/stubs/shared/types.ts'), 'utf8');
const compiled = ts.transpileModule(source, {
    compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 },
    reportDiagnostics: true,
});

assert.deepEqual(compiled.diagnostics, []);
const runtime = await import('data:text/javascript;base64,' + Buffer.from(compiled.outputText).toString('base64'));

const table = {
    type: 'table',
    key: 'root/members',
    props: { statePrefix: 'lvt_members_', rows: [
        { name: 'Ahmad', status: 'active' },
        { name: 'Maya', status: 'pending' },
    ] },
    components: [
        {
            type: 'text-column', key: 'root/members/name',
            props: { name: 'name', searchable: true, sortable: true, toggleable: true },
            components: [],
        },
        {
            type: 'text-column', key: 'root/members/status',
            props: { name: 'status', searchable: false, hiddenByDefault: true },
            components: [],
        },
        {
            type: 'select-filter', key: 'root/members/status-filter',
            props: { name: 'status', options: { active: 'Active', pending: 'Pending' } },
            components: [],
        },
    ],
};

const columns = runtime.tableColumns(table);
assert.equal(columns.length, 2);
assert.deepEqual(runtime.defaultHiddenColumns(columns), ['status']);
assert.deepEqual(runtime.tableFilters(table)[0].options, { active: 'Active', pending: 'Pending' });
assert.deepEqual(runtime.visibleTableRows(runtime.tableRows(table), columns, 'MAYA', null, 'asc', {}).map(row => row.name), ['Maya']);
assert.deepEqual(runtime.visibleTableRows(runtime.tableRows(table), columns, '', null, 'asc', { status: 'active' }).map(row => row.name), ['Ahmad']);
assert.deepEqual(runtime.visibleTableRows(runtime.tableRows(table), columns, '', 'name', 'desc', {}).map(row => row.name), ['Maya', 'Ahmad']);
assert.deepEqual(runtime.tableColumnsInOrder(columns, ['status', 'name'], []).map(col => col.key), ['status', 'name']);

// PHP converts array keys '0', '1' into numeric keys. JSON can therefore
// encode SelectFilter options as a list instead of an object.
const numericTable = {
    ...table,
    components: [{
        type: 'select-filter', key: 'root/members/numeric',
        props: { name: 'level', options: ['Zero', 'One'] },
        components: [],
    }],
};
assert.deepEqual(runtime.tableFilters(numericTable)[0].options, { 0: 'Zero', 1: 'One' });

assert.equal(runtime.safeHref('javascript:alert(1)'), null);
assert.equal(runtime.safeHref('//malicious.example/'), null);
assert.equal(runtime.safeHref('https://example.com/docs'), 'https://example.com/docs');

globalThis.window = { location: { href: 'https://example.test/admin?lvt_members_page=3&lvt_other_page=8&lang=ar' } };
const url = new URL(runtime.tableStateUrl(table, { search: 'Sara' }), 'https://example.test');
assert.equal(url.searchParams.get('lvt_members_page'), null);
assert.equal(url.searchParams.get('lvt_members_search'), 'Sara');
assert.equal(url.searchParams.get('lvt_other_page'), '8');
assert.equal(url.searchParams.get('lang'), 'ar');

assert.equal(runtime.translate('ar', 'submit'), 'حفظ التغييرات');
assert.equal(runtime.translate('fr', 'saved'), 'Modifications enregistrées.');
assert.equal(runtime.translate('unsupported', 'submit'), 'Save changes');


// Exercise the exact frontend-neutral runtime shipped to React/Vue/Svelte.
const formSource = readFileSync(resolve('resources/stubs/shared/form-state.ts'), 'utf8');
const formCompiled = ts.transpileModule(formSource, {
    compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 },
    reportDiagnostics: true,
});
assert.deepEqual(formCompiled.diagnostics, []);
const formRuntime = await import('data:text/javascript;base64,' + Buffer.from(formCompiled.outputText).toString('base64'));

assert.deepEqual(formRuntime.reconcileField('Typing', 'Stale from server', true), {
    value: 'Typing', dirty: true,
});
assert.deepEqual(formRuntime.reconcileField('Typing', 'Typing', true), {
    value: 'Typing', dirty: false,
});
assert.deepEqual(formRuntime.reconcileField('Old', 'New', false), {
    value: 'New', dirty: false,
});

const repeater = {
    type: 'repeater', key: 'root/items',
    props: {
        name: 'items', minItems: 1, maxItems: 3, defaultItems: 1,
        defaultRows: [{ description: 'Existing', quantity: 2 }],
        addable: true, deletable: true, reorderable: true,
    },
    components: [{
        type: 'text-input', key: 'root/items/description',
        props: { name: 'description', required: true }, components: [],
    }],
};
let repeaterRows = formRuntime.initialRepeaterRows(repeater);
assert.equal(repeaterRows.length, 1);
assert.equal(repeaterRows[0].key, 'initial-0');
assert.equal(formRuntime.canRemoveRepeaterRow(repeater, repeaterRows.length), false);
assert.deepEqual(
    formRuntime.repeaterFieldNode(repeater.components[0], repeaterRows[0], 0, 'items').props,
    { name: 'items[0][description]', required: true, value: 'Existing' },
);

repeaterRows = formRuntime.addRepeaterRow(repeater, repeaterRows);
repeaterRows = formRuntime.addRepeaterRow(repeater, repeaterRows);
assert.equal(repeaterRows.length, 3);
assert.equal(formRuntime.addRepeaterRow(repeater, repeaterRows).length, 3);
const movedKey = repeaterRows[2].key;
const moved = formRuntime.moveRepeaterRow(repeater, repeaterRows, movedKey, -1);
assert.equal(moved[1].key, movedKey);
assert.equal(formRuntime.repeaterFieldNode(repeater.components[0], moved[1], 1, 'items').key,
    repeater.components[0].key + ':' + movedKey);
repeaterRows = formRuntime.removeRepeaterRow(repeater, moved, movedKey);
assert.equal(repeaterRows.length, 2);

// Empty repeaters are preserved as [] on submit; arrays are indexed for Laravel.
const nestedForm = new FormData();
nestedForm.append('notify', '0');
nestedForm.append('notify', '1');
nestedForm.append('items[0][description]', 'Pencil');
nestedForm.append('items[0][quantity]', '4');
nestedForm.append('items[1][description]', 'Notebook');
nestedForm.append('orders[0][lines][0][sku]', 'AAA');
nestedForm.append('__proto__[polluted]', 'unsafe');
nestedForm.append('constructor[prototype][polluted]', 'unsafe');
const payload = formRuntime.formDataToPayload(nestedForm, ['items', 'empty', 'orders[0][lines]']);
assert.deepEqual(payload, {
    items: [
        { description: 'Pencil', quantity: '4' },
        { description: 'Notebook' },
    ],
    empty: [],
    orders: [{ lines: [{ sku: 'AAA' }] }],
    notify: '1',
});
assert.equal({}.polluted, undefined);

console.log('Lavertia shared adapter runtime checks passed.');
