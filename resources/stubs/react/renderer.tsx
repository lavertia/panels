import { useEffect, useState } from 'react';
import { Link } from '@inertiajs/react';
import type { ComponentNode } from './types';
import { safeHref, tableColumns, tableRows } from './types';

export default function LavertiaRenderer({ node }: { node: ComponentNode }) {
    const [input, setInput] = useState(String(node.props.value ?? ''));

    useEffect(() => {
        setInput(String(node.props.value ?? ''));
    }, [node.props.value]);

    const children = node.components.map((child) => (
        <LavertiaRenderer key={child.key} node={child} />
    ));

    switch (node.type) {
        case 'container':
        case 'column':
            return <div className="flex min-w-0 flex-col gap-4">{children}</div>;

        case 'row':
            return <div className="flex min-w-0 flex-wrap gap-4">{children}</div>;

        case 'card':
            return <section className="min-w-0 rounded-lg border border-gray-200 p-4 dark:border-gray-700">{children}</section>;

        case 'text':
            return <p className="text-gray-900 dark:text-gray-100">{String(node.props.content ?? '')}</p>;

        case 'infolist':
            return <dl className="grid min-w-0 gap-3">{children}</dl>;

        case 'text-entry':
            return (
                <div className="min-w-0">
                    <dt className="text-xs text-gray-500">{String(node.props.label ?? node.props.name ?? '')}</dt>
                    <dd className="text-sm">{String(node.props.value ?? '—')}</dd>
                </div>
            );

        case 'text-input':
            return (
                <div className="flex min-w-0 flex-col gap-1">
                    <label htmlFor={node.key} className="text-sm font-medium">
                        {String(node.props.label ?? node.props.name ?? '')}
                    </label>
                    <input
                        id={node.key}
                        name={String(node.props.name ?? '')}
                        value={input}
                        placeholder={String(node.props.placeholder ?? '')}
                        onChange={(event) => setInput(event.target.value)}
                        className="rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700"
                    />
                </div>
            );

        case 'table': {
            const columns = tableColumns(node);
            const rows = tableRows(node);

            return (
                <div className="min-w-0 overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead>
                            <tr>
                                {columns.map((column) => (
                                    <th key={column.key} scope="col" className="px-3 py-2 text-start font-semibold">
                                        {column.label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                            {rows.map((row, index) => (
                                <tr key={String(row.id ?? index)}>
                                    {columns.map((column) => (
                                        <td key={column.key} className="px-3 py-2">
                                            {String(row[column.key] ?? '')}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {rows.length === 0 && <p className="px-3 py-4 text-gray-500">No records</p>}
                </div>
            );
        }

        case 'action': {
            const label = String(node.props.label ?? node.props.name ?? '');
            const href = safeHref(node.props.href);
            const classes = 'inline-flex w-fit rounded-md bg-gray-900 px-3 py-2 text-sm text-white dark:bg-gray-100 dark:text-gray-900';

            if (!href) {
                return <span className="text-sm text-gray-500">{label}</span>;
            }

            if (href.startsWith('/')) {
                return <Link href={href} className={classes}>{label}</Link>;
            }

            return <a href={href} rel="noopener noreferrer" className={classes}>{label}</a>;
        }

        case 'text-column':
            return null;

        default:
            return <p role="alert" className="text-sm text-red-700">Unsupported component: {node.type}</p>;
    }
}
