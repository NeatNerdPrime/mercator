// vite.config.js
import {defineConfig} from 'vite';
import path from 'path';
import laravel from 'laravel-vite-plugin';
import {viteStaticCopy} from 'vite-plugin-static-copy';
import fs from 'fs';

const version = fs.readFileSync('version.txt', 'utf-8').trim();

export default defineConfig({
    define: {
        'process.env.APP_VERSION': JSON.stringify(version),
    },

    server: {
        host: 'localhost',
        port: 5173,
    },

    plugins: [
        laravel({
            input: [
                // Core
                'resources/js/app.js',
                'resources/css/app.css',
                // Charts
                'resources/charts/chart-home.js',
                'resources/charts/chart-maturity.js',
                'resources/charts/chart-relation.js',
                'resources/charts/chart-patching.js',
                // Mapping
                'resources/css/mapping.css',
                // D3 / Viz
                'resources/js/graphviz.js',
                'resources/js/vis-network.js',
                // Maps
                'resources/graphs/map.show.ts',
                'resources/graphs/map.edit.ts',
                // BPMN (ex-package autonome)
                'resources/BPMN/bpmn.ts',
                'resources/BPMN/bpmn-show.ts',
                // Parser
                'resources/js/sql-parser.js',
            ],
            refresh: true,
        }),
        viteStaticCopy({
            targets: [
                {
                    src: 'resources/fonts/bpmn.ttf',
                    dest: 'fonts',
                },
            ],
        }),
    ],

    resolve: {
        // @maxgraph/core: without this, Vite bundles it twice — once from
        // Mercator's own node_modules, once from the locally-linked
        // @sourcentis/bpmn-editor package's own node_modules (it lists
        // @maxgraph/core as a peerDependency, but also as a devDependency
        // for its own standalone build/tests, and npm's `file:` link
        // preserves the symlink so Node resolution finds that copy too).
        // Two separate MaxGraph module instances in one bundle risks real
        // bugs (shared static state, registries, instanceof checks not
        // agreeing across the two copies) — dedupe forces a single instance.
        dedupe: ['chart.js', '@maxgraph/core'],
        alias: {
            '@': '/resources/js',
            '@sourcentis/chartjs-gauge': path.resolve(
                __dirname,
                'vendor/sourcentis/chartjs-gauge/js/index.js'
            ),
        },
    },

    build: {
        sourcemap: true,
        target: 'esnext',
        chunkSizeWarningLimit: 5000,
    },
});
