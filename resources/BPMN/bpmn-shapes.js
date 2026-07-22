import { Shape } from "@maxgraph/core";
export class BpmnDataObjectShape extends Shape {
    paintVertexShape(c, x, y, w, h) {
        const fold = Math.min(w, h) * 0.2;
        c.begin();
        c.moveTo(x, y);
        c.lineTo(x + w - fold, y);
        c.lineTo(x + w, y + fold);
        c.lineTo(x + w, y + h);
        c.lineTo(x, y + h);
        c.close();
        c.fillAndStroke();
        // coin replié
        c.begin();
        c.moveTo(x + w - fold, y);
        c.lineTo(x + w - fold, y + fold);
        c.lineTo(x + w, y + fold);
        c.stroke();
    }
}
