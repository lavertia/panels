import { useEffect, useRef, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { Link, router } from '@inertiajs/react';
import type { ComponentNode } from './types';
import { LavertiaTable } from './table';
import {
    addRepeaterRow,
    canAddRepeaterRow,
    canRemoveRepeaterRow,
    formDataToPayload,
    initialRepeaterRows,
    moveRepeaterRow,
    reconcileField,
    removeRepeaterRow,
    repeaterFieldNode,
} from './form-state';
import { runClientAction } from './client-actions';
import {
    badgeClass,
    headerLinks,
    safeHref,
    statAccentClass,
    translate,
} from './types';

function FormRenderer({ node, children, onSaved }: { node: ComponentNode; children: ReactNode; onSaved?: () => void }) {
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saved, setSaved] = useState(false);
    const action = typeof node.props.action === 'string'
        && node.props.action.startsWith('/') && !node.props.action.startsWith('//')
        ? node.props.action : null;
    const method: 'post' | 'put' | 'patch' =
        node.props.method === 'put' || node.props.method === 'patch' ? node.props.method : 'post';
    const tx = (key: Parameters<typeof translate>[1]) => translate(node.props.locale, key);

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        if (!action || processing) return;

        const repeaterPaths = Array.from(
            event.currentTarget.querySelectorAll<HTMLElement>('[data-lavertia-repeater]'),
            (element) => element.dataset.lavertiaRepeater ?? '',
        ).filter(Boolean);
        const data = formDataToPayload(new FormData(event.currentTarget), repeaterPaths);

        setSaved(false);
        setErrors({});
        setProcessing(true);
        router.visit(action, {
            method,
            data,
            preserveScroll: true,
            onSuccess: () => {
                setSaved(true);
                onSaved?.();
                if (Array.isArray(node.props.refreshOnSuccess)) {
                    const only = node.props.refreshOnSuccess.filter(
                        (value): value is string => typeof value === 'string',
                    );
                    if (only.length > 0) router.reload({ only });
                }
            },
            onError: (validationErrors) => setErrors(validationErrors),
            onFinish: () => setProcessing(false),
        });
    }

    return <form method="post" action={action ?? undefined} onSubmit={submit}
        className="space-y-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-gray-900/70">
        <h3 className="text-sm font-semibold">{String(node.props.label ?? node.props.name ?? '')}</h3>
        <div className="grid gap-4 sm:grid-cols-2">{children}</div>
        {Object.keys(errors).length > 0 && <ul role="alert" className="list-disc space-y-1 ps-5 text-sm text-rose-600">
            {Object.entries(errors).map(([name, message]) => <li key={name}>{name}: {message}</li>)}
        </ul>}
        {saved && <p role="status" className="text-sm text-emerald-700">{tx('saved')}</p>}
        {action ? <button type="submit" disabled={processing}
            className="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-gray-950 disabled:opacity-50">
            {processing ? tx('saving') : String(node.props.submitLabel ?? tx('submit'))}
        </button> : <p role="status" className="text-xs text-gray-500">{tx('draft')}</p>}
    </form>;
}




