# Génération de données de test

Mercator fournit une commande Artisan qui génère un jeu de données de test réaliste et cohérent — périmètres, applications, bases de données, serveurs logiques, flux applicatifs et infrastructure physique (sites, bâtiments, étages, locaux, baies, serveurs/switchs/routeurs physiques, périphériques, postes de travail, téléphones, bornes WiFi, zones de sécurité) — pour les démonstrations, les tests d'interface à grande échelle et les tests de charge.

```bash
php artisan mercator:generate-test-data [options]
```

Cette commande n'a pas d'interface web : elle s'exécute en ligne de commande sur le serveur, par un administrateur.

---

## Démarrage rapide

```bash
# Utiliser un profil prédéfini
php artisan mercator:generate-test-data --scenario=pme

# Prévisualiser ce qui serait créé, sans rien écrire
php artisan mercator:generate-test-data --scenario=large_enterprise --dry-run 

# Compteurs entièrement personnalisés
php artisan mercator:generate-test-data --perimeters=3 --applications=200 --servers=100 --databases=50 --flows=500 --sites=6 --buildings=15 --bays=40 --physical-servers=80 --peripherals=20 --workstations=150
```

Lancée sans aucune option, la commande utilise le `default_scenario` défini dans `config/data-scenarios.php` (`pme` par défaut).

---

## Scénarios prédéfinis

Utilisez `--scenario=<nom>` pour choisir un des profils définis dans `config/data-scenarios.php` :

| Scénario | Périmètres | Applications | Bases de données | Serveurs | Flux | Sites | Bâtiments | Baies | Serveurs physiques | Périphériques | Postes de travail | Zones de sécurité |
|----------|-----------:|-------------:|------------------:|---------:|-----:|------:|----------:|------:|---------------------:|---------------:|--------------------:|--------------------:|
| `pme` | 1 | 15 | 5 | 8 | 7 | 1 | 2 | 4 | 6 | 4 | 10 | 2 |
| `mid_market` | 2 | 80 | 25 | 40 | 50 | 3 | 6 | 12 | 20 | 15 | 60 | 4 |
| `large_enterprise` | 4 | 400 | 100 | 200 | 400 | 6 | 15 | 40 | 80 | 40 | 300 | 8 |
| `load_test` | 2 | 1000 | 200 | 2000 | 1667 | 4 | 10 | 30 | 100 | 30 | 150 | 6 |

Les flux représentent le tiers de ce qu'un simple compteur "applications" suggérerait, puisque chacun peut désormais relier n'importe lequel des quatre types d'extrémité (application, service, module, base de données) et non plus seulement deux applications — voir **Flux applicatifs** plus bas.

Les étages, locaux, réseaux, sous-réseaux, VLANs, téléphones, bornes WiFi, switchs et routeurs physiques, groupes applicatifs, services/modules applicatifs, zones d'administration et domaines AD ne figurent pas dans ce tableau : étages et locaux sont un tirage direct par site/étage (voir `floors_per_site` / `locals_per_floor` dans le fichier de configuration, 1-8 et 5-10 par défaut), réseaux/sous-réseaux/VLANs sont un réseau par site avec un tirage direct de sous-réseaux par réseau (`subnetworks_per_network`, 4-16 par défaut), les téléphones correspondent toujours au nombre de postes de travail effectivement créés, les bornes WiFi sont une probabilité par étage/local, switchs/routeurs découlent directement du nombre de baies/étages/sites (un switch par baie, un switch par étage, au plus un routeur par site), les groupes applicatifs sont un tirage direct par périmètre (`application_blocks_per_perimeter`, 5-7 par défaut), les services/modules applicatifs sont un tirage direct par application/service (`application_services_per_application` 0-5, `application_modules_per_service` 0-3), et les zones d'administration/domaines AD sont un tirage direct par site (`zone_admins_per_site` 1-3, `domains_per_site` 3-5) — voir plus bas pour l'ensemble de ces cas.

