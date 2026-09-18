# Les rôles dans Mercator

Le rôle est le mécanisme principal pour accorder les droits de liste, de lecture et d'écriture sur les objets de Mercator. Cette documentation explique ce qu'est un rôle, comment créer et gérer les rôles, et comment ils fonctionnent avec les périmètres pour contrôler l'accès des utilisateurs.

## Introduction — Qu'est-ce qu'un rôle ?

Un **rôle** est un ensemble de **permissions** qui détermine ce qu'un utilisateur peut faire dans Mercator :

- **Consulter** (lecture) — lister les objets et voir leur détail
- **Créer** — ajouter de nouveaux objets d'un type donné
- **Modifier** — éditer des objets existants
- **Supprimer** — retirer des objets

Chaque utilisateur peut détenir **un ou plusieurs rôles**, et les rôles peuvent être rattachés à des périmètres afin de partitionner les accès par établissement.

!!! info "Trois mécanismes de contrôle d'accès"
    Mercator combine trois notions complémentaires pour contrôler ce que les utilisateurs peuvent faire :
    
    - Le **rôle** définit *ce que* l'utilisateur peut faire (consulter, créer, modifier, supprimer quelles catégories d'objets)
    - Le **périmètre** définit *sur les objets de quel établissement* ces droits s'appliquent (voir la documentation *Périmètres*)
    - L'assignation d'un **cartographe** délègue la responsabilité d'**objets individuels précis**, indépendamment du périmètre ou du rôle (voir la documentation *Cartographes*)
    
    Les trois fonctionnent ensemble pour offrir un contrôle d'accès fin.

## Le modèle de permissions

### Catégories de permissions

Mercator organise les permissions par **type d'objet** et par **action** :