function RepeaterRenderer({ node }: { node: ComponentNode }) {
    const [rows, setRows] = useState(() => initialRepeaterRows(node));
    const [collapsed, setCollapsed] = useState<Record<string, boolean>>({});
    const name = String(node.props.name ?? '');

    return (
        <section className="col-span-full space-y-3" data-lavertia-repeater={name}
            aria-label={String(node.props.label ?? name)}>
            <h3 className="text-sm font-semibold">{String(node.props.label ?? name)}</h3>
            {rows.map((row, index) => (
                <div key={row.key} className="rounded-lg border border-gray-200 p-3 dark:border-white/15">
                    <div className="mb-3 flex items-center justify-between gap-2">
                        <span className="text-xs font-semibold">{translate(node.props.locale, 'itemLabel')} {index + 1}</span>
                        <div className="flex flex-wrap gap-1">
                            {node.props.reorderable !== false && <>
                                <button type="button" disabled={index === 0} aria-label={translate(node.props.locale, 'moveUp')}
                                    onClick={() => setRows((previous) => moveRepeaterRow(node, previous, row.key, -1))}
                                    className="rounded border px-2 py-1 text-xs disabled:opacity-40">↑</button>
                                <button type="button" disabled={index === rows.length - 1} aria-label={translate(node.props.locale, 'moveDown')}
                                    onClick={() => setRows((previous) => moveRepeaterRow(node, previous, row.key, 1))}
                                    className="rounded border px-2 py-1 text-xs disabled:opacity-40">↓</button>
                            </>}
                            {node.props.collapsible === true &&
                                <button type="button" aria-label={collapsed[row.key] ? translate(node.props.locale, 'expand') : translate(node.props.locale, 'collapse')}
                                    onClick={() => setCollapsed((previous) => ({ ...previous, [row.key]: !previous[row.key] }))}
                                    className="rounded border px-2 py-1 text-xs">{collapsed[row.key] ? '+' : '−'}</button>}
                            {node.props.deletable !== false &&
                                <button type="button" disabled={!canRemoveRepeaterRow(node, rows.length)}
                                    aria-label={translate(node.props.locale, 'removeItem')}
                                    onClick={() => setRows((previous) => removeRepeaterRow(node, previous, row.key))}
                                    className="rounded border px-2 py-1 text-xs disabled:opacity-40">×</button>}
                        </div>
                    </div>
                    {/* Keep fields mounted while collapsed so the form payload retains values. */}
                    <div className={collapsed[row.key] ? 'hidden' : 'grid gap-3 sm:grid-cols-2'}>
                        {node.components.map((field) => {
                            const child = repeaterFieldNode(field, row, index, name);
                            return <LavertiaRenderer key={child.key} node={child} />;
                        })}
                    </div>
                </div>
            ))}
            {canAddRepeaterRow(node, rows.length) &&
                <button type="button" onClick={() => setRows((previous) => addRepeaterRow(node, previous))}
                    className="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold dark:border-white/20">
                    + {translate(node.props.locale, 'addItem')}
                </button>}
        </section>
    );
}

function ActionLink({ node }: { node: ComponentNode }) {
    const dialogRef = useRef<HTMLDialogElement>(null);
    const [clientBusy, setClientBusy] = useState(false);
    const [clientError, setClientError] = useState<string | null>(null);
    const label = String(node.props.label ?? node.props.name ?? 'Action');

    async function execute(): Promise<void> {
        if (typeof node.props.clientAction !== 'string' || clientBusy) return;
        setClientBusy(true);
        setClientError(null);
        try {
            await runClientAction(node.props.clientAction, node.props.clientParams);
        } catch {
            setClientError('Unable to run this client action.');
        } finally {
            setClientBusy(false);
        }
    }
    const href = safeHref(node.props.href);
    const appearance = node.props.appearance;
    const base = 'inline-flex min-h-9 items-center justify-center rounded-lg px-3.5 py-2 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500';
    const variant = appearance === 'primary'
        ? 'bg-amber-500 text-gray-950 shadow-sm hover:bg-amber-400 dark:bg-amber-400 dark:hover:bg-amber-300'
        : appearance === 'outline'
            ? 'border border-gray-300 bg-transparent text-gray-700 hover:bg-gray-100 dark:border-white/20 dark:text-gray-200 dark:hover:bg-white/10'
            : 'border border-gray-200 bg-white text-gray-800 shadow-sm hover:bg-gray-50 dark:border-white/15 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700';
    const classes = base + ' ' + variant;

    const modalForm = node.props.modal === true
        ? node.components.find((component) => component.type === 'form')
        : undefined;
    if (modalForm) {
        return <>
            <button type="button" className={classes}
                onClick={() => dialogRef.current?.showModal()}>{label}</button>
            <dialog ref={dialogRef} onClick={(event) => {
                if (event.target === dialogRef.current) dialogRef.current.close();
            }} aria-label={label}
                className="m-auto w-[min(95vw,680px)] max-h-[90vh] overflow-y-auto rounded-xl border border-gray-200 bg-white p-5 text-gray-900 shadow-2xl backdrop:bg-gray-900/60 dark:border-white/20 dark:bg-gray-950 dark:text-white">
                <div className="mb-4 flex items-center justify-between gap-4">
                    <h2 className="text-lg font-semibold">{label}</h2>
                    <button type="button" onClick={() => dialogRef.current?.close()}
                        aria-label="Close modal" className="rounded border px-2 py-1">×</button>
                </div>
                <FormRenderer node={modalForm}
                    onSaved={() => {
                        if (node.props.closeOnSuccess !== false) dialogRef.current?.close();
                    }}>
                    {modalForm.components.map((child) =>
                        <LavertiaRenderer key={child.key} node={child} />)}
                </FormRenderer>
            </dialog>
        </>;
    }

    if (typeof node.props.clientAction === 'string') {
        return <span className="inline-flex flex-col gap-1">
            <button type="button" className={classes} disabled={clientBusy} onClick={() => { void execute(); }}>
                {label}
            </button>
            {clientError && <span role="alert" className="text-xs text-rose-600">{clientError}</span>}
        </span>;
    }

    if (!href) return <span className="text-sm text-gray-500">{label}</span>;
    if (href.startsWith('/')) return <Link href={href} className={classes}>{label}</Link>;

    return <a href={href} className={classes} rel="noopener noreferrer">{label}</a>;
}

