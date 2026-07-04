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
    const bounds = graph.getGraphBounds();
    const x = Math.floor(bounds.x) - margin;
    const y = Math.floor(bounds.y) - margin;
    const width = Math.ceil(bounds.width) + margin * 2;
    const height = Math.ceil(bounds.height) + margin * 2;
    svgClone.setAttribute('viewBox', `${x} ${y} ${width} ${height}`);
    svgClone.setAttribute('width', String(width));
    svgClone.setAttribute('height', String(height));

    bringLabelsToFront(svgClone);
    await embedImagesInSVG(svgClone);

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