- **Serveurs** → Consulter, Créer, Modifier, Supprimer
- **Applications** → Consulter, Créer, Modifier, Supprimer
- **Réseaux** → Consulter, Créer, Modifier, Supprimer
- **Sites** → Consulter, Créer, Modifier, Supprimer
- ... (une catégorie par type d'objet)

### Modèles de rôles intégrés

Mercator fournit des modèles de rôles standards pour simplifier la configuration :

| Rôle | Description | Permissions typiques |
|------|-------------|---------------------|
| **Admin** | Accès complet à tous les types d'objets et gestion des périmètres | Toutes les permissions |
| **Éditeur** | Peut consulter, créer et modifier des objets (pas les supprimer) | Tous les droits CRUD sauf la suppression |
| **Lecteur** | Accès en lecture seule à tous les objets | Consultation uniquement |
| **Opérateur** | Gère les serveurs et l'infrastructure physique | Créer/Modifier les serveurs, sites et objets physiques |

!!! note "Administrateurs"
    Les **administrateurs** ont un statut particulier. Ils ne sont jamais filtrés par périmètre et voient toujours l'ensemble de la cartographie, quelle que soit la configuration des périmètres. Les rôles d'administrateur sont généralement attribués aux directeurs informatiques ou aux responsables système.

## Créer et gérer les rôles

### Créer un nouveau rôle

1. Aller dans **Administration → Rôles**
2. Cliquer sur **+ Créer un rôle**
3. Saisir un **nom de rôle** (ex. : « Administrateur serveurs », « Audit en lecture seule »)
4. Sélectionner un **périmètre** (par défaut, le périmètre par défaut, id = 1)
   - *Voir la documentation Périmètres pour plus de détails*
5. Cocher les **permissions** à accorder :
   - Pour chaque type d'objet, choisir : Consulter, Créer, Modifier, Supprimer
6. Cliquer sur **Créer**

### Modifier un rôle existant

1. Aller dans **Administration → Rôles**
2. Trouver le rôle dans la liste
3. Cliquer sur **Modifier**
4. Changer le **nom**, le **périmètre** ou les **permissions**
5. Cliquer sur **Enregistrer**

### Supprimer un rôle

1. Aller dans **Administration → Rôles**
2. Trouver le rôle dans la liste
3. Cliquer sur **Supprimer**
4. Confirmer

!!! warning "Avant de supprimer un rôle"
    Vérifiez combien d'utilisateurs sont assignés à ce rôle. Le supprimer leur retire leurs accès. Pensez à réassigner d'abord les utilisateurs à un autre rôle.

## Assigner des rôles aux utilisateurs

### Assigner des rôles à un utilisateur

1. Aller dans **Administration → Utilisateurs**
2. Trouver l'utilisateur dans la liste
3. Cliquer sur **Modifier**
4. Dans **Rôles**, cocher les rôles à assigner
   - Un utilisateur peut avoir plusieurs rôles (pour accéder à plusieurs périmètres)
5. Cliquer sur **Enregistrer**

### Retirer un rôle à un utilisateur

1. Aller dans **Administration → Utilisateurs**
2. Trouver l'utilisateur
3. Cliquer sur **Modifier**
4. Dans **Rôles**, décocher le rôle
5. Cliquer sur **Enregistrer**

### Un utilisateur avec plusieurs rôles

Un utilisateur peut détenir des rôles dans plusieurs périmètres. Les permissions sont **cumulées** :

```
L'utilisateur « Alice » a :
  - Le rôle « Admin » dans le Périmètre 1 (« Siège »)
    → Accès complet à tous les objets du Siège
  
  - Le rôle « Lecteur » dans le Périmètre 2 (« Agence A »)
    → Accès en lecture seule à tous les objets de l'Agence A

Résultat : Alice voit et peut modifier les objets du Périmètre 1,
           et voit uniquement (sans modifier) les objets du Périmètre 2
```

Les **périmètres accessibles à l'utilisateur** sont l'union des périmètres de tous ses rôles.

!!! tip "Combiner les permissions entre périmètres"
    Un utilisateur peut avoir un rôle en lecture seule dans un périmètre et un rôle à accès complet dans un autre. Cela permet de doser précisément les accès.

## Le rôle administrateur intégré

Le rôle **Administrateur** est particulier :

- Il possède **toutes les permissions** par défaut
- Il n'est **jamais filtré par périmètre** — il voit l'ensemble de la cartographie
- Il a accès aux fonctions d'**Administration**
- Il ne peut pas être supprimé (toujours disponible)
- Il ne doit être attribué qu'à du personnel informatique de confiance

Les administrateurs sont responsables de :

- Créer et gérer les autres rôles
- Assigner les rôles aux utilisateurs
- Gérer les périmètres
- Auditer les accès et les modifications
- Traiter les demandes de changement d'accès des utilisateurs

!!! warning "Restreindre l'accès administrateur"
    N'attribuez le rôle Administrateur qu'aux personnes qui ont réellement besoin d'un accès complet au système. Pour la plupart des utilisateurs, créez des rôles spécifiques avec des permissions limitées.

## Relation entre rôle et périmètre

Chaque rôle est rattaché à **exactement un périmètre**. Les permissions du rôle ne s'appliquent qu'aux objets de ce périmètre.

### Exemple : configuration multi-établissements

Votre organisation compte deux sites : le Siège et l'Agence A. Vous souhaitez que :

- le personnel du Siège gère tous les objets du Siège
- le personnel de l'Agence A gère uniquement les objets de l'Agence A
- les dirigeants consultent les deux sites (en lecture seule)

**Solution :**

1. Créer deux périmètres :
   - Périmètre 1 : « Siège »
   - Périmètre 2 : « Agence A »
   - (Voir la documentation *Périmètres* pour plus de détails)

2. Créer des rôles par site :
   - Rôle « Admin Siège » → Périmètre 1 → Permissions d'administration
   - Rôle « Admin Agence » → Périmètre 2 → Permissions d'administration
   - Rôle « Lecteur global » → Périmètre 1 → Consultation uniquement
   - Rôle « Lecteur global » → Périmètre 2 → Consultation uniquement

3. Assigner les utilisateurs :
   - Personnel du Siège → rôle « Admin Siège »
   - Personnel de l'Agence → rôle « Admin Agence »
   - Dirigeants → deux rôles « Lecteur global » (un par périmètre)

Résultat : chaque équipe gère son propre site, les dirigeants voient les deux (en lecture seule), et les données sont correctement partitionnées.

!!! info "Voir la documentation Périmètres"
    Pour une explication complète du fonctionnement des périmètres, de quand les activer et de la manière de migrer les données, reportez-vous à la documentation *Périmètres*.

## Connexion et accès des utilisateurs

### À la première connexion

Un nouvel utilisateur auquel des rôles ont été assignés :

1. Se connecte avec ses identifiants
2. Reçoit automatiquement toutes les permissions de ses rôles
3. Peut voir tous les objets des périmètres auxquels ses rôles donnent accès
4. S'il y a plusieurs périmètres : voit un **sélecteur de périmètre** pour passer de l'un à l'autre

### Session et changements de rôles

- **Les changements de rôles prennent effet immédiatement** au prochain rechargement de la page
- Si un rôle est retiré pendant que l'utilisateur est connecté, il peut conserver un accès en cache jusqu'à ce qu'il actualise la page ou se reconnecte
- **Le retrait du rôle Admin** prend effet à la prochaine connexion

## Bonnes pratiques pour les rôles

### 1. Choisir des noms de rôles clairs

Utilisez des noms descriptifs qui indiquent à la fois le niveau et la portée :

✓ Bon :
- « Administrateur serveurs Siège »
- « Lecteur seul Agence »
- « Inspecteur d'audit »

✗ À éviter :
- « Rôle 1 »
- « Admin »
- « Rôle utilisateur »

### 2. Principe du moindre privilège

Accordez aux utilisateurs le **minimum** de permissions dont ils ont besoin :

- Nouveau personnel → commencer avec le rôle « Lecteur »
- Passer à « Éditeur » uniquement en cas de besoin
- Permissions « Admin » ou « Supprimer » → uniquement pour le personnel de confiance

### 3. Utiliser les modèles de rôles

Pour rester cohérent, basez les nouveaux rôles sur les modèles intégrés :

- Copiez « Éditeur » et personnalisez-le plutôt que de partir de zéro
- Cela garantit la cohérence des schémas courants dans toute votre organisation

### 4. Documenter la finalité des rôles

Pour chaque rôle personnalisé, documentez :

- **À quoi il sert** (ex. : « Gérer l'inventaire des serveurs physiques »)
- **Qui l'utilise** (ex. : « L'équipe infrastructure »)
- **Quand l'accorder** (ex. : « À l'embauche, après formation »)