Toute option manuelle (`--applications`, `--servers`, ...) surcharge uniquement ce compteur par-dessus le scénario choisi — inutile de tous les préciser.

---

## Options

| Option | Description |
|--------|-------------|
| `--scenario=<nom>` | Profil prédéfini (voir tableau ci-dessus). Ignoré pour un compteur déjà fixé manuellement. |
| `--perimeters=<n>` | Nombre total de périmètres souhaité (le périmètre par défaut compte pour 1 — seuls les périmètres manquants sont créés). |
| `--applications=<n>` | Nombre d'applications à créer. |
| `--databases=<n>` | Nombre de bases de données à créer. |
| `--servers=<n>` | Nombre de serveurs logiques à créer. |
| `--flows=<n>` | Nombre de flux applicatifs à créer (nécessite au moins 2 applications). |
| `--sites=<n>` | Nombre de sites à créer. |
| `--buildings=<n>` | Nombre de bâtiments à créer. |
| `--bays=<n>` | Nombre de baies à créer. |
| `--physical-servers=<n>` | Nombre de serveurs physiques à créer. |
| `--peripherals=<n>` | Nombre de périphériques (imprimantes, badgeuses, onduleurs, ...) à créer. |
| `--workstations=<n>` | Nombre de postes de travail à créer. |
| `--security-zones=<n>` | Nombre de zones de sécurité à créer. |
| `--chunk=<n>` | Taille des lots pour les insertions en masse (par défaut dans `config/data-scenarios.php`, `500`). |
| `--seed=<n>` | Graine aléatoire, pour obtenir un résultat reproductible d'une exécution à l'autre. |
| `--dry-run` | Calcule et affiche le plan de génération, sans rien écrire en base. |
| `--force` | Ignore la confirmation interactive. Ne supprime **jamais** de données existantes. |

Il n'y a pas d'option `--floors`, `--locals` (locaux), `--networks`, `--subnetworks`, `--vlans`, `--phones`, `--wifi-terminals`, `--physical-switches`, `--physical-routers`, `--application-blocks`, `--application-services`, `--application-modules`, `--zone-admins`, `--domains`, `--macro-processes`, `--processes`, `--activities`, `--operations`, `--tasks`, `--actors`, `--informations`, `--entities`, `--relations` ni `--data-processing` — voir plus bas pourquoi.

---

## Ce qui est généré

