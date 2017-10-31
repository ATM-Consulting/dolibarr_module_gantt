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
 *	\file		lib/gantt.lib.php
 *	\ingroup	gantt
 *	\brief		This file is an example module library
 *				Put some comments here
 */

function ganttAdminPrepareHead()
{
    global $langs, $conf;

    $langs->load("gantt@gantt");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/gantt/admin/gantt_setup.php", 1);
    $head[$h][1] = $langs->trans("Parameters");
    $head[$h][2] = 'settings';
    $h++;
    $head[$h][0] = dol_buildpath("/gantt/admin/gantt_about.php", 1);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';
    $h++;

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    //$this->tabs = array(
    //	'entity:+tabname:Title:@gantt:/gantt/mypage.php?id=__ID__'
    //); // to add new tab
    //$this->tabs = array(
    //	'entity:-tabname:Title:@gantt:/gantt/mypage.php?id=__ID__'
    //); // to remove a tab
    complete_head_from_modules($conf, $langs, $object, $head, $h, 'gantt');

    return $head;
}


function _add_project_included_into_date(&$TTask) {

	global $db,$langs,$range,$conf;

	$sql="SELECT p.rowid as fk_project FROM ".MAIN_DB_PREFIX."projet p
			WHERE p.dateo <= '".$range->sql_date_end."' AND p.datee >=  '".$range->sql_date_start."' ";
	$res = $db->query($sql);
	while($obj = $db->fetch_object($res)) {

		$project = new Project($db);
		$project->fetch($obj->fk_project);

		if($project->id>0)$project->title = $project->ref.' '.$project->title;

		$project->ganttid = 'P'.$project->id;

		$TTask[$project->id]=array(
				'childs'=>array()
				,'object'=>$project
		);
		_adding_task_project_end($project, $TTask[$project->id]['childs']);
		_load_child_tasks( $TTask[$project->id]['childs'] , $project);
	}
}