export default function LavertiaRenderer({ node }: { node: ComponentNode }) {
    const [input, setInput] = useState(String(node.props.value ?? ''));
    const [checked, setChecked] = useState(node.props.checked === true);
    const inputDirty = useRef(false);
    const checkedDirty = useRef(false);

    useEffect(() => {
        const next = reconcileField(input, String(node.props.value ?? ''), inputDirty.current);
        inputDirty.current = next.dirty;
        setInput(next.value);
    }, [node.props.value]);

    useEffect(() => {
        const next = reconcileField(checked, node.props.checked === true, checkedDirty.current);
        checkedDirty.current = next.dirty;
        setChecked(next.value);
    }, [node.props.checked]);

    const children = node.components.map((child) => <LavertiaRenderer key={child.key} node={child} />);

    switch (node.type) {
        case 'container':
        case 'column':
            return <div className="flex min-w-0 flex-col gap-6">{children}</div>;

        case 'row':
            return <div className="flex min-w-0 flex-wrap items-start gap-4">{children}</div>;

        case 'page-header': {
            const crumbs = headerLinks(node.props.breadcrumbs);
            const tabs = headerLinks(node.props.tabs).filter(tab => tab.url !== null);

            return (
                <div className="space-y-4">
                    {crumbs.length > 0 && (
                        <nav aria-label="Breadcrumbs">
                            <ol className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                {crumbs.map((crumb, index) => (
                                    <li key={index} className="flex items-center gap-2">
                                        {index > 0 && <span aria-hidden="true">/</span>}
                                        {crumb.url ? <Link href={crumb.url} className="hover:underline">{crumb.label}</Link>
                                            : <span aria-current="page">{crumb.label}</span>}
                                    </li>
                                ))}
                            </ol>
                        </nav>
                    )}
                    <header className="flex flex-col gap-4 border-b border-gray-200 pb-6 sm:flex-row sm:items-end sm:justify-between dark:border-white/10">
                        <div className="min-w-0">
                            {typeof node.props.eyebrow === 'string' && node.props.eyebrow.length > 0 && <p className="mb-2 text-xs font-bold uppercase tracking-widest text-amber-700 dark:text-amber-400">{String(node.props.eyebrow)}</p>}
                            <h1 className="text-2xl font-bold tracking-tight text-gray-950 sm:text-3xl dark:text-white">{String(node.props.title ?? 'Overview')}</h1>
                            {typeof node.props.description === 'string' && node.props.description.length > 0 && <p className="mt-2 max-w-2xl text-sm leading-6 text-gray-500 dark:text-gray-400">{String(node.props.description)}</p>}
                        </div>
                        {children.length > 0 && <div className="flex shrink-0 flex-wrap items-center gap-2">{children}</div>}
                    </header>
                    {tabs.length > 0 && (
                        <nav aria-label="Page tabs" className="flex gap-4 overflow-x-auto border-b border-border">
                            {tabs.map(tab => <Link key={tab.url} href={tab.url!} aria-current={tab.active ? 'page' : undefined}
                                className={`shrink-0 border-b-2 px-1 pb-2 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring ${tab.active ? 'border-primary font-semibold text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'}`}>{tab.label}</Link>)}
                        </nav>
                    )}
                </div>
            );
        }

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
                    {typeof node.props.description === 'string' && node.props.description.length > 0 && <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">{String(node.props.description)}</p>}
                </article>
            );

        case 'section':
            return (
                <section className="space-y-4" aria-label={String(node.props.title ?? 'Section')}>
                    <div>
                        <h2 className="text-base font-semibold text-gray-950 dark:text-white">{String(node.props.title ?? '')}</h2>
                        {typeof node.props.description === 'string' && node.props.description.length > 0 && <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{String(node.props.description)}</p>}
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

case 'form':
            return <FormRenderer node={node}>{children}</FormRenderer>;

        case 'repeater':
            return <RepeaterRenderer node={node} />;

        case 'select':
            return <label className="flex flex-col gap-1.5 text-sm font-semibold">
                {String(node.props.label ?? node.props.name ?? '')}
                <select name={String(node.props.name ?? '')} value={input} required={node.props.required === true} onChange={(event) => { inputDirty.current = true; setInput(event.target.value); }}
                    className="rounded-lg border border-gray-300 bg-white px-3 py-2.5 font-normal dark:border-white/20 dark:bg-gray-950">
                    <option value="">—</option>
                    {Object.entries(node.props.options && typeof node.props.options === 'object' ? node.props.options as Record<string, string> : {}).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                </select>
            </label>;

        case 'textarea':
            return <label className="flex flex-col gap-1.5 text-sm font-semibold">
                {String(node.props.label ?? node.props.name ?? '')}
                <textarea name={String(node.props.name ?? '')} required={node.props.required === true} rows={Number(node.props.rows ?? 3)} value={input} onChange={(event) => { inputDirty.current = true; setInput(event.target.value); }}
                    className="rounded-lg border border-gray-300 bg-white px-3 py-2.5 font-normal dark:border-white/20 dark:bg-gray-950" />
            </label>;

        case 'checkbox':
            return <label className="flex items-center gap-2 text-sm font-semibold">
                <input type="hidden" name={String(node.props.name ?? '')} value="0" />
                <input type="checkbox" name={String(node.props.name ?? '')} value="1" checked={checked} onChange={(event) => { checkedDirty.current = true; setChecked(event.target.checked); }} />
                {String(node.props.label ?? node.props.name ?? '')}
            </label>;

                case 'text-input':
            return (
                <div className="flex min-w-0 flex-col gap-1.5">
                    <label htmlFor={node.key} className="text-sm font-semibold text-gray-800 dark:text-gray-200">{String(node.props.label ?? node.props.name ?? '')}</label>
                    <input
                        id={node.key}
                        name={String(node.props.name ?? '')}
                        type="text"
                        required={node.props.required === true}
                        value={input}
                        placeholder={String(node.props.placeholder ?? '')}
                        onChange={(event) => { inputDirty.current = true; setInput(event.target.value); }}
                        aria-describedby={node.props.helperText ? node.key + '-help' : undefined}
                        className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20 dark:border-white/20 dark:bg-gray-950 dark:text-gray-100"
                    />
                    {typeof node.props.helperText === 'string' && node.props.helperText.length > 0 && <p id={node.key + '-help'} className="text-xs text-gray-500 dark:text-gray-400">{String(node.props.helperText)}</p>}
                </div>
            );

        case 'table':
            return <LavertiaTable node={node} />;

        case 'action':
            return <ActionLink node={node} />;

        case 'text-column':
            return null;

        default:
            return <p role="alert" className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900/50 dark:bg-rose-900/20 dark:text-rose-300">Unsupported component: {node.type}</p>;
    }
}
