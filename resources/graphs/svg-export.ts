import type { Graph } from '@maxgraph/core';

// Export SVG d'un graphe MaxGraph, partagé entre map.edit.ts et map.show.ts.

const XLINK_NS = 'http://www.w3.org/1999/xlink';

// MaxGraph peint chaque cellule (sommet ou arête) dans l'ordre du modèle : le
// <g> du label d'un sommet (verticalLabelPosition: 'bottom') ou d'une arête
// est un enfant direct du même calque de dessin que les traits d'arête, donc
// une arête ajoutée après un sommet le recouvre, texte compris. On force ici
// tous les groupes contenant du texte à passer en dernier enfant de leur
// parent (= peints en dernier = au-dessus de tout le reste du calque),
// sans toucher à l'ordre des sommets/arêtes eux-mêmes.
//
// Le <text> n'est pas l'enfant direct de ce groupe de premier niveau : MaxGraph
// l'enveloppe dans un <g fill=... font-family=...> intermédiaire (propre au
// TextShape), lui-même enfant du <g> réellement placé dans le calque de dessin.
// Remonter d'un seul niveau (text.parentElement) ne fait donc rien : on
// obtient déjà l'unique enfant de ce groupe intermédiaire, sans changer sa
// position parmi les autres cellules. On remonte donc tant que le parent
// courant n'a qu'un seul enfant (wrapper intermédiaire), jusqu'à atteindre le
// <g> qui a effectivement d'autres sommets/arêtes comme frères.
function bringLabelsToFront(svg: SVGSVGElement): void {
    const labelGroups = new Set<Element>();
    svg.querySelectorAll('text').forEach((text) => {
        let node: Element | null = text.parentElement;
        while (node?.parentElement && node.parentElement.childElementCount === 1) {
            node = node.parentElement;
        }
        if (node) labelGroups.add(node);
    });
    labelGroups.forEach((group) => group.parentNode?.appendChild(group));
}

// Enveloppe tous les groupes de dessin de la racine <svg> dans un <g> porteur
// d'une transformation (translation + mise à l'échelle), afin de normaliser le
// repère exporté (origine 0,0, unités modèle). <defs>/<style>/<title>/<metadata>
// sont laissés à la racine : aucune géométrie à transformer, et les références
// url(#id) doivent rester résolubles globalement.
function normalizeExportContent(
    svg: SVGSVGElement,
    t: { tx: number; ty: number; k: number },
): void {
    const SVG_NS = 'http://www.w3.org/2000/svg';
    const skip = new Set(['defs', 'style', 'title', 'metadata']);
    const wrapper = svg.ownerDocument.createElementNS(SVG_NS, 'g');
    wrapper.setAttribute('transform', `translate(${t.tx} ${t.ty}) scale(${t.k})`);

    // Snapshot des enfants avant déplacement (childNodes est vivant).
    Array.from(svg.childNodes).forEach((node) => {
        if (
            node.nodeType === globalThis.Node.ELEMENT_NODE &&
            skip.has((node as Element).tagName.toLowerCase())
        ) {
            return;
        }
        wrapper.appendChild(node);
    });

    svg.appendChild(wrapper);
}

async function embedImagesInSVG(svg: SVGSVGElement): Promise<void> {
    const images = Array.from(svg.querySelectorAll('image'));
    await Promise.all(images.map(async (img) => {
        const href = img.getAttribute('xlink:href') ?? img.getAttribute('href');
        if (!href || href.startsWith('data:')) return;
        try {
            const response = await fetch(href);
            const blob = await response.blob();
            const dataUrl = await new Promise<string>((resolve, reject) => {
                const reader = new FileReader();
                reader.onloadend = () => resolve(reader.result as string);
                reader.onerror = reject;
                reader.readAsDataURL(blob);
            });
            // Inkscape n'affiche les <image> qu'à partir de xlink:href — l'attribut
            // SVG2 "href" nu (seul utilisé jusqu'ici) n'est reconnu que par les
            // moteurs de rendu basés navigateur. On renseigne les deux pour rester
            // compatible avec les deux familles de lecteurs.
            img.setAttributeNS(XLINK_NS, 'xlink:href', dataUrl);
            img.setAttribute('href', dataUrl);
        } catch (err) {
            console.error('Erreur embed image SVG', err);
        }
    }));
}

