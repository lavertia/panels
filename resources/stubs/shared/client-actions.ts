/**
 * Host-controlled client actions. PHP serializes only registered identifiers
 * and JSON-safe params. No eval, arbitrary scripts, or server-sent functions.
 */
export type ClientActionHandler = (params: Readonly<Record<string, unknown>>) => void | Promise<void>;

const registry = new Map<string, ClientActionHandler>();
const ACTION_NAME = /^[A-Za-z][A-Za-z0-9_.-]{0,127}$/;

export function registerClientAction(name: string, handler: ClientActionHandler): () => void {
    if (!ACTION_NAME.test(name)) {
        throw new Error('Invalid Lavertia client action name.');
    }

    registry.set(name, handler);

    // Dispose on frontend module unload/HMR if the host needs to unregister.
    return () => {
        if (registry.get(name) === handler) registry.delete(name);
    };
}

export async function runClientAction(name: string, rawParams: unknown): Promise<void> {
    if (!ACTION_NAME.test(name)) throw new Error('Invalid Lavertia client action name.');

    const handler = registry.get(name);
    if (!handler) throw new Error('Unregistered Lavertia client action: ' + name);

    const params = rawParams !== null && typeof rawParams === 'object' && !Array.isArray(rawParams)
        ? rawParams as Record<string, unknown>
        : {};

    await handler(params);
}
