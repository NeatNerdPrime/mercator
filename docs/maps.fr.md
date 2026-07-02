# Les cartes dans Mercator

## Introduction

L'éditeur de cartes de Mercator permet de **placer librement les objets de la cartographie sur un canevas** (schéma) et
de les positionner, relier et mettre en forme à sa convenance : serveurs, applications, réseaux, sites, flux, câbles…

Contrairement aux vues auto-générées (Graphviz pour les vues statiques, Vis.js pour l'exploration), une carte est un
document **persistant et éditable** : sa disposition est enregistrée et reste stable d'une ouverture à l'autre, ce qui
en fait le support idéal pour des schémas de présentation ou de référence (comité de sécurité, documentation
d'architecture, rapport…).

Techniquement, l'éditeur est construit sur la bibliothèque **MaxGraph**. Il est accessible depuis
**Administration → Cartographie → Cartes**, en création ou en modification d'une carte existante.

## Créer ou ouvrir une carte

Une carte est identifiée par :

- un **nom** (obligatoire),
- un **type**, qui permet de classer les cartes entre elles (ex. : réseau, application, site…).

À l'ouverture, la carte affiche les objets déjà positionnés lors des sessions précédentes ainsi que les liens qui les
relient.

## Ajouter des objets sur la carte

### Filtrer les objets disponibles

Le sélecteur **Objets** liste tous les objets de la cartographie pouvant être ajoutés au schéma. Il peut être restreint
grâce à deux filtres combinables :

- **Filtre** (par vue) : Écosystème, Système d'information, Applications, Administration, Infrastructure logique, Flux,
  Infrastructure physique, Infrastructure réseau, Liens physiques ;
- **Attributs** : filtre les objets selon les tags/attributs qui leur sont associés.

### Ajouter un objet précis

Une fois un objet choisi dans le sélecteur **Objets**, son icône s'affiche à côté du bouton **Ajouter**. Deux façons de
le placer sur le canevas :

- cliquer sur **Ajouter** : l'objet est inséré au centre de la zone visible ;
- **glisser-déposer** l'icône directement à l'endroit souhaité du canevas.

Si l'objet est déjà présent sur la carte, l'action ne le duplique pas : elle sélectionne l'objet existant (et restaure
au passage les liens qui auraient pu être supprimés par erreur).

### Déployer les objets liés automatiquement

Deux mécanismes permettent de faire apparaître automatiquement les objets liés à un objet déjà présent :

- **Double-clic** sur une icône : les objets directement liés qui ne sont pas encore sur le schéma sont ajoutés et
  répartis en cercle autour de l'objet, avec leurs liens ;
- **Déployer** : effectue la même opération de façon récursive, sur plusieurs niveaux, à partir de l'objet
  sélectionné. Il faut sélectionner un objet avant de cliquer sur le bouton, sous peine d'un message d'avertissement.

Ces deux actions respectent les filtres de vue/attributs actifs ainsi que les options suivantes, situées à côté du
bouton **Déployer** :

- **Profondeur** (1 à 5) : nombre de niveaux de liens explorés par le déploiement récursif ;
- **Direction** : limite le déploiement aux objets **Amont**, **Aval**, ou **Les deux** (selon l'ordre des vues de la
  cartographie).

!!! tip "Éviter les repositionnements indésirables"
    Le double-clic et le déploiement automatique replacent uniquement les **nouveaux** objets ; les objets déjà
    positionnés manuellement ne bougent pas. Si le moteur physique (voir plus bas) est actif, en revanche, l'ensemble
    du schéma peut se réorganiser après l'ajout — désactivez-le si vous souhaitez conserver une disposition figée.

### Recommencer ou mettre à jour la carte

- **Recommencer** vide entièrement le canevas (à l'exception de l'arrière-plan), pour repartir d'une carte vide sans
  supprimer la carte elle-même ;
- ![Update](images/icons/lightning-fill.svg){: .toolbar-icon } **Update** resynchronise les objets déjà présents avec les données actuelles de la cartographie :
  libellé et icône sont rafraîchis, les objets supprimés de la cartographie sont retirés du schéma. Les liens de type
  câble conservent leur couleur et leur style personnalisés lors de cette mise à jour.

## Organiser la carte

### Déplacer et aligner les objets

- déplacement à la souris (glisser-déposer) ;
- déplacement fin au clavier avec les flèches directionnelles (1 pixel par appui), une fois l'objet sélectionné ;
- ![Grille](images/icons/grid-3x3-gap.svg){: .toolbar-icon } **Grille** : affiche une grille de repère par-dessus le canevas pour faciliter l'alignement visuel.

### Moteur physique (aimant)

![Physique](images/icons/magnet.svg){: .toolbar-icon } L'icône **aimant**  active un moteur de répulsion entre les objets : les icônes se repoussent pour éviter de se chevaucher et s'organisent automatiquement autour des groupes. Cette fonction est **désactivée par défaut** et doit
être activée volontairement — elle ne s'enclenche donc jamais toute seule pendant une simple modification manuelle du
schéma.

!!! info "Objets dans un groupe"
    Lorsque le moteur physique est actif, les objets placés à l'intérieur d'un cadre (groupe) restent confinés dans
    ses limites : ils ne peuvent pas s'échapper ni migrer vers un autre groupe.

### Grouper / dégrouper des objets

Après avoir sélectionné plusieurs éléments (objets, textes, cadres…), le bouton ![Group](images/icons/plus-square-dotted.svg){: .toolbar-icon } **Group** les rassemble dans un même
groupe, qui peut ensuite être déplacé comme un tout. ![Ungroup](images/icons/dash-square-dotted.svg){: .toolbar-icon } **Ungroup** libère les éléments du groupe sélectionné.

### Annoter le schéma : texte et cadres

Deux éléments d'annotation peuvent être glissés depuis la barre d'outils latérale vers le canevas :

- ![Text](images/icons/fonts.svg){: .toolbar-icon } **Text** : une zone de texte libre, éditable en cliquant dessus ;
- ![Border](images/icons/bounding-box.svg){: .toolbar-icon } **Border** : un rectangle de regroupement/annotation, utile pour délimiter visuellement une zone
  (un site, une DMZ, un environnement…).

Un clic droit sur un texte ou un cadre ouvre un menu contextuel de mise en forme :

- pour un **texte** : police, taille, couleur, gras, italique, souligné ;
- pour un **lien** (voir ci-dessous) : couleur, style et épaisseur de trait, routage.

### Habiller les liens

Un clic droit sur un lien (ou une sélection de plusieurs liens) ouvre un menu contextuel permettant de définir :

- la **couleur** du trait ;
- le **style** de trait : continu, pointillé, point, trait-point ;
- l'**épaisseur** (1 à 5 px) ;
- le **routage** : ligne droite, arc, ou à angles (orthogonal).

Ces réglages peuvent être appliqués à plusieurs liens sélectionnés simultanément. Lorsque plusieurs liens existent
entre les deux mêmes objets, ils sont automatiquement écartés en arc pour rester lisibles.

### Arrière-plan (papier peint)

![Arrière-plan](images/icons/image.svg){: .toolbar-icon } L'icône **Arrière-plan** ouvre un menu permettant de :

- choisir une image parmi une galerie prédéfinie ;
- **importer** sa propre image depuis son poste ;
- **supprimer** l'arrière-plan courant.

L'image occupe le fond du canevas à sa taille d'origine et reste toujours **derrière** les autres éléments : elle
n'est ni sélectionnable ni déplaçable, afin d'éviter de la déplacer accidentellement pendant l'édition du schéma.

### Zoom et plein écran

- ![Zoom in](images/icons/zoom-in.svg){: .toolbar-icon } **Zoom in** / ![Zoom out](images/icons/zoom-out.svg){: .toolbar-icon } **Zoom out** : zoom avant/arrière sur le canevas ;
- ![Recentrer](images/icons/crosshair.svg){: .toolbar-icon } **Recentrer** : cadre automatiquement l'ensemble des objets du schéma à l'écran ;
- l'icône en haut à droite de la zone d'édition **agrandit/réduit** l'éditeur en plein écran, en masquant la barre
  latérale pour maximiser l'espace de travail.

### Afficher IP et attributs

Deux bascules permettent d'enrichir les libellés affichés sous chaque icône :

- **Show IP** : affiche l'adresse IP de l'objet, lorsqu'elle existe ;
- **Show Attr** : affiche les attributs/tags de l'objet.

## Annuler / rétablir et enregistrer

- ![Undo](images/icons/arrow-counterclockwise.svg){: .toolbar-icon } **Undo** / ![Redo](images/icons/arrow-clockwise.svg){: .toolbar-icon } **Redo** (ou `Ctrl+Z` / `Ctrl+Y`) annulent ou rétablissent les dernières modifications du schéma ;
- **Delete** (touche `Suppr`/`Retour arrière` ou bouton **Supprimer**) retire les éléments sélectionnés du canevas
  (l'arrière-plan ne peut pas être supprimé de cette façon) ;
- ![Save](images/icons/floppy-fill.svg){: .toolbar-icon } **Sauve** immédiatement la carte en arrière-plan, sans recharger la page ;
- le bouton **Enregistrer**, en bas de page, sauvegarde la carte puis revient à la liste des cartes.

!!! warning "Modifications non enregistrées"
    Si la carte contient des modifications non sauvegardées, Mercator demande confirmation avant de quitter la page
    (fermeture de l'onglet, navigation, ou clic sur **Retour à la liste**).

## Exporter la carte

Le bouton ![Export](images/icons/download.svg){: .toolbar-icon } **Export** génère un fichier **SVG** du schéma tel qu'affiché à l'écran, images
comprises (elles sont intégrées directement dans le fichier). Ce format vectoriel est particulièrement adapté à une
réutilisation dans un rapport ou une présentation.

## Raccourcis clavier

| Raccourci | Action |
|---|---|
| `Ctrl+Z` | Annuler |
| `Ctrl+Y` | Rétablir |
| `Suppr` / `Retour arrière` | Supprimer la sélection |
| `↑` `↓` `←` `→` | Déplacer la sélection d'un pixel |
| Double-clic sur une icône | Déployer les objets directement liés |
| Glisser-déposer | Ajouter un objet, un texte ou un cadre |
| Clic droit sur un lien | Ouvrir le menu de mise en forme du lien |
| Clic droit sur un texte | Ouvrir le menu de mise en forme du texte |

## Bonnes pratiques

- Désactivez le moteur physique une fois la disposition souhaitée obtenue, afin d'éviter que l'ajout d'un nouvel
  objet ne réorganise l'ensemble du schéma.
- Privilégiez des images d'arrière-plan de taille raisonnable : une image trop grande peut dépasser la zone visible
  du canevas et être partiellement masquée.
- Utilisez le bouton **Update** après une évolution de la cartographie (renommage, changement d'icône…) pour
  resynchroniser une carte existante sans avoir à la reconstruire.
- Pensez à cliquer régulièrement sur l'icône **disquette** pendant une session d'édition longue, en complément de
  l'enregistrement final par le bouton **Enregistrer**.