function _get_task_for_of($fk_project = 0) {

	global $db,$langs,$range,$conf;

	$TCacheProject = $TCacheOrder  = $TCacheWS = array();

	$PDOdb=new TPDOdb;

	$idNoAffectation = 1;

	$sql = "SELECT t.rowid
		FROM ".MAIN_DB_PREFIX."projet_task t LEFT JOIN ".MAIN_DB_PREFIX."projet_task_extrafields tex ON (tex.fk_object=t.rowid)
			LEFT JOIN ".MAIN_DB_PREFIX."projet p ON (p.rowid=t.fk_projet)
		WHERE t.dateo IS NOT NULL ";

	if($fk_project>0) $sql.= " AND fk_projet=".$fk_project;
	else {
		$sql.= " AND tex.fk_of IS NOT NULL AND tex.fk_of>0 AND (t.progress<100 OR t.progress IS NULL)
			AND p.fk_statut = 1
			";

		$sql.=" AND t.dateo <= '".$range->sql_date_end."' AND t.datee >=  '".$range->sql_date_start."' ";

		if(!empty($conf->global->GANTT_MANAGE_SHARED_PROJECT)) $sql.=" AND p.entity IN (".getEntity('project',1).")";
		else $sql.=" AND p.entity=".$conf->entity;

	}

	if(GETPOST('restrictWS')>0) {
		$sql.=" AND tex.fk_workstation=".(int)GETPOST('restrictWS');
	}
	else if(GETPOST('restrictWS','int') === '0' ) {
		$sql.=" AND (tex.fk_workstation IS NULL) ";
	}

	$sql.=" ORDER BY t.rowid ";

	$res = $db->query($sql);
	if($res===false) {
		var_dump($db);exit;
	}

	$TTask=array();
	if($fk_project == 0 && !empty($conf->global->GANTT_INCLUDE_PROJECT_WIHOUT_TASK)) {
		_add_project_included_into_date($TTask);
	}

	while($obj = $db->fetch_object($res)) {

		$task = new Task($db);
		$task->fetch($obj->rowid);
		$task->fetch_optionals($gantt_milestonetask->id);

		$task->ganttid = 'T'.$task->id;
		$task->label = strip_tags(strtr($task->label, array("\n"=>' ',"\r"=>'')));
		$task->title = $task->label;
		$task->text = $task->ref.' '.$task->label;
		if($task->planned_workload>0) {
			$task->text.=' '.dol_print_date($task->planned_workload,'hour');
		}

		if($task->array_options['options_fk_of']>0) {

			$of=new TAssetOF();
			$of->load($PDOdb, $task->array_options['options_fk_of']);

		}
		else{

			$of=new stdClass;
			$of->id = 0;
			$of->numero = 'None';
			$of->fk_commande = 0;
		}

		if($of->id>0) {
			$of->ganttid = 'M'.(int)$of->id;
		}
		else {
			$of->ganttid = 'MNA'.$idNoAffectation; $idNoAffectation++;
		}

		$of->title = $of->numero.' '.$of->getLibStatus(true);

		if($of->fk_commande>0) {

			if(!empty($TCacheOrder[$of->fk_commande])) {

				$order=$TCacheOrder[$of->fk_commande];

			}
			else{
				$order=new Commande($db);
				$order->fetch($of->fk_commande);
				$order->fetch_thirdparty();

				if($order->id>0){
					$order->title = $order->ref.' '.$order->thirdparty->name;
				}
				else {
					$order->title = $langs->trans('UndefinedOrder');
				}
				$TCacheOrder[(int)$order->id] = $order;
			}


		}
		else {

			$order = new Commande($db);

			$order->title = $langs->trans('UndefinedOrder');

		}
		if($order->id >0){
			$order->ganttid = 'O'.$order->id;
		}
		else{
			$order->ganttid= 'ONA'.$idNoAffectation; $idNoAffectation++;
		}


		if(!empty($TCacheProject[$task->fk_project])) {

			$project=$TCacheProject[$task->fk_project];

		}
		else{
			$project = new Project($db);
			$project->fetch($task->fk_project);

			if($project->id>0)$project->title = $project->ref.' '.$project->title;
			else {
				$project->title = $langs->trans('UndefinedProject');
			}

			$TCacheProject[$project->id] = $project;
		}

		if($project->id>0) {
			$project->ganttid = 'P'.$project->id;
		}
		else {
			$project->ganttid = 'PNA'.$idNoAffectation; $idNoAffectation++;
		}

		if(!empty($conf->workstation->enabled)) {
			if(!empty($TCacheWS[$task->array_options['options_fk_workstation']])) {

				$ws=$TCacheWS[$task->array_options['options_fk_workstation']];

			}
			else{
				$ws = new TWorkstation();
				$ws->load($PDOdb,$task->array_options['options_fk_workstation']);
				$ws->text = $ws->title = $ws->name;
				$TCacheWS[$ws->id] = $ws;

			}
		}
		else{
			$ws=new StdClass;
			$ws->element = 'workstation';
			$ws->id = 0;
		}

		if($ws->id>0) {
			$ws->ganttid = 'W'.(int)$ws->id;
		}
		else{
			$ws->ganttid = 'WNA'.$idNoAffectation; $idNoAffectation++;
			$ws->title = $langs->trans('UndefinedWorkstation');
		}


		if(empty($TTask[$project->id])) {

			$TTask[$project->id]=array(
					'childs'=>array()
					,'object'=>$project
			);
			_adding_task_project_end($project, $TTask[$project->id]['childs']);
			_load_child_tasks( $TTask[$project->id]['childs'] , $project);

		}

		$order->id=(int)$order->id;

		if(empty($TTask[$project->id]['childs'][$order->id])) {

			$TTask[$project->id]['childs'][$order->id]=array(
					'childs'=>array()
					,'object'=>$order
			);

			_load_child_tasks( $TTask[$project->id]['childs'][$order->id]['childs'], $order);
		}

		_adding_task_order($order, $TTask[$project->id]['childs'][$order->id]['childs']);

		if(empty($TTask[$project->id]['childs'][$order->id]['childs'][$of->id])) {

			$TTask[$project->id]['childs'][$order->id]['childs'][$of->id]=array(
					'childs'=>array()
					,'object'=>$of
			);
		}
		_adding_task_supplier_order($PDOdb,$of, $TTask[$project->id]['childs'][$order->id]['childs'][$of->id]['childs']);

		if(empty($TTask[$project->id]['childs'][$order->id]['childs'][$of->id]['childs'][$ws->id])) {

			$TTask[$project->id]['childs'][$order->id]['childs'][$of->id]['childs'][$ws->id]=array(
					'childs'=>array()
					,'object'=>$ws
			);
		}

		$TTask[$project->id]['childs'][$order->id]['childs'][$of->id]['childs'][$ws->id]['childs'][$task->id] = $task;

		_load_child_tasks( $TTask[$project->id]['childs'][$order->id]['childs'][$of->id]['childs'][$ws->id]['childs'],$task);
	}
	_load_child_tasks( $TTask);
	return $TTask;

}

