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

// Sections d'import (import sélectif)
$string['import_sections_heading']       = 'Sections à importer';
$string['import_settings_label']         = 'Paramètres du thème';
$string['import_plugin_settings_label']  = 'Paramètres du plugin (tuiles, banneau)';
$string['import_core_settings_label']    = 'Paramètres Moodle de base (page d\'accueil, nom du thème…)';
$string['import_files_label']            = 'Fichiers (images, logos, fonds…)';
$string['import_menus_label']            = 'Smart Menus';
$string['import_flavours_label']         = 'Flavours';

// Import
$string['import']                 = 'Importer une configuration';
$string['import_desc']            = 'Importer un fichier ZIP exporté depuis un autre Moodle 4.1 avec ce plugin. Les caches du thème sont purgés automatiquement après l\'import.';
$string['import_file']            = 'Fichier de configuration (.zip)';
$string['import_blocks']               = 'Restaurer les blocs (site-index, course-index, tableau de bord)';
$string['import_blocks_warn']          = 'Attention : ceci supprimera et remplacera tous les blocs existants sur la page d\'accueil, les pages de catégories de cours et le tableau de bord par défaut. N\'importez que des fichiers ZIP exportés par ce plugin depuis une instance Moodle de confiance — les données de configuration des blocs sont désérialisées lors de la restauration.';
$string['import_reset_dashboards']     = 'Réinitialiser les tableaux de bord de tous les utilisateurs';
$string['import_reset_dashboards_desc']= 'Après la restauration des blocs du tableau de bord, supprime toutes les personnalisations de tableau de bord des utilisateurs afin qu\'ils voient tous le nouveau tableau de bord par défaut. Irréversible.';
$string['import_btn']             = 'Importer';

// Messages de résultat
$string['import_success']         = 'Import réussi — {$a->settings} paramètres de thème, {$a->coresettings} paramètres core, {$a->files} fichiers.';
$string['import_success_menus']   = 'Smart Menus : {$a} menu(s) restauré(s).';
$string['import_success_flavours']= 'Flavours : {$a} flavour(s) restauré(s).';
$string['import_success_blocks']            = 'Blocs : {$a} bloc(s) restauré(s).';
$string['import_success_dashboards_reset']  = 'Les tableaux de bord des utilisateurs ont été réinitialisés au nouveau modèle par défaut.';
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
$string['heading_tiles_desc'] = 'Configure les tuiles statistiques affichées automatiquement en haut de la page d\'accueil. Les compteurs (cours, inscrits) sont calculés en temps réel. Aucun placeholder n\'est nécessaire dans le résumé du site.';
$string['tilescfg']           = 'Configuration des tuiles (JSON)';
$string['tilescfg_desc']      = 'Tableau JSON d\'objets tuile. Chaque objet supporte : <code>title</code>, <code>subtitle</code>, <code>type</code> (courses/users/custom/none), <code>catid</code>, <code>subcats</code>, <code>value</code> (custom), <code>icon</code> (nom FA), <code>color</code> (blue/green/orange/purple/red/teal), <code>link</code>, <code>newtab</code>.<br>Exemple : <pre>[{"title":"Réseaux","type":"courses","catid":3,"subcats":true,"icon":"sitemap","color":"orange","link":"/course/index.php?categoryid=3"}]</pre>';

