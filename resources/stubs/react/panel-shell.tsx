import { Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import type { LavertiaPayload, NavigationLink } from './types';
import { panelNavigationGroups, safeHref } from './types';

function Icon({ name }: { name: 'menu' | 'close' | 'dashboard' | 'page' }) {
    const props = { width: 19, height: 19, viewBox: '0 0 24 24', stroke: 'currentColor',
        fill: 'none', strokeWidth: 1.8, strokeLinecap: 'round' as const, strokeLinejoin: 'round' as const,
        'aria-hidden': true as const };
    if (name === 'menu') return <svg {...props}><path d="M4 7h16M4 12h16M4 17h16" /></svg>;
    if (name === 'close') return <svg {...props}><path d="M6 6l12 12M18 6 6 18" /></svg>;
    if (name === 'dashboard') return <svg {...props}>
        <rect x="3" y="3" width="7.5" height="7.5" rx="1.4" />
        <rect x="13.5" y="3" width="7.5" height="7.5" rx="1.4" />
        <rect x="3" y="13.5" width="7.5" height="7.5" rx="1.4" />
        <rect x="13.5" y="13.5" width="7.5" height="7.5" rx="1.4" />
    </svg>;
    return <svg {...props}><rect x="4" y="3" width="16" height="18" rx="2" /><path d="M8 8h8M8 12h8M8 16h4" /></svg>;
}

function Navigation({ links, compact, onNavigate }: {
    links: NavigationLink[];
    compact: boolean;
    onNavigate: () => void;
}) {
    const groups = panelNavigationGroups(links);
    return <nav aria-label="Panel navigation" className="flex flex-col gap-5 px-3 py-4">
        {groups.map(group => <section key={group.label} aria-label={group.label} className="space-y-1">
            {!compact && <p className="px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{group.label}</p>}
            {group.items.map(item => {
                const href = safeHref(item.url);
                if (!href?.startsWith('/')) return null;
                return <Link key={item.slug} href={href} onClick={onNavigate}
                    title={compact ? item.label : undefined}
                    aria-label={compact ? item.label : undefined}
                    aria-current={item.active ? 'page' : undefined}
                    className={[
                        'flex min-h-10 items-center gap-3 rounded-lg text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500',
                        compact ? 'justify-center px-2' : 'px-3',
                        item.active || item.descendantActive
                            ? 'bg-amber-50 text-amber-950 dark:bg-amber-500/15 dark:text-amber-200'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white',
                    ].join(' ')}>
                    <span className="shrink-0"><Icon name={item.icon === 'layout-dashboard' ? 'dashboard' : 'page'} /></span>
                    {!compact && <span className="min-w-0 flex-1 truncate">{item.label}</span>}
                </Link>;
            })}
        </section>)}
        {groups.length === 0 && !compact && <p className="px-3 py-5 text-xs text-slate-500 dark:text-slate-400">Register navigation in your PanelProvider.</p>}
    </nav>;
}

/**
 * SSR-safe shell for Laravel's No Starter Kit + React scaffold.
 * Server-provided links have already passed Page authorization checks.
 */
export default function PanelShell({ lavertia, children }: { lavertia: LavertiaPayload; children: ReactNode }) {
    const panel = lavertia.panel;
    const [collapsed, setCollapsed] = useState(false);
    const [mobileOpen, setMobileOpen] = useState(false);
    const mobileCloseRef = useRef<HTMLButtonElement>(null);
    const navigation = lavertia.navigation ?? [];
    const sidebarEnabled = panel?.navigationPosition !== 'none' && panel?.navigationPosition !== 'top';
    const name = panel?.brandName || 'Lavertia';

    useEffect(() => {
        if (!mobileOpen) return;
        mobileCloseRef.current?.focus();
        const close = (event: KeyboardEvent) => { if (event.key === 'Escape') setMobileOpen(false); };
        document.addEventListener('keydown', close);
        return () => document.removeEventListener('keydown', close);
    }, [mobileOpen]);

    const closeMobile = () => setMobileOpen(false);

    function sidebarContent(compact: boolean, mobile: boolean) {
        return <>
            <div className="flex h-[72px] shrink-0 items-center justify-between gap-2 border-b border-slate-200 px-4 dark:border-slate-800">
                <div className="flex min-w-0 items-center gap-3">
                    {panel?.brandLogo
                        ? <img src={panel.brandLogo} alt="" className="size-10 shrink-0 rounded-xl object-contain" />
                        : <span aria-hidden="true" className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-amber-500 text-lg font-black text-slate-950">L</span>}
                    {!compact && <div className="min-w-0">
                        <strong className="block truncate text-sm font-bold text-slate-950 dark:text-white">{name}</strong>
                        <span className="block truncate text-xs text-slate-500 dark:text-slate-400">{panel?.brandSubtitle || 'Administration'}</span>
                    </div>}
                </div>
                {mobile && <button type="button" ref={mobileCloseRef} onClick={closeMobile} aria-label="Close navigation"
                    className="rounded-lg p-2 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-amber-500 dark:hover:bg-slate-800"><Icon name="close" /></button>}
            </div>
            <div className="min-h-0 flex-1 overflow-y-auto">
                <Navigation links={navigation} compact={compact} onNavigate={closeMobile} />
            </div>
            <div className="border-t border-slate-200 p-4 text-center text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                {compact ? <span title="Powered by Lavertia">L</span> : 'Powered by Lavertia'}
            </div>
        </>;
    }

    return <div data-lavertia-shell="standalone" lang={lavertia.locale ?? 'en'} dir={lavertia.direction ?? 'ltr'}
        className="flex min-h-screen bg-slate-50 text-slate-950 dark:bg-slate-950 dark:text-slate-100">
        {sidebarEnabled && <>
            {mobileOpen && <button type="button" aria-label="Dismiss navigation" onClick={closeMobile} className="fixed inset-0 z-40 bg-slate-950/60 lg:hidden" />}
            {mobileOpen && <aside role="dialog" aria-modal="true" aria-label="Mobile navigation"
                className="fixed inset-y-0 start-0 z-50 flex w-72 flex-col border-e border-slate-200 bg-white shadow-2xl lg:hidden dark:border-slate-800 dark:bg-slate-900">
                {sidebarContent(false, true)}
            </aside>}
            <aside id="lavertia-panel-navigation" aria-label="Sidebar" className={[
                'sticky top-0 hidden h-screen shrink-0 flex-col border-e border-slate-200 bg-white transition-[width] duration-150 lg:flex dark:border-slate-800 dark:bg-slate-900',
                collapsed ? 'w-[72px]' : 'w-64',
            ].join(' ')}>
                {sidebarContent(collapsed, false)}
            </aside>
        </>}
        <div className="flex min-w-0 flex-1 flex-col">
            {panel?.topbar !== false && <header className="sticky top-0 z-20 flex min-h-[72px] items-center gap-3 border-b border-slate-200 bg-white/95 px-4 backdrop-blur-sm sm:px-6 dark:border-slate-800 dark:bg-slate-900/95">
                {sidebarEnabled && <button type="button" aria-label="Open navigation" aria-controls="lavertia-panel-navigation" aria-expanded={mobileOpen}
                    onClick={() => setMobileOpen(true)} className="inline-flex size-10 items-center justify-center rounded-lg border border-slate-200 focus-visible:outline-2 focus-visible:outline-amber-500 lg:hidden dark:border-slate-700">
                    <Icon name="menu" /></button>}
                {sidebarEnabled && panel?.sidebarCollapsible !== false && <button type="button" aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                    onClick={() => setCollapsed(value => !value)} className="hidden size-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-amber-500 lg:inline-flex dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    <Icon name="menu" /></button>}
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-semibold">{lavertia.title ?? 'Overview'}</p>
                    <p className="hidden text-xs text-slate-500 sm:block dark:text-slate-400">{name} / Workspace</p>
                </div>
                <span className="hidden rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-900 sm:inline-flex dark:border-amber-700/40 dark:bg-amber-500/10 dark:text-amber-300">React</span>
            </header>}
            <main id="main-content" className="w-full flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                <div className="mx-auto flex w-full max-w-7xl flex-col gap-7">{children}</div>
            </main>
        </div>
    </div>;
}
