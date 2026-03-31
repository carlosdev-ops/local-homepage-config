<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * French language strings for local_homepage_config.
 *
 * @package    local_homepage_config
 * @copyright  2026 Carlos Costa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Identité du plugin
$string['pluginname']             = 'Configuration page d\'accueil';
$string['homepage_config:manage'] = 'Gérer la configuration de la page d\'accueil';

// Libellés du menu d'administration
$string['settings']               = 'Config. visuelle page d\'accueil';
$string['exportimport']           = 'Config. visuelle — Export / Import';
$string['pluginsettings']         = 'Config. visuelle — Paramètres';

// Panneau de résumé (index.php)
$string['current_config']         = 'Configuration actuelle';
$string['last_exported']          = 'Dernier export : {$a}';
$string['last_imported']          = 'Dernier import : {$a}';
$string['never']                  = 'jamais';
$string['settings_count']         = '{$a} paramètres de thème (slider, tuiles, bandeaux, SCSS, couleurs…)';
$string['files_count']            = '{$a} fichiers téléversés (images slider, logos, fonds…)';
$string['menus_count']            = '{$a} Smart Menu(s)';
$string['flavours_count']         = '{$a} Flavour(s)';
$string['blocks_count']           = '{$a} bloc(s) sur les pages site-index / course-index';

// Export
$string['export']                 = 'Exporter la configuration';
$string['export_desc']            = 'Télécharger la configuration visuelle complète sous forme de fichier ZIP. Contient : paramètres Boost Union, images, Smart Menus, Flavours et blocs des pages d\'accueil / catégories de cours.';
$string['export_btn']             = 'Télécharger l\'export (.zip)';

// Import
$string['import']                 = 'Importer une configuration';
$string['import_desc']            = 'Importer un fichier ZIP exporté depuis un autre Moodle 4.1 avec ce plugin. Les caches du thème sont purgés automatiquement après l\'import.';
$string['import_file']            = 'Fichier de configuration (.zip)';
$string['import_blocks']          = 'Restaurer les blocs (site-index + course-index)';
$string['import_blocks_warn']     = 'Attention : ceci supprimera et remplacera tous les blocs existants sur la page d\'accueil et les pages de catégories de cours. N\'importez que des fichiers ZIP exportés par ce plugin depuis une instance Moodle de confiance — les données de configuration des blocs sont désérialisées lors de la restauration.';
$string['import_btn']             = 'Importer';

// Messages de résultat
$string['import_success']         = 'Import réussi — {$a->settings} paramètres de thème, {$a->core_settings} paramètres core, {$a->files} fichiers.';
$string['import_success_menus']   = 'Smart Menus : {$a} menu(s) restauré(s).';
$string['import_success_flavours']= 'Flavours : {$a} flavour(s) restauré(s).';
$string['import_success_blocks']  = 'Blocs : {$a} bloc(s) restauré(s).';
$string['import_errors']          = 'Import terminé avec {$a} erreur(s). Voir les détails ci-dessous.';
$string['import_error_details']   = 'Détails des erreurs d\'import';

// Erreurs
$string['cannotopenzip']          = 'Impossible d\'ouvrir le fichier ZIP. Vérifiez que le fichier est un export valide.';
$string['invalidexportfile']      = 'Fichier d\'export invalide : manifeste introuvable. Utilisez un fichier généré par ce plugin.';
$string['ziparchive_missing']     = 'L\'extension PHP ZipArchive est requise mais non disponible sur ce serveur.';
$string['incompatibleformatversion'] = 'Cet export a été créé avec une version plus récente du plugin (format {$a->got}). Le format maximum supporté est {$a->max}. Veuillez mettre à jour le plugin avant d\'importer.';
$string['import_err_upload']      = 'Échec du téléversement. Vérifiez les paramètres PHP (upload_max_filesize, post_max_size).';
$string['import_err_extension']   = 'Type de fichier invalide. Sélectionnez un fichier .zip exporté par ce plugin.';
$string['import_err_mimetype']    = 'Le fichier téléversé ne semble pas être une archive ZIP valide (type détecté : {$a}).';

