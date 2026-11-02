import {
    cloneElement, createContext, useContext, useEffect, useId, useRef, useState,
} from 'react';
import type {
    ButtonHTMLAttributes, ChangeEvent, MouseEvent, ReactElement, ReactNode, SVGProps,
} from 'react';
import { createPortal } from 'react-dom';

/**
 * Portable Table controls. These work in a blank Laravel + React application,
 * with no dependency on a particular Starter Kit, Radix, or shadcn components.
 * Apps can replace this presentation layer without changing the PHP contract.
 */
export function cn(...classes: Array<string | false | null | undefined>): string {
    return classes.filter(Boolean).join(' ');
}

type IconProps = SVGProps<SVGSVGElement>;

function Icon({ name, ...props }: IconProps & { name: string }) {
    const base: SVGProps<SVGSVGElement> = {
        width: 18,
        height: 18,
        viewBox: '0 0 24 24',
        fill: 'none',
        stroke: 'currentColor',
        strokeWidth: 1.8,
        strokeLinecap: 'round',
        strokeLinejoin: 'round',
        'aria-hidden': true,
        ...props,
    };
    const paths: Record<string, ReactNode> = {
        'arrow-up': <path d="m12 19 0-14m-6 6 6-6 6 6" />,
        'arrow-down': <path d="m12 5 0 14m-6-6 6 6 6-6" />,
        'chevron-left': <path d="m15 18-6-6 6-6" />,
        'chevron-right': <path d="m9 18 6-6-6-6" />,
        'chevrons-left': <><path d="m11 17-5-5 5-5M18 17l-5-5 5-5" /></>,
        'chevrons-right': <><path d="m6 7 5 5-5 5M13 7l5 5-5 5" /></>,
        'columns': <><rect x="3" y="4" width="18" height="16" rx="2" /><path d="M9 4v16m6-16v16" /></>,
        'check-circle': <><circle cx="12" cy="12" r="9" /><path d="m8 12 2.5 2.5L16 9" /></>,
        'x-circle': <><circle cx="12" cy="12" r="9" /><path d="m9 9 6 6m0-6-6 6" /></>,
        'download': <><path d="M12 3v12m-4-4 4 4 4-4" /><path d="M5 17v4h14v-4" /></>,
        'filter': <><path d="M4 5h16l-6.5 7v6l-3 2v-8z" /></>,
        'search': <><circle cx="10.5" cy="10.5" r="6.5" /><path d="m16 16 4 4" /></>,
        'image': <><rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="8.5" cy="8.5" r="1.5" /><path d="m3 17 5-5 4 4 3-3 6 6" /></>,
        'layers': <><path d="m12 3 9 5-9 5-9-5 9-5zm-9 9 9 5 9-5M3 16l9 5 9-5" /></>,
        'more': <><circle cx="5" cy="12" r="1" /><circle cx="12" cy="12" r="1" /><circle cx="19" cy="12" r="1" /></>,
        'reset': <><path d="M3 12a9 9 0 1 0 3-6.7L3 8" /><path d="M3 3v5h5" /></>,
        'grip': <><circle cx="9" cy="6" r="1" /><circle cx="15" cy="6" r="1" /><circle cx="9" cy="12" r="1" /><circle cx="15" cy="12" r="1" /><circle cx="9" cy="18" r="1" /><circle cx="15" cy="18" r="1" /></>,
    };

    return <svg {...base}>{paths[name] ?? paths.more}</svg>;
}

const icon = (name: string) => (props: IconProps) => <Icon name={name} {...props} />;
export const ArrowDown = icon('arrow-down');
export const ArrowUp = icon('arrow-up');
export const CheckCircle2 = icon('check-circle');
export const ChevronLeft = icon('chevron-left');
export const ChevronRight = icon('chevron-right');
export const ChevronsLeft = icon('chevrons-left');
export const ChevronsRight = icon('chevrons-right');
export const Columns3 = icon('columns');
export const Download = icon('download');
export const Filter = icon('filter');
export const ImageIcon = icon('image');
export const Layers3 = icon('layers');
export const MoreHorizontal = icon('more');
export const RotateCcw = icon('reset');
export const Search = icon('search');
export const XCircle = icon('x-circle');
export const GripVertical = icon('grip');

