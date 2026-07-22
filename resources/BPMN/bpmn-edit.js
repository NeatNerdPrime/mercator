// src/bpmn-edit.ts
import { Client, Graph, InternalEvent, RubberBandHandler, UndoManager, } from '@maxgraph/core';
import { applyGraphStyles } from './graph-styles';
import { downloadSvg, embedFontInSvg, exportGraphToSvg } from "./bpmn-svg";
import { addBPMNAnnotation, addBPMNGateway, addBPMNState, addBPMNTask } from "./bpmn-helpers";
function insertLane(graph, parent, x, y) {
    const v = graph.insertVertex({ parent, value: 'Lane', position: [x, y], size: [600, 150], style: { baseStyleNames: ['lane'] } });
    graph.orderCells(true, [v]);
}
function insertActivities(graph, parent, x, y) {
    graph.insertVertex({ parent, value: '', position: [x, y], size: [300, 200], style: { baseStyleNames: ['activities'] } });
}
function insertData(graph, parent, x, y) {
    const v = graph.insertVertex({ parent, value: '', position: [x, y], size: [60, 80], style: { baseStyleNames: ['data'] } });
    graph.insertVertex({ parent: v, value: '', position: [0, 0], size: [26, 26], style: { baseStyleNames: ['bpmnIcon'] } });
}
function insertConversation(graph, parent, x, y) {
    graph.insertVertex({ parent, value: '', position: [x, y], size: [40, 40], style: { baseStyleNames: ['conversation'] } });
}
const DROP_CONFIGS = {
    'task-node': { inserter: addBPMNTask, useLane: true },
    'state-node': { inserter: addBPMNState, useLane: true },
    'gateway-node': { inserter: addBPMNGateway, useLane: true },
    'data-node': { inserter: insertData, useLane: true },
    'activities-node': { inserter: insertActivities, useLane: true },
    'annotation-node': { inserter: addBPMNAnnotation, useLane: false },
    'lane-node': { inserter: insertLane, useLane: false },
    'conversation-node': { inserter: insertConversation, useLane: false },
};
function isLaneOrActivities(cell) {
    const names = cell?.style?.baseStyleNames ?? [];
    return names.includes('lane') || names.includes('activities');
}
function resolveDropTarget(graph, event, useLane) {
    const pt = graph.getPointForEvent(event);
    const defaultParent = graph.getDefaultParent();
    if (!useLane)
        return { parent: defaultParent, x: pt.x, y: pt.y };
    const dropCell = graph.getCellAt(event.offsetX, event.offsetY);
    const laneCell = isLaneOrActivities(dropCell) ? dropCell : null;
    const parent = laneCell ?? defaultParent;
    let { x, y } = pt;
    if (laneCell) {
        const state = graph.getView()?.getState?.(laneCell);
        if (state?.origin) {
            x -= state.origin.x;
            y -= state.origin.y;
        }
        y = Math.max(y, 30); // empêche le drop dans l'en-tête de lane
    }
    return { parent, x, y };
}
function registerDragSource(btnId, nodeType) {
    document.getElementById(btnId)?.addEventListener('dragstart', (e) => {
        e.dataTransfer?.setData('node-type', nodeType);
    });
}
function installDropHandlers(graph, container) {
    // Autoriser le drop sur le container
    container.addEventListener('dragover', (e) => e.preventDefault());
    // Enregistrer les sources de drag
    registerDragSource('task-btn', 'task-node');
    registerDragSource('state-btn', 'state-node');
    registerDragSource('gateway-btn', 'gateway-node');
    registerDragSource('data-btn', 'data-node');
    registerDragSource('lane-btn', 'lane-node');
    registerDragSource('activities-btn', 'activities-node');
    registerDragSource('annotation-btn', 'annotation-node');
    registerDragSource('conversation-btn', 'conversation-node');
    container.addEventListener('drop', (event) => {
        event.preventDefault();
        const nodeType = event.dataTransfer?.getData('node-type');
        if (!nodeType)
            return;
        const config = DROP_CONFIGS[nodeType];
        if (!config)
            return;
        const { parent, x, y } = resolveDropTarget(graph, event, config.useLane);
        graph.batchUpdate(() => config.inserter(graph, parent, x, y));
    });
}
// ── Editor initialisation ──────────────────────────────────────────────────────
export function initBpmnEditor(containerId = 'graph-container') {
    Client.imageBasePath = '/dist/images';
    const container = document.getElementById(containerId);
    if (!container)
        throw new Error(`#${containerId} introuvable`);
    container.tabIndex = 0;
    const graph = new Graph(container);
    graph.gridSize = 10;
    graph.gridEnabled = true;
    applyGraphStyles(graph);
    graph.setDropEnabled(true);
    graph.setPanning(true);
    graph.setConnectable(false);
    graph.setCellsEditable(true);
    graph.setCellsResizable(true);
    graph.setCellsMovable(true);
    graph.setAllowDanglingEdges(false);
    graph.setDisconnectOnMove(false);
    graph.setSplitEnabled(false);
    graph.setHtmlLabels(true);
    new RubberBandHandler(graph);
    // Undo
    const undoManager = new UndoManager();
    undoManager.__suspended = false;
    const undoListener = (_sender, evt) => {
        if (undoManager.__suspended)
            return;
        undoManager.undoableEditHappened(evt.getProperty('edit'));
    };
    graph.getDataModel().addListener(InternalEvent.UNDO, undoListener);
    graph.getView().addListener(InternalEvent.UNDO, undoListener);
    // Double-clic sur icône d'état → édite le parent
    graph.addListener(InternalEvent.DOUBLE_CLICK, (_sender, evt) => {
        const cell = evt.getProperty("cell");
        if (!cell)
            return;
        hideMenu();
        if (cell.style?.baseStyleNames?.includes("stateIcon")) {
            const parent = cell.parent;
            if (parent) {
                graph.startEditingAtCell(parent);
                evt.consume();
            }
        }
    });
    // Déplacement du label uniquement pour "state" et "gateway"
    const baseIsLabelMovable = graph.isLabelMovable?.bind(graph);
    graph.isLabelMovable = (cell) => {
        const names = cell?.style?.baseStyleNames;
        if (names?.includes('state') || names?.includes('gateway'))
            return true;
        return baseIsLabelMovable ? baseIsLabelMovable(cell) : false;
    };
    // Pas de sélection pour les icônes internes
    const prevIsCellSelectable = graph.isCellSelectable?.bind(graph);
    graph.isCellSelectable = (cell) => {
        if (!cell)
            return false;
        const names = cell?.style?.baseStyleNames ?? [];
        if (names.includes('stateIcon') || names.includes('bpmnIcon') || names.includes('bpmnBadge'))
            return false;
        return prevIsCellSelectable ? prevIsCellSelectable(cell) : true;
    };
    // Autoriser le drop uniquement dans les lanes
    graph.getDropTarget = function (cells, _evt, cell) {
        if (cell?.style?.baseStyleNames?.includes?.("lane"))
            return cell;
        return null;
    };
    installDropHandlers(graph, container);
    return { graph, undoManager };
}
// ── UI wiring ──────────────────────────────────────────────────────────────────
export function wireEditorUi(graph, undoManager) {
    graph.container.addEventListener('pointerdown', () => {
        graph.container.focus();
    });
    document.getElementById('download-svg')?.addEventListener('click', async () => {
        const svg = exportGraphToSvg(graph);
        await embedFontInSvg(svg, {
            fontUrl: "/vendor/mercator-bpmn/fonts/bpmn.ttf",
            fontFamily: "bpmn",
            mime: "font/ttf",
        });
        downloadSvg(svg, "bpmn-export.svg");
    });
    document.getElementById('zoom-in-btn')?.addEventListener('click', () => graph.zoomIn());
    document.getElementById('zoom-out-btn')?.addEventListener('click', () => graph.zoomOut());
    document.getElementById('fit-in-btn')?.addEventListener('click', () => graph.center());
    const undoButton = document.getElementById('undoButton');
    const redoButton = document.getElementById('redoButton');
    undoButton?.addEventListener('click', () => { if (undoManager.canUndo())
        undoManager.undo(); });
    redoButton?.addEventListener('click', () => { if (undoManager.canRedo())
        undoManager.redo(); });
    graph.container.addEventListener('keydown', (event) => {
        if (event.ctrlKey && event.key.toLowerCase() === 'z') {
            event.preventDefault();
            event.stopPropagation();
            if (undoManager.canUndo())
                undoManager.undo();
            return;
        }
        if (event.ctrlKey && event.key.toLowerCase() === 'y') {
            event.preventDefault();
            event.stopPropagation();
            if (undoManager.canRedo())
                undoManager.redo();
            return;
        }
        if (event.ctrlKey && event.key.toLowerCase() === 'a') {
            event.preventDefault();
            event.stopPropagation();
            graph.selectAll();
            return;
        }
        if (event.key === 'Delete' || event.key === 'Backspace') {
            if (graph.isEditing())
                return;
            event.preventDefault();
            event.stopPropagation();
            const cells = graph.getSelectionCells();
            if (!cells?.length)
                return;
            graph.getDataModel().beginUpdate();
            try {
                graph.removeCells(cells, true);
            }
            finally {
                graph.getDataModel().endUpdate();
            }
        }
    });
}
export function hideMenu() {
    document.getElementById("vertex-menu")?.classList.add("hidden");
}
export function enableArrowKeyMovement(graph, step = 10) {
    graph.container.addEventListener('keydown', (event) => {
        if (graph.isEditing())
            return;
        let dx = 0;
        let dy = 0;
        switch (event.key) {
            case 'ArrowUp':
                dy = -step;
                break;
            case 'ArrowDown':
                dy = step;
                break;
            case 'ArrowLeft':
                dx = -step;
                break;
            case 'ArrowRight':
                dx = step;
                break;
            default: return;
        }
        hideMenu();
        event.preventDefault();
        const cells = graph.getSelectionCells();
        if (!cells?.length)
            return;
        graph.batchUpdate(() => {
            for (const cell of cells) {
                if (!cell?.isVertex?.())
                    continue;
                const geo = cell.getGeometry();
                if (!geo)
                    continue;
                const newGeo = geo.clone();
                newGeo.translate(dx, dy);
                graph.model.setGeometry(cell, newGeo);
            }
        });
    });
}
export function showStatus(message, duration = 2000) {
    const status = document.getElementById('status');
    if (!status)
        return;
    status.textContent = message;
    status.classList.add('show');
    setTimeout(() => status.classList.remove('show'), duration);
}
