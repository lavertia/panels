import { Head } from '@inertiajs/react';
import LavertiaRenderer from '../../components/lavertia/renderer';
import type { LavertiaPayload } from '../../components/lavertia/types';

export default function LavertiaPage({ lavertia }: { lavertia: LavertiaPayload }) {
    if (lavertia.version !== 1) {
        return <p role="alert">Unsupported Lavertia protocol version.</p>;
    }

    return (
        <>
            <Head title={lavertia.title ?? 'Lavertia'} />
            <main className="mx-auto w-full max-w-7xl p-4">
                <LavertiaRenderer node={lavertia.tree} />
            </main>
        </>
    );
}