// ── Page de paramètres ────────────────────────────────────────────────────────

// Section : Composant du thème
$string['heading_theme']          = 'Composant du thème';
$string['heading_theme_desc']     = 'Thème Moodle dont les entrées <code>config_plugins</code> et les fichiers seront exportés / importés.';

$string['themecomponent']         = 'Nom du composant';
$string['themecomponent_desc']    = 'Identifiant interne Moodle du thème, ex. <code>theme_boost_union</code>. Doit correspondre au composant stocké dans la table <code>config_plugins</code>.';

// Section : Tables dédiées
$string['heading_tables']         = 'Tables DB dédiées au thème';
$string['heading_tables_desc']    = 'Noms des tables supplémentaires créées par le thème. Laisser vide pour désactiver l\'export de la fonctionnalité correspondante.';

$string['tablemenus']             = 'Table des Smart Menus';
$string['tablemenus_desc']        = 'Table DB contenant les définitions des Smart Menus (sans le préfixe <code>mdl_</code>).';

$string['tablemenuitems']         = 'Table des items de Smart Menus';
$string['tablemenuitems_desc']    = 'Table DB contenant les items des Smart Menus (sans le préfixe <code>mdl_</code>).';

$string['tableflavours']          = 'Table des Flavours';
$string['tableflavours_desc']     = 'Table DB contenant les définitions des Flavours / variantes visuelles (sans le préfixe <code>mdl_</code>).';

// Section : Avancé
$string['heading_advanced']       = 'Avancé';
$string['heading_advanced_desc']  = 'Ajustez quels fichiers, blocs et paramètres core sont inclus dans l\'export.';

$string['flavourfileareas']       = 'File areas des Flavours';
$string['flavourfileareas_desc']  = 'File areas dont l\'<code>itemid</code> correspond à l\'ID d\'une ligne Flavour. Un par ligne. Ces fichiers sont traités séparément pour que les IDs soient correctement remappés à l\'import.';

$string['blockpagetypes']         = 'Préfixes de page type pour les blocs';
$string['blockpagetypes_desc']    = 'Préfixes de <code>pagetypepattern</code> utilisés pour sélectionner les blocs à exporter. Un par ligne. Un joker <code>%</code> est ajouté automatiquement, ex. <code>course-index</code> capture aussi <code>course-index-category-5</code>.';

$string['coreconfigkeys']         = 'Clés de config core';
$string['coreconfigkeys_desc']    = 'Clés de la table <code>config</code> Moodle (sans plugin) à inclure dans l\'export. Une par ligne. Seules ces clés pourront être écrites lors d\'un import.';

// Section : Tuiles dynamiques
$string['heading_tiles']      = 'Tuiles dynamiques — page d\'accueil';
$string['heading_tiles_desc'] = 'Configure les tuiles injectées dans <code>&lt;div id="hpc-tiles"&gt;&lt;/div&gt;</code> sur la page d\'accueil. Les compteurs (cours, inscrits) sont calculés en temps réel à chaque chargement de page.';
$string['tilescfg']           = 'Configuration des tuiles (JSON)';
$string['tilescfg_desc']      = 'Tableau JSON d\'objets tuile. Chaque objet supporte : <code>title</code>, <code>subtitle</code>, <code>type</code> (courses/users/custom/none), <code>catid</code>, <code>subcats</code>, <code>value</code> (custom), <code>icon</code> (nom FA), <code>color</code> (blue/green/orange/purple/red/teal), <code>link</code>, <code>newtab</code>.<br>Exemple : <pre>[{"title":"Réseaux","type":"courses","catid":3,"subcats":true,"icon":"sitemap","color":"orange","link":"/course/index.php?categoryid=3"}]</pre>';

// Événements
$string['event_config_exported']  = 'Configuration page d\'accueil exportée';
$string['event_config_imported']  = 'Configuration page d\'accueil importée';

// Confidentialité
$string['privacy:metadata']       = 'Ce plugin ne stocke aucune donnée personnelle. Il lit et écrit uniquement la configuration du site Moodle.';
