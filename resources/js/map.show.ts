import {
    Cell,
    type CellStateStyle,
    EventObject,
    Graph,
    GraphDataModel,
    InternalEvent,
    ModelXmlSerializer,
} from '@maxgraph/core';
import { downloadGraphSVG } from './svg-export';

//-----------------------------------------------------------------------
// Interfaces métier

interface MapNode {
    id: string;
    vue: string;
    label: string;
    image: string;
    type: string;
}

type NodeMap = Map<string, MapNode>;

declare const _nodes: NodeMap;

// Drapeau métier ajouté au style MaxGraph (absent de CellStateStyle).
interface AppCellStyle extends CellStateStyle {
    isBackground?: boolean;
}

//-----------------------------------------------------------------------
// Initialisation du graphe

const container = document.getElementById('graph-container') as HTMLDivElement | null;
if (!container) {
    throw new Error('#graph-container introuvable');
}

const graph = new Graph(container, new GraphDataModel());
const model = graph.getDataModel();

graph.setEnabled(false);
InternalEvent.disableContextMenu(container);

//-----------------------------------------------------------------------
// Style des sommets

// Le curseur "pointer" sur les sommets-image est géré manuellement via le
// mouseListener ci-dessous (CellStateStyle n'a pas de propriété "cursor" :
// elle ne serait pas prise en compte par le rendu).

// Pas d'icône de pliage/dépliage sur cette vue lecture seule, quel que soit
// l'état (replié ou non) du groupe — contrairement à options.expandedImage,
// qui ne masque que l'icône des groupes dépliés.
graph.getFoldingImage = () => null;

//-----------------------------------------------------------------------
// Style des arêtes

const edgeDefaultStyle = graph.getStylesheet().getDefaultEdgeStyle();
edgeDefaultStyle.labelBackgroundColor = '#FFFFFF';
edgeDefaultStyle.strokeWidth  = 2;
edgeDefaultStyle.rounded      = false;
edgeDefaultStyle.entryPerimeter = false;

//-------------------------------------------------------------------------
// LOAD

const FIT_MARGIN = 20;

// Zoom pour que tous les objets soient visibles, alignés en haut à gauche du
// conteneur. Calcul manuel (plutôt que FitPlugin.fit()) pour garder un
// contrôle exact en pixels sur la marge, quel que soit le niveau de zoom.
function fitTopLeft(margin: number): void {
    const view   = graph.getView();
    const bounds = graph.getGraphBounds();
    if (!bounds || bounds.width <= 0 || bounds.height <= 0) return;

    const originalScale = view.scale || 1;
    const modelWidth     = bounds.width  / originalScale;
    const modelHeight    = bounds.height / originalScale;

    const availableWidth  = container!.clientWidth  - margin * 2;
    const availableHeight = container!.clientHeight - margin * 2;

    let newScale = Math.min(availableWidth / modelWidth, availableHeight / modelHeight);
    if (!Number.isFinite(newScale) || newScale <= 0) newScale = 1;

    const modelLeft = bounds.x / originalScale - view.translate.x;
    const modelTop  = bounds.y / originalScale - view.translate.y;

    const translateX = margin / newScale - modelLeft;
    const translateY = margin / newScale - modelTop;

    view.scaleAndTranslate(newScale, translateX, translateY);
}

export function loadGraph(xml: string): void {
    new ModelXmlSerializer(model).import(xml);

    // Le rendu doit être validé avant de mesurer les limites du graphe,
    // sinon getGraphBounds() est vide et le calcul de zoom ne fait rien.
    graph.refresh();

    fitTopLeft(FIT_MARGIN);
}

(window as any).loadGraph = loadGraph;

//--------------------------------------------------------------------------
// Navigation au clic sur un sommet

graph.addListener(InternalEvent.CLICK, (_sender: unknown, evt: EventObject) => {
    const cell = evt.getProperty('cell') as Cell | null;
    if (!cell?.isVertex()) return;

    const node = _nodes.get(cell.id as string);
    if (!node) return;

    const id = (cell.id as string).split('_').pop();
    if (id === undefined) return;

    window.location.href = `/admin/${node.type}/${id}`;
});

//-----------------------------------------------------------------------
// Curseur pointeur sur les sommets image

graph.addMouseListener({
    mouseMove(_sender, me) {
        const cell = me.getCell();
        const style = cell?.style as AppCellStyle | undefined;
        const isImageVertex = !!cell?.isVertex() && style?.image != null && !style.isBackground;
        container!.style.cursor = isImageVertex ? 'pointer' : 'default';
    },
    mouseDown(_sender, _me) {},
    mouseUp(_sender, _me)   {},
});

//---------------------------------------------------------------------------
// Export SVG

document.getElementById('download-btn')?.addEventListener('click', () => downloadGraphSVG(graph));
