import { compile } from '@r0kshan/cairn';

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const root = document.getElementById('cairn-app');

        if (!root) {
            return;
        }

        const searchUrl = root.dataset.searchUrl;
        const generateUrl = root.dataset.generateUrl;
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const i18n = {
            noSelection: root.dataset.i18nNoSelection,
            remove: root.dataset.i18nRemove,
            errorGenerate: root.dataset.i18nErrorGenerate,
        };

        let selection = [];

        try {
            selection = JSON.parse(root.dataset.initialSelection || '[]');
        } catch (e) {
            selection = [];
        }

        const $type = $('#cairn-type');
        const $object = $('#cairn-object');
        const $addButton = $('#cairn-add');
        const $clearButton = $('#cairn-clear');
        const $list = $('#cairn-selection-list');
        const $diagram = $('#cairn-diagram');
        const $prompt = $('#cairn-prompt');
        const $loading = $('#cairn-loading');
        const $diagnostics = $('#cairn-diagnostics');

        $type.select2({
            placeholder: '...',
            allowClear: false,
            width: '100%',
        });

        $object.select2({
            placeholder: '...',
            allowClear: false,
            width: '100%',
            minimumInputLength: 0,
            ajax: {
                url: searchUrl,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        type: $type.val(),
                        search: params.term || '',
                    };
                },
                processResults: function (data) {
                    return { results: data };
                },
                cache: true,
            },
        });

        $type.on('change', function () {
            $object.val(null).trigger('change');
        });

        function renderSelectionList() {
            $list.empty();

            if (selection.length === 0) {
                $list.append($('<li>', { class: 'list-group-item text-muted' }).text(i18n.noSelection));

                return;
            }

            selection.forEach(function (item, index) {
                const $item = $('<li>', { class: 'list-group-item d-flex justify-content-between align-items-center' });
                $item.append($('<span>').text('[' + item.typeLabel + '] ' + item.text));

                const $remove = $('<button>', {
                    type: 'button',
                    class: 'btn btn-sm btn-outline-danger',
                    'data-index': index,
                    'aria-label': i18n.remove,
                }).html('&times;');

                $item.append($remove);
                $list.append($item);
            });
        }

        $list.on('click', 'button[data-index]', function () {
            const index = parseInt($(this).attr('data-index'), 10);
            selection.splice(index, 1);
            renderSelectionList();
            regenerate();
        });

        $addButton.on('click', function () {
            const type = $type.val();
            const typeLabel = $type.find('option:selected').text();
            const objectData = $object.select2('data')[0];

            if (!type || !objectData || !objectData.id) {
                return;
            }

            const id = parseInt(objectData.id, 10);
            const exists = selection.some(function (item) {
                return item.type === type && item.id === id;
            });

            if (exists) {
                return;
            }

            selection.push({ type: type, id: id, text: objectData.text, typeLabel: typeLabel });
            renderSelectionList();

            $object.val(null).trigger('change');

            regenerate();
        });

        $clearButton.on('click', function () {
            if (selection.length === 0) {
                return;
            }

            selection = [];
            renderSelectionList();
            regenerate();
        });

        function showPrompt() {
            $diagram.empty();
            $diagnostics.empty().addClass('d-none');
            $prompt.removeClass('d-none');
        }

        function showLoading() {
            $prompt.addClass('d-none');
            $diagnostics.empty().addClass('d-none');
            $loading.removeClass('d-none');
        }

        // Le SVG produit par Cairn n'a qu'un `viewBox`, sans `width`/`height` explicites :
        // sans intervention, le navigateur l'étire pour remplir toute la largeur du
        // conteneur (donc du texte agrandi artificiellement). On fige sa taille naturelle et
        // on laisse `#cairn-diagram` (overflow: auto) défiler si le diagramme est plus grand.
        function pinSvgToNaturalSize(svgEl) {
            if (!svgEl || !svgEl.viewBox || !svgEl.viewBox.baseVal) {
                return;
            }

            const viewBox = svgEl.viewBox.baseVal;

            if (viewBox.width > 0 && viewBox.height > 0) {
                svgEl.setAttribute('width', viewBox.width);
                svgEl.setAttribute('height', viewBox.height);
                svgEl.style.maxWidth = 'none';
            }
        }

        // Codes de diagnostic Cairn sans objet pour Mercator : non actionnables (rien dans le
        // modèle de données ne permet de les corriger), donc masqués pour ne pas polluer l'UI.
        //   W0540 - "system-to-system flow without protocol" : application_flows n'a pas de
        //           colonne protocole/format (voir §3 de la spec), ce warning serait toujours présent.
        const IGNORED_DIAGNOSTIC_CODES = ['W0540'];

        function renderDiagnostics(diagnostics) {
            $diagnostics.empty();

            const relevant = (diagnostics || []).filter(function (d) {
                return IGNORED_DIAGNOSTIC_CODES.indexOf(d.code) === -1;
            });

            const errors = relevant.filter(function (d) { return d.severity === 'error'; });
            const warnings = relevant.filter(function (d) { return d.severity === 'warning'; });

            errors.forEach(function (d) {
                $diagnostics.append($('<div>', { class: 'alert alert-danger py-1 px-2 mb-1 small' }).text(d.message));
            });

            warnings.forEach(function (d) {
                $diagnostics.append($('<div>', { class: 'alert alert-warning py-1 px-2 mb-1 small' }).text(d.message));
            });

            $diagnostics.toggleClass('d-none', errors.length === 0 && warnings.length === 0);
        }

        function regenerate() {
            if (selection.length === 0) {
                showPrompt();

                return;
            }

            showLoading();

            fetch(generateUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    selection: selection.map(function (item) {
                        return { type: item.type, id: item.id };
                    }),
                }),
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('http_error');
                    }

                    return response.json();
                })
                .then(function (payload) {
                    $loading.addClass('d-none');

                    if (payload.empty) {
                        showPrompt();

                        return;
                    }

                    return compile(payload.dsl).then(function (result) {
                        $diagram.empty();
                        renderDiagnostics(result.diagnostics);

                        if (result.svg === null) {
                            return;
                        }

                        $diagram.html(result.svg);
                        pinSvgToNaturalSize($diagram.find('svg')[0]);
                    });
                })
                .catch(function () {
                    $loading.addClass('d-none');
                    $diagnostics
                        .empty()
                        .removeClass('d-none')
                        .append($('<div>', { class: 'alert alert-danger py-1 px-2 mb-1 small' }).text(i18n.errorGenerate));
                });
        }

        renderSelectionList();
        regenerate();
    });
})();