export function Checkbox({
    checked, onCheckedChange, ...props
}: {
    checked: boolean | 'indeterminate';
    onCheckedChange: (value: boolean) => void;
} & Omit<React.InputHTMLAttributes<HTMLInputElement>, 'checked' | 'onChange'>) {
    const ref = useRef<HTMLInputElement>(null);
    useEffect(() => {
        if (ref.current) ref.current.indeterminate = checked === 'indeterminate';
    }, [checked]);

    return <input {...props} ref={ref} type="checkbox" checked={checked === true}
        onChange={(event: ChangeEvent<HTMLInputElement>) => onCheckedChange(event.target.checked)}
        className={cn('size-4 cursor-pointer accent-amber-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500', props.className)} />;
}

interface Option { value: string; label: string; }

export function TableSelect({
    label, value, options, onValueChange, className,
}: {
    label: string; value: string; options: Option[];
    onValueChange: (value: string) => void; size?: 'sm'; className?: string;
}) {
    return <select aria-label={label} value={value}
        onChange={event => onValueChange(event.target.value)}
        className={cn('h-9 min-w-0 rounded-lg border border-slate-300 bg-white px-2 text-sm text-slate-900 focus-visible:outline-2 focus-visible:outline-amber-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100', className)}>
        {options.map(option => <option key={option.value} value={option.value}>{option.label}</option>)}
    </select>;
}

export const SearchableSelect = TableSelect;

type MenuContextValue = {
    open: boolean;
    setOpen: (value: boolean) => void;
    trigger: React.RefObject<HTMLDivElement | null>;
    menuId: string;
};
const MenuContext = createContext<MenuContextValue | null>(null);

function useMenu() {
    const context = useContext(MenuContext);
    if (!context) throw new Error('DropdownMenu components must have DropdownMenu parent.');
    return context;
}

export function DropdownMenu({ children }: { children: ReactNode }) {
    const [open, setOpen] = useState(false);
    const trigger = useRef<HTMLDivElement>(null);
    const menuId = useId();

    return <MenuContext.Provider value={{ open, setOpen, trigger, menuId }}>
        <div ref={trigger} className="relative inline-flex">{children}</div>
    </MenuContext.Provider>;
}

export function DropdownMenuTrigger({
    children,
}: {
    children: ReactElement<ButtonHTMLAttributes<HTMLButtonElement>>; asChild?: boolean;
}) {
    const menu = useMenu();
    return cloneElement(children, {
        'aria-expanded': menu.open,
        'aria-haspopup': 'menu',
        'aria-controls': menu.menuId,
        onClick: (event: MouseEvent<HTMLButtonElement>) => {
            children.props.onClick?.(event);
            menu.setOpen(!menu.open);
        },
    } as ButtonHTMLAttributes<HTMLButtonElement>);
}

export function DropdownMenuContent({
    children, className,
}: { children: ReactNode; className?: string; align?: 'end' | 'start' }) {
    const { open, setOpen, trigger, menuId } = useMenu();
    const element = useRef<HTMLDivElement>(null);
    const [position, setPosition] = useState({ top: 0, left: 0 });

    useEffect(() => {
        if (!open) return;

        function place() {
            const bounds = trigger.current?.getBoundingClientRect();
            if (!bounds) return;
            const width = element.current?.offsetWidth ?? 260;
            const left = Math.min(
                Math.max(8, bounds.right - width),
                Math.max(8, window.innerWidth - width - 8),
            );
            setPosition({ top: bounds.bottom + 5, left });
        }
        place();
        window.addEventListener('resize', place);
        window.addEventListener('scroll', place, true);

        function outside(event: PointerEvent) {
            if (event.target instanceof Node &&
                !trigger.current?.contains(event.target) &&
                !element.current?.contains(event.target)) setOpen(false);
        }
        function keydown(event: KeyboardEvent) {
            if (event.key === 'Escape') {
                setOpen(false);
                trigger.current?.querySelector<HTMLButtonElement>('button')?.focus();
            }
        }
        document.addEventListener('pointerdown', outside);
        document.addEventListener('keydown', keydown);
        return () => {
            window.removeEventListener('resize', place);
            window.removeEventListener('scroll', place, true);
            document.removeEventListener('pointerdown', outside);
            document.removeEventListener('keydown', keydown);
        };
    }, [open, setOpen, trigger]);

    if (!open || typeof document === 'undefined') return null;

    return createPortal(<div ref={element} id={menuId} role="menu" data-slot="dropdown-menu-content"
        style={{ position: 'fixed', top: position.top, left: position.left }}
        className={cn('z-[90] w-64 rounded-xl border border-slate-200 bg-white p-1.5 text-slate-900 shadow-xl outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100', className)}>
        {children}
    </div>, document.body);
}

