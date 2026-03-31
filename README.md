# local_homepage_config

## English summary

**local_homepage_config** is a Moodle 4.1+ local plugin with two features:

1. **Visual configuration export/import** — Packages a complete snapshot of your Boost Union theme (settings, uploaded images, Smart Menus, Flavours, front-page blocks) into a ZIP file that can be imported on another Moodle instance. Useful for deploying a consistent look across multiple sites or environments.

2. **Dynamic homepage tiles** — Injects live course/user count tiles into any `<div id="hpc-tiles">` placeholder placed in a section summary. Counts are server-side rendered via a Mustache template and cached for 5 minutes.

### Requirements
- Moodle 4.1+
- PHP ZipArchive extension
- Boost Union theme (configurable)

### Installation
Copy the `local/homepage_config` directory into your Moodle installation and run the upgrade script (`admin/cli/upgrade.php` or visit Site administration → Notifications).

### Quick start
1. Go to **Site administration → Appearance → Homepage Visual Config**.
2. Click **Download export (.zip)** on the source instance.
3. Upload the ZIP on the target instance and click **Import**.

### Limitations
- Block export is fully supported for `html` blocks only. Other block types are migrated via their `configdata` field; auxiliary table rows are not transferred.
- Only import ZIP files produced by this plugin from a **trusted** Moodle instance (block configdata is deserialized during restore).

---

*Full documentation in French below.*

---

# Plugin `local_homepage_config` — Guide complet

## Table des matières