Cela aide les administrateurs à assigner les rôles de manière cohérente.

### 5. Revoir régulièrement les assignations de rôles

Chaque trimestre, auditez :

- **Qui a des rôles Admin** (doit rester minimal)
- **Les rôles inutilisés** (les supprimer ou les fusionner)
- **La dérive des rôles** (permissions élargies au-delà du périmètre initial)
- **Les rôles orphelins** (assignés à personne, à nettoyer)

### 6. Séparer par domaine de responsabilité

Si possible, créez des rôles pour des responsabilités précises :

- « Administrateur serveurs » (gère uniquement les serveurs)
- « Administrateur réseaux » (gère uniquement les réseaux)
- « Lecture seule générale » (consulte tout)

Cela facilite l'octroi d'accès partiels et l'audit de qui peut faire quoi.

---

## Dépannage

### « Un utilisateur ne voit pas des objets qu'il devrait voir »

Vérifier :

1. **Les rôles de l'utilisateur** (Administration → Utilisateurs → [utilisateur] → Rôles)
   - L'utilisateur a-t-il au moins un rôle assigné ?
   - Ce rôle a-t-il la permission « Consulter » pour ce type d'objet ?

2. **Le périmètre du rôle** (Administration → Rôles → [rôle])
   - Le rôle est-il assigné au bon périmètre ?
   - Si les périmètres sont utilisés : l'objet est-il dans ce périmètre ?

3. **Le périmètre de l'objet** (ouvrir l'objet, vérifier le champ Périmètre)
   - Si les périmètres sont utilisés : l'objet est-il dans l'un des périmètres des rôles de l'utilisateur ?

4. **Les assignations de cartographes**
   - L'utilisateur est-il désigné cartographe de cet objet ?
   - (Voir la documentation *Cartographes*)

**Solution :** assigner à l'utilisateur un rôle dans le bon périmètre avec la permission « Consulter » pour ce type d'objet.

### « Un utilisateur ne peut pas modifier un objet »

Vérifier :

1. **Les permissions du rôle** (Administration → Rôles → [rôle])
   - Le rôle a-t-il la permission « Modifier » pour ce type d'objet ?

2. **Les permissions d'écriture**
   - Un rôle « Consulter » = lecture seule (ne peut pas modifier)
   - Un rôle « Modifier » ou « Admin » = peut modifier

3. **L'appartenance au périmètre** (si les périmètres sont utilisés)
   - Le périmètre du rôle est-il le même que celui de l'objet ?

**Solution :** assigner à l'utilisateur un rôle avec la permission « Modifier » dans le bon périmètre.

### « Je ne peux pas supprimer un rôle car il est utilisé »

Cela signifie que des utilisateurs sont encore assignés à ce rôle.

**Solution :**

1. Aller dans **Administration → Rôles** et trouver le rôle
2. Cliquer sur **Voir les assignations** pour voir quels utilisateurs le possèdent
3. Réassigner ces utilisateurs à un autre rôle (Administration → Utilisateurs)
4. Une fois qu'aucun utilisateur n'est assigné, vous pouvez supprimer le rôle

### « Un administrateur voit tout mais un lecteur ne voit rien »

**Pour le lecteur :**

1. Vérifier que le lecteur a au moins un rôle assigné (Administration → Utilisateurs → [utilisateur] → Rôles)
2. Vérifier que le rôle a la permission « Consulter »
3. Si les périmètres sont utilisés : vérifier que les objets sont dans le périmètre du rôle

**Pour l'administrateur :**

- Les administrateurs voient toujours tout, quels que soient le périmètre ou les permissions du rôle
- C'est voulu (les administrateurs gèrent l'ensemble du système)

### « Les changements de rôles ne prennent pas effet »

L'utilisateur a peut-être des permissions en cache. Lui demander de :

1. **Recharger la page** (F5 ou Cmd+R)
2. **Se déconnecter puis se reconnecter** (si l'actualisation ne suffit pas)

Après la déconnexion/reconnexion, les nouvelles permissions du rôle prennent effet immédiatement.