export async function downloadGraphSVG(graph: Graph): Promise<void> {
    const svgElement = graph.container.querySelector('svg') as SVGSVGElement | null;
    if (!svgElement) {
        alert('SVG introuvable');
        return;
    }

    // On clone le SVG affiché à l'écran avant de le modifier : le viewBox
    // couvre tout le graphe (indépendamment du pan/zoom courant), ce qui
    // casserait l'affichage en cours d'édition si on le posait sur le SVG
    // en direct plutôt que sur une copie destinée uniquement à l'export.
    const svgClone = svgElement.cloneNode(true) as SVGSVGElement;
    // Déclare explicitement le namespace xlink sur la racine : le SVG en direct
    // s'en passe (les attributs xlink:href posés via setAttributeNS restent
    // valides dans le DOM sans elle), mais un lecteur strict comme Inkscape
    // exige la déclaration dans le XML sérialisé.
    svgClone.setAttribute('xmlns:xlink', XLINK_NS);

    const margin = 20;
    const scale = graph.getView().scale || 1;
    const bounds = graph.getGraphBounds();

    // getGraphBounds() renvoie des coordonnées « vue » (déjà multipliées par
    // view.scale et décalées par view.translate). On les ramène en coordonnées
    // « modèle » (division par l'échelle) pour obtenir des dimensions réelles,
    // indépendantes du zoom courant, et on normalise l'origine du viewBox à
    // (0,0) : un lecteur externe peut alors lire width/height et recalculer un
    // cadre sans se soucier d'un décalage d'origine.
    const modelWidth = bounds.width / scale;
    const modelHeight = bounds.height / scale;
    const vbWidth = Math.max(1, Math.ceil(modelWidth) + margin * 2);
    const vbHeight = Math.max(1, Math.ceil(modelHeight) + margin * 2);

    svgClone.setAttribute('viewBox', `0 0 ${vbWidth} ${vbHeight}`);
    svgClone.setAttribute('width', String(vbWidth));
    svgClone.setAttribute('height', String(vbHeight));

    bringLabelsToFront(svgClone);
    await embedImagesInSVG(svgClone);

    // Le contenu dessiné par MaxGraph est en coordonnées « vue ». On l'enveloppe
    // dans un groupe qui (a) le ramène à l'échelle 1 (scale 1/scale) et (b) le
    // translate pour que le coin haut-gauche du graphe tombe sur (margin,margin)
    // dans le repère normalisé. <defs>/<style> restent hors du groupe : ce sont
    // des définitions globales (url(#id)) sans géométrie à transformer.
    if (modelWidth > 0 && modelHeight > 0) {
        normalizeExportContent(svgClone, {
            tx: margin - bounds.x / scale,
            ty: margin - bounds.y / scale,
            k: 1 / scale,
        });
    }

    const serializer = new XMLSerializer();
    const svgString = serializer.serializeToString(svgClone);
    const blob = new Blob([svgString], {type: 'image/svg+xml;charset=utf-8'});
    const url = URL.createObjectURL(blob);

    const now = new Date();
    const timestamp =
        now.getFullYear() +
        String(now.getMonth() + 1).padStart(2, '0') +
        String(now.getDate()).padStart(2, '0') +
        String(now.getHours()).padStart(2, '0') +
        String(now.getMinutes()).padStart(2, '0');

    const link = document.createElement('a');
    link.href = url;
    link.download = `graph-${timestamp}.svg`;
    link.click();
    URL.revokeObjectURL(url);
}
