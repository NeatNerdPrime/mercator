// bpmn-api.ts
import { BpmnElementDef } from "./bpmn-menu-select";

const API_HEADERS = {
    "Accept": "application/json",
    "X-Requested-With": "XMLHttpRequest",
} as const;

function isBpmnObject(o: unknown): o is { id: string; name: string; url: string } {
    return (
        typeof o === "object" &&
        o !== null &&
        "id" in o &&
        "name" in o &&
        "url" in o
    );
}

async function fetchBpmnObjects(endpoint: string): Promise<BpmnElementDef[]> {
    const res = await fetch(endpoint, {
        method: "GET",
        headers: API_HEADERS,
        credentials: "same-origin",
    });

    if (!res.ok) {
        const body = await res.text().catch(() => "");
        throw new Error(`GET ${endpoint} failed (${res.status}): ${body}`);
    }

    const data: unknown = await res.json();
    if (!Array.isArray(data)) throw new Error("Unexpected response: expected an array");

    return data.filter(isBpmnObject).map((o): BpmnElementDef => ({
        id:    o.id,
        name:  o.name,
        glyph: o.id[0],
        url:   o.url,
    }));
}

export const fetchGraphObjects       = (): Promise<BpmnElementDef[]> => fetchBpmnObjects("/admin/bpmn/objects");
export const fetchInformationObjects = (): Promise<BpmnElementDef[]> => fetchBpmnObjects("/admin/bpmn/information");
export const fetchActorObjects       = (): Promise<BpmnElementDef[]> => fetchBpmnObjects("/admin/bpmn/actors");
export const fetchProcessObjects     = (): Promise<BpmnElementDef[]> => fetchBpmnObjects("/admin/bpmn/process");
