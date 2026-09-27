// Graphviz (WASM)
import {Graphviz} from "@hpcc-js/wasm-graphviz";

window.graphvizReady = Graphviz.load()
    .then((graphviz) => {
        window.graphviz = graphviz;
        document.dispatchEvent(new Event('graphvizReady'));
        return graphviz;
    })
    .catch((err) => {
        console.error('Failed to load Graphviz WASM:', err);
        document.dispatchEvent(new CustomEvent('graphvizError', {detail: err}));
        throw err;
    });

/**
 * Calcule la mise en page d'un DOT et l'injecte dans #graph.
 *
 * layout() est synchrone et bloque le thread principal : on affiche d'abord le
 * curseur d'attente (classe html.graph-busy) et on laisse le navigateur le peindre
 * (double rAF) avant de lancer le calcul.
 *
 * @returns {Promise<void>} résolue une fois le SVG injecté
 */
window.renderGraphviz = function (dotSrc, engine, options = {}, target = 'graph') {
    const radios = document.querySelectorAll('input[name="engine"]');
    document.documentElement.classList.add('graph-busy');
    radios.forEach(r => r.disabled = true);

    return new Promise((resolve, reject) => {
        requestAnimationFrame(() => requestAnimationFrame(() => {
            try {
                document.getElementById(target).innerHTML =
                    window.graphviz.layout(dotSrc, "svg", engine, options);
                resolve();
            } catch (err) {
                console.error('Graphviz layout failed:', err);
                reject(err);
            } finally {
                document.documentElement.classList.remove('graph-busy');
                radios.forEach(r => r.disabled = false);
            }
        }));
    });
};

/**
 * Branche les boutons radio input[name="engine"] : un changement de moteur relance
 * seulement la mise en page côté client (le DOT n'en dépend pas) et met l'URL à
 * jour, sans renvoyer le formulaire au serveur.
 *
 * @param {(engine: string) => void} render  fonction de rendu à rappeler
 */
window.bindGraphvizEngineRadios = function (render) {
    document.querySelectorAll('input[name="engine"]').forEach(radio =>
        radio.addEventListener('change', (e) => {
            render(e.target.value);
            // Garde l'URL synchronisée (rechargement, partage du lien)
            const url = new URL(window.location.href);
            url.searchParams.set('engine', e.target.value);
            history.replaceState(null, '', url);
        })
    );
};

/**
 * Cas standard des rapports : un DOT fixe calculé côté serveur.
 */
window.initGraphvizReport = function ({dotSrc, engine, images = []}) {
    const render = (eng) => window.renderGraphviz(dotSrc, eng, {images});

    window.graphvizReady.then(() => {
        render(engine);
        window.bindGraphvizEngineRadios(render);
    });
};