function _adding_task_order(&$order,&$TData) {

	global $db, $langs, $conf;
	if(!empty($conf->global->GANTT_DISABLE_ORDER_MILESTONE)) {
		return false;
	}

	if($order->date_livraison>0) {
		$object=new stdClass();
		$object->element = 'milestone';
		$object->title = $object->text = $langs->trans('EndOfOrder', $order->ref, dol_print_date($order->date_livraison));
		$object->date= $order->date_livraison+ 84399; //23:59:59
		$object->ganttid = 'JS'.$order->id;
		$object->bound='after';
		$object->visible = 1;

		$TData[$object->ganttid]['object'] = $object;

	}

}

function _adding_task_project_end(&$project,&$TData) {
	global $db, $langs, $conf;
	if(!empty($conf->global->GANTT_DISABLE_PROJECT_MILESTONE)) {
		return false;
	}

	$date_start = !empty($project->array_options['options_date_start_prod']) ? strtotime( $project->array_options['options_date_start_prod'] ): $project->date_start;

	if($date_start>0) {

		$object=new stdClass();
		$object->element = 'milestone';
		$object->title = $object->text = $langs->trans('StartOfProject', $project->ref, dol_print_date($date_start));
		$object->date=$date_start;
		$object->ganttid = 'JPS'.$project->id;
		$object->bound='before';

		if($date_start>$project->date_start)$object->visible = 1;

		$TData[$object->ganttid]['object'] = $object;

	}

	if($project->date_end>0) {
		$object=new stdClass();
		$object->element = 'milestone';
		$object->title = $object->text = $langs->trans('EndOfProject', $project->ref, dol_print_date($project->date_end));
		$object->date= $project->date_end + 84399; //23:59:59
		$object->ganttid = 'JPE'.$project->id;
		$object->bound='after';

		$TData[$object->ganttid]['object'] = $object;

	}

}

function _adding_task_supplier_order(&$PDOdb, &$assetOf,&$TData) {
	global $db, $langs, $conf;

	if(!empty($conf->global->GANTT_DISABLE_SUPPLIER_ORDER_MILESTONE) || $assetOf->id<=0) {
		return false;
	}

	dol_include_once('/fourn/class/fournisseur.commande.class.php');
	$TIdCommandeFourn = $assetOf->getElementElement($PDOdb);

	if(count($TIdCommandeFourn)){
		foreach($TIdCommandeFourn as $idcommandeFourn){
			$cmd = new CommandeFournisseur($db);
			$cmd->fetch($idcommandeFourn);

			if($cmd->statut>0 && $cmd->statut<5 && $cmd->date_livraison>0) {
				$object=new stdClass();
				$object->element = 'milestone';
				$object->title = $object->text = $langs->trans('AwaitingDelivery', $cmd->ref, dol_print_date($cmd->date_livraison));
				$object->date= $cmd->date_livraison;
				$object->ganttid = 'JSO'.$cmd->id;
				$object->bound='before';
				$object->visible = 1;

				$TData[$object->ganttid]['object'] = $object;

			}


		}
	}

}

