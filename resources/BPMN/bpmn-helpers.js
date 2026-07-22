import { BPMN_ICONS } from "./bpmn-icons";
import { setConversationFlow } from "./bpmn-arrows";
// ── BPMN element factories ─────────────────────────────────────────────────────
export function addBPMNState(graph, parent, x, y) {
    const vertex = graph.insertVertex({
        parent,
        value: "",
        position: [x, y],
        size: [40, 40],
        style: { baseStyleNames: ["state"] },
    });
    const icon = graph.insertVertex({
        parent: vertex,
        value: BPMN_ICONS.START_EVENT,
        position: [0, 0],
        size: [40, 40],
        style: { baseStyleNames: ["stateIcon"] },
    });
    const g = icon.getGeometry();
    if (g) {
        g.relative = true;
        g.x = 0.5;
        g.y = 0.5;
        g.offset = { x: -20, y: -20 };
        icon.setGeometry(g);
    }
    return vertex;
}
export function addBPMNTask(graph, parent, x, y) {
    const vertex = graph.insertVertex({
        parent,
        value: "",
        position: [x, y],
        size: [100, 80],
        style: { baseStyleNames: ["process"] },
    });
    const icon = graph.insertVertex({
        parent: vertex,
        value: "",
        position: [0, 0],
        size: [26, 26],
        style: { baseStyleNames: ["bpmnIcon"] },
    });
    const g = icon.getGeometry();
    if (g) {
        g.relative = true;
        g.x = 0;
        g.y = 0;
        g.offset = { x: 0, y: -2 };
        icon.setGeometry(g);
    }
    return vertex;
}
export function addBPMNGateway(graph, parent, x, y) {
    const vertex = graph.insertVertex({
        parent,
        value: '',
        position: [x, y],
        size: [40, 40],
        style: { baseStyleNames: ['gateway'] },
    });
    const icon = graph.insertVertex({
        parent: vertex,
        value: BPMN_ICONS.GATEWAY,
        position: [0, 0],
        size: [45, 45],
        style: { baseStyleNames: ["stateIcon"] },
    });
    const g = icon.getGeometry();
    if (g) {
        g.relative = true;
        g.x = 0.5;
        g.y = 0.5;
        g.offset = { x: -23, y: -23 };
        icon.setGeometry(g);
    }
    return vertex;
}
export function addBPMNAnnotation(graph, parent, x, y) {
    const vertex = graph.insertVertex({
        parent,
        value: "",
        position: [x, y],
        size: [100, 80],
        style: { baseStyleNames: ["annotation"] },
    });
    graph.setCellStyles("fillColor", "#FFFFFF", [vertex]);
    return vertex;
}
export function addBPMNConnection(graph, source, target) {
    const edge = graph.insertEdge({
        parent: graph.getDefaultParent(),
        source,
        target,
        style: { baseStyleNames: ["bpmn-edge"] },
    });
    if (isConversationVertex(graph, source) || isConversationVertex(graph, target))
        setConversationFlow(graph, edge);
    return edge;
}
// ── Vertex type predicates ─────────────────────────────────────────────────────
function cellHasBaseStyle(cell, baseStyle) {
    const s = cell?.style;
    if (s && typeof s === "object" && Array.isArray(s.baseStyleNames))
        return s.baseStyleNames.includes(baseStyle);
    return false;
}
export const isProcessVertex = (_graph, cell) => !!cell && cellHasBaseStyle(cell, "process");
export const isStateVertex = (_graph, cell) => !!cell && cellHasBaseStyle(cell, "state");
export const isGatewayVertex = (_graph, cell) => !!cell && cellHasBaseStyle(cell, "gateway");
export const isActivitiesVertex = (_graph, cell) => !!cell && cellHasBaseStyle(cell, "activities");
export const isLaneVertex = (_graph, cell) => !!cell && cellHasBaseStyle(cell, "lane");
export const isConversationVertex = (_graph, cell) => !!cell && cellHasBaseStyle(cell, "conversation");
export const isDataVertex = (_graph, cell) => !!cell && (cellHasBaseStyle(cell, "data") || cellHasBaseStyle(cell, "database"));
// ── Icon helpers ───────────────────────────────────────────────────────────────
function findIconChild(cell) {
    const count = cell.getChildCount();
    for (let i = 0; i < count; i++) {
        const child = cell.getChildAt(i);
        if (!child)
            continue;
        if (cellHasBaseStyle(child, "bpmnIcon") || cellHasBaseStyle(child, "stateIcon"))
            return child;
    }
    return null;
}
export function setIconCellValue(graph, processVertex, value) {
    const iconCell = findIconChild(processVertex);
    if (!iconCell)
        return;
    graph.batchUpdate(() => graph.model.setValue(iconCell, value));
}
export function setDatabaseVertex(graph, cell) {
    const iconCell = findIconChild(cell);
    if (!iconCell)
        return;
    const style = cell.getClonedStyle();
    style.baseStyleNames = ["database"];
    graph.batchUpdate(() => {
        graph.setCellStyle(style, [cell]);
        graph.model.setValue(iconCell, "");
    });
}
export function setDataVertex(graph, cell) {
    const iconCell = findIconChild(cell);
    if (!iconCell)
        return;
    const style = cell.getClonedStyle();
    style.baseStyleNames = ["data"];
    graph.batchUpdate(() => {
        graph.setCellStyle(style, [cell]);
        graph.model.setValue(iconCell, "");
    });
}
export function setInputDataVertex(graph, cell) {
    const iconCell = findIconChild(cell);
    if (!iconCell)
        return;
    const style = cell.getClonedStyle();
    style.baseStyleNames = ["data"];
    graph.batchUpdate(() => {
        graph.setCellStyle(style, [cell]);
        graph.model.setValue(iconCell, BPMN_ICONS.DATA_INPUT);
    });
}
export function setOutputDataVertex(graph, cell) {
    const iconCell = findIconChild(cell);
    if (!iconCell)
        return;
    const style = cell.getClonedStyle();
    style.baseStyleNames = ["data"];
    graph.batchUpdate(() => {
        graph.setCellStyle(style, [cell]);
        graph.model.setValue(iconCell, BPMN_ICONS.DATA_OUTPUT);
    });
}
