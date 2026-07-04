import { Graph } from '@maxgraph/core';

export function initCopyPaste(graph: Graph): void {
    let clipboard: any[] = [];

    document.addEventListener('keydown', (evt: KeyboardEvent) => {
        if (graph.isEditing()) return;

        const ctrl = evt.ctrlKey || evt.metaKey;

        if (ctrl && evt.key === 'c') {
            const cells = graph.getSelectionCells();
            if (cells.length > 0) {
                clipboard = graph.cloneCells(cells);
                evt.preventDefault();
            }
        } else if (ctrl && evt.key === 'v') {
            if (clipboard.length > 0) {
                graph.model.beginUpdate();
                try {
                    const parent      = graph.getDefaultParent();
                    const pastedCells = graph.addCells(clipboard, parent);
                    graph.moveCells(pastedCells, 20, 20);
                    graph.setSelectionCells(pastedCells);
                    clipboard = graph.cloneCells(clipboard);
                } finally {
                    graph.model.endUpdate();
                }
                evt.preventDefault();
            }
        } else if (ctrl && evt.key === 'x') {
            const cells = graph.getSelectionCells();
            if (cells.length > 0) {
                clipboard = graph.cloneCells(cells);
                graph.removeCells(cells);
                evt.preventDefault();
            }
        }
    });
}