/*
 * Complete avec les tâches previ du parent
 *
 */
function _load_child_tasks(&$TData, $gantt_parent_objet = false, $level = 0, $maxDeep = 3) {
	global $db,$range;

	if($level>$maxDeep) return;

	$projet_previ=new Project($db);
	$projet_previ->fetch(0,'PREVI');
	$fk_projet_previ = (int)$projet_previ->id;

	$sql = "SELECT t.rowid
				FROM ".MAIN_DB_PREFIX."projet_task t LEFT JOIN ".MAIN_DB_PREFIX."projet_task_extrafields tex ON (tex.fk_object=t.rowid)
						LEFT JOIN ".MAIN_DB_PREFIX."projet p ON (p.rowid=t.fk_projet)
						WHERE t.fk_projet=$fk_projet_previ AND ";


	if($level > 0)
	{
		$sql.= " t.fk_task_parent = ".(int)$gantt_parent_objet->id;
	}
	elseif(empty($gantt_parent_objet)) {
		$sql.= " (tex.fk_gantt_parent_task IS NULL OR tex.fk_gantt_parent_task='0')";
	}
	else {
		$sql.= " tex.fk_gantt_parent_task = '".$gantt_parent_objet->ganttid."'";
	}

	if(GETPOST('restrictWS')>0) {
		$sql.=" AND tex.fk_workstation=".(int)GETPOST('restrictWS');
	}
	else if(GETPOST('restrictWS','int') == 0 ) {
		$sql.=" AND (tex.fk_workstation IS NULL) ";
	}

	$sql.=" AND t.dateo BETWEEN '".$range->sql_date_start."' AND '".$range->sql_date_end."'";

	$sql.=" AND p.entity IN (".getEntity('project',1).")";

	//echo $sql.$sqlWhere;
	$res = $db->query($sql);
	if($res===false) {
		var_dump($db);exit;
	}

	$hasChild = false;
	while($obj = $db->fetch_object($res)) {
		$task = new Task($db);
		$task->fetch($obj->rowid);
		$task->title = $task->label;
		$task->text = 'PREVI '.$task->label;
		$task->ganttid = 'T'.$task->id;
		$task->fk_task_parent = $gantt_parent_objet?$gantt_parent_objet->id:0;

		$TData['PREVI'.$task->id]['object'] = $task;

		$hasChild = true;

		if(_load_child_tasks( $TData['PREVI'.$task->id]['childs'] ,$task,($level+1) , $maxDeep)) {

			$TData['PREVI'.$task->id]['object']->element = ( $level==0 ? 'project' : 'commande');

		}


	}

	return $hasChild;

}

