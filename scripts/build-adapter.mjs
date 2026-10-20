import { mkdir, copyFile, writeFile, rm } from 'node:fs/promises';
import { resolve, dirname } from 'node:path';
import { build } from 'vite';
import react from '@vitejs/plugin-react';
import vue from '@vitejs/plugin-vue';
import { svelte } from '@sveltejs/vite-plugin-svelte';

const options = {
    react: { extension: 'tsx', plugin: react },
    vue: { extension: 'vue', plugin: vue },
    svelte: { extension: 'svelte', plugin: svelte },
};

const stack = process.argv[2];

if (!Object.hasOwn(options, stack)) {
    throw new Error('Expected stack: react, vue or svelte');
}

const { extension, plugin } = options[stack];
const projectRoot = process.cwd();
const root = resolve(projectRoot, '.lavertia-smoke', stack);
const targetPage = resolve(root, 'resources/js/pages/lavertia/page.' + extension);
const targetRenderer = resolve(root, 'resources/js/components/lavertia/renderer.' + extension);
const targetTypes = resolve(root, 'resources/js/components/lavertia/types.ts');

await mkdir(dirname(targetPage), { recursive: true });
await mkdir(dirname(targetRenderer), { recursive: true });

await copyFile(resolve(projectRoot, 'resources/stubs', stack, 'page.' + extension), targetPage);
await copyFile(resolve(projectRoot, 'resources/stubs', stack, 'renderer.' + extension), targetRenderer);
await copyFile(resolve(projectRoot, 'resources/stubs/shared/types.ts'), targetTypes);
await copyFile(resolve(projectRoot, 'resources/stubs/shared/form-state.ts'), resolve(root, 'resources/js/components/lavertia/form-state.ts'));
await writeFile(resolve(root, 'entry.ts'), "import Page from './resources/js/pages/lavertia/page." + extension + "'; void Page;\n");
await writeFile(resolve(root, 'index.html'), '<!doctype html><html><body><div id="app"></div><script type="module" src="/entry.ts"></script></body></html>');

try {
    await build({
        root,
        plugins: [plugin()],
        logLevel: 'info',
        build: {
            outDir: resolve(projectRoot, '.lavertia-dist', stack),
            emptyOutDir: true,
        },
    });

    console.log('Lavertia ' + stack + ' adapter compiled successfully.');
} finally {
    await rm(root, { recursive: true, force: true });
}
