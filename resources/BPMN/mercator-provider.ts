// resources/BPMN/mercator-provider.ts
// Implements @sourcentis/bpmn-editor's BpmnObjectProvider port against
// Mercator's own cartography endpoints. All Laravel/Mercator-specific
// concerns (routes, headers, credentials) live here — the editor package
// itself knows nothing about any of this.
import type { BpmnElementDef, BpmnObjectProvider } from '@sourcentis/bpmn-editor';

const API_HEADERS = {
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
} as const;

function isBpmnObject(o: unknown): o is { id: string; name: string; url: string } {
    return (
        typeof o === 'object' &&
        o !== null &&
        'id' in o &&
        'name' in o &&
        'url' in o
    );
}

async function fetchBpmnObjects(endpoint: string): Promise<BpmnElementDef[]> {
    const res = await fetch(endpoint, {
        method: 'GET',
        headers: API_HEADERS,
        credentials: 'same-origin',
    });

    if (!res.ok) {
        const body = await res.text().catch(() => '');
        throw new Error(`GET ${endpoint} failed (${res.status}): ${body}`);
    }

    const data: unknown = await res.json();
    if (!Array.isArray(data)) throw new Error('Unexpected response: expected an array');

    return data.filter(isBpmnObject).map((o): BpmnElementDef => ({
        id:    o.id,
        name:  o.name,
        glyph: o.id[0],
        url:   o.url,
    }));
}

export const MercatorBpmnProvider: BpmnObjectProvider = {
    getGraphObjects:       () => fetchBpmnObjects('/admin/bpmn/objects'),
    getInformationObjects: () => fetchBpmnObjects('/admin/bpmn/information'),
    getActorObjects:       () => fetchBpmnObjects('/admin/bpmn/actors'),
    getProcessObjects:     () => fetchBpmnObjects('/admin/bpmn/process'),
};