function _get_json_data(&$object, $close_init_status, $fk_parent_object=null, $time_task_limit_no_before=0,$time_task_limit_no_after=0) {

	if($object->element == 'commande') {
		return ' {"id":"'.$object->ganttid.'",objElement:"'.$object->element.'", "text":"'.$object->title.'", "type":gantt.config.types.order'.(!is_null($fk_parent_object) ? ' ,parent:"'.$fk_parent_object.'" ' : '' ).', open: '.$close_init_status.'}';
	}
	else if($object->element == 'workstation') {

		return ' {"id":"'.$object->ganttid.'",objElement:"'.$object->element.'", "text":"'.$object->title.'", "type":gantt.config.types.project'.(!is_null($fk_parent_object) ? ' ,parent:"'.$fk_parent_object.'" ' : '' ).', open: true}';
	}
	else if($object->element == 'project') {

		$taskColor='';
		$projectColor='';
		if(ColorTools::validate_color($object->array_options['options_color']))
		{
			$taskColor= ColorTools::adjustBrightness($object->array_options['options_color'], -50); //TODO récupérer la taskColor du projet...
			$projectColor= ',color:"'.$object->array_options['options_color'].'"';
		}

		return ' {"id":"'.$object->ganttid.'",objElement:"'.$object->element.'", "text":"'.$object->title.'", "type":gantt.config.types.project, open: '.$close_init_status.$projectColor.'}';

	}
	else if($object->element == 'of') {
		return ' {"id":"'.$object->ganttid.'",objElement:"'.$object->element.'", "text":"'.$object->title.'", "type":gantt.config.types.of'.(!is_null($fk_parent_object) ? ' ,parent:"'.$fk_parent_object.'" ' : '' ).', open: '.$close_init_status.'}';
	}
	elseif($object->element == 'project_task') {
		global $range,$TWS,$workstationList;

		_check_task_wihout_workstation($object);

		if($range->autotime) {
			if(empty($range->date_start) || $object->date_start<$range->date_start)$range->date_start=$object->date_start;
			if(empty($range->date_end) || $range->date_end<$object->date_end)$range->date_end=$object->date_end;
		}

		$duration = $object->date_end>0 ? ceil( ($object->date_end - $object->date_start) / 86400 ) : ceil($object->planned_workload / (3600 * 7));
		if($duration<1)$duration = 1;

		$fk_workstation = (int) $object->array_options['options_fk_workstation'];
		if($fk_workstation>0) $TWS[$fk_workstation] = $workstationList[$fk_workstation]; //TODO ouh que c'est moche !

		$needed_ressource= $object->array_options['options_needed_ressource']>0 ? $object->array_options['options_needed_ressource'] : 1;

		return ' {"id":"'.$object->ganttid.'",needed_ressource:'.(int)$needed_ressource.',time_task_limit_no_before:'.(int)$time_task_limit_no_before.',time_task_limit_no_after:'.(int)$time_task_limit_no_after.',planned_workload:'.(int)$object->planned_workload.' ,objElement:"'.$object->element.'",objId:"'.$object->id.'", workstation:'.$fk_workstation.' , "text":"'.$object->text.'" , "title":"'.$object->title.'", "start_date":"'.date('d-m-Y',$object->date_start).'", "duration":"'.$duration.'"'.(!is_null($fk_parent_object) ? ' ,parent:"'.$fk_parent_object.'" ' : '' ).', progress: '.($object->progress / 100).',owner:"'.$fk_workstation.'", type:gantt.config.types.task , open: '.$close_init_status.'}';

	}
	else if($object->element== 'milestone' || $object->element == 'release') {
		/*
		 global $range;
		 if($range->autotime) {
		 if(empty($range->date_start) || $object->date<$range->date_start)$range->date_start=$object->date;
		 if(empty($range->date_end) || $range->date_end<$object->date)$range->date_end=$object->date;
		 }*/

		$date = date('d-m-Y',$object->date);
		return ' {"id":"'.$object->ganttid.'",objElement:"'.$object->element.'", "text":"'.$object->text.'", "start_date":"'.$date.'", "duration":1 '.(!is_null($fk_parent_object) ? ' ,parent:"'.$fk_parent_object.'" ' : '' ).', type:gantt.config.types.release, visible:'.( empty($object->visible) ? 0 : 1 ).'}';

	}

	return '{ nonObjectManaged:"'.$object->element.'" }';
}

/*
 * @deprecated
 * @param taskColor		web hexa color format like #FFFFFF
 */
