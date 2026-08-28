// resources/BPMN/bpmn.ts
// Thin adapter: wires @sourcentis/bpmn-editor (mounted with `ui: 'none'` so
// it reuses Mercator's own Bootstrap sidebar/toolbar instead of building its
// own) to this page's DOM and to Mercator's provider/persistence
// implementations. All Laravel/CSRF/routing concerns live in
// mercator-provider.ts / mercator-persistence.ts — nothing here or in the
// editor package itself knows about them.
import { createBpmnEditor } from '@sourcentis/bpmn-editor';
import { MercatorBpmnProvider } from './mercator-provider';
import { MercatorBpmnPersistence } from './mercator-persistence';

declare global {
    interface Window {
        loadGraph?: (xml: string) => void;
        getXMLGraph?: () => string;
    }
}

function showStatus(message: string, duration = 2000): void {
    const status = document.getElementById('status');
    if (!status) return;
    status.textContent = message;
    status.classList.add('show');
    setTimeout(() => status.classList.remove('show'), duration);
}

const graphContainer = document.getElementById('graph-container');
if (!graphContainer) throw new Error('#graph-container introuvable');

const editor = createBpmnEditor(graphContainer, {
    ui: 'none',
    // Mercator's own sidebar buttons (#task-btn, #state-btn, …) carry
    // data-node-type attributes matching the editor's drag-to-insert
    // contract — see resources/views/bpmn/edit.blade.php.
    paletteRoot: document.getElementById('bpmn-sidebar'),
    provider: MercatorBpmnProvider,
    persistence: MercatorBpmnPersistence,
    onNavigate: (url) => { window.location.href = url; },
});

editor.on('error', (err) => console.error('[bpmn]', err));

// ── Toolbar wiring — Mercator owns this chrome, drives the editor purely
// through its public instance API ───────────────────────────────────────
document.getElementById('zoom-in-btn')?.addEventListener('click', () => editor.zoomIn());
document.getElementById('zoom-out-btn')?.addEventListener('click', () => editor.zoomOut());

document.getElementById('download-svg')?.addEventListener('click', () => {
    void editor.exportSvg('bpmn-export.svg');
});

document.getElementById('download-btn')?.addEventListener('click', () => {
    try {
        const xml = editor.exportBpmnXml();
        const name = (document.querySelector('#name') as HTMLInputElement | null)?.value || 'bpmn-export';

        const blob = new Blob([xml], { type: 'application/xml' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `${name}.bpmn`;
        link.click();
        URL.revokeObjectURL(url);
    } catch (error) {
        console.error(error);
        showStatus('✗ Erreur lors de l\'export BPMN', 3000);
    }
});

const fileInput = document.getElementById('file-input') as HTMLInputElement | null;
fileInput?.addEventListener('change', (e: Event) => {
    void (async () => {
        const target = e.target as HTMLInputElement;
        const file = target.files?.[0];
        if (!file) return;

        try {
            const text = await file.text();
            editor.importBpmnXml(text);
            showStatus('✓ Fichier chargé avec succès');
        } catch (error) {
            console.error(error);
            showStatus('✗ Erreur lors du chargement du fichier');
        } finally {
            target.value = '';
        }
    })();
});

document.getElementById('save-btn')?.addEventListener('click', () => {
    void (async () => {
        const id      = Number((document.querySelector('#id')   as HTMLInputElement | null)?.value);
        const name    = (document.querySelector('#name') as HTMLInputElement | null)?.value ?? '';
        const type    = (document.querySelector('#type') as HTMLInputElement | null)?.value ?? '';
        const content = editor.getXml();

        if (!content) {
            showStatus('✗ Impossible de générer le BPMN', 3000);
            return;
        }

        try {
            await MercatorBpmnPersistence.save({ id, name, type, content });
            showStatus('✓ Graphe sauvegardé', 2000);
        } catch (error) {
            alert(error instanceof Error ? error.message : 'Erreur lors de la sauvegarde du graphe.');
        }
    })();
});

// ── Compat shims for the inline blade <script> (form-submit fallback save
// path and the initial `loadGraph($content)` call) ──────────────────────
window.loadGraph   = (xml: string) => editor.loadXml(xml);
window.getXMLGraph = () => editor.getXml();

showStatus('👋 Bienvenue ! Charge un fichier BPMN', 3000);