1. [À quoi sert ce plugin](#1-à-quoi-sert-ce-plugin)
2. [Ce qui est exporté — inventaire complet](#2-ce-qui-est-exporté--inventaire-complet)
3. [Ce qui n'est PAS exporté — et pourquoi](#3-ce-qui-nest-pas-exporté--et-pourquoi)
4. [Installation](#4-installation)
5. [Utilisation pas-à-pas](#5-utilisation-pas-à-pas)
6. [Page de paramètres — tout configurer](#6-page-de-paramètres--tout-configurer)
7. [Structure du fichier ZIP exporté](#7-structure-du-fichier-zip-exporté)
8. [Architecture du code](#8-architecture-du-code)
9. [Comportement détaillé de l'import](#9-comportement-détaillé-de-limport)
10. [Adapter le plugin à un autre thème](#10-adapter-le-plugin-à-un-autre-thème)
11. [Dépannage](#11-dépannage)

---

## 1. À quoi sert ce plugin

Ce plugin permet de **reproduire fidèlement la configuration visuelle d'un Moodle vers un autre**, sans accès direct à la base de données ni manipulation de fichiers serveur.

**Cas d'usage typique :**
- Vous avez un Moodle de production avec un visuel soigné (slider, couleurs, menus, tuiles…)
- Vous voulez créer un Moodle de formation, de test, ou une nouvelle instance pour un client
- Vous exportez la configuration depuis l'instance source → un fichier `.zip`
- Vous importez ce fichier sur l'instance cible → la configuration est appliquée en quelques secondes

**Condition :** les deux instances doivent utiliser **Moodle 4.1** et le même thème (par défaut : Boost Union).

---

## 2. Ce qui est exporté — inventaire complet

### 2.1 Paramètres du thème (`config_plugins`)

Tous les réglages du thème Boost Union stockés dans la table `mdl_config_plugins` avec le plugin `theme_boost_union` :

| Fonctionnalité | Exemples de clés |
|---|---|
| **Slider** | `slide1enabled`, `slide1content`, `sliderarrownav`… |
| **Tuiles publicitaires** | `tile1enabled`, `tile1title`, `tilecolumns`… |
| **Info Banners** | `infobanner1enabled`, `infobanner1content`… |
| **Couleurs & Branding** | `brandcolor`, `navbarcolor`, `bootstrapcolorsuccess`… |
| **SCSS personnalisé** | `rawscss`, `rawscsspre` |
| **Logo, favicon** | (références aux fichiers, exportés séparément) |
| **Comportement navigation** | Menus, drawers, etc. |
| **Pages statiques** | À propos, contact, aide, mentions légales… |
| **Bas de page** | Contenu du footnote |

### 2.2 Paramètres Moodle core

Un sous-ensemble ciblé de la table `mdl_config` (paramètres globaux Moodle, sans plugin) :

| Clé | Effet |
|---|---|
| `frontpage` | Éléments affichés sur la page d'accueil (visiteurs non connectés) |
| `frontpageloggedin` | Éléments affichés sur la page d'accueil (connectés) |
| `defaulthomepage` | Page par défaut : `0`=site, `1`=tableau de bord, `3`=mes cours |

> Ces clés sont configurables dans la page de paramètres du plugin.

### 2.3 Fichiers du thème

Tous les fichiers uploadés dans le composant `theme_boost_union` :
- Images de fond des slides
- Images de fond des tuiles publicitaires
- Logo (grand format)
- Logo compact (navbar)
- Favicon
- Image de fond globale
- Fichiers propres aux Flavours (logo, favicon, fond par variante)

### 2.4 Smart Menus

Tables `theme_boost_union_menus` + `theme_boost_union_menuitems` exportées intégralement :
- Définitions des menus (titre, position, visibilité, restrictions par rôle/cohorte/langue/date)
- Tous les items de chaque menu (liens, URLs, icônes, restrictions, apparence responsive)

### 2.5 Flavours (variantes visuelles)

Table `theme_boost_union_flavours` :
- Toutes les définitions de variantes (couleurs, SCSS, logo, fond par cohort / catégorie)
- Les fichiers associés à chaque flavour (logo, fond, favicon) avec **remapping d'ID** — voir section 9

### 2.6 Blocs des pages d'accueil et de catégories de cours

- Blocs sur `site-index` (page d'accueil du site)
- Blocs sur `course-index` et `course-index-category-*` (pages de liste de cours)
- Pour les blocs HTML : le contenu est extrait et réinséré
- Pour les autres blocs : la configuration sérialisée est préservée

---

## 3. Ce qui n'est PAS exporté — et pourquoi

| Élément | Pourquoi absent |
|---|---|
| **Structure HTML des boîtes de cours** (`/course/index.php`) | Générée par `core_course_renderer` — code Moodle non modifiable |
| **CSS Bootstrap / Boost core** | Fait partie du thème parent, non personnalisable via export |
| **Blocs sur d'autres pages** (cours, tableau de bord…) | Hors scope — à ajouter dans `blockpagetypes` si besoin |
| **Contenu des cours** | Hors scope (utiliser la sauvegarde/restauration Moodle) |
| **Comptes utilisateurs** | Données personnelles — jamais exportées |
| **Paramètres de sécurité, auth, SMTP…** | Risque sécurité — délibérément exclus |

---

## 4. Installation

### Sur l'instance source ET cible

1. Copier le dossier `local/homepage_config/` dans `{moodle_root}/local/`
2. Se connecter en admin → **Administration du site → Notifications**
3. Cliquer **Continuer** pour installer le plugin
4. Le plugin apparaît dans deux endroits :
   - **Administration → Présentation → Config. visuelle — Export / Import**
   - **Administration → Plugins → Plugins locaux → Homepage Configuration → Paramètres**

---

## 5. Utilisation pas-à-pas

### Étape 1 — Exporter depuis l'instance source

1. Aller sur **Administration → Présentation → Config. visuelle — Export / Import**
2. Vérifier le résumé (nombre de paramètres, fichiers, menus, flavours, blocs)
3. Cliquer **Télécharger l'export (.zip)**
4. Sauvegarder le fichier `homepage_config_YYYYMMDD.zip`

### Étape 2 — Importer sur l'instance cible

1. Aller sur **Administration → Présentation → Config. visuelle — Export / Import**
2. Dans la section Import, sélectionner le fichier `.zip`
3. Cocher **Restaurer les blocs** si vous souhaitez aussi copier les blocs de page d'accueil
   > ⚠️ Cette option **supprime** les blocs existants avant de les recréer
4. Cliquer **Importer**
5. Un message de confirmation indique le nombre d'éléments importés
6. Les caches du thème sont purgés automatiquement — le visuel est actif immédiatement

---

## 6. Page de paramètres — tout configurer

Accessible via : **Administration → Plugins → Plugins locaux → Homepage Configuration**

Si vous n'utilisez pas Boost Union, ou si votre version du thème utilise des noms de tables différents, modifiez ces valeurs avant d'exporter.

### Section : Composant du thème

| Champ | Valeur par défaut | Description |
|---|---|---|
| **Nom du composant** | `theme_boost_union` | Identifiant Moodle du thème. Doit correspondre exactement à ce qui est dans `mdl_config_plugins.plugin`. |

### Section : Tables DB dédiées

| Champ | Valeur par défaut | Description |
|---|---|---|
| **Table Smart Menus** | `theme_boost_union_menus` | Table des menus intelligents (sans préfixe `mdl_`) |
| **Table items Smart Menus** | `theme_boost_union_menuitems` | Table des items de menus |
| **Table Flavours** | `theme_boost_union_flavours` | Table des variantes visuelles |

> Si la table n'existe pas sur l'instance courante, le plugin ignore cette section silencieusement.

### Section : Avancé

#### File areas des Flavours
```
flavours_look_logo
flavours_look_logocompact
flavours_look_favicon
flavours_look_backgroundimage
```
Ces file areas sont **spéciales** : leur `itemid` est l'ID de la ligne flavour dans la DB (et non `0` comme les autres). Lors de l'import, les IDs changent — le plugin recrée les fichiers avec le nouvel ID. Si Boost Union ajoute de nouvelles file areas de flavours dans une future version, ajoutez-les ici.

#### Préfixes de page type pour les blocs
```
site-index
course-index
```
Un `%` est ajouté automatiquement, donc `course-index` capturera aussi `course-index-category-5`. Pour exporter aussi les blocs du tableau de bord, ajoutez `my-index`. Pour les pages de cours, ajoutez `course-view`.

#### Clés de config core
```
frontpage
frontpageloggedin
defaulthomepage
```
**Sécurité importante :** lors de l'import, seules les clés listées ici sont acceptées. Même si un fichier ZIP malveillant contenait d'autres clés core (SMTP, sécurité…), elles seraient ignorées.

---

## 7. Structure du fichier ZIP exporté

```
homepage_config_20260327.zip
│
├── manifest.json           ← Métadonnées de l'export (version format, dates, composant)
├── settings.json           ← Paramètres theme_boost_union (config_plugins)
├── plugin_settings.json    ← Paramètres propres au plugin (ex: tilescfg)
├── core_settings.json      ← Paramètres Moodle core ciblés
├── smartmenus.json         ← Définitions des Smart Menus
├── smartmenu_items.json    ← Items des Smart Menus
├── flavours.json           ← Définitions des Flavours
├── blocks.json             ← Blocs des pages d'accueil / catégories
│
└── files/
    ├── {filearea}/
    │   └── {itemid}/
    │       └── {filename}
    │
    │   Exemples :
    ├── slide_background_images/
    │   └── 0/
    │       └── hero.jpg
    ├── logo/
    │   └── 0/
    │       └── logo.png
    ├── flavours_look_logo/
    │   └── 42/             ← itemid = ID de la flavour dans la DB source
    │       └── logo-rh.png
    └── flavours_look_backgroundimage/
        └── 42/
            └── fond-rh.jpg
```

### Contenu de `manifest.json`

```json
{
  "format_version": "2.0",
  "exported_at": "2026-03-27T14:30:00+00:00",
  "moodle_version": 2022112803,
  "moodle_release": "4.1.3+ (Build: 20230420)",
  "theme_component": "theme_boost_union",
  "plugin_config": {
    "themecomponent": "theme_boost_union",
    "tablemenus": "theme_boost_union_menus",
    "tablemenuitems": "theme_boost_union_menuitems",
    "tableflavours": "theme_boost_union_flavours",
    "flavourfileareas": "flavours_look_logo\nflavours_look_logocompact\n...",
    "blockpagetypes": "site-index\ncourse-index",
    "coreconfigkeys": "frontpage\nfrontpageloggedin\ndefaulthomepage"
  }
}
```

Le champ `plugin_config` enregistre la configuration **utilisée au moment de l'export**. Cela permet de savoir exactement avec quels paramètres le fichier a été créé, même si la configuration du plugin a changé depuis.

---

## 8. Architecture du code

```
local/homepage_config/
│
├── version.php                    ← Version 3.1.1, requires Moodle 4.1
├── settings.php                   ← Enregistrement dans l'arbre admin Moodle
├── index.php                      ← Page Export / Import (UI admin)
├── lib.php                        ← Hook before_footer + rendu des tuiles
│
├── amd/
│   ├── src/tiles_init.js          ← Module AMD source : déplace les tuiles dans le DOM
│   └── build/tiles_init.min.js    ← Version minifiée (chargée en prod)
│
├── templates/
│   └── tiles.mustache             ← Template Mustache des tuiles dynamiques
│
├── classes/
│   ├── manager.php                ← Toute la logique export/import/résumé
│   ├── external/
│   │   └── get_tile_counts.php    ← Web service : comptages cours/utilisateurs
│   ├── event/
│   │   ├── config_exported.php    ← Événement Moodle : export déclenché
│   │   └── config_imported.php    ← Événement Moodle : import déclenché
│   └── privacy/
│       └── provider.php           ← RGPD : aucune donnée perso stockée
│
├── db/
│   ├── access.php                 ← Capability : local/homepage_config:manage
│   ├── caches.php                 ← Définition du cache applicatif tilecounts (TTL 5 min)
│   └── services.php               ← Déclaration du web service get_tile_counts
│
└── lang/
    ├── en/local_homepage_config.php
    └── fr/local_homepage_config.php
```

### La classe `manager` — méthodes publiques

| Méthode | Rôle |
|---|---|
| `export_to_zip()` | Crée le ZIP dans un répertoire temporaire, retourne le chemin |
| `import_from_zip($path, $restore_blocks)` | Applique la config depuis le ZIP, retourne les stats |
| `get_summary()` | Retourne compteurs + timestamps dernier export/import pour l'UI |

### La classe `manager` — méthodes privées clés

| Méthode | Rôle |
|---|---|
| `cfg($key)` | Lit un paramètre depuis `config_plugins` ou retombe sur `DEFAULTS` |
| `cfg_lines($key)` | Idem, retourne un tableau (pour les valeurs multi-lignes) |
| `file_to_zipkey($file)` | Convertit un `stored_file` en chemin ZIP : `files/{area}/{id}/{filename}` |
| `zipkey_to_filerecord($zipname)` | Parse un chemin ZIP en tableau de métadonnées de fichier |
| `restore_smart_menus($menus, $items)` | Supprime et recrée les menus avec remapping d'ID |
| `restore_flavours($flavours, $zip, ...)` | Pré-indexe les fichiers ZIP (O(n+m)), puis recrée les flavours avec remapping d'ID |
| `collect_blocks($context)` | Collecte les blocs des pages suivies (LIKE sur pagetypepattern) |
| `restore_blocks($data, $context)` | Supprime et recrée les blocs (`html` : contenu complet ; autres : configdata uniquement) |

### Web service — `local_homepage_config_get_tile_counts`

Déclaré dans `db/services.php`, implémenté dans `classes/external/get_tile_counts.php`.

- **Accès :** sans authentification (`loginrequired => false`) — données publiques
- **Paramètres :** `cats` (liste d'IDs séparés par virgule), `type` (`courses`/`users`), `sub` (1/0)
- **Retour :** tableau de `{catid, count}`
- **Usage AJAX :** `/lib/ajax/service.php` avec le nom de fonction `local_homepage_config_get_tile_counts`
- Réutilise les helpers de `lib.php` (cache applicatif inclus)

### Le fichier `settings.php`

Deux rôles distincts :
1. Enregistre une **page externe** (`admin_externalpage`) sous *Présentation* pour l'Export/Import
2. Ajoute des items à **`$settings`** (variable gérée par Moodle pour les plugins locaux) → apparaît sous *Plugins → Plugins locaux → Homepage Configuration*

---

## 9. Comportement détaillé de l'import

### Ordre des opérations

La validation du manifest (version format, intégrité du fichier) a lieu en **précondition** — une exception est levée si elle échoue. Ensuite :

```
1. Paramètres du thème      → set_config($name, $value, 'theme_boost_union')
                               + paramètres propres au plugin (tilescfg…)
2. Paramètres core          → set_config($name, $value)   [liste blanche uniquement]
3. Fichiers (hors flavours) → suppression puis recréation dans le file system Moodle
4. Smart Menus              → suppression totale puis réinsertion avec remapping ID
5. Flavours                 → suppression totale + fichiers, puis réinsertion + fichiers (nouvel ID)
6. Blocs (si coché)         → suppression totale puis réinsertion
7. theme_reset_all_caches() → le nouveau visuel est actif immédiatement
```

### Remapping des IDs de Flavours — explication

**Problème :** Un flavour a l'ID `42` sur l'instance source. Ses fichiers (logo, fond) sont stockés avec `itemid = 42`. Sur l'instance cible, ce flavour sera inséré avec un nouvel ID, par exemple `3` (car la table était vide ou avait d'autres entrées).

**Solution :** Le plugin construit une map `{old_id → new_id}`, puis ré-importe les fichiers du ZIP avec le `new_id` comme `itemid`. Le reste de Moodle peut ainsi retrouver les fichiers en cherchant `itemid = 3`.

### Comportement si une table n'existe pas

Si la table `theme_boost_union_menus` n'existe pas sur l'instance cible (par exemple, si le thème n'est pas installé), le plugin **ignore silencieusement** cette section. Aucune erreur fatale.

### Import des fichiers — gestion des doublons

Avant de créer un fichier, le plugin cherche si un fichier identique existe déjà (`contextid` + `component` + `filearea` + `itemid` + `filepath` + `filename`). Si oui, il est **supprimé puis remplacé**. Cela évite les doublons dans `mdl_files`.

---

## 10. Adapter le plugin à un autre thème

Si vous utilisez un thème différent de Boost Union (ex: un thème custom), modifiez les paramètres via l'UI admin :

### Scénario : thème `theme_mycustom` sans Smart Menus ni Flavours

| Paramètre | Valeur à mettre |
|---|---|
| Composant du thème | `theme_mycustom` |
| Table Smart Menus | *(laisser vide)* |
| Table items Smart Menus | *(laisser vide)* |
| Table Flavours | *(laisser vide)* |
| File areas des Flavours | *(laisser vide)* |
| Préfixes page type | `site-index` *(ou autre selon vos besoins)* |
| Clés config core | `frontpage` `frontpageloggedin` `defaulthomepage` |

> Quand une table est vide (chaîne vide), `table_exists('')` retourne `false` → la section est ignorée.

### Scénario : thème avec une table dédiée différente

Votre thème a une table `mdl_theme_mycustom_slideshows` :
- Paramètre "Table Smart Menus" → `theme_mycustom_slideshows`
- Adaptez le schéma de la table en modifiant `restore_smart_menus()` dans `manager.php` si le schéma est très différent

---

## 11. Dépannage

### Le ZIP est vide ou la page plante à l'export

**Cause probable :** L'extension PHP `ZipArchive` n'est pas installée.
**Solution :** `sudo apt install php-zip` puis redémarrer PHP-FPM / Apache.

### L'import dit "fichier invalide"

**Cause :** Le fichier ZIP ne contient pas `manifest.json`.
**Solution :** Utilisez uniquement des fichiers générés par ce plugin. Les exports Moodle natifs (Admin Presets) ne sont pas compatibles.

### Les images ne s'affichent pas après l'import

**Causes possibles :**
1. Les caches de fichiers Moodle ne sont pas à jour → aller dans **Admin → Purger tous les caches**
2. Le `filearea` de l'image dans le ZIP ne correspond pas à ce que le thème attend → vérifier les noms de file areas dans les paramètres

### Les Smart Menus sont absents après l'import

**Cause :** La table `theme_boost_union_menus` n'existe pas sur l'instance cible.
**Solution :** Vérifier que Boost Union est bien installé ET que ses tables DB sont créées (aller dans **Admin → Notifications** pour finaliser l'installation du thème).

### Les flavours sont créés mais sans leurs fichiers

**Cause :** Un file area de flavour n'est pas dans la liste "File areas des Flavours".
**Solution :** Ajouter le file area manquant dans les paramètres du plugin, puis ré-exporter/réimporter.

### Erreurs partielles après l'import

Les erreurs non fatales (un fichier ou un menu qui n'a pas pu être restauré) sont affichées directement dans l'interface, dans une carte d'avertissement sous le résumé de configuration, immédiatement après l'import.

Elles sont également loggées avec `debugging()` au niveau `DEBUG_DEVELOPER` pour le suivi serveur :
- Admin → Administration du site → Développement → Mode débogage → **DEVELOPER**
- Ou consulter les logs PHP du serveur

---

## Versions

| Version | Changements |
|---|---|
| **3.1.1** | Sécurité : assainissement du fallback `unserialize` sur import de blocs. Perf : O(n×m) → O(n+m) dans `restore_flavours`. Rendu tuiles via Mustache + AMD (plus de script inline). Web service `get_tile_counts` remplace l'endpoint PHP brut. Erreurs d'import affichées dans l'UI. Timestamps dernier export/import dans le résumé. |
| **3.0.0** | Tous les paramètres configurables via l'UI admin (plus rien de hard-codé) |
| **2.0.0** | Ajout Smart Menus, Flavours, core settings, blocs course-index |
| **1.0.0** | Export/import config_plugins + fichiers + blocs site-index |