function _format_task_for_gantt(&$tasksList, &$TData,&$TLink,$owner=0,$t_start=false,$t_end=false, $taskColor=false)
{
	return false;

	if(!empty($tasksList))
	{
		foreach($tasksList as &$task) {
			if(empty($t_start) || $task->date_start<$t_start)$t_start=$task->date_start;
			if(empty($t_end) || $t_end<$task->date_end)$t_end=$task->date_end;
			$duration = $task->date_end>0 ? ceil( ($task->date_end - $task->date_start) / 86400 ) : ceil($task->planned_workload / (3600 * 7));
			if($duration<1)$duration = 1;

			$type = ',type:gantt.config.types.task';
			if(empty($task->fk_task_parent) && empty($task->array_options['options_fk_gantt_parent_task'])) {
				$type = ',type:gantt.config.types.project';
			}

			// Check if a color is define for this task
			if(!empty($task->array_options['options_color']) && ColorTools::validate_color($task->array_options['options_color']))
			{
				$taskColor = $task->array_options['options_color'];
			}

			$taskColorCode='';
			if(ColorTools::validate_color($taskColor))
			{
				$taskColorCode= ',color:"'.$taskColor.'"';
			}

			$workstation = ',workstation:0';
			if(!empty($task->array_options['options_fk_workstation']))
			{
				$workstation = ',workstation:'.$task->array_options['options_fk_workstation'];
			}

			$needed_ressource= ',needed_ressource:0';
			if(!empty($event->array_options['options_needed_ressource']))
			{
				$needed_ressource= ',needed_ressource:'.(int)$event->array_options['options_needed_ressource'];
			}

			if( $type == ',type:gantt.config.types.project') {
				$TData[] = ' {id:"'.$task->ganttid.'",objId:"'.$task->id.'",objElement:"'.$task->element .'",text:"'.$task->text.'", "title":"'.$task->title.'"'.$type.' '.$taskColorCode.'}';
			}
			else {
				$TData[] = ' {"id":"'.$task->ganttid.'"'.$needed_ressource.',objId:"'.$task->id.'",objElement:"'.$task->element.(empty($task->array_options['options_fk_gantt_parent_task']) ? '' : '", source:"'.$task->array_options['options_fk_gantt_parent_task'].'"').', "text":"'.$task->text.'", "title":"'.$task->title.'", "start_date":"'.date('d-m-Y',$task->date_start).'", "duration":"'.$duration.'"'.(!is_null($task->array_options['options_fk_gantt_parent_task']) ? ' ,parent:"'.$task->array_options['options_fk_gantt_parent_task'].'" ' : '' ).', progress: '.($task->progress / 100).',owner:"'.$owner.'" '.$type.' '.$taskColorCode.$workstation.'}';
			}


			if($task->fk_task_parent>0) {
				// $TLink[] = ' {id:'.(count($TLink)+1).', source:"'.$task->array_options['options_fk_gantt_parent_task'].'", target:"'.$task->ganttid.'", type:"0"}';
			}
		}
	}
}

/*
 * stock les posts de travail dans une variable globale
 * return int 			count of result
 */
function _get_workstation()
{
	global $db,$langs, $workstationList,$conf;

	if(empty($conf->workstation->enabled)) {
		$workstationList=array();
		return 0;
	}

	$sql = "SELECT w.rowid as id , w.name, w.nb_hour_capacity, w.nb_hour_capacity, w.nb_ressource FROM ".MAIN_DB_PREFIX."workstation w  ";

	//echo $sql.$sqlWhere;
	$res = $db->query($sql);
	if($res===false) {
		var_dump($db);exit;
	}

	$workstationList = array();

	while($obj = $db->fetch_object($res)) {
		$workstationList[$obj->id] = $obj;
	}
	return count($workstationList);
}

/*
 * formate la liste des workstations pour le select de la lightbox
 */
function _get_workstation_list()
{
	global $db,$langs,$workstationList;

	if(empty($workstationList)){ _get_workstation(); }

	$TData[] = '{key:"0", label: " "}';
	foreach($workstationList as $ws) {

		$TData[] = '{key:"'.$ws->id.'", label: "'.$ws->name.'"}';

	}
	return implode(',',$TData);
}
/*
 * Récuperation des evenements type agenda
 */