* **Périmètres** — choisis dans un ensemble de noms plausibles à consonance organisationnelle (`Production`, `Filiale Nord`, `Donnees Sante`, ...), avec repli sur un nom généré une fois cet ensemble épuisé. Les périmètres déjà existants sont conservés ; seul le nombre manquant est créé.
* **Applications / Bases de données / Serveurs logiques** — noms réalistes (`acme-billing-prod`, `db-crm-staging`, `web03-prod`, ...), types et niveaux de besoin de sécurité plausibles, répartis uniformément entre les périmètres disponibles. Chaque serveur logique est également apparié à un serveur physique de son propre périmètre (`logical_server_physical_server`), et son adresse IP est piochée dans un sous-réseau du site de ce serveur physique plutôt que d'être une adresse générique — sauf en l'absence de serveur physique/sous-réseau éligible, auquel cas une IP générique est utilisée.
* **Groupes applicatifs** — chaque périmètre reçoit 5 à 7 groupes applicatifs (`application_blocks_per_perimeter`), et chaque application est rattachée à un groupe pris **dans son propre périmètre**.
* **Services et modules applicatifs** — pas non plus un compteur cible : chaque *application* reçoit 0 à 5 services applicatifs (`application_services_per_application`), chacun dédié à cette seule application (jamais partagé), et chaque *service applicatif* reçoit 0 à 3 modules applicatifs (`application_modules_per_service`), chacun dédié à ce seul service. Noms, types, éditeur/version réalistes pour les deux.
* **Liens Application ↔ Serveur** — chaque application est reliée à 1, 2 ou 3 serveurs logiques pris **dans son propre périmètre**, jamais 0 : 90 % des applications n'en ont qu'un seul, le reste se partage entre deux et trois (`APPLICATION_SERVER_COUNTS`, pas une plage min/max configurable).
* **Liens Application ↔ Base de données** — chaque application est reliée à un nombre aléatoire de bases de données prises **dans son propre périmètre** (voir `databases_per_application` dans le fichier de configuration).
* **Flux applicatifs** — relient deux *extrémités*, chacune indépendamment une application, un service applicatif, un module applicatif ou une base de données (n'importe quelle combinaison — service vers base de données, module vers module, application vers service, ...), jamais le même objet exact des deux côtés. Une part configurable des flux (`cross_perimeter_flow_probability`, `10 %` par défaut) relie volontairement deux extrémités de **périmètres différents**, afin d'exercer la visibilité croisée des flux entre périmètres ; les autres restent dans un même périmètre. Les scénarios prédéfinis génèrent trois fois moins de flux qu'avant cette diversification des extrémités, pour garder un volume total de liens comparable.
* **Infrastructure physique (côté technique)** — sites, bâtiments, baies, serveurs physiques et périphériques, chaînés de façon cohérente : un bâtiment référence un site de son propre périmètre, une baie référence le même site que son bâtiment, un serveur physique/périphérique référence la même baie/bâtiment/site — jamais un mélange de périmètres différents. Un objet enfant sans parent éligible dans son propre périmètre (ex. `--buildings` demandé mais `--sites=0`) est tout de même créé, simplement sans ce lien-là.
* **Réseaux, sous-réseaux et VLANs (côté logique)** — un `Network` par site, nommé exactement comme ce site (`Network` n'a pas de colonne `site_id`, l'association se fait donc par le nom plutôt que par une clé étrangère). Chaque réseau reçoit 4 à 16 sous-réseaux (`subnetworks_per_network`), et chaque sous-réseau est apparié 1:1 à son propre VLAN via `subnetworks.vlan_id`.
* **Étages et locaux (côté bureautique)** — une seconde hiérarchie, parallèle et indépendante de `--buildings`/`--bays` : chaque site reçoit 1 à 8 étages nommés `ET1`, `ET2`, ... (`floors_per_site`), et chaque étage reçoit 5 à 10 locaux nommés façon `LOCAL042` (`locals_per_floor`). Les deux sont stockés comme des bâtiments, simplement avec `type` = `Etage` / `Local`.
* **Switchs et routeurs physiques** — pas un compteur cible non plus : chaque *baie* reçoit son propre switch physique (câblage technique/rack), chaque *étage* reçoit lui aussi son propre switch physique (câblage bureautique — un second switch indépendant, pas partagé avec celui d'une baie), et chaque *site* ayant au moins une baie reçoit exactement un routeur physique, placé dans l'une des baies de ce site. Un site sans aucune baie n'a pas de routeur (et aucun de ses switchs d'étage n'est relié à un routeur non plus).
* **Liens physiques** — un lien physique relie chaque serveur physique au switch de sa propre baie, et chaque switch — qu'il vienne d'une baie ou d'un étage — au routeur de son site, de façon à ce que tout le câblage rack/site soit navigable, pas seulement la liste des équipements.
* **Postes de travail et téléphones** — toujours situés dans un *local*, jamais directement dans un étage ou un bâtiment. Les téléphones sont toujours en nombre exactement égal aux postes de travail effectivement créés.
* **Bornes WiFi** — contrairement à tous les autres objets ci-dessus, ce n'est pas un compteur cible : chaque *étage ou local* a indépendamment 80 % de chances (`wifi_terminal_probability` dans le fichier de configuration) de recevoir sa propre borne. Avec 0 site (donc 0 étage/local), il y a 0 borne, quoi que demande le reste.
* **Liens postes de travail et bornes WiFi** — chaque poste de travail et chaque borne WiFi est relié par un lien physique au switch le plus proche : celui de son propre étage, ou, s'il se trouve dans un local, celui de l'étage qui contient ce local. Jamais un switch de baie (un poste de travail n'est pas dans un rack).
* **Zones de sécurité** — chacune est reliée à 1 à 3 bâtiments de son propre périmètre (voir `buildings_per_zone` dans le fichier de configuration). Les zones se rattachent à de vrais bâtiments, pas à des étages ou des locaux.
* **Infrastructure d'annuaire / Active Directory (par site)** — pas non plus un compteur cible : chaque *site* reçoit 1 à 3 zones d'administration (`zone_admins_per_site`), exactement un service d'annuaire (`Annuaire`, rattaché à l'une de ces zones et, quand le périmètre du site en a une, à une application), et exactement une forêt Active Directory/LDAP (`ForestAd`, rattachée à l'une de ces mêmes zones). Chaque site reçoit aussi 3 à 5 domaines (`domains_per_site`), tous rattachés à la forêt AD de ce site (`domain_forest_ad`). Aucune des tables `zone_admins`/`annuaires`/`forest_ads`/`domains` n'a de colonne `site_id` : le découpage "par site" ne structure donc que la génération, pas une association interrogeable en base au-delà du périmètre partagé.
* **Utilisateurs administrateurs** — chaque site reçoit exactement autant de `admin_users` que de postes de travail effectivement créés sur ce site, répartis aussi également que possible entre les domaines de ce site : le `user_count` de chaque domaine est fixé exactement au nombre de `admin_users` qui lui sont réellement rattachés (`domain_id`), pas une estimation a posteriori.
* **Cartographie métier (par périmètre)** — pas non plus un compteur cible, et générée systématiquement pour chaque périmètre (indépendamment de toute option `--applications`/`--sites`/...) : chaque *périmètre* reçoit 3 à 5 macro-processus (`macro_processes_per_perimeter`), chacun avec 3 à 10 processus (`processes_per_macro_process`, `macroprocess_id`) ; chaque *processus* reçoit 5 à 10 activités dédiées (`activities_per_process`, via `activity_process`) ; chaque *activité* reçoit 1 à 3 opérations dédiées (`operations_per_activity`, via `activity_operation`) — chaque opération hérite aussi du *même* `process_id` que son activité parente, `Operation` ayant à la fois une clé étrangère directe vers `Process` et une relation many-to-many avec `Activity` ; et chaque *opération* reçoit 1 à 3 tâches dédiées (`tasks_per_operation`, via `operation_task`). Rien n'est partagé à aucun niveau entre deux parents : chaque activité/opération/tâche appartient à exactement un processus/une activité/une opération.
* **Acteurs** — chaque périmètre reçoit 5 à 20 acteurs (`actors_per_perimeter`), chacun affecté à 1 à 5 opérations (`actor_operations_per_actor`) **de ce même périmètre** (`actor_operation`) — une véritable relation many-to-many, contrairement aux liens parent/enfant dédiés ci-dessus.
* **Informations** — chaque périmètre reçoit 5 à 20 enregistrements `Information` (`informations_per_perimeter`). Aucune relation n'est créée avec les processus — voir plus bas pour ses liens vers les bases de données et les flux.
* **Liens Information ↔ Base de données et Information ↔ Flux** — chaque base de données reçoit 1 à 5 informations (`informations_per_database`, `database_information`), et chaque flux applicatif en reçoit 0 à 2 (`informations_per_flow`, `application_flow_information`) — toutes deux prises **dans ce même périmètre** que la base de données/le flux.
* **Entités et relations** — chaque périmètre reçoit un nombre fixe de 100 entités (`entities_per_perimeter`, un compte plutôt qu'une plage — "une centaine" selon la demande, pas un min/max). Chaque entité est ensuite reliée à 0 à 3 autres entités **de ce même périmètre** (`relations_per_entity`), jamais à elle-même — contrairement à `ApplicationFlow`, `Relation` n'a aucun scope multi-périmètre, donc les deux extrémités d'une relation restent toujours dans un seul périmètre. Une entité seule dans son périmètre (ne devrait pas arriver à 100 par périmètre, mais gérée par précaution) n'obtient simplement aucune relation plutôt qu'une boucle sur elle-même.
* **Traitements de données (registre RGPD)** — chaque périmètre reçoit 20 à 50 enregistrements `DataProcessing` (`data_processing_per_perimeter`), chacun relié à 1 à 3 applications, 1 à 3 processus et 1 à 3 informations (`data_processing_links_per_type`, trois pivots indépendants) — toutes prises **dans ce même périmètre**. Une seule base légale RGPD (article 6) est tirée par enregistrement, reflétée de façon cohérente à la fois dans `legal_basis` et dans le `lawfulness_*` correspondant (les cinq autres restant à `false`).
* **Étiquettes d'attributs** — chaque objet de cartographie généré (applications, serveurs, sites, réseaux, bâtiments, postes de travail, ...) reçoit 3 à 5 étiquettes libres dans son champ `attributes`, piochées parmi un ensemble fixe : `OK`, `Checked`, `Dev`, `Test`, `T1`, `T2`, `T3`, `O1`, `Saved`, `A1` à `A5`, `Sec`, `Lab` (séparées par un espace, selon la convention déjà utilisée ailleurs dans Mercator pour ce champ).

Chaque modèle déjà présent dans Mercator (`app/Factories/*Factory.php`) fournit les attributs fictifs de base — cette commande ajoute par-dessus la répartition par périmètre et les relations entre objets.

---

## Accès multi-périmètres

Dès que l'exécution aboutit à **plus d'un périmètre** (existants + nouvellement créés), la commande :

1. Active elle-même la fonctionnalité périmètres (le même drapeau que **Admin → Configuration → Périmètres**) — le sélecteur de périmètre est ainsi immédiatement utilisable, sans étape manuelle séparée.
2. Crée un rôle administrateur par périmètre, intitulé `admin.perimeter.<slug>` (ex. `admin.perimeter.filiale-nord`), disposant de **toutes** les permissions.
3. Si le compte administrateur canonique (`admin@admin.com`) existe, l'y rattache — sans jamais lui retirer ses rôles existants — afin qu'il puisse immédiatement basculer vers n'importe quel périmètre généré. Si ce compte n'existe pas, cette étape est simplement ignorée (le reste n'est pas affecté).

Toute cette étape est ignorée lorsque l'exécution ne produit qu'un seul périmètre (ex. le scénario `pme`) — il n'y a alors rien à basculer. Elle est idempotente : relancer la commande sur les mêmes périmètres réutilise les rôles déjà créés (et rafraîchit leurs permissions) au lieu de les dupliquer, et rattacher à nouveau le compte admin ne fait rien s'il l'est déjà.

---

## Sécurité

* Tout est écrit dans une seule transaction de base de données : en cas d'échec en cours de route, rien n'est persisté.
* `--dry-run` n'effectue aucune écriture — elle se contente d'indiquer ce qui *serait* créé (y compris si la fonctionnalité périmètres serait activée).
* `--force` ne fait que sauter la confirmation interactive ; elle ne supprime ni n'écrase jamais de données existantes.
* Activer la fonctionnalité périmètres et rattacher `admin@admin.com` aux nouveaux rôles (voir ci-dessus) est le seul cas où cette commande modifie un comportement applicatif global, et pas seulement des lignes en base — cela n'arrive que lorsque le nombre de périmètres résultant est supérieur à un, et c'est annoncé avant la confirmation interactive.

---

## Exemple de sortie

```
+---------------------------------------------------------+-------------------+
| Objet                                                   | Quantité demandée |
+---------------------------------------------------------+-------------------+
| Périmètres (total)                                      | 4                 |
| Applications                                            | 400               |
| Groupes applicatifs par périmètre (~24 au total)        | 5-7               |
| Services applicatifs par application (~1000 au total)   | 0-5               |
| Modules applicatifs par service (~1500 au total)        | 0-3               |
| Bases de données                                        | 100               |
| Serveurs logiques                                       | 200               |
| Flux applicatifs (application/service/module/BDD)       | 400               |
| Sites                                                   | 6                 |
| Réseaux (= sites)                                       | 6                 |
| Sous-réseaux par réseau (~60 au total)                  | 4-16              |
| VLANs (= sous-réseaux)                                  | 60                |
| Bâtiments                                               | 15                |
| Baies                                                   | 40                |
| Étages par site (~27 au total)                          | 1-8               |
| Locaux par étage (~203 au total)                        | 5-10              |
| Switchs physiques (= baies)                             | 40                |
| Switchs physiques (= étages)                            | 27                |
| Routeurs physiques (<= sites)                           | 6                 |
| Serveurs physiques                                      | 80                |
| Périphériques                                           | 40                |
| Postes de travail (dans un local)                       | 300               |
| Téléphones (= postes de travail, dans un local)         | 300               |
| Bornes WiFi (~80% des étages/locaux)                    | 184               |
| Zones de sécurité                                       | 8                 |
| Zones d'administration par site (~12 au total)          | 1-3               |
| Annuaires (= sites)                                     | 6                 |
| Forêts Active Directory / LDAP (= sites)                | 6                 |
| Domaines par site (~24 au total)                        | 3-5               |
| Utilisateurs admin_users (= postes de travail)          | 300               |
| Macro-processus par périmètre (~16 au total)            | 3-5               |
| Processus par macro-processus (~104 au total)           | 3-10              |
| Activités par processus (~780 au total)                 | 5-10              |
| Opérations par activité (~1560 au total)                | 1-3               |
| Tâches par opération (~3120 au total)                   | 1-3               |
| Acteurs par périmètre (~50 au total)                    | 5-20              |
| Informations par périmètre (~50 au total)               | 5-20              |
| Entités par périmètre (400 au total)                    | 100               |
| Relations par entité (~600 au total)                    | 0-3               |
| Traitements de données par périmètre (~140 au total)    | 20-50             |
+---------------------------------------------------------+-------------------+
Plus d'un périmètre : la fonctionnalité périmètres sera activée, un rôle admin.perimeter.<nom> sera créé pour chacun, et le compte admin@admin.com (s'il existe) y sera rattaché.

+-----------------------------------------------------+---------+
| Résultat                                             | Valeur  |
+-----------------------------------------------------+---------+
| Périmètres (total / créés)                           | 4 / 3   |
| Fonctionnalité périmètres activée                    | Oui     |
| Rôles admin.perimeter.* créés                        | 4       |
| Compte admin@admin.com rattaché aux rôles            | Non     |
| Applications créées                                  | 400     |
| Groupes applicatifs créés                            | 22      |
| Services applicatifs créés                           | 1010    |
| Liens applications <-> services applicatifs          | 1010    |
| Modules applicatifs créés                            | 1475    |
| Liens services applicatifs <-> modules applicatifs   | 1475    |
| Bases de données créées                              | 100     |
| Serveurs logiques créés                              | 200     |
| Liens serveurs logiques <-> serveurs physiques       | 200     |
| Liens applications <-> serveurs                      | 470     |
| Liens applications <-> bases de données              | 400     |
| Flux applicatifs créés                               | 400     |
| Sites créés                                          | 6       |
| Réseaux créés                                        | 6       |
| Sous-réseaux créés                                   | 67      |
| VLANs créés                                          | 67      |
| Bâtiments créés                                      | 15      |
| Baies créées                                         | 40      |
| Étages créés                                         | 33      |
| Locaux créés                                         | 239     |
| Switchs physiques créés (baies)                      | 40      |
| Switchs physiques créés (étages)                     | 33      |
| Routeurs physiques créés                             | 6       |
| Serveurs physiques créés                             | 80      |
| Liens serveurs <-> switchs                           | 80      |
| Liens switchs de baie <-> routeurs                   | 40      |
| Liens switchs d'étage <-> routeurs                   | 33      |
| Périphériques créés                                  | 40      |
| Postes de travail créés                              | 300     |
| Liens postes de travail <-> switchs                  | 300     |
| Téléphones créés                                     | 300     |
| Bornes WiFi créées                                   | 215     |
| Liens bornes WiFi <-> switchs                        | 215     |
| Zones de sécurité créées                             | 8       |
| Liens zones <-> bâtiments                            | 11      |
| Zones d'administration créées                        | 12      |
| Annuaires créés                                      | 6       |
| Forêts Active Directory / LDAP créées                | 6       |
| Domaines créés                                       | 26      |
| Liens forêts AD <-> domaines                         | 26      |
| Utilisateurs admin_users créés                       | 300     |
| Macro-processus créés                                | 17      |
| Processus créés                                      | 124     |
| Liens processus <-> activités                        | 924     |
| Activités créées                                     | 924     |
| Liens activités <-> opérations                       | 1830    |
| Opérations créées                                    | 1830    |
| Liens opérations <-> tâches                          | 3643    |
| Tâches créées                                        | 3643    |
| Acteurs créés                                        | 65      |
| Liens acteurs <-> opérations                         | 206     |
| Informations créées                                  | 52      |
| Liens bases de données <-> informations              | 299     |
| Liens flux applicatifs <-> informations              | 390     |
| Entités créées                                       | 400     |
| Relations créées                                     | 611     |
| Traitements de données créés                         | 166     |
| Liens traitements de données <-> applications        | 342     |
| Liens traitements de données <-> processus           | 320     |
| Liens traitements de données <-> informations        | 338     |
+-----------------------------------------------------+---------+
```

Les 200 serveurs logiques ont bien reçu un serveur physique + une IP adaptée à son site (200/200) : avec 80 serveurs physiques répartis sur 4 périmètres, chaque périmètre en avait au moins un disponible.

À noter que chaque poste de travail et chaque borne WiFi a bien obtenu un lien (300/300 et 215/215) : tant que chaque site a au moins une baie, le switch de son étage existe forcément (même un routeur n'est pas requis ici — les switchs d'étage existent toujours pour chaque étage, indépendamment des routeurs), donc postes de travail et bornes WiFi trouvent toujours le switch de leur étage. À noter aussi que chaque sous-réseau a bien exactement un VLAN (67/67), que chaque application a reçu 1 à 3 serveurs logiques avec ~90 % n'en ayant qu'un, que chaque groupe applicatif appartient au même périmètre que les applications qui lui sont rattachées (22 groupes répartis sur 4 périmètres, 5 à 7 par périmètre), que chaque service/module applicatif est dédié à exactement une application/un service (1010 services pour 1010 liens, 1475 modules pour 1475 liens), que les deux extrémités de chaque flux pointent vers un objet existant (application/service/module/base de données) sans jamais former de boucle, que chaque zone d'administration/annuaire/forêt AD/domaine/admin_user s'enchaîne de façon cohérente dans le périmètre de son site (12 zones d'administration et 26 domaines répartis sur 6 sites, 300 admin_users correspondant exactement aux 300 postes de travail créés), et que toute la chaîne métier (924 activités pour 924 liens processus, 1830 opérations pour 1830 liens activités, 3643 tâches pour 3643 liens opérations) reste dans son périmètre de bout en bout, chaque opération héritant du même processus que son activité parente. Chaque périmètre a aussi reçu exactement 100 entités (400/400 sur 4 périmètres), et chacune des 611 relations créées relie deux entités du même périmètre sans jamais former de boucle. Chaque base de données a reçu 1 à 5 informations (299 liens pour 100 bases de données) et chaque flux en a reçu 0 à 2 (390 liens pour 400 flux), toutes deux toujours dans le même périmètre. Chacun des 166 traitements de données (un registre RGPD) a reçu 1 à 3 liens vers des applications, processus et informations (respectivement 342/320/338 liens), tous dans le même périmètre, et le `legal_basis` de chacun correspond exactement à un seul drapeau `lawfulness_*` à `true`.
