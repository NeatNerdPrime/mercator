import { PanningHandler, } from '@maxgraph/core';
export class BackgroundOnlyPanningHandler extends PanningHandler {
    constructor(graph) {
        super(graph);
        this.useLeftButtonForPanning = true;
        this.usePopupTrigger = false;
        this.ignoreCell = false;
        // consumePanningTrigger est une méthode dans cette version de MaxGraph — pas d'affectation booléenne
    }
    isPanningTrigger(me) {
        if (me.getEvent().button !== 0)
            return false;
        if (me.getCell() == null) {
            return true;
        }
        return false;
    }
    mouseDown(sender, me) {
        if (this.isPanningTrigger(me)) {
            super.mouseDown(sender, me);
            me.consume();
        }
    }
    mouseMove(sender, me) {
        if (this.active) {
            super.mouseMove(sender, me);
            me.consume();
        }
    }
    mouseUp(sender, me) {
        if (this.active) {
            super.mouseUp(sender, me);
            me.consume();
            this.reset();
        }
    }
    reset() {
        this.graph.getPlugin('SelectionCellsHandler')?.reset();
        this.graph.getPlugin('RubberBandHandler')?.reset();
    }
}