function _get_events( &$TData,&$TLink,$fk_project=0,$owner=0,$taskColor= '#f7d600')
{
	global $db,$range;

	$day_range = empty($conf->global->GANTT_DAY_RANGE_FROM_NOW) ? 90 : $conf->global->GANTT_DAY_RANGE_FROM_NOW;

	$sql = "SELECT a.id
		FROM ".MAIN_DB_PREFIX."actioncomm a
			LEFT JOIN ".MAIN_DB_PREFIX."actioncomm_extrafields aex ON (aex.fk_object=a.id)
		WHERE ";

	$sql.=" ( a.datep BETWEEN '".$range->sql_date_start."' AND '".$range->sql_date_end."' ";
	$sql.=" OR a.datep2 BETWEEN '".$range->sql_date_start."' AND '".$range->sql_date_end."' )";
	$sql.=" AND aex.fk_workstation > 0 ";

	if($fk_project > 0)
	{
		$sql.= " AND a.fk_project=".(int)$fk_project;
	}
	else
	{
		$sql.= " AND ( a.fk_project=0 OR ISNULL(a.fk_project) )";
	}

	$sql.=" AND a.entity IN (".getEntity('actioncomm',1).")";

	//echo $sql;
	//exit();
	$res = $db->query($sql);
	if($res===false) {
		var_dump($db);exit;
	}

	while($obj = $db->fetch_object($res))
	{

		$event = new ActionComm($db);
		$event->fetch($obj->id);
		if(empty($event->array_options))
		{
			$event->fetch_optionals();
		}

		$event->ganttid = 'A'.$event->id;
		$event->title = 'AGENDA'.' '. $event->label;

		if($range->autotime) {
			if(empty($range->date_start) || $event->datep<$range->date_start) $range->date_start=$event->datep;
			if(empty($range->date_end) || $range->date_end<$event->datef ) $range->date_end=$event->datef;
		}

		$duration = $event->datef>0 ? ceil( ($event->datef- $event->datep) / 86400 ) : ceil($event->planned_workload / (3600 * 7));

		if($duration<1)$duration = 1;

		$type = ',type:gantt.config.types.actioncomm';


		$taskColorCode= '';
		if(ColorTools::validate_color($taskColor))
		{
			$taskColorCode= ',color:"'.$taskColor.'"';
		}


		$workstation = ',workstation:0';
		if(!empty($event->array_options['options_fk_workstation']))
		{
			$workstation = ',workstation:'.$event->array_options['options_fk_workstation'];
		}

		$parent = $source = '';
		if($fk_project>0)
		{
			$parent = ',source:"P'.$fk_project.'"';
			$source = ',parent:"P'.$fk_project.'"';
		}

		$needed_ressource= ',needed_ressource:0';
		if(!empty($event->array_options['options_needed_ressource']))
		{
			$needed_ressource= ',needed_ressource:'.$event->array_options['options_needed_ressource'];
		}


		$TData[] = ' {"id":"'.$event->ganttid.'"'.$needed_ressource.',objId:"'.$event->id.'",objElement:"'.$event->element.'", "text":"'.$event->title.'", "start_date":"'.date('d-m-Y',$event->datep).'", "duration":"'.$duration.'" , progress:'.$event->percentage.' '.$type.' '.$taskColorCode.$workstation.$parent.$source.'}';


	}
}

//TODO useless, just tell if there or not task without workstation
function _check_task_wihout_workstation(&$task) {
	global $TTaskNoOrdoTime;

	if(empty($TTaskNoOrdoTime)) $TTaskNoOrdoTime = array();

	if(empty($task->array_options['options_fk_workstation'])) {

		$duration = $task->date_end>0 ? ceil( ($task->date_end - $task->date_start) / 86400 ) : ceil($task->planned_workload / (3600 * 7));
		if($duration<1)$duration = 1;

		$t = $task->date_start;
		$end_no_time = $task->date_start +( $duration * 86400 );

		while($t<$end_no_time) {

			if(empty($TTaskNoOrdoTime[date('Y-m-d',$t)]))$TTaskNoOrdoTime[date('Y-m-d',$t)]=0;

			$TTaskNoOrdoTime[date('Y-m-d',$t)] += (int)($task->planned_workload / $duration);

			$t = strtotime('+1day',$t);
		}


	}

}