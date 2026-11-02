import { Head, usePage } from '@inertiajs/react';
import LavertiaRenderer from '../../components/lavertia/renderer';
import PanelShell from '../../components/lavertia/panel-shell';
import type { LavertiaPayload } from '../../components/lavertia/types';

export default function LavertiaPage({ lavertia }: { lavertia: LavertiaPayload }) {
    const page = usePage();

    if (lavertia.version !== 1) {
        return <p role="alert" className="p-6 text-sm text-rose-700">Unsupported Lavertia protocol version.</p>;
    }

    // The host Starter Kit retains its own layout and navigation. A standalone
    // Laravel React app instead gets Lavertia's independent Panel Shell.
    const hostOwnsShell = Object.prototype.hasOwnProperty.call(page.props, 'lavertiaSidebar');
    const view = <LavertiaRenderer node={lavertia.tree} />;

    return <>
        <Head title={lavertia.title ?? 'Lavertia'} />
        {hostOwnsShell || !lavertia.panel
            ? <main lang={lavertia.locale ?? 'en'} dir={lavertia.direction ?? 'ltr'}
                className="min-h-screen bg-slate-50 px-4 py-6 text-slate-950 sm:px-6 lg:px-8 dark:bg-slate-950 dark:text-slate-100">
                <div className="mx-auto max-w-7xl">{view}</div>
            </main>
            : <PanelShell lavertia={lavertia}>{view}</PanelShell>}
    </>;
}
