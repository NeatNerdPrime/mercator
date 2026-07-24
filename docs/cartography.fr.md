# Cartographie / Vues

La cartographie du système d'information est organisée en plusieurs vues complémentaires, allant progressivement du
métier vers la technique. Plutôt que de gérer des inventaires cloisonnés, Mercator place l'ensemble des objets dans un
graphe de dépendances : on ne gère plus des listes isolées, mais des relations entre objets. On peut ainsi identifier
les chemins critiques, repérer les fournisseurs stratégiques, et comprendre ce qui dépend de quoi.

## Vision RGPD

La vue RGPD permet de maintenir le registre des traitements et de faire le lien avec les processus, informations,
applications et mesures de sécurité mises en place.

## Vision métier

La vue de l'écosystème présente les différentes entités — fournisseurs, partenaires, sous-parties de l'organisation —
avec lesquelles le SI interagit, ainsi que les relations qu'elles entretiennent : contrats de support, partenariats,
adhésions.

La vue métier du système d'information représente le SI à travers ses macroprocessus, processus, activités, opérations,
acteurs et informations manipulées. Ces éléments constituent les valeurs métier au sens de la méthode d'appréciation des
risques EBIOS Risk Manager.

[<img src="/mercator/images/information_system.png" width="700">](images/information_system.png)

## Vision applicative

La vue des applications décrit les composants logiciels du système d'information : applications regroupées en blocs
applicatifs, bases de données, services et modules, ainsi que les liens avec les processus métier qu'elles supportent.

La vue des flux applicatifs décrit les échanges d'information entre les différentes applications, services, modules et
bases de données.

[<img src="/mercator/images/applications.png" width="700">](images/applications.png)

## Vision administrative

La vue de l'administration répertorie les périmètres et les niveaux de privilèges des utilisateurs et des
administrateurs, ainsi que les annuaires qui les référencent.

[<img src="/mercator/images/administration.png" width="500">](images/administration.png)

## Vision logique

La vue des infrastructures logiques illustre le cloisonnement logique des réseaux : plages d'adresses IP, VLANs,
fonctions de filtrage et de routage. Elle permet notamment de comparer ce que le système est *capable* de faire avec ce
qu'il était *autorisé* à faire.

[<img src="/mercator/images/logical.png" width="500">](images/logical.png)

## Vision infrastructure physique

La vue des infrastructures physiques décrit les équipements physiques qui composent le système d'information : serveurs,
baies, salles, bâtiments et sites.

[<img src="/mercator/images/physical.png" width="700">](images/physical.png)

---

## Exploration des vues et des moteurs de rendu

La cartographie peut être [explorée](application.fr.md#exploration-de-la-cartographie) de manière intuitive : un double-clic sur un objet affiche immédiatement tous les objets qui lui sont liés — physiquement ou logiquement — ainsi que tous les flux entrants et sortants.

Des vues hiérarchiques sont également disponibles. À partir d’un macro-processus, vous pouvez visualiser tous les processus, activités et opérations qui en dépendent, ou sélectionner un site pour afficher toutes les pièces et tous les équipements qu’il contient.

[<img src="/mercator/images/explore.png" width="700">](images/explore.png)

Vous pouvez sélectionner le moteur de rendu graphique directement depuis chaque vue. **Dot, Neato, FDP, Sfdp, Twopi, Circo** : chaque moteur Graphviz produit un rendu différent en fonction de la nature et de la densité du graphe. Cette flexibilité vous permet d’optimiser la lisibilité en fonction du contexte : cartographie d’un réseau dense, vue d’une application ou analyse d’impact à plusieurs niveaux. Un réglage simple pour un confort visuel très concret.