// Section : Panneau publicitaire
$string['heading_banner']         = 'Panneau publicitaire';
$string['heading_banner_desc']    = 'Configure le panneau rotatif affiché automatiquement en haut de la page d\'accueil. Les diapositives HTML défilent automatiquement. Laissez le JSON vide pour désactiver le panneau.';
$string['bannercfg']              = 'Configuration des diapositives (JSON)';
$string['bannercfg_desc']         = 'Tableau JSON d\'objets diapositives. Chaque objet doit avoir une clé <code>html</code> contenant le HTML brut de la diapositive. Le nombre de diapositives est illimité.<br>Exemple : <pre>[{"html":"&lt;div&gt;Contenu diapo 1&lt;/div&gt;"},{"html":"&lt;div&gt;Contenu diapo 2&lt;/div&gt;"}]</pre>Le HTML est filtré par HTMLPurifier au rendu — les balises <code>&lt;script&gt;</code> et les gestionnaires d\'événements sont retirés automatiquement.';
$string['bannerinterval']         = 'Intervalle de rotation (secondes)';
$string['bannerinterval_desc']    = 'Nombre de secondes d\'affichage de chaque diapositive avant de passer automatiquement à la suivante. Défaut : 5.';
$string['bannerheight']           = 'Hauteur minimale';
$string['bannerheight_desc']      = 'Hauteur minimale du panneau. Accepte toute valeur CSS : <code>200px</code>, <code>30vh</code>, <code>10em</code>, etc. Laisser vide pour que le contenu détermine la hauteur.';
$string['bannermaxwidth']         = 'Largeur maximale';
$string['bannermaxwidth_desc']    = 'Largeur maximale du panneau (centré automatiquement). Accepte toute valeur CSS : <code>1200px</code>, <code>80%</code>, <code>60rem</code>, etc. Laisser vide pour pleine largeur.';
$string['banner_aria_label']         = 'Panneau publicitaire';
$string['banner_css_length_invalid'] = 'Valeur invalide. Entrez une longueur CSS valide (ex. <code>200px</code>, <code>30vh</code>, <code>80%</code>) ou laissez vide.';

// Tableau de l'historique des imports
$string['history_title']    = 'Historique des imports (10 derniers)';
$string['history_date']     = 'Date';
$string['history_user']     = 'Utilisateur';
$string['history_theme']    = 'Composant thème';
$string['history_settings'] = 'Paramètres';
$string['history_files']    = 'Fichiers';
$string['history_extras']   = 'Menus / Flavours / Blocs';
$string['history_errors']   = 'Erreurs';
$string['history_actions']  = 'Actions';

// Snapshot / rollback
$string['rollback_btn']       = 'Restaurer';
$string['rollback_confirm']   = 'La configuration va être restaurée à l\'état qu\'elle avait juste avant cet import. La configuration actuelle sera écrasée. Continuer ?';
$string['rollback_success']   = 'Configuration restaurée avec succès depuis le snapshot pré-import.';
$string['snapshotnotfound']   = 'Snapshot introuvable. Il a peut-être été supprimé ou appartient à un autre site.';
$string['snapshot_expired']   = 'Ce snapshot a plus de 24 heures et n\'est plus disponible pour une restauration.';

// Avertissement références de cohortes (étape de preview)
$string['cohort_warn_title']    = 'Références à des cohortes détectées';
$string['cohort_warn_intro']    = 'Les éléments suivants font référence à des IDs de cohortes du site source. Comme les IDs de cohortes diffèrent entre instances Moodle, ces conditions ne fonctionneront probablement pas correctement après l\'import et devront être reconfigurées manuellement :';
$string['cohort_warn_menus']    = '{$a} élément(s) de Smart Menu avec des conditions de visibilité basées sur des cohortes';
$string['cohort_warn_flavours'] = '{$a} Flavour(s) avec des règles de portée basées sur des cohortes';
$string['cohort_warn_action']   = 'Après l\'import : ouvrez chaque Smart Menu et Flavour concerné dans les paramètres Boost Union et mettez à jour les conditions de cohorte pour correspondre aux cohortes disponibles sur ce site.';

