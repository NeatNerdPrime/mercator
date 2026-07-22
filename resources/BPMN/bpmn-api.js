const API_HEADERS = {
    "Accept": "application/json",
    "X-Requested-With": "XMLHttpRequest",
};
function isBpmnObject(o) {
    return (typeof o === "object" &&
        o !== null &&
        "id" in o &&
        "name" in o &&
        "url" in o);
}
async function fetchBpmnObjects(endpoint) {
    const res = await fetch(endpoint, {
        method: "GET",
        headers: API_HEADERS,
        credentials: "same-origin",
    });
    if (!res.ok) {
        const body = await res.text().catch(() => "");
        throw new Error(`GET ${endpoint} failed (${res.status}): ${body}`);
    }
    const data = await res.json();
    if (!Array.isArray(data))
        throw new Error("Unexpected response: expected an array");
    return data.filter(isBpmnObject).map((o) => ({
        id: o.id,
        name: o.name,
        glyph: o.id[0],
        url: o.url,
    }));
}
export const fetchGraphObjects = () => fetchBpmnObjects("/admin/bpmn/objects");
export const fetchInformationObjects = () => fetchBpmnObjects("/admin/bpmn/information");
export const fetchActorObjects = () => fetchBpmnObjects("/admin/bpmn/actors");
export const fetchProcessObjects = () => fetchBpmnObjects("/admin/bpmn/process");
