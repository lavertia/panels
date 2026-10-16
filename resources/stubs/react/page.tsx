import { Head } from '@inertiajs/react';
import LavertiaRenderer from '../../components/lavertia/renderer';
import type { LavertiaPayload } from '../../components/lavertia/types';

export default function LavertiaPage({ lavertia }: { lavertia: LavertiaPayload }) {
    if (lavertia.version !== 1) {
        return <p role="alert" className="p-6 text-sm text-rose-700">Unsupported Lavertia protocol version.</p>;
    }

    return (
        <>
            <Head title={lavertia.title ?? 'Lavertia'} />
            <main lang={lavertia.locale ?? "en"} dir={lavertia.direction ?? "ltr"} className="min-h-[calc(100vh-8rem)] bg-gray-50/70 px-4 py-6 sm:px-6 lg:px-8 dark:bg-gray-950/40">
                <div className="mx-auto w-full max-w-[1440px]">
                    <LavertiaRenderer node={lavertia.tree} />
                </div>
            </main>
        </>
    );
}
