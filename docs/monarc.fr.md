# Intégration Monarc

Cette page décrit l'intégration entre Mercator et [MONARC](https://www.monarc.lu) (Méthode Optimisée d'aNalyse des Risques), l'outil open source d'analyse de risques développé par le [NC3 Luxembourg](https://www/nc3.lu), conforme à la norme ISO/IEC 27005. Elle permet de générer une analyse de risques (analyse de risques) à partir de la cartographie du système d'information, puis de la synchroniser directement vers une instance MONARC Front Office.

Les objets de la cartographie sont mis en correspondance avec les types d'actifs (« assets ») du référentiel public [MOSP](https://objects.monarc.lu/) (MONARC Objects Sharing Platform), qui fournit la base de connaissance (actifs, menaces, vulnérabilités, risques et référentiels de sécurité) utilisée pour construire l'analyse.

## Activer et configurer la connexion Monarc

La connexion à une instance MONARC Front Office se configure dans **Configuration → onglet Monarc**.

| Champ | Description |
|-------|-------------|
| Activer l'intégration Monarc | Affiche l'entrée « Monarc » dans le menu Outils et autorise l'accès à l'écran de génération |
| URL de l'instance Monarc | URL de base de l'instance MONARC Front Office |
| Identifiant | Compte utilisé pour s'authentifier sur l'API Monarc |
| Mot de passe | Laisser vide pour conserver le mot de passe déjà enregistré |

Un bouton **Tester la connexion** permet de vérifier que l'authentification fonctionne avant de générer une analyse.

📢 *Ces valeurs peuvent également être pré-remplies via les variables d'environnement `MONARC_URL` et `MONARC_LOGIN` (le mot de passe se saisit uniquement depuis l'écran de configuration).*

Cette même page affiche l'état de la liaison avec l'analyse de risques Monarc actuellement suivie (voir [Réinitialiser la liaison](#reinitialiser-la-liaison)).

---

## Accès à l'écran de génération

L'écran de génération de l'analyse de risques est accessible via le menu **Outils → Monarc**, uniquement lorsque l'intégration est activée et pour les utilisateurs disposant de la permission d'accès aux rapports.

## Construction de l'analyse

### 1. Modèle d'analyse

| Champ | Description |
|-------|-------------|
| Nom de l'analyse | Champ combiné : sélectionnez une analyse de risques déjà existante dans Monarc, ou saisissez un nouveau nom pour en créer une |
| Description | Description libre de l'analyse |
| Langue | Langue de l'analyse de risques (français ou anglais) |
| Modèle d'analyse | Modèle Monarc utilisé uniquement lors de la création d'une nouvelle analyse de risques |

Lorsqu'une analyse de risques existante est sélectionnée, Mercator interroge Monarc pour récupérer sa langue et reconstruit automatiquement la sélection d'objets ci-dessous à partir de ce qui lui a déjà été envoyé.

⚠️ *Lier une analyse de risques qui n'a pas été créée depuis Mercator peut dupliquer des objets lors de la première synchronisation : Mercator ne peut pas savoir ce que l'analyse de risques contient déjà si elle n'a pas été alimentée par lui.*

### 2. Référentiels de sécurité (optionnel)

Sélectionnez un ou plusieurs référentiels publiés sur MOSP (par ex. ISO 27002, ISO 27017) pour inclure leurs mesures de sécurité dans l'export.

### 3. Sélection des objets de la cartographie

Pour chaque vue de la cartographie (Écosystème, Système d'Information, Administration, Infrastructure logique, Infrastructure physique), un tableau liste une ligne par type d'actif Monarc rencontré :

| Colonne | Description |
|---------|-------------|
| Objets Mercator correspondants | Famille(s) d'objets Mercator associée(s) à ce type d'actif |
| Type d'actif | Code de l'actif MOSP |
| Objet Monarc | Libellé de l'actif dans la langue de l'utilisateur |
| Risques | Nombre de couples menace/vulnérabilité (AMV) associés à cet actif ; cliquer sur le nombre affiche le détail |
| Type générique | Ajoute un seul objet générique pour tout ce type d'actif, à la place des objets Mercator individuels |
| Objets Mercator | Sélection multiple des objets Mercator (application, processus, serveur…) à inclure dans l'analyse |

Cocher **Type générique** désactive la liste d'objets de la ligne (sans effacer une sélection déjà faite) et ajoute un unique objet représentant la catégorie entière.

### 4. Actions

| Bouton | Effet |
|--------|-------|
| Enregistrer | Sauvegarde la sélection en cours sans générer de fichier ni contacter Monarc |
| Exporter JSON | Télécharge l'analyse construite au format JSON, importable manuellement dans Monarc |
| Effacer | Réinitialise l'ensemble de la sélection |


## Synchronisation directe avec Monarc

En complément de l'export JSON, la carte **Synchronisation analyse de risques Monarc** permet de créer ou mettre à jour l'analyse de risques directement via l'API Monarc, sans passer par un fichier :

- si l'analyse de risques n'existe pas encore dans Monarc, elle est créée à partir du modèle d'analyse choisi ;
- seuls les objets Mercator (et leurs risques) qui n'ont **pas déjà été envoyés** lors d'une synchronisation précédente sont importés.

Mercator tient à jour, pour chaque analyse de risques liée, la liste des objets déjà synchronisés. Une synchronisation répétée sans changement de sélection n'envoie donc rien de nouveau (« Déjà à jour »).

⚠️ *Les suppressions ou modifications faites dans Mercator ne sont jamais répercutées vers Monarc lors d'une resynchronisation : seuls les ajouts sont synchronisés.*

L'état de la liaison est rappelé au-dessus du bouton de synchronisation :

- l'analyse de risques actuellement liée (numéro et nom) ou l'absence de liaison ;
- la date de la dernière synchronisation ;
- le nombre d'objets déjà suivis pour cette analyse de risques.

Un message d'avertissement s'affiche si l'analyse de risques liée a été supprimée directement dans Monarc, ou si aucun modèle d'analyse n'a pu être récupéré.


## Réinitialiser la liaison

Le bouton **Réinitialiser la liaison** se trouve dans **Configuration → onglet Monarc**.

Cette action supprime uniquement la liaison locale conservée par Mercator (l'analyse de risques suivie et la liste des objets déjà envoyés) : la prochaine synchronisation créera une toute nouvelle analyse de risques. **L'analyse de risques et son contenu ne sont jamais supprimés dans Monarc.**


## Tableau récapitulatif

| Fonctionnalité | Emplacement | Permission requise |
|-----------------|-------------|---------------------|
| Activer/configurer la connexion Monarc | Configuration → onglet Monarc | `configure` |
| Tester la connexion | Configuration → onglet Monarc | `configure` |
| Réinitialiser la liaison | Configuration → onglet Monarc | `configure` |
| Générer / exporter / synchroniser une analyse | Outils → Monarc | `reports_access` |
