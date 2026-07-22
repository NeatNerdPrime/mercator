// src/bpmn-save.ts
import { ModelXmlSerializer } from '@maxgraph/core';
import { showStatus } from './bpmn-edit';
export function loadGraphXml(graph, xml) {
    new ModelXmlSerializer(graph.model).import(xml);
}
export function getXMLGraph(graph) {
    return new ModelXmlSerializer(graph.model).export();
}
export async function saveGraphToDatabase(id, name, type, content) {
    if (!name || name.trim() === '') {
        const errorMsg = 'Le nom du graphe est obligatoire.';
        alert(errorMsg);
        throw new Error(errorMsg);
    }
    try {
        const response = await fetch(`/admin/bpmn/${id}`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                    ?.getAttribute('content') ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ _method: 'PUT', id, name, type, content }),
        });
        if (response.status !== 200) {
            let errorMsg = 'Erreur lors de la sauvegarde du graphe.';
            try {
                const error = await response.json();
                errorMsg = error.message || errorMsg;
            }
            catch (_) { /* ignore */ }
            throw new Error(errorMsg);
        }
        const data = await response.json();
        const graphId = data.graph_id;
        const idInput = document.getElementById('id');
        if (idInput && graphId)
            idInput.value = graphId.toString();
        if (id === -1 && graphId)
            window.history.replaceState({}, '', `/admin/bpmn/${graphId}`);
        showStatus('✓ Graphe sauvegardé', 2000);
        return graphId;
    }
    catch (error) {
        alert('Erreur lors de la sauvegarde du graphe.');
        throw error;
    }
}
export function bindSaveButton(graph, buttonId = 'save-btn') {
    const btn = document.getElementById(buttonId);
    if (!btn)
        return;
    btn.addEventListener('click', () => {
        const id = Number(document.querySelector('#id')?.value);
        const name = document.querySelector('#name')?.value ?? '';
        const type = document.querySelector('#type')?.value ?? '';
        const xml = getXMLGraph(graph);
        if (!xml) {
            showStatus('✗ Impossible de générer le BPMN', 3000);
            return;
        }
        saveGraphToDatabase(id, name, type, xml);
    });
}
export function exposeGraphHelpers(graph) {
    window.loadGraph = (xml) => loadGraphXml(graph, xml);
    window.getXMLGraph = () => getXMLGraph(graph);
}
