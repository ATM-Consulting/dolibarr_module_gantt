<?php

if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', 1); // Disables token renewal

	require '../config.php';
	dol_include_once('/projet/class/project.class.php');
	dol_include_once('/projet/class/task.class.php');
	dol_include_once('/comm/action/class/actioncomm.class.php');
	dol_include_once('/gantt/class/gantt.class.php');

	$langs->load("gantt@gantt");

	$get=GETPOST('get');
	$put = GETPOST('put');
	switch ($put) {

		case 'gantt':

			echo _put_gantt($_REQUEST);

			break;

		case 'task':

			echo _create_task($_REQUEST);

			break;

		case 'projects':

			_put_projects($_REQUEST['TProject']);

			echo 1;

			break;

		case 'delete_task':

			_delete_task();

			break;
		case 'split':
			__out(_split_task(GETPOST('taskid'), GETPOST('tache1'), GETPOST('tache2')),'json');
			break;

		case 'ws-time':
			__out(_set_ws_time(GETPOST('wsid'), GETPOST('date'), GETPOST('nb_hour_capacity'), GETPOST('nb_ressource')),'json');
			break;

		case 'ws-remove-time':
			__out(_remove_ws_time(GETPOST('wsid'), GETPOST('date')),'json');
			break;

	}

	switch ($get) {

		case 'workstation-capacity':
		    header('Content-Type: application/json');
			__out(_get_ws_capactiy(  GETPOST('wsid'),GETPOST('t_start'),GETPOST('t_end'),GETPOST('scale_unit') ),'json' );

			break;

		case 'better-pattern':
			// déprécié
			__out(_get_better_pattern(  GETPOST('tasksid'),GETPOST('t_start'),GETPOST('t_end') ),'json' );
			break;

		case 'set-better-pattern':
			$tasksid = explode(',', GETPOST('tasksid', 'none'));
			$tab = GanttPatern::set_optimal_task_order($tasksid, GETPOST('t_start'),GETPOST('t_end'),true );
			__out($tab,'json' );
			break;

		case 'keep-alive':
			echo 1;
			break;
	}

	function _get_better_pattern($tasksid, $t_start, $t_end) {

		return GanttPatern::get_better(explode(',', $tasksid), $t_start, $t_end);
	}
	function _remove_ws_time($wsid, $date) {
		global $conf;

		if(empty($conf->workstation->enabled)) return 0;

		dol_include_once('/workstation/class/workstation.class.php');

		$PDOdb=new TPDOdb;

		$wssc = new TWorkstationSchedule();
		$wssc->loadByWSDate($PDOdb, $wsid, $date);
		$wssc->delete($PDOdb);

		return 1;

	}

	function _set_ws_time($wsid, $date, $nb_hour_capacity, $nb_ressource) {
		global $conf;

		if(empty($conf->workstation->enabled)) return 0;

		dol_include_once('/workstation/class/workstation.class.php');

		$PDOdb=new TPDOdb;

		$ws=new TWorkstation;
		$ws->load($PDOdb, $wsid);

		$wssc = new TWorkstationSchedule();
		$wssc->loadByWSDate($PDOdb, $wsid, $date);

		$wssc->fk_workstation = $wsid;
		$wssc->date_off = strtotime($date);

		$wssc->nb_hour_capacity = $nb_hour_capacity;
		$wssc->nb_ressource=$ws->nb_ressource - $nb_ressource;

		$wssc->save($PDOdb);

		if($wssc->nb_ressource == 0 && $wssc->nb_hour_capacity == $ws->nb_hour_capacity)$wssc->delete($PDOdb);

		return $wssc->getId();
	}

	function _split_task($taskid, $task1time, $task2time) {
		global $db, $user, $conf;

		$task =new Task($db);
		$task->fetch($taskid);
		$task->fetch_optionals($task->id);

		$codeSplit = strpos($task->array_options['options_fk_gantt_parent_task'],'SPLITCN')===false ? 'SPLITCN'.$task->id : $task->array_options['options_fk_gantt_parent_task'];

		$task->array_options['options_fk_gantt_parent_task'] = $codeSplit;
		$task->planned_workload = $task1time * 3600;


		$task2 = new Task($db);
		foreach($task as $k=>$v) {

			if($k!='id' && $k!='progress' &$k!='duration_effective' && $k!='ref' ) {
				$task2->{$k} = $v;
			}

		}

		$task2->planned_workload = $task2time * 3600;

		$defaultref='';
		$obj = empty($conf->global->PROJECT_TASK_ADDON)?'mod_task_simple':$conf->global->PROJECT_TASK_ADDON;
		if (! empty($conf->global->PROJECT_TASK_ADDON) && is_readable(DOL_DOCUMENT_ROOT ."/core/modules/project/task/".$conf->global->PROJECT_TASK_ADDON.".php"))
		{
			require_once DOL_DOCUMENT_ROOT ."/core/modules/project/task/".$conf->global->PROJECT_TASK_ADDON.'.php';
			$modTask = new $obj;
			$defaultref = $modTask->getNextValue(0,$task2);
		}

		if (is_numeric($defaultref) && $defaultref <= 0) $defaultref='';

		$task2->ref = $defaultref;

		$task2->fk_task_parent = $task->id;
        $task2->date_c=time();

		if($task2->create($user)>0) {
		    $task->update($user);
		}
		else {
		    var_dump($task2);
		}

		return $task2->id;
	}

	function _create_task($data) {
		global $db, $user, $langs,$conf;

		$p=new Project($db);
		if($p->fetch(0,'PREVI')<=0) {

			$p->ref='PREVI';
			$p->title = $langs->trans('Provisionnal');

			$p->create($user);
		}


		$o=new Task($db);

		$defaultref='';
		$obj = empty($conf->global->PROJECT_TASK_ADDON)?'mod_task_simple':$conf->global->PROJECT_TASK_ADDON;
		if (! empty($conf->global->PROJECT_TASK_ADDON) && is_readable(DOL_DOCUMENT_ROOT ."/core/modules/project/task/".$conf->global->PROJECT_TASK_ADDON.".php"))
		{
			require_once DOL_DOCUMENT_ROOT ."/core/modules/project/task/".$conf->global->PROJECT_TASK_ADDON.'.php';
			$modTask = new $obj;
			$defaultref = $modTask->getNextValue($p->thirdparty,null);
		}

		if (is_numeric($defaultref) && $defaultref <= 0) $defaultref='';

		$o->ref = $defaultref;

		$o->date_c = time();

		$o->fk_project = $p->id;

		$o->label = $data['label'];

		$o->date_start = $data['start'] / 1000;
		$o->date_end = ($data['end'] / 1000) - 1; //Pour que cela soit à 23:59:59 de la vieille
		$o->progress = $data['progress'] * 100;

		$o->planned_workload = $data['duration'] * 3600 * 7; //7h par jour, à revoir

		//check parent projet to set correct task parent. 0 if parent comme from an other project and task->id if parent comme from PREVI Project task
		$parenttaskId = _get_task_id_from_task_gantt_id($data['parent']);
		$parenttask = new Task($db);
		$parenttask->fetch($parenttaskId);

		if($parenttask->fk_project === $p->id)
		{
			$o->fk_task_parent = $parenttaskId;
		}
		else
		{
			$o->fk_task_parent = 0;
		}

		$o->array_options['options_fk_gantt_parent_task'] = $data['parent'];


		$r = $o->create($user);

		if($r>0) return 'T'.$r;
		else {

			var_dump($r, $o);
		}

	}

	function _put_gantt($data) {
		global $db, $user;

		switch($data['ganttid'][0]) {
			case 'T':
				$description = preg_replace("/^(PREVI )/","",$data['description']);
				$o=new Task($db);
				$o->fetch((int)$data['id']);
				if(empty($o->array_options))$o->fetch_optionals($o->id);

				$o->label = $description;
				$o->date_start = $data['start'] / 1000;
				$o->date_end = ($data['end'] / 1000) - 1;//Pour que cela soit à 23:59:59 de la vieille
				$o->planned_workload = (int)$data['planned_workload'];
				$o->progress = $data['progress'] * 100;
				$o->array_options['options_fk_workstation'] = (int)$data['workstation'];
				$o->array_options['options_needed_ressource'] = (int)$data['needed_ressource'];
				$res = $o->update($user);

			if($res<0 ) {
				var_dump($res);
			}

			if($data['fk_user']>0) {
			    $o->delete_linked_contact('internal');
			    $o->add_contact($data['fk_user'], 181,'internal');//todo code !
			}
				return $res;
				break;

			case 'A':
				$description = preg_replace("/^(AGENDA )/","",$data['description']);
				$o=new ActionComm($db);
				$o->fetch((int)$data['id']);
				$o->fetch_optionals($o->id);
				$o->label = $description;
				$o->datep = $data['start'] / 1000;
				$o->datef = ($data['end'] / 1000) - 1; //Pour que cela soit à 23:59:59 de la vieille
				$o->array_options['options_fk_workstation'] = (int)$data['workstation'];
				$o->array_options['options_needed_ressource'] = (int)$data['needed_ressource'];

				//var_dump($data);
				return $o->update($user);

				break;

			case 'P':

                $o=new Project($db);
                $o->fetch((int)$data['id']);

                if($o->date_start<=0) {
                // date de début du projet non défini, on prends donc la date de début de la 1ère tâche

                    $res = $db->query("SELECT MIN(dateo) as date_start
                            FROM ".MAIN_DB_PREFIX."projet_task as t
                            WHERE fk_projet=".$o->id." AND (progress IS NULL OR progress<100) AND dateo IS NOT NULL ");

                    if($res!==false) {
                        $obj = $db->fetch_object($res);
                        if($obj && !empty($obj->date_start)) {

                            $old_start_date = strtotime($obj->date_start);

                        }


                    }

                }
                else {
                    $old_start_date = $o->date_start;
                }


                $o->date_start = $data['start'] / 1000;
                $o->date_end = ($data['end'] / 1000) - 1;
                $o->update($user);

                if(empty($old_start_date)) return -1;
                else return $o->shiftTaskDate($old_start_date);

			    break;

		}

	}

	function _get_ws_capactiy($wsid, $t_start, $t_end,$scale_unit) {

	    $wsids=explode(',',$wsid);

	    $Tab=array();
	    foreach($wsids as $wsid) {
	        $Tab[$wsid] =  GanttPatern::get_ws_capacity($wsid, $t_start, $t_end,0,$scale_unit);
	    }
	    return $Tab;
	}

	function _put_projects(&$TProject) {
		global $db,$langs,$conf,$user;

		foreach($TProject['tasks'] as &$data ) {

			$type = $data['id'][0];
			$id = substr($data['id'],1);


			if($type=='P') {

				$project = new Project($db);
				$project->fetch($id);

				$project->date_start = $data['start'] / 1000;
				$project->date_end = $data['end'] / 1000;

				$project->update($user);
			}
			else{

				$task=new Task($db);
				$task->fetch($id);

				$task->date_start = $data['start'] / 1000;
				$task->date_end = $data['end'] / 1000;
					//var_dump($data['depends']);
				if(!empty($data['depends'])) {

					list($d1) = explode(',', $data['depends']);

					$task->fk_task_parent = (int)substr($TProject['tasks'][$d1-1]['id'],1);
				//	var_dump($d1,$TProject['tasks'][$d1]['id'],$task->fk_task_parent );
				}

				$task->update($user);

			}



		}

	}


	function _delete_task ()
	{
		global $user,$db,$langs;

		$prevent_child_deletion = (bool)GETPOST('prevent_child_deletion');
		$task_id = _get_task_id_from_task_gantt_id(GETPOST('task_id'));

		$retDatas= array('result' => false, 'msg' => '');

		$task=new Task($db);
		if($task->fetch($task_id)>0)
		{
			//TODO check user rights

			$deleteError = false;

			if ($prevent_child_deletion &&  ( $task->hasChildren() || $task->hasTimeSpent()) )
			{
				$deleteError= true;
				$retDatas['msg'] = $langs->trans('TaskHasChildrenOrHasTimeSpent');
			}

			if(!$deleteError)
			{
				$retDatas['result'] = $task->delete($user);
			}

		}

		echo json_encode($retDatas);
		exit;
	}

	function _get_task_id_from_task_gantt_id($id)
	{
		if(substr ( $id, 0,1) === 'T')
		{
			return  (int) substr ( $id, 1);
		}
		else
		{
			return  0;
		}

	}

	function _getCapacityLeftRangeAgenda(&$PDOdb,&$ws,&$TDate,$t_start, $t_end){

		return GanttPatern::getCapacityLeftRangeAgenda($PDOdb,$ws,$TDate,$t_start, $t_end);

	}