// Aperçu de l'import (étape 1)
$string['preview_title']          = 'Aperçu de l\'import';
$string['preview_source']         = 'Source de l\'export';
$string['preview_theme']          = 'Composant thème : {$a}';
$string['preview_format']         = 'Version du format : {$a}';
$string['preview_moodle']         = 'Version Moodle : {$a}';
$string['preview_exported_at']    = 'Exporté le : {$a}';
$string['preview_contents']       = 'Ce qui sera importé';
$string['preview_settings_count'] = '{$a} paramètre(s) (thème, plugin, core)';
$string['preview_files_count']    = '{$a} fichier(s) (images, logos…)';
$string['preview_menus_count']    = '{$a} Smart Menu(s)';
$string['preview_flavours_count'] = '{$a} Flavour(s)';
$string['preview_blocks_count']         = '{$a} bloc(s) (restauration des blocs activée)';
$string['preview_reset_dashboards_note']= 'Toutes les personnalisations de tableau de bord des utilisateurs seront réinitialisées après l\'import.';
$string['preview_warning']        = 'Cette opération va écraser la configuration actuelle.';
$string['preview_snapshot_note']  = 'Un snapshot de la configuration actuelle sera pris automatiquement — vous pourrez restaurer depuis l\'historique des imports pendant 24 heures.';
$string['preview_confirm_btn']    = 'Confirmer l\'import';
$string['preview_cancel']         = 'Annuler';
$string['preview_expired']        = 'La session d\'import a expiré. Veuillez re-soumettre le fichier.';

// Diff des paramètres (étape de preview)
$string['diff_title']             = 'Changements des paramètres';
$string['diff_badge_changed']     = '{$a} paramètre(s) vont changer';
$string['diff_badge_unchanged']   = '{$a} inchangé(s)';
$string['diff_source_theme']      = 'thème';
$string['diff_source_plugin']     = 'plugin';
$string['diff_source_core']       = 'core';
$string['diff_col_source']        = 'Source';
$string['diff_col_name']          = 'Paramètre';
$string['diff_col_current']       = 'Valeur actuelle';
$string['diff_col_incoming']      = 'Nouvelle valeur';
$string['diff_notset']            = '(non défini)';
$string['diff_show_unchanged']    = 'Afficher {$a} paramètre(s) inchangé(s)';
$string['diff_hide_unchanged']    = 'Masquer les paramètres inchangés';

// Événements
$string['event_config_exported']  = 'Configuration page d\'accueil exportée';
$string['event_config_imported']  = 'Configuration page d\'accueil importée';

// Confidentialité
$string['privacy:metadata']       = 'Ce plugin enregistre une entrée dans la table local_homepage_config_import à chaque import de configuration effectué par un administrateur. Le journal contient l\'identifiant de l\'utilisateur, l\'horodatage et des compteurs récapitulatifs (paramètres, fichiers, blocs…). Aucun contenu de la configuration importée n\'est associé à l\'utilisateur.';
$string['privacy:metadata:import']              = 'Journal de chaque import de configuration effectué par un administrateur.';
$string['privacy:metadata:import:userid']       = 'Identifiant de l\'utilisateur ayant effectué l\'import.';
$string['privacy:metadata:import:timecreated']  = 'Date et heure de l\'import.';
$string['privacy:metadata:import:themecomponent'] = 'Composant de thème Moodle ciblé par l\'import (ex. theme_boost_union).';
$string['privacy:metadata:import:settings']     = 'Nombre de paramètres de thème restaurés.';
$string['privacy:metadata:import:coresettings'] = 'Nombre de paramètres Moodle de base restaurés.';
$string['privacy:metadata:import:files']        = 'Nombre de fichiers restaurés.';
$string['privacy:metadata:import:menus']        = 'Nombre de Smart Menus restaurés.';
$string['privacy:metadata:import:flavours']     = 'Nombre de Flavours restaurés.';
$string['privacy:metadata:import:blocks']       = 'Nombre de blocs restaurés.';
$string['privacy:metadata:import:errors']       = 'Nombre d\'erreurs rencontrées lors de l\'import.';
$string['privacy:metadata:import:restoreblocks']  = 'Indique si la restauration des blocs était activée pour cet import.';
$string['privacy:metadata:import:snapshotfileid'] = 'Identifiant du fichier snapshot pré-import stocké dans moodledata (0 si non disponible).';
