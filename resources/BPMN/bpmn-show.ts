// resources/BPMN/bpmn-show.ts
// Thin adapter for the read-only "show" page. `readOnly: true` gets the
// editor package to handle everything the old hand-rolled bootstrap used to
// do itself: disabled editing, wheel zoom, cursor + click-to-navigate on
// cells with a url, and auto-resizing the container to fit the loaded
// diagram. `ui: 'none'` — this page has no toolbar chrome at all.
import { createBpmnEditor } from '@sourcentis/bpmn-editor';

declare global {
    interface Window {
        loadGraph?: (xml: string) => void;
        getXMLGraph?: () => string;
    }
}

const graphContainer = document.getElementById('graph-container');
if (!graphContainer) throw new Error('#graph-container introuvable');

const editor = createBpmnEditor(graphContainer, {
    ui: 'none',
    readOnly: true,
    onNavigate: (url) => { window.location.href = url; },
});

editor.on('error', (err) => console.error('[bpmn-show]', err));

// Compat shim for the inline blade <script> (`loadGraph($graph)` call).
window.loadGraph   = (xml: string) => editor.loadXml(xml);
window.getXMLGraph = () => editor.getXml();
