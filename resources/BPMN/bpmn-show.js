// src/bpmn-show.ts
import { initBpmnEditor } from './bpmn-edit';
import { installEdgeRules } from "./bpmn-arrows";
import { InternalEvent, ShapeRegistry } from "@maxgraph/core";
import { BpmnDataObjectShape } from "./bpmn-shapes";
import { exposeGraphHelpers } from "./bpmn-save";
// Init éditeur (graph + undo/redo)
const { graph } = initBpmnEditor('graph-container');
// Dashed edges for database vertices
installEdgeRules(graph);
// Register shapes
ShapeRegistry.add("bpmnDataObject", BpmnDataObjectShape);
// Pour load/save
exposeGraphHelpers(graph);
// Disable graph for show mode
graph.setEnabled(false);
graph.setCellsSelectable(true);
// Zoom à la molette
InternalEvent.addMouseWheelListener((evt, up) => {
    if (up)
        graph.zoomIn();
    else
        graph.zoomOut();
    InternalEvent.consume(evt);
}, graph.container);
// Cursor sur les objets avec URL
graph.container.addEventListener("mousemove", (e) => {
    const rect = graph.container.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    const cell = graph.getCellAt(x, y);
    graph.container.style.cursor = cell?.url ? "pointer" : "default";
});
// Click sur les objets avec une URL
graph.addListener(InternalEvent.CLICK, (_sender, evt) => {
    const cell = evt.getProperty("cell");
    if (!cell || !cell.isVertex())
        return;
    if (cell.url)
        window.location.href = cell.url;
});
// Ajuster la taille du conteneur après chargement du graphe
function adjustContainerSize(graph, padding = 30) {
    const bounds = graph.getGraphBounds();
    const container = graph.container;
    if (!bounds || !container)
        return;
    const finalHeight = Math.max(200, bounds.y + bounds.height + padding);
    container.style.height = `${finalHeight}px`;
    container.style.width = "100%";
}
// Wrapper pour la fonction loadGraph exposée par exposeGraphHelpers
const originalLoadGraph = window.loadGraph;
if (originalLoadGraph) {
    window.loadGraph = function (xmlContent) {
        originalLoadGraph(xmlContent);
        setTimeout(() => adjustContainerSize(graph, 30), 150);
    };
}
