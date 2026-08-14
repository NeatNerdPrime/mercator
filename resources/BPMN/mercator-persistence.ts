// resources/BPMN/mercator-persistence.ts
// Implements @sourcentis/bpmn-editor's BpmnPersistence port against
// Mercator's own /admin/bpmn/{id} endpoint. CSRF, credentials, _method
// override, and reflecting the saved id back into the page (#id input +
// history.replaceState for a first save) all live here — none of it is
// known to the editor package itself.
import type { BpmnPersistence, BpmnPersistencePayload, BpmnPersistenceResult } from '@sourcentis/bpmn-editor';

export const MercatorBpmnPersistence: BpmnPersistence = {
    async save({ id, name, type, content }: BpmnPersistencePayload): Promise<BpmnPersistenceResult> {
        if (!name || name.trim() === '') {
            throw new Error('Le nom du graphe est obligatoire.');
        }

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
            } catch {
                // ignore — keep the generic message
            }
            throw new Error(errorMsg);
        }

        const data = await response.json();
        const graphId: number = data.graph_id;

        const idInput = document.getElementById('id') as HTMLInputElement | null;
        if (idInput && graphId) idInput.value = graphId.toString();

        if (id === -1 && graphId) {
            window.history.replaceState({}, '', `/admin/bpmn/${graphId}`);
        }

        return { id: graphId };
    },
};
