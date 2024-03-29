
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

		if($project->id>0)$project->title = (empty($conf->global->GANTT_HIDE_TASK_REF) ? $project->ref.' ' : '').$project->title;

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

	$TCacheProject = $TCacheOrder  = $TCacheWS = $TCacheOF = array();

	$PDOdb=new TPDOdb;

    dol_include_once('/gantt/class/gantt.class.php');
	$TTaskObject = GanttPatern::getTasks($range->sql_date_start, $range->sql_date_end, $fk_project, GETPOST('restrictWS','int'),GETPOST('ref_of'),GETPOST('ref_cmd'));

	$TTask=array();
	if($fk_project == 0 && !empty($conf->global->GANTT_INCLUDE_PROJECT_WIHOUT_TASK)) {
		_add_project_included_into_date($TTask);
	}

	foreach($TTaskObject as &$task) {

        $task->ganttid = 'T' . $task->id;
        $task->label = strip_tags(strtr($task->label, array("\n" => ' ', "\r" => '')));
        $task->title = $task->label;
        $task->ref = $task->ref;

        if(empty($task->planned_workload)) $task->planned_workload = 1;

        if(!empty($conf->global->GANTT_HIDE_TASK_REF)) {
            $task->text = $task->label;
        }
        else {
            $task->text = $task->ref . ' ' . $task->label;
            if($task->planned_workload > 0) {
                $task->text .= ' ' . round($task->planned_workload / 3600, 1) . 'h';
            }
        }

        if(!empty($conf->of->enabled) && !empty($conf->global->ASSET_CUMULATE_PROJECT_TASK)) {
            if(!isset($conf->tassetof)) $conf->tassetof = new \stdClass(); // for warning
            $conf->tassetof->enabled = 1; // pour fetchobjectlinked
            $task->fetchObjectLinked(0, 'tassetof', $task->id, $task->element, 'OR', 1, 'sourcetype', 0);
        }
        $TTaskOfs = array();
        if(((!empty($conf->global->ASSET_CUMULATE_PROJECT_TASK) && !empty($task->linkedObjectsIds['tassetof'])) || $task->array_options['options_fk_of'] > 0)
            && !empty($conf->of->enabled)) {

            if(!empty($conf->global->ASSET_CUMULATE_PROJECT_TASK)) {
                if(!empty($task->linkedObjectsIds['tassetof'])) {
                    foreach($task->linkedObjectsIds['tassetof'] as $fk_of) $TTaskOfs[] = _loadOF($TCacheOF, $fk_of);
                }
            }
            else $TTaskOfs[] = _loadOF($TCacheOF, $task->array_options['options_fk_of']);
        }
        else {

            $of = new stdClass;
            $of->id = 0;
            $of->numero = 'None';
            $of->fk_commande = 0;
            $of->element = 'of';
            $TTaskOfs[] = $of;
        }

        foreach($TTaskOfs as &$of) {

            if($of->id > 0) {
                $of->ganttid = 'M' . (int)$of->id;
            }
            else {
                $of->ganttid = 'MNA' . $idNoAffectation;
                $idNoAffectation++;
            }

            if($of->fk_commande > 0) {

                if(!empty($TCacheOrder[$of->fk_commande])) {

                    $order = $TCacheOrder[$of->fk_commande];
                }
                else {
                    $order = new Commande($db);
                    $order->fetch($of->fk_commande);
                    $order->fetch_thirdparty();

                    if($order->id > 0) {
                        $order->title = $order->ref . ' ' . $order->thirdparty->name;
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
            if($order->id > 0) {
                $order->ganttid = 'O' . $order->id;
            }
            else {
                $order->ganttid = 'ONA' . $idNoAffectation;
                $idNoAffectation++;
            }

            if(!empty($TCacheProject[$task->fk_project])) {

                $project = $TCacheProject[$task->fk_project];
            }
            else {
                $project = new Project($db);
                $project->fetch($task->fk_project);

                if($project->id > 0) {
                    $project->title = (empty($conf->global->GANTT_HIDE_TASK_REF) ? $project->ref . ' ' : '') . $project->title;

                    if($project->socid > 0) {
                        $project->fetch_thirdparty();
                        $project->title .= ' - ' . $project->thirdparty->name;
                    }
                }
                else {
                    $project->title = $langs->trans('UndefinedProject');
                }

                $TCacheProject[$project->id] = $project;
            }

            if($project->id > 0) {
                $project->ganttid = 'P' . $project->id;
            }
            else {
                $project->ganttid = 'PNA' . $idNoAffectation;
                $idNoAffectation++;
            }

            if(!empty($conf->workstation->enabled)) {
                if(!empty($TCacheWS[$task->array_options['options_fk_workstation']])) {

                    $ws = $TCacheWS[$task->array_options['options_fk_workstation']];
                }
                else {
                    $ws = new TWorkstation();
                    $ws->load($PDOdb, $task->array_options['options_fk_workstation']);
                    $ws->text = $ws->title = $ws->name;
                    $TCacheWS[$ws->id] = $ws;
                }
            }
            else {
                $ws = new StdClass;
                $ws->element = 'workstation';
                $ws->id = 0;
            }

            if($ws->id > 0) {
                $ws->ganttid = 'W' . (int)$ws->id;
            }
            else {
                $ws->ganttid = 'WNA' . $idNoAffectation;
                $idNoAffectation++;
                $ws->title = $langs->trans('UndefinedWorkstation');
            }

            if(empty($TTask[$project->id])) {

                $TTask[$project->id] = array(
                    'childs' => array()
                    , 'object' => $project
                );
                _adding_task_project_end($project, $TTask[$project->id]['childs']);
                _load_child_tasks($TTask[$project->id]['childs'], $project);
            }

            $order->id = (int)$order->id;

            if(empty($TTask[$project->id]['childs'][$order->id])) {

                $TTask[$project->id]['childs'][$order->id] = array(
                    'childs' => array()
                    , 'object' => $order
                );

                _load_child_tasks($TTask[$project->id]['childs'][$order->id]['childs'], $order);
            }

            _adding_task_order($order, $TTask[$project->id]['childs'][$order->id]['childs']);

            if(empty($TTask[$project->id]['childs'][$order->id]['childs'][$of->id])) {

                $TTask[$project->id]['childs'][$order->id]['childs'][$of->id] = array(
                    'childs' => array()
                    , 'object' => $of
                );
            }
            _adding_task_supplier_order($PDOdb, $of, $TTask[$project->id]['childs'][$order->id]['childs'][$of->id]['childs']);

            if(empty($TTask[$project->id]['childs'][$order->id]['childs'][$of->id]['childs'][$ws->id])) {

                $TTask[$project->id]['childs'][$order->id]['childs'][$of->id]['childs'][$ws->id] = array(
                    'childs' => array()
                    , 'object' => $ws
                );
            }

            if($obj->nb_days_before_beginning > 0) {
                _add_delay_included_into_of_ws($obj->nb_days_before_beginning, $task, $of, $TTask[$project->id]['childs'][$order->id]['childs'][$of->id]['childs'][$ws->id]['childs']);
            }

            $TTask[$project->id]['childs'][$order->id]['childs'][$of->id]['childs'][$ws->id]['childs'][$task->id] = $task;

            _load_child_tasks($TTask[$project->id]['childs'][$order->id]['childs'][$of->id]['childs'][$ws->id]['childs'], $task);
        }
    }

	_load_child_tasks( $TTask );

	return $TTask;

}

function _add_delay_included_into_of_ws($nb_days_before_beginning, &$task,&$assetOf, &$TData) {

	global $db, $langs, $conf, $TLink;

	if($nb_days_before_beginning>0 && empty($assetOf->cancel_adding_delay) && empty($conf->global->GANTT_DISABLE_DELAY_PARENT)) {
		$object=new stdClass();
		$object->element = 'project_task_delay';
		$object->title = $object->text = $langs->trans('DelayForTask', $nb_days_before_beginning);
		$object->date= strtotime('+1 day midnight');
		$object->duration = $nb_days_before_beginning;

		$object->ganttid = 'JD'.$task->id;
		$object->bound='after';
		$object->visible = 1;

		$TData[$object->ganttid] = $object;

		$linkId = count($TLink)+1;
		$TLink[$linkId] = array('id'=>$linkId, 'source'=>$object->ganttid, 'target'=>$task->ganttid, 'type'=>'0');
	}

	return false;

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
		$object->date= $order->date_livraison+ 86399; //23:59:59
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
		$object->date= $project->date_end + 86399; //23:59:59
		$object->ganttid = 'JPE'.$project->id;
		$object->bound='after';

		$TData[$object->ganttid]['object'] = $object;

	}

}

/*
 * _adding_task_supplier_order sous fonction de recherche de lien produit ligne commandé et of
 */
function _atso_find_task_for_line(&$TData, &$cmd,&$assetOf) {

	global $TLink, $langs,$workstationList;

	$find = false;
	foreach($cmd->lines as &$line) {

		foreach($assetOf->TAssetOFLine as &$lineOf) {

			if($lineOf->type == 'NEEDED' && $lineOf->fk_product>0 && $lineOf->fk_product == $line->fk_product && !empty($lineOf->TWorkstation)) {

				foreach($lineOf->TWorkstation as &$ws) {
					$gantt_id = 'JSO'.$cmd->id.'-'.$lineOf->id;
					if(isset($TData[$gantt_id]))continue;

					foreach($assetOf->TAssetWorkstationOF as &$wsof) {

						if($ws->id == $wsof->fk_asset_workstation && $wsof->fk_project_task>0) {

							$object=new stdClass();
							$object->element = 'project_task_delay';
							$object->subelement = 'supplier_order_delivery';
							$object->objId = $cmd->id;
							$object->title = $object->text = $langs->trans('AwaitingDelivery', $cmd->ref, dol_print_date($cmd->date_livraison));
							$object->date= strtotime('midnight',$cmd->date_livraison);
							$object->duration = 1;

							$object->ganttid = $gantt_id;
							$object->bound='after';
							$object->visible = 1;

							$object->workstation_type = 'STT';

							$TData[$object->ganttid]['object']= $object;

							$linkId = count($TLink)+1;
							$TLink[$linkId] = array('id'=>$linkId, 'source'=>$object->ganttid, 'target'=>'T'.$wsof->fk_project_task, 'type'=>'0');

							$find = true;

							break;
						}

					}

				}

			}

		}

	}

	return $find;
}

function _adding_task_supplier_order(&$PDOdb, &$assetOf,&$TData) {
	global $db, $langs, $conf, $TAlreadyDoneOfATSO;

	if(!empty($conf->global->GANTT_DISABLE_SUPPLIER_ORDER_MILESTONE) || $assetOf->id<=0) {
		return false;
	}

	if(empty($TAlreadyDoneOfATSO))$TAlreadyDoneOfATSO=array();
	if(!empty($TAlreadyDoneOfATSO[$assetOf->id])) return;
	else $TAlreadyDoneOfATSO[$assetOf->id] = true;

	dol_include_once('/fourn/class/fournisseur.commande.class.php');
	$TIdCommandeFourn = $assetOf->getElementElement($PDOdb);

	$today=strtotime('midnight');

	if(count($TIdCommandeFourn)){
		foreach($TIdCommandeFourn as $idcommandeFourn){
			$cmd = new CommandeFournisseur($db);
			$cmd->fetch($idcommandeFourn);

			if($cmd->statut>0 && $cmd->statut<5 && $cmd->date_livraison>$today) {

				$find_detail = false;
				if(!empty($conf->global->ASSET_DEFINED_WORKSTATION_BY_NEEDED)) {

					$find_detail = _atso_find_task_for_line($TData, $cmd,$assetOf);
				}


				if(!$find_detail){

					$object=new stdClass();
					$object->element = 'milestone';
					$object->title = $object->text = $langs->trans('AwaitingDelivery', $cmd->ref, dol_print_date($cmd->date_livraison));
					$object->date= $cmd->date_livraison;
					$object->ganttid = 'JSO'.$cmd->id;
					$object->bound='before';
					$object->visible = 1;

					$TData[$object->ganttid]['object'] = $object;


				}
				else {
					$assetOf->cancel_adding_delay = true;

				}

			}

		}
	}

}

/*
 * Complete avec les tâches previ du parent
 *
 */
function _load_child_tasks(&$TData, $gantt_parent_objet = false, $level = 0, $maxDeep = 3) {
	global $db,$range, $conf, $user;

	if($level>$maxDeep || empty($conf->global->GANTT_ALLOW_PREVI_TASK)) return;

	$projet_previ=new Project($db);
	$projet_previ->fetch(0,'PREVI');
	$fk_projet_previ = (int)$projet_previ->id;

	if($projet_previ->statut!=1) {
		$projet_previ->setValid($user);
	}

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

	if(GETPOST('restrictWS')>0 && !empty($conf->workstation->enabled)) {
		$sql.=" AND tex.fk_workstation=".(int)GETPOST('restrictWS');
	}
	else if(GETPOST('restrictWS','int') == 0 && !empty($conf->workstation->enabled)) {
		$sql.=" AND (tex.fk_workstation IS NULL) ";
	}

	$sql.=" AND t.dateo BETWEEN '".$range->sql_date_start."' AND '".$range->sql_date_end."'";

	$sql.=" AND p.entity IN (".getEntity('project',1).")";

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

function _get_json_data(&$object, $close_init_status, $fk_parent_object=null, $time_task_limit_no_before=0,$time_task_limit_no_after=0, $taskColor = '',$move_projects_mode = false) {
        global $conf;

	if($object->element == 'commande') {
	    $date_max = $object->date_livraison ? strtotime('+1day midnight',$object->date_livraison) : 0;

		$r = '{"id":"'.$object->ganttid.'",ref:"'.$object->ref.'"';
		if($date_max>0) $r.=', date_max:'.(int)$date_max;
		$r.= ',objElement:"'.$object->element.'", "text":"'.$object->title.'", "type":gantt.config.types.order'.(!is_null($fk_parent_object) ? ' ,parent:"'.$fk_parent_object.'" ' : '' ).', open: '.$close_init_status.'}';

		return $r;

	}
	else if($object->element == 'workstation') {

		$taskColorCode='';
		// Check if a color is define for this task
		if(!empty($object->background) && ColorTools::validate_color($object->background))
		{
			$taskColor = $object->background;
			$taskColorCode= ',color:"'.$taskColor.'"';
		}


		return '{"id":"'.$object->ganttid.'"'.$taskColorCode.',objElement:"'.$object->element.'", "text":"'.$object->title.'", "type":gantt.config.types.project'.(!is_null($fk_parent_object) ? ' ,parent:"'.$fk_parent_object.'" ' : '' ).', open: '.((!$conf->global->GANTT_DEFAULT_OPENTAB_STATUS) ? 'true' : 'false').'}';
	}
	else if($object->element == 'project') {

		$taskColor='';
		$projectColor='';
		if(ColorTools::validate_color($object->array_options['options_color']))
		{
			$taskColor= ColorTools::adjustBrightness($object->array_options['options_color'], -50); //TODO récupérer la taskColor du projet...
			$projectColor= ',color:"'.$object->array_options['options_color'].'"';
		}

		if(empty($object->date_start)) $object->date_start = time();
		if(empty($object->date_end)) {
		    $object->date_end =strtotime('+1week', $object->date_start);
		    $object->date_max = strtotime('+1year', $object->date_start);
		}
		else {
		    $object->date_max = $object->date_end;
		}

		$res = '{"id":"'.$object->ganttid.'",ref:"'.$object->ref.'"
                , objElement:"'.$object->element.'","objId":"'.$object->id.'"
                , "text":"'.$object->title.'"';


		if($move_projects_mode) {

            $duration = ceil( ($object->date_end - $object->date_start) / 86400 );
            if($duration<1)$duration = 1;

            $res.=', "start_date":"'.date('d-m-Y',$object->date_start).'","duration":"'.$duration.'"';
            $res.= ', "type":gantt.config.types.task';
		}
		else {

		    $res.= ', "type":gantt.config.types.project, date_max:'.(int)strtotime('+1day midnight',$object->date_max);
            $res.=', open: '.$close_init_status.$projectColor;
		}

        $res.='}';
/*
        if($move_projects_mode) {
            global $langs;

            $obj=new stdClass();
            $obj->element = 'milestone';
            $obj->title = $obj->text = $langs->trans('StartOfProject', $object->ref, dol_print_date($object->date_start));
            $obj->date=$object->date_start;
            $obj->ganttid = 'JPS'.$object->id;
            $obj->bound='before';
            $obj->visible = 1;
            $res.="\n,"._get_json_data($obj, $close_init_status,$object->ganttid);

            $obj=new stdClass();
            $obj->element = 'milestone';
            $obj->title = $obj->text = $langs->trans('EndOfProject', $object->ref, dol_print_date($object->date_end));
            $obj->date=$object->date_end + 84399;
            $obj->ganttid = 'JPE'.$object->id;
            $obj->bound='after';
            $obj->visible = 1;

            $res.="\n,"._get_json_data($obj, $close_init_status,$object->ganttid);

        }
*/
        return $res;

	}
	else if($object->element == 'of') {
		return '{"id":"'.$object->ganttid.'",ref:"'.$object->ref.'", date_max:'.(int)strtotime('+1day midnight',$object->date_besoin).',objElement:"'.$object->element.'", "text":"'.$object->title.'", "type":gantt.config.types.of'.(!is_null($fk_parent_object) ? ' ,parent:"'.$fk_parent_object.'" ' : '' ).', open: '.$close_init_status.'}';
	}
	elseif($object->element == 'container') {

	    $taskColorCode='';
	    if(!empty($object->array_options['options_color']) && ColorTools::validate_color($object->array_options['options_color']))
	    {
	        $taskColor = $object->array_options['options_color'];
	    }
	    if(!empty($taskColor))$taskColorCode= ',"color":"'.$taskColor.'"';

	    return '{"id":"'.$object->ganttid.'"'.$taskColorCode.' ,"objElement":"'.$object->element.'"'
	           .',"text":"'.strtr($object->text,array('"'=>'\"')).'"'
	           .',"parent":"'.$fk_parent_object.'"'
	           .',"type":gantt.config.types.task , "open": '.$close_init_status.'}';

	}
	elseif($object->element == 'project_task') {
		global $range,$TWS,$workstationList;

		_check_task_wihout_workstation($object);

		if($range->autotime) {
			if(empty($range->date_start) || $object->date_start<$range->date_start)$range->date_start=$object->date_start;
			if(empty($range->date_end) || $range->date_end<$object->date_end)$range->date_end=$object->date_end;
		}

		if($object->date_start>$object->date_end && !empty($object->date_end))$object->date_start=$object->date_end; //TODO or the contrary ?

		$duration = $object->date_end>0 ? ceil( ($object->date_end - $object->date_start) / 86400 ) : ceil($object->planned_workload / (3600 * 7));
		if($duration<1)$duration = 1;

		$fk_workstation = (int) $object->array_options['options_fk_workstation'];
		if($fk_workstation>0) $TWS[$fk_workstation] = $workstationList[$fk_workstation]; //TODO ouh que c'est moche !

		$needed_ressource= $object->array_options['options_needed_ressource']>0 ? $object->array_options['options_needed_ressource'] : 1;

		$taskColorCode='';
		// Check if a color is define for this task
		if(!empty($object->array_options['options_color']) && ColorTools::validate_color($object->array_options['options_color']))
		{
			$taskColor = $object->array_options['options_color'];
		}

		if(!empty($taskColor))$taskColorCode= ',"color":"'.$taskColor.'"';

		$ws_type = (empty($workstationList[$fk_workstation])?'':$workstationList[$fk_workstation]->type);
		$visible = isset($object->visible) && $object->visible == 0 ? 0 : 1;

		$contacts = $object->getListContactId();
		$fk_user = empty($contacts[0]) ? 0 : $contacts[0];

		return '{"id":"'.$object->ganttid.'"'.$taskColorCode.',"fk_user":'.$fk_user.',"ref":"'.$object->ref.'","needed_ressource":'.(int)$needed_ressource
				.',"time_task_limit_no_before":'.(int)$time_task_limit_no_before.',"time_task_limit_no_after":'.(int)$time_task_limit_no_after
				.',"planned_workload":'.(int)$object->planned_workload.' ,"objElement":"'.$object->element.'","objId":"'.$object->id.'"'
				.',"workstation_type":"'.$ws_type.'"'
				.',"workstation":'.$fk_workstation.' , "text":"'.strtr($object->text,array('"'=>'\"')).'" , "title":"'.strtr($object->title,array('"'=>'\"')).'", "start_date":"'.date('d-m-Y',$object->date_start).'"'
				.',"duration":"'.$duration.'"'.(!is_null($fk_parent_object) ? ' ,"parent":"'.$fk_parent_object.'" ' : '' ).', "progress": '.($object->progress / 100)
				.',"owner":"'.$fk_workstation.'", "type":gantt.config.types.task , "open": '.$close_init_status.', "visible":'.$visible.'}';

	}
	else if($object->element== 'milestone' || $object->element == 'release') {
		/*
		 global $range;
		 if($range->autotime) {
		 if(empty($range->date_start) || $object->date<$range->date_start)$range->date_start=$object->date;
		 if(empty($range->date_end) || $range->date_end<$object->date)$range->date_end=$object->date;
		 }*/

		$date = date('d-m-Y',$object->date);
		return '{"id":"'.$object->ganttid.'","objElement":"'.$object->element.'", "text":"'.$object->text.'", "start_date":"'.$date.'", "duration":1 '.(!is_null($fk_parent_object) ? ' ,parent:"'.$fk_parent_object.'" ' : '' ).', "type":gantt.config.types.release, "visible":'.( empty($object->visible) ? 0 : 1 ).'}';

	}
	else if($object->element == 'project_task_delay') {

		$subElement = empty($object->subelement) ? '' :$object->subelement;
		$date = date('d-m-Y',$object->date);
		return '{"id":"'.$object->ganttid.'","subElement":"'.$subElement.'","objElement":"'.$object->element.'", "text":"'.$object->text.'", "start_date":"'.$date.'", "duration":'.$object->duration.' '.(!is_null($fk_parent_object) ? ' ,"parent":"'.$fk_parent_object.'" ' : '' ).', "type":gantt.config.types.delay, "visible":'.( empty($object->visible) ? 0 : 1 ).'}';

	}

	var_dump('nonObjectManaged', $object);exit;

	return '{ nonObjectManaged:"'.$object->element.'" }';
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

	$sql = "SELECT w.rowid as id , w.name, w.nb_hour_capacity, w.nb_hour_capacity, w.nb_ressource,w.background,w.type,w.fk_usergroup FROM ".MAIN_DB_PREFIX."workstation w  ";

	//echo $sql.$sqlWhere;
	$res = $db->query($sql);
	if($res===false) {
		var_dump($db);exit;
	}

	dol_include_once('/user/class/usergroup.class.php');

	$workstationList = array();

	while($obj = $db->fetch_object($res)) {
		$workstationList[$obj->id] = $obj;

		$usergroup=new UserGroup($db);
		$usergroup->fetch($obj->fk_usergroup);
		$users = $usergroup->listUsersForGroup('',0);
		foreach($users as &$u) {
		    $u = $u->getFullName($langs);
		}

		$workstationList[$obj->id]->users = $users;
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

	return false;// TODO rewrite this function

	$day_range = empty($conf->global->GANTT_DAY_RANGE_FROM_NOW) ? 90 : $conf->global->GANTT_DAY_RANGE_FROM_NOW;

	$sql = "SELECT a.id
		FROM ".MAIN_DB_PREFIX."actioncomm a
			LEFT JOIN ".MAIN_DB_PREFIX."actioncomm_extrafields aex ON (aex.fk_object=a.id)
		WHERE ";

	$sql.=" ( a.datep BETWEEN '".$range->sql_date_start."' AND '".$range->sql_date_end."' ";
	$sql.=" OR a.datep2 BETWEEN '".$range->sql_date_start."' AND '".$range->sql_date_end."' )";
	if (!empty($conf->workstation->enabled)) {
		$sql.=" AND aex.fk_workstation > 0 ";
	}

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


		$TData[] = '{"id":"'.$event->ganttid.'"'.$needed_ressource.',objId:"'.$event->id.'",objElement:"'.$event->element.'", "text":"'.$event->title.'", "start_date":"'.date('d-m-Y',$event->datep).'", "duration":"'.$duration.'" , progress:'.$event->percentage.' '.$type.' '.$taskColorCode.$workstation.$parent.$source.'}';


	}
}

function _check_task_wihout_workstation(&$task) {
	global $flag_task_not_ordonnanced;

	if(!isset($flag_task_not_ordonnanced)) $flag_task_not_ordonnanced= false;

	if(empty($task->array_options['options_fk_workstation'])) {
		$flag_task_not_ordonnanced= true;
	}

}
function checkDataGantt(&$TData, &$TLink ) {

	foreach($TLink as $k=>&$link) {

		if(!isset($TData[$link['source']]) || !isset($TData[$link['target']])) {
			unset($TLink[$k]);
			continue;
		}

		$json = strtr($TData[$link['source']],array('gantt.config.types.delay'=>1,'gantt.config.types.task'=>2));

		$source =(Object)json_decode($json, true);
		if(is_null($source)) {
			var_dump($json);
			exit('Error bad json');
		}

		if(!empty($source->workstation_type) && $source->workstation_type == 'STT') {

			$find = false;
			foreach($TLink as $k2=>&$link2) {

				if($link['target'] == $link2['target'] && $link['source']!=$link2['source']) {

					$json2 = strtr($TData[$link2['source']],array('gantt.config.types.delay'=>1,'gantt.config.types.task'=>2));
					$source2 =(Object)json_decode($json2, true);

					if(is_null($source2)) {
						var_dump($json2);
						exit('Error bad json');
					}

					if($source2->subElement == 'supplier_order_delivery') {
						$find = true;
						break;
					}

				}

			}

			if($find) {
				// la tâche est déjà liée à une réception commande fournisseur la tâche de sous-traitance n'a donc aucun intérêt
				unset($TLink[$k], $TData[$link['source']]);

			}

		}

	}
}

function _loadOF(&$TCacheOF, $fk_of){
    global $db, $conf;
    if(!empty($TCacheOF[$fk_of])) {
        $of = $TCacheOF[$fk_of];
    }
    else{



        $of=new TAssetOF();

        // object OF too heavy for that
        $resof = $db->query("SELECT aof.numero,p.label,l.qty_needed,aof.status,aof.fk_commande FROM ".MAIN_DB_PREFIX."assetOf aof
                            LEFT JOIN ".MAIN_DB_PREFIX."assetOf_line l ON (l.fk_assetOf=aof.rowid)
                                LEFT JOIN ".MAIN_DB_PREFIX."product p ON (l.fk_product=p.rowid)
                        WHERE aof.rowid=".(int)$fk_of." AND l.type='TO_MAKE'

                ");
        if($resof===false) {
            var_dump($db);exit;
        }
        $oobjOf=$db->fetch_object($resof);


        $of->id = (int)$fk_of;
        $of->numero = $oobjOf->numero;
        $of->fk_commande = (int)$oobjOf->fk_commande;
        $of->qty_needed = $oobjOf->qty_needed;
        $of->ref = $of->numero;

        $of->product_to_make_name = $oobjOf->label;

        if(!empty($conf->global->GANTT_HIDE_TASK_REF)) {
            $of->title = $of->numero.' '.$of->product_to_make_name.' x '.$of->qty_needed;
        }
        else {
            $of->title = $of->numero.' '.$of->getLibStatus(true).' '.$of->product_to_make_name .' x '.$of->qty_needed;
        }

        $TCacheOF[$fk_of] = $of;
    }

    return $of;
}