export function DropdownMenuLabel({ children }: { children: ReactNode }) {
    return <div className="px-2 py-1.5 text-xs font-semibold text-slate-500 dark:text-slate-400">{children}</div>;
}
export function DropdownMenuSeparator() {
    return <div role="separator" className="my-1 border-t border-slate-200 dark:border-slate-700" />;
}
export function DropdownMenuCheckboxItem({
    children, checked, disabled, onCheckedChange, className,
}: {
    children: ReactNode; checked: boolean; disabled?: boolean;
    onCheckedChange: (checked: boolean) => void; className?: string;
}) {
    return <button type="button" role="menuitemcheckbox" aria-checked={checked}
        data-slot="dropdown-menu-checkbox-item" disabled={disabled}
        onClick={() => onCheckedChange(!checked)}
        className={cn('flex min-h-9 w-full items-center gap-2 rounded-md px-2 py-1.5 text-start text-sm hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-amber-500 disabled:cursor-not-allowed disabled:opacity-40 dark:hover:bg-slate-800', className)}>
        <span aria-hidden="true" className="w-4 shrink-0">{checked ? '✓' : ''}</span>
        <span className="min-w-0 truncate">{children}</span>
    </button>;
}
export function DropdownMenuItem({ children, onSelect }: { children: ReactNode; onSelect: () => void }) {
    const { setOpen } = useMenu();
    return <button type="button" role="menuitem" onClick={() => { onSelect(); setOpen(false); }}
        className="flex min-h-9 w-full items-center rounded-md px-3 text-start text-sm hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-amber-500 dark:hover:bg-slate-800">
        {children}
    </button>;
}

type DialogContextValue = { open: boolean; onOpenChange: (open: boolean) => void };
const DialogContext = createContext<DialogContextValue | null>(null);

export function Dialog({ open, onOpenChange, children }: DialogContextValue & { children: ReactNode }) {
    return <DialogContext.Provider value={{ open, onOpenChange }}>{children}</DialogContext.Provider>;
}

export function DialogContent({ children, className }: { children: ReactNode; className?: string }) {
    const dialog = useContext(DialogContext);
    const ref = useRef<HTMLDialogElement>(null);

    useEffect(() => {
        const element = ref.current;
        if (!element || !dialog) return;
        if (dialog.open && !element.open) element.showModal();
        if (!dialog.open && element.open) element.close();
    }, [dialog]);

    if (!dialog?.open || typeof document === 'undefined') return null;
    return createPortal(<dialog ref={ref} aria-label="Record details"
        onCancel={() => dialog.onOpenChange(false)}
        onClose={() => dialog.onOpenChange(false)}
        className={cn('fixed inset-0 m-auto w-[min(36rem,calc(100%-2rem))] rounded-xl border border-slate-200 bg-white p-5 text-slate-900 shadow-2xl backdrop:bg-slate-950/60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100', className)}>
        <button type="button" onClick={() => dialog.onOpenChange(false)} aria-label="Close dialog"
            className="absolute end-3 top-3 rounded-md px-2 py-1 hover:bg-slate-100 dark:hover:bg-slate-800">×</button>
        {children}
    </dialog>, document.body);
}
export function DialogHeader({ children }: { children: ReactNode }) {
    return <div className="mb-4 space-y-1">{children}</div>;
}
export function DialogTitle({ children }: { children: ReactNode }) {
    return <h2 className="text-lg font-semibold">{children}</h2>;
}
export function DialogDescription({ children }: { children: ReactNode }) {
    return <p className="text-sm text-slate-500 dark:text-slate-400">{children}</p>;
}
