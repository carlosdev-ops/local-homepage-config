# Guide utilisateur — local_homepage_config

> Plugin Moodle 4.1–4.5 · version 3.3.0

---

## Table des matières

1. [À quoi sert ce plugin ?](#1-à-quoi-sert-ce-plugin-)
2. [Ce que le plugin ne fait PAS](#2-ce-que-le-plugin-ne-fait-pas)
3. [Prérequis](#3-prérequis)
4. [Accès à la page d'administration](#4-accès-à-la-page-dadministration)
5. [Fonctionnalité 1 — Export / Import de configuration visuelle](#5-fonctionnalité-1--export--import-de-configuration-visuelle)
6. [Fonctionnalité 2 — Tuiles dynamiques sur la page d'accueil](#6-fonctionnalité-2--tuiles-dynamiques-sur-la-page-daccueil)
7. [Fonctionnalité 3 — Bandeau publicitaire rotatif](#7-fonctionnalité-3--bandeau-publicitaire-rotatif)
8. [Paramètres avancés](#8-paramètres-avancés)
9. [Automatisation CLI](#9-automatisation-cli)
10. [Résolution des problèmes courants](#10-résolution-des-problèmes-courants)

---

## 1. À quoi sert ce plugin ?

`local_homepage_config` est un plugin local Moodle qui regroupe **trois fonctionnalités indépendantes**, toutes accessibles depuis l'administration du site :

| Fonctionnalité | Ce qu'elle fait |
|---|---|
| **Export / Import** | Emballe la configuration visuelle complète (thème, fichiers, menus, flaveurs, blocs) dans un ZIP transférable entre instances Moodle |
| **Tuiles dynamiques** | Affiche automatiquement des compteurs de cours et d'utilisateurs en haut de la page d'accueil, sans code personnalisé |
| **Bandeau publicitaire** | Affiche un carrousel HTML rotatif en haut de la page d'accueil, configurable entièrement depuis l'administration |

Ces trois fonctionnalités sont **indépendantes** : vous pouvez utiliser l'export/import sans activer les tuiles ni le bandeau, et vice-versa.

---

## 2. Ce que le plugin ne fait PAS

Il est important de connaître les limites du plugin avant de l'utiliser.

### Export / Import

| Ce que le plugin ne fait PAS | Raison / Alternative |
|---|---|
| **N'exporte pas le contenu des cours** (textes, activités, ressources) | Utilisez la sauvegarde Moodle standard (`admin/backup`) |
| **N'exporte pas les comptes utilisateurs** | Hors périmètre — données personnelles gérées séparément |
| **N'exporte pas les inscriptions** | Hors périmètre |
| **N'exporte pas les catégories de cours** | Hors périmètre |
| **N'exporte pas tous les blocs avec un contenu complet** | Seul le bloc `html` est exporté avec son contenu textuel. Les autres types de blocs (agenda, activités récentes, etc.) sont copiés avec leur configuration mais **pas leurs données auxiliaires** (ex. : entrées d'agenda) |
| **N'exporte pas les blocs hors `site-index` et `course-index`** | Seules les pages configurées dans *Préfixes de types de page* sont incluses |
| **N'est pas un outil de backup/restore Moodle complet** | Il cible uniquement la configuration visuelle (apparence, menus, flaveurs) |
| **Ne restaure pas automatiquement les blocs sans confirmation** | La restauration des blocs est une option distincte, décochée par défaut, avec avertissement de sécurité |
| **N'importe pas un ZIP provenant d'une version incompatible** | Un ZIP créé par une version future du plugin est rejeté avec un message d'erreur clair |
| **Ne fusionne pas les configurations** | L'import **remplace** les données existantes (menus, flaveurs, blocs) ; il n'y a pas de fusion |

### Tuiles dynamiques

| Ce que le plugin ne fait PAS | Raison / Alternative |
|---|---|
| **N'affiche pas les tuiles sur toutes les pages** | Les tuiles apparaissent uniquement sur la page d'accueil (`site-index`) |
| **Ne met pas à jour les compteurs en temps réel (live)** | Les counts sont mis en cache 5 minutes pour éviter une surcharge de la base de données |
| **Ne crée pas automatiquement le placeholder** | L'administrateur doit placer `<div id="hpc-tiles"></div>` dans le résumé d'une section de la page d'accueil |
| **Ne compte pas les cours masqués** | Seuls les cours visibles (`visible = 1`) sont comptabilisés |
| **Ne compte pas les utilisateurs sans inscription active** | Seules les inscriptions actives (`status = 0`) sont comptabilisées |
| **Ne supporte pas les tuiles personnalisées avec du HTML** | La valeur personnalisée (`type: "custom"`) est une chaîne de texte simple, pas du HTML |

### Bandeau publicitaire

| Ce que le plugin ne fait PAS | Raison / Alternative |
|---|---|
| **N'affiche pas le bandeau sur toutes les pages** | Le bandeau apparaît uniquement sur la page d'accueil (`site-index`) |
| **Ne crée pas automatiquement le placeholder** | À partir de la v3.2.0, le bandeau est injecté automatiquement — aucun placeholder n'est nécessaire |
| **N'exécute pas de JavaScript dans les slides** | Les balises `<script>` et les gestionnaires d'événements (`onclick`, etc.) sont supprimés automatiquement par HTMLPurifier |
| **N'héberge pas d'images** | Les images des slides doivent être hébergées (Moodle filemanager ou URL externe) ; le HTML peut contenir des balises `<img>` |
| **Ne supporte pas les vidéos en lecture automatique** | HTMLPurifier peut filtrer certains attributs selon la configuration Moodle |
| **N'est pas visible pour les utilisateurs non connectés si la page d'accueil est protégée** | Dépend de la configuration de votre instance Moodle |

---

## 3. Prérequis

Avant d'utiliser le plugin, vérifiez que votre instance répond aux critères suivants :

| Prérequis | Valeur minimale |
|---|---|
| Moodle | 4.1 (testé jusqu'à 4.5) |
| PHP | 7.4 (testé jusqu'à 8.4) |
| Extension PHP | `ZipArchive` (pour l'export/import) |
| Thème | Boost Union (configurable — voir §8) |
| Rôle requis | Administrateur du site |

Pour vérifier que `ZipArchive` est disponible :
```
Administration du site → Rapport du serveur → Informations sur PHP
```
Cherchez `zip` dans la liste des extensions actives.

---

## 4. Accès à la page d'administration

Le plugin est accessible depuis **deux chemins** :

- **Chemin principal :** `Administration du site → Plugins locaux → Configuration page d'accueil → Config. visuelle — Export / Import`
- **Raccourci :** `Administration du site → Apparence → Config. visuelle — Export / Import`

Les **paramètres** (tuiles, bandeau, thème, tables) se trouvent à :
`Administration du site → Plugins locaux → Configuration page d'accueil → Paramètres`

---

## 5. Fonctionnalité 1 — Export / Import de configuration visuelle

### 5.1 Ce qui est exporté

Le fichier ZIP produit contient les éléments suivants :

| Fichier dans le ZIP | Contenu |
|---|---|
| `manifest.json` | Métadonnées (version, date, instance source, composant thème) |
| `settings.json` | Tous les paramètres du thème (`config_plugins` pour le composant configuré) |
| `plugin_settings.json` | Paramètres propres au plugin (tuiles, bandeau, dimensions) |
| `core_settings.json` | Clés de configuration Moodle core (page d'accueil, thème actif…) |
| `files/` | Fichiers uploadés associés au thème (logos, images de fond, slider…) |
| `smartmenus.json` | Définitions des Smart Menus |
| `smartmenu_items.json` | Éléments des Smart Menus |
| `flavours.json` | Définitions des Flaveurs (variantes visuelles) |
| `blocks.json` | Instances de blocs sur les pages configurées |

### 5.2 Procédure d'export

1. Aller sur la page Export / Import.
2. Consulter le **panneau de résumé** (nombre de paramètres, fichiers, menus, flaveurs, blocs) pour vérifier ce qui sera exporté.
3. Cliquer **Télécharger l'export (.zip)**.
4. Le ZIP est généré et téléchargé immédiatement.

> **Note :** Seuls les paramètres présents dans la base de données au moment de l'export sont inclus. Si le thème n'a jamais été configuré, le ZIP sera vide ou minimal.

### 5.3 Procédure d'import (en 2 étapes)

L'import est **en deux étapes** pour éviter les erreurs irréversibles. Un snapshot automatique est pris avant chaque import, ce qui rend l'opération annulable en cas de problème.

**Étape 1 — Prévisualisation**

1. Uploader le fichier ZIP dans le champ *Fichier de configuration (.zip)*.
2. Cocher / décocher les **sections à importer** (voir §5.5).
3. Cocher *Restaurer les blocs* si vous souhaitez remplacer les blocs existants (voir §5.6).
4. Cliquer **Importer**.
5. Un **écran de prévisualisation** s'affiche avec :
   - L'instance source (composant thème, version Moodle, date d'export)
   - Le nombre de paramètres, fichiers, menus, flaveurs et blocs qui seront importés
   - Un **tableau de diff** : chaque paramètre est marqué *modifié*, *ajouté* ou *inchangé*
   - Un avertissement **cohortes** si des menus ou flaveurs référencent des IDs de cohortes (voir §5.7)

**Étape 2 — Confirmation**

6. Cliquer **Confirmer l'import** pour lancer l'opération.
7. Un récapitulatif s'affiche : nombre d'éléments importés par type, et liste des erreurs éventuelles (non fatales).
8. Les caches de thème sont purgés automatiquement.

> **Important :** Si vous cliquez *Annuler* à l'étape 2, aucun changement n'est effectué.

### 5.4 Snapshot automatique et rollback

Avant chaque import confirmé, le plugin **prend automatiquement un snapshot** (export de la configuration actuelle) et le stocke en interne. Ce snapshot vous permet d'annuler l'import en cas de résultat insatisfaisant.

**Comment revenir en arrière :**

1. Dans le tableau *Historique des imports*, repérez l'import concerné (moins de 24 h).
2. Cliquez le bouton **Retour arrière** dans la colonne *Actions*.
3. Confirmez dans la boîte de dialogue.
4. La configuration précédant l'import est restaurée et les caches sont purgés.

> **Durée de validité :** 24 heures. Passé ce délai, le snapshot est supprimé automatiquement et le bouton de rollback n'est plus affiché.

> **Désactiver le snapshot :** En CLI uniquement, avec `--no-snapshot` (voir §9). Depuis l'interface web, le snapshot est toujours pris.

### 5.5 Import sélectif — sections

Lors de l'upload du ZIP, vous pouvez cocher ou décocher chaque section à importer :

| Section | Contenu | Coché par défaut |
|---|---|---|
| **Paramètres du thème** | `config_plugins` du composant thème | ✅ |
| **Paramètres du plugin** | Configuration propre au plugin (tuiles, bandeau…) | ✅ |
| **Paramètres Moodle core** | `frontpage`, `defaulthomepage`, `theme`… | ✅ |
| **Fichiers** | Logos, images de fond, slider… | ✅ |
| **Smart Menus** | Menus + items | ✅ |
| **Flaveurs** | Variantes visuelles + leurs fichiers | ✅ |
| **Blocs** | Instances de blocs sur les pages configurées | ☐ (opt-in) |

> **Exemple d'usage :** Vous souhaitez appliquer uniquement les couleurs et le SCSS depuis un ZIP mais conserver vos menus locaux → décochez *Smart Menus*, *Flaveurs* et *Fichiers*.

### 5.6 Option "Restaurer les blocs"

Cette option est **décochée par défaut** et affichée avec un avertissement de sécurité pour les raisons suivantes :

- Elle **supprime tous les blocs existants** sur les pages configurées, puis les remplace par ceux du ZIP.
- La configuration des blocs (`configdata`) est désérialisée depuis le fichier — n'importez que des ZIP provenant d'instances **de confiance**.
- Si vous n'avez pas de blocs personnalisés importants sur votre instance cible, vous pouvez cocher cette option sans risque.

**Option complémentaire — Réinitialiser les tableaux de bord :** disponible uniquement lorsque *Restaurer les blocs* est coché. Elle supprime les personnalisations de tableau de bord de tous les utilisateurs (`my_pages`) pour forcer l'affichage du nouveau tableau de bord par défaut.

### 5.7 Avertissement cohortes

Si le ZIP contient des **Smart Menus items** ou des **Flaveurs** qui référencent des cohortes (par ID numérique), un avertissement orange s'affiche à l'étape de prévisualisation :

> *"Des références à des cohortes ont été détectées. Après l'import, vérifiez manuellement les conditions de visibilité des menus et des flaveurs concernées."*

**Pourquoi ?** Les IDs de cohortes varient d'une instance à l'autre. Un menu visible uniquement pour la cohorte `42` sur l'instance source ne sera pas visible si la cohorte correspondante a l'ID `7` sur l'instance cible. Le plugin ne restitue pas les cohortes — c'est intentionnel (les cohortes contiennent des données utilisateurs).

**Que faire après l'import ?** Aller dans *Thème → Smart Menus* et *Thème → Flaveurs*, ouvrir chaque élément signalé, et corriger l'ID de cohorte dans les conditions de restriction.

### 5.8 Historique des imports

Chaque import réussi est enregistré dans un tableau visible sur la page Export / Import :
- Date et heure
- Utilisateur ayant effectué l'import
- Composant thème importé
- Nombre de paramètres, fichiers, menus, flaveurs, blocs restaurés
- Nombre d'erreurs
- Bouton **Retour arrière** (si snapshot disponible et moins de 24 h)

Les 10 derniers imports sont affichés. Ces données sont accessibles via l'API RGPD de Moodle.

---

## 6. Fonctionnalité 2 — Tuiles dynamiques sur la page d'accueil

### 6.1 Principe

Les tuiles affichent des **compteurs en temps réel** (cours, utilisateurs) ou des **valeurs personnalisées** directement sur la page d'accueil. Elles sont rendues côté serveur et n'utilisent pas d'AJAX.

### 6.2 Activation

1. Aller dans **Paramètres → Section "Tuiles dynamiques"**.
2. Remplir le champ *Configuration des tuiles (JSON)*.
3. Placer `<div id="hpc-tiles"></div>` dans le résumé d'une section de la page d'accueil (Mode édition → modifier la section).

> **Le plugin détecte automatiquement le placeholder** et y injecte les tuiles. Si le placeholder est absent, les tuiles ne sont pas affichées (aucune erreur).

### 6.3 Format JSON des tuiles

Chaque tuile est un objet JSON avec les propriétés suivantes :

| Propriété | Type | Obligatoire | Description |
|---|---|---|---|
| `title` | string | **oui** | Titre de la tuile |
| `subtitle` | string | non | Sous-titre (affiché en dessous du compteur) |
| `type` | string | non | `"courses"`, `"users"`, `"custom"`, `"none"` (défaut : `"none"`) |
| `catid` | int | non | ID de la catégorie de cours (0 = toute l'instance, défaut : 0) |
| `subcats` | bool | non | Inclure les sous-catégories (défaut : `true`) |
| `value` | string | non | Valeur affichée quand `type = "custom"` |
| `icon` | string | non | Nom d'une icône Font Awesome sans le préfixe `fa-` (ex. : `"sitemap"`) |
| `color` | string | non | `"blue"`, `"green"`, `"orange"`, `"purple"`, `"red"`, `"teal"` (défaut : `"blue"`) |
| `link` | string | non | URL — toute la tuile devient un lien cliquable |
| `newtab` | bool | non | Ouvrir le lien dans un nouvel onglet (défaut : `false`) |

**Exemple complet :**
```json
[
  {
    "title": "Réseaux",
    "subtitle": "formations disponibles",
    "type": "courses",
    "catid": 3,
    "subcats": true,
    "icon": "sitemap",
    "color": "orange",
    "link": "/course/index.php?categoryid=3"
  },
  {
    "title": "Apprenants",
    "subtitle": "inscrits sur la plateforme",
    "type": "users",
    "catid": 0,
    "icon": "users",
    "color": "blue"
  },
  {
    "title": "Nouveauté",
    "subtitle": "Formation lancée en 2026",
    "type": "custom",
    "value": "100% en ligne",
    "icon": "star",
    "color": "teal",
    "link": "/course/view.php?id=42",
    "newtab": true
  }
]
```

### 6.4 Comportement des compteurs

| `type` | `catid` | Ce qui est compté |
|---|---|---|
| `"courses"` | `0` | Tous les cours visibles de l'instance (hors site racine) |
| `"courses"` | `5` | Cours visibles dans la catégorie 5 |
| `"courses"` | `5` + `subcats: true` | Cours visibles dans la catégorie 5 et toutes ses sous-catégories |
| `"users"` | `0` | Utilisateurs distincts inscrits dans au moins un cours actif |
| `"users"` | `5` | Utilisateurs inscrits dans des cours de la catégorie 5 |
| `"custom"` | — | La valeur du champ `value` est affichée telle quelle |
| `"none"` | — | Aucun compteur affiché (titre et icône seulement) |

### 6.5 Mise en cache

Les compteurs sont mis en cache **5 minutes** (cache applicatif Moodle, partagé entre tous les utilisateurs). La mise à jour manuelle est automatique : dès que vous modifiez la configuration JSON et sauvegardez, le cache est purgé immédiatement.

### 6.6 Mise en page automatique

Le plugin adapte automatiquement la largeur des colonnes selon le nombre de tuiles :

| Nombre de tuiles | Affichage |
|---|---|
| 1 | Pleine largeur |
| 2 | 2 colonnes (50 % chacune) |
| 3 | 3 colonnes (33 % chacune) |
| 4+ | 4 colonnes (25 % chacune, défilement de lignes) |

---

## 7. Fonctionnalité 3 — Bandeau publicitaire rotatif

### 7.1 Principe

Le bandeau est un **carrousel HTML** affiché automatiquement en haut de la page d'accueil. Les slides défilent à intervalle configurable. Aucun placeholder n'est nécessaire dans le contenu de la page — le bandeau est injecté automatiquement au-dessus du contenu principal.

### 7.2 Activation

1. Aller dans **Paramètres → Section "Bandeau publicitaire"**.
2. Remplir le champ *Configuration des slides (JSON)*.
3. Sauvegarder.

Le bandeau apparaît immédiatement sur la page d'accueil.

> Pour **désactiver** le bandeau : vider le champ JSON (ou le mettre à `[]`) et sauvegarder.

### 7.3 Format JSON des slides

Chaque slide est un objet JSON avec une propriété `html` :

| Propriété | Type | Obligatoire | Description |
|---|---|---|---|
| `html` | string | **oui** | Contenu HTML du slide (sanitisé automatiquement par HTMLPurifier) |

**Exemple :**
```json
[
  {
    "html": "<div style='text-align:center; padding:2rem;'><h2>Bienvenue sur notre plateforme</h2><p>Découvrez nos 150 formations en ligne.</p><a href='/course/index.php' class='btn btn-primary'>Voir les formations</a></div>"
  },
  {
    "html": "<div style='text-align:center; padding:2rem;'><img src='https://example.com/banner.jpg' alt='Formation du mois' style='max-width:100%; height:auto;'></div>"
  },
  {
    "html": "<div style='text-align:center; padding:2rem;'><h2>Nouveau : Certification professionnelle</h2><p>Ouverte aux inscriptions jusqu'au 30 juin 2026.</p></div>"
  }
]
```

> Le nombre de slides est **illimité**.

### 7.4 Sécurité du contenu HTML

Le HTML de chaque slide passe **obligatoirement** par HTMLPurifier (via `format_text()`) avant d'être affiché. HTMLPurifier :

- **Supprime** : `<script>`, `<iframe>`, `onload`, `onclick`, `onerror`, URLs `javascript:`, et toute tentative d'injection XSS
- **Conserve** : `<h1>`–`<h6>`, `<p>`, `<div>`, `<span>`, `<img>`, `<a>`, `<ul>`, `<li>`, `<strong>`, `<em>`, styles inline simples

> Même si un compte administrateur est compromis, le HTML malveillant est nettoyé avant d'atteindre les navigateurs des utilisateurs.

### 7.5 Paramètres du bandeau

| Paramètre | Description | Valeur par défaut |
|---|---|---|
| **Intervalle de rotation (secondes)** | Durée d'affichage de chaque slide avant passage au suivant | 5 secondes |
| **Hauteur minimale** | Valeur CSS : `200px`, `30vh`, `10em`… Laisser vide pour que le contenu dicte la hauteur | *(vide)* |
| **Largeur maximale** | Valeur CSS : `1200px`, `80%`, `60rem`… Laisser vide pour pleine largeur | *(vide)* |

**Formats CSS acceptés pour hauteur et largeur :**
`px`, `em`, `rem`, `vh`, `vw`, `%` — les valeurs invalides affichent une erreur inline dans l'interface d'administration.

### 7.6 Navigation et accessibilité

| Fonctionnalité | Comportement |
|---|---|
| **Navigation automatique** | Les slides défilent à l'intervalle configuré |
| **Pause au survol** | Le défilement s'arrête quand le curseur est sur le bandeau |
| **Pause au focus clavier** | Le défilement s'arrête quand le clavier est dans le bandeau |
| **Flèches du clavier** | `→` / `↓` : slide suivant · `←` / `↑` : slide précédent |
| **Touches Home / End** | Premier / dernier slide |
| **Points de navigation (dots)** | Cliquables pour accéder directement à un slide |
| **Lecteur d'écran** | Région `aria-live` annonce poliment "1 / 3" à chaque changement |
| **Un seul slide** | Aucune animation, aucun dot, aucun timer — affichage statique |

### 7.7 Mise en cache

Le HTML rendu est mis en cache **5 minutes** (partagé entre tous les utilisateurs). La modification de n'importe quel paramètre du bandeau (JSON, intervalle, hauteur, largeur) purge le cache **immédiatement**.

---

## 8. Paramètres avancés

Accessibles dans **Paramètres → Section "Avancé"** et **Section "Composant thème"**.

### 8.1 Composant thème

| Paramètre | Description | Valeur par défaut |
|---|---|---|
| **Nom du composant thème** | Identifiant Moodle du thème exporté/importé | `theme_boost_union` |

Modifier ce paramètre si vous utilisez un thème différent de Boost Union. Utilisez le nom Moodle complet (ex. : `theme_adaptable`, `theme_moove`).

> **Limitation :** Le plugin a été conçu et testé avec Boost Union. D'autres thèmes peuvent fonctionner partiellement si leur structure de tables et de zones de fichiers correspond aux conventions attendues.

### 8.2 Tables du thème

| Paramètre | Description | Valeur par défaut |
|---|---|---|
| **Table Smart Menus** | Nom de la table DB des menus (sans préfixe `mdl_`) | `theme_boost_union_menus` |
| **Table éléments de Smart Menus** | Nom de la table DB des éléments de menus | `theme_boost_union_menuitems` |
| **Table Flaveurs** | Nom de la table DB des flaveurs | `theme_boost_union_flavours` |

Laisser vide pour désactiver l'export/import de la fonctionnalité correspondante.

### 8.3 Zones de fichiers des flaveurs

Liste des zones de fichiers (`filearea`) dont l'`itemid` correspond à un identifiant de flaveur. Une par ligne. Par défaut :

```
flavours_look_logo
flavours_look_logocompact
flavours_look_favicon
flavours_look_backgroundimage
```

Ces zones sont traitées séparément lors de l'import pour que les identifiants de fichiers soient remappés correctement vers les nouvelles lignes de flaveurs.

### 8.4 Préfixes de types de page (blocs)

Préfixes utilisés pour sélectionner les blocs à exporter. Un par ligne. Par défaut :

```
site-index
course-index
```

Le plugin ajoute automatiquement un wildcard `%`, donc `course-index` capture aussi `course-index-category-5`.

### 8.5 Clés de configuration core

Clés de la table Moodle `config` (sans plugin) à inclure dans l'export. Une par ligne. Par défaut :

```
frontpage
frontpageloggedin
defaulthomepage
theme
```

> **Sécurité :** Lors de l'import, seules les clés présentes dans cette liste sont écrites. Une clé présente dans le ZIP mais absente de cette liste est **ignorée**. Cela protège contre l'import de configurations malveillantes.

---

## 9. Automatisation CLI

Le plugin fournit deux scripts en ligne de commande pour automatiser les transferts dans des pipelines (CI/CD, cron, scripts de déploiement).

### 9.1 Export CLI

```bash
php local/homepage_config/cli/export.php --output=/chemin/vers/sortie.zip
```

| Option | Description |
|---|---|
| `-o, --output=PATH` | Chemin du fichier ZIP à créer (requis) |
| `-h, --help` | Afficher l'aide |

**Exemple :**
```bash
php local/homepage_config/cli/export.php --output=/backups/moodle_theme_$(date +%Y%m%d).zip
```

### 9.2 Import CLI

```bash
php local/homepage_config/cli/import.php --file=/chemin/vers/config.zip [options]
```

| Option | Description |
|---|---|
| `-f, --file=PATH` | Chemin du ZIP à importer (requis) |
| `--skip=LIST` | Sections à ignorer, séparées par virgule : `settings`, `plugin_settings`, `core_settings`, `files`, `menus`, `flavours` |
| `--blocks` | Restaurer les blocs (destructif) |
| `--reset-dashboards` | Réinitialiser les tableaux de bord utilisateurs (nécessite `--blocks`) |
| `--no-snapshot` | Ne pas prendre de snapshot avant l'import |
| `--dry-run` | Afficher les changements sans rien écrire |
| `-h, --help` | Afficher l'aide |

**Codes de sortie :**

| Code | Signification |
|---|---|
| `0` | Succès (ou dry-run terminé) |
| `1` | Erreur fatale (fichier introuvable, format incompatible…) |
| `2` | Import terminé avec des erreurs non fatales |

**Exemples :**

```bash
# Import complet (toutes sections sauf blocs) :
php local/homepage_config/cli/import.php --file=config.zip

# Import sans menus ni flaveurs :
php local/homepage_config/cli/import.php --file=config.zip --skip=menus,flavours

# Import avec blocs + réinitialisation des tableaux de bord :
php local/homepage_config/cli/import.php --file=config.zip --blocks --reset-dashboards

# Aperçu des changements sans rien toucher :
php local/homepage_config/cli/import.php --file=config.zip --dry-run
```

> **Note :** Les scripts CLI requièrent d'être lancés depuis la racine Moodle ou avec un chemin absolu vers `config.php`. Ils n'ont pas besoin de session web — ils utilisent directement l'API Moodle.

---

## 10. Résolution des problèmes courants

### Le rollback n'est pas disponible dans l'historique

**Causes possibles :**
1. L'import a plus de 24 heures — le snapshot a été supprimé automatiquement.
2. L'import a été effectué avec `--no-snapshot` en CLI.
3. L'import a été effectué avec une version du plugin antérieure à 3.3.0.

Dans ce cas, vous pouvez restaurer manuellement un export précédent via la page Export / Import (section Import).

### L'avertissement cohortes s'affiche — que faire ?

L'avertissement signale que des Smart Menus items ou des Flaveurs dans le ZIP référencent des IDs de cohortes. Après l'import :

1. Aller dans **Thème → Boost Union → Smart Menus** → ouvrir chaque item avec une condition de cohorte et vérifier que la cohorte sélectionnée est correcte.
2. Aller dans **Thème → Boost Union → Flaveurs** → ouvrir chaque flaveur avec une règle de cohorte et vérifier l'ID.

### Les tuiles n'apparaissent pas

**Vérifications :**
1. Le JSON est-il valide ? (copiez-le dans un validateur JSON en ligne)
2. Le placeholder `<div id="hpc-tiles"></div>` est-il présent dans le résumé d'une section de la page d'accueil ?
3. La page courante est-elle bien la page d'accueil (`site-index`) ?
4. Le champ JSON contient-il au moins une tuile avec une propriété `title` non vide ?

### Le bandeau n'apparaît pas

**Vérifications :**
1. Le JSON du bandeau est-il valide et non vide ?
2. Chaque slide a-t-il une propriété `html` avec du contenu non vide ?
3. JavaScript est-il activé dans le navigateur ?
4. Consultez la console du navigateur (F12) pour détecter des erreurs AMD.

### L'import échoue avec "Type de fichier invalide"

Le fichier uploadé n'est pas reconnu comme un ZIP. Causes possibles :
- Mauvaise extension (le fichier doit se terminer par `.zip`)
- Fichier corrompu
- ZIP généré par un outil tiers (seuls les ZIP produits par ce plugin sont supportés)

### L'import échoue avec "Version de format incompatible"

Le ZIP a été produit par une **version plus récente** du plugin que celle installée sur l'instance cible. Solution : mettre à jour le plugin sur l'instance cible.

### La page d'administration ne charge pas (erreur PHP)

Cause probable : l'extension PHP `ZipArchive` n'est pas installée.

```bash
sudo apt install php-zip
sudo systemctl reload apache2
```

### Les compteurs de tuiles ne se mettent pas à jour

Les counts sont mis en cache 5 minutes. Pour forcer la mise à jour immédiate, modifiez et sauvegardez la configuration JSON des tuiles (même sans changement), ce qui purge le cache.

### Les caches thème ne sont pas purgés après un import

En cas de problème visuel persistant après un import, purgez manuellement les caches :
```
Administration du site → Développement → Purger tous les caches
```

---

## Résumé des responsabilités

| L'administrateur est responsable de… | Le plugin se charge de… |
|---|---|
| Placer le placeholder `<div id="hpc-tiles">` dans le contenu de la page | Détecter le placeholder et y injecter les tuiles |
| Écrire un JSON valide pour les tuiles et les slides | Valider le JSON et ignorer les entrées malformées sans faire planter la page |
| N'importer que des ZIP provenant d'instances de confiance | Sanitiser le HTML des slides et la configuration des blocs |
| Choisir d'activer ou non la restauration des blocs | Afficher un avertissement clair avant toute action irréversible |
| Héberger les images utilisées dans les slides | Nettoyer le HTML des slides via HTMLPurifier |
| Configurer les bonnes tables et zones de fichiers si le thème n'est pas Boost Union | Utiliser les valeurs par défaut adaptées à Boost Union |
