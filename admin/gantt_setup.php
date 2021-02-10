<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * 	\file		admin/gantt.php
 * 	\ingroup	gantt
 * 	\brief		This file is an example module setup page
 * 				Put some comments here
 */
// Dolibarr environment
$res = @include("../../main.inc.php"); // From htdocs directory
if (! $res) {
    $res = @include("../../../main.inc.php"); // From "custom" directory
}

// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once '../lib/gantt.lib.php';
dol_include_once('abricot/includes/lib/admin.lib.php');

// Translations
$langs->load("gantt@gantt");

// Access control
if (! $user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'alpha');

/*
 * Actions
 */
if (preg_match('/set_(.*)/',$action,$reg))
{
	$code=$reg[1];
	if (dolibarr_set_const($db, $code, GETPOST($code), 'chaine', 0, '', $conf->entity) > 0)
	{
		header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	}
	else
	{
		dol_print_error($db);
	}
}

if (preg_match('/del_(.*)/',$action,$reg))
{
	$code=$reg[1];
	if (dolibarr_del_const($db, $code, 0) > 0)
	{
		Header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	}
	else
	{
		dol_print_error($db);
	}
}

/*
 * View
 */
$page_name = "GanttSetup";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'
    . $langs->trans("BackToModuleList") . '</a>';
print_fiche_titre($langs->trans($page_name), $linkback);

// Configuration header
$head = ganttAdminPrepareHead();
dol_fiche_head(
    $head,
    'settings',
    $langs->trans("Module104039Name"),
    -1,
    "gantt@gantt"
);

// Check abricot version
if(!function_exists('setup_print_title') || !function_exists('isAbricotMinVersion') || isAbricotMinVersion('3.1.0') < 0 ){
	print '<div class="error" >'.$langs->trans('AbricotNeedUpdate').' : <a href="http://wiki.atm-consulting.fr/index.php/Accueil#Abricot" target="_blank"><i class="fa fa-info"></i> Wiki</a></div>';
	exit;
}

// Setup page goes here
$form=new Form($db);
$var=false;
print '<table class="noborder" width="100%">';

// **************************
// CONFIGURATION NUMEROTATION
// **************************
setup_print_title('Parameters');

// Cacher les postes de charges dans l'arborescence du Prod Planning
setup_print_on_off('GANTT_HIDE_WORKSTATION');

// Cacher les parents inexistants
setup_print_on_off('GANTT_HIDE_INEXISTANT_PARENT');

// Désactiver les jalons de commandes fournisseurs
setup_print_on_off('GANTT_DISABLE_SUPPLIER_ORDER_MILESTONE');

// Désactiver la limite de fin et début de projet
setup_print_on_off('GANTT_DISABLE_PROJECT_MILESTONE');

// Désactiver le jalon de livraison de la commande client
setup_print_on_off('GANTT_DISABLE_ORDER_MILESTONE');

// Les jalons ne sont que des alertes
setup_print_on_off('GANTT_BOUND_ARE_JUST_ALERT');

// Repousser les dates de début des tâches parentes si hors bornes
setup_print_on_off('GANTT_MODIFY_PARENT_DATES_AS_CHILD');

// Déplacer les enfants avec le parent
setup_print_on_off('GANTT_MOVE_CHILD_AS_PARENT');

// Voir les projets partagés entre entité
setup_print_on_off('GANTT_MANAGE_SHARED_PROJECT');

// Inclure les projets sans tâche sur la plage mais dans les bornes
setup_print_on_off('GANTT_INCLUDE_PROJECT_WIHOUT_TASK');

// Voir la capacité des postes de charge aussi sur la vue d'un projet
setup_print_on_off('GANTT_SHOW_WORKSTATION_ON_1PROJECT');

// Ne pas afficher l'entête projet
setup_print_on_off('GANTT_DO_NOT_SHOW_PROJECTS');

// Recalculer les dates de la tâche à sa création
setup_print_on_off('GANTT_SET_TASK_DATES_ON_CREATE');

// Permettre l'usage de tâches prévisionnelles
setup_print_on_off('GANTT_ALLOW_PREVI_TASK');

// Surcharge allouée aux postes de charges
$type = '<input type="number" step="0.1" name="GANTT_OVERLOAD_TOLERANCE" value="'.(float)$conf->global->GANTT_OVERLOAD_TOLERANCE.'" />';
setup_print_input_form_part('GANTT_OVERLOAD_TOLERANCE', false, '', array(), $type);

// Cacher les détails (ref de tâche, état de l'of,...)
setup_print_on_off('GANTT_HIDE_TASK_REF');

// Afficher les tâches dans la vue agenda
setup_print_on_off('GANTT_SHOW_TASK_INTO_CALENDAR_VIEW');

// La notion de délai se rapporte à la tâche parente si tâche enfant
setup_print_on_off('GANTT_DELAY_IS_BETWEEN_TASK');

// Ne pas rafraîchir automatique la capacité des postes de charges affichés
setup_print_on_off('GANTT_DONT_AUTO_REFRESH_WS');

// Permettre l'édition directe du pourcentage de progression
setup_print_on_off('GANTT_ALLOW_DIRECT_PROGRESS_EDITING');

// Ne pas dérouler par défaut les jalons liés aux postes de travail dans la vue Gantt
setup_print_on_off('GANTT_DEFAULT_OPENTAB_STATUS');

if (!empty($conf->of->enabled)){
	// Prendre en compte la date de besoin des "Ordres de Fabrication" lors du repositionnement des tâches en automatique
	setup_print_on_off('BETTER_TASK_POSITION_INCLUDE_OF_PRIORITY');
}

/*
$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("GANTT_USE_CACHE_FOR_X_MINUTES").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_GANTT_USE_CACHE_FOR_X_MINUTES">';
echo  '<input type="number" name="GANTT_USE_CACHE_FOR_X_MINUTES" value="'.(int)$conf->global->GANTT_USE_CACHE_FOR_X_MINUTES.'">';
print '<input type="submit" value="'.$langs->trans('Ok').'" />';
print '</form>';
print '</td></tr>';
*/

// Example with a yes / no select
$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("BasedOn").'</td>';
print '<td align="center" colspan="3" style="background:rgb(10, 168, 203)"><img src="../img/twGantt.png" alt="Twproject jQuery Gantt" border="0" />';

print '</td></tr>';


print '</table>';

dol_fiche_end(-1);

llxFooter();

$db->close();
