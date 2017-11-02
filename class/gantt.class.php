<?php

class GanttPatern {

	static function get_ws_capacity($wsid, $t_start, $t_end, $fk_task = 0) {

		global $conf;

		if(empty($conf->workstation->enabled)) return array();

		dol_include_once('/workstation/class/workstation.class.php');

		$PDOdb=new TPDOdb;

		$ws=new TWorkstation;
		$ws->load($PDOdb, $wsid);

		$Tab = $ws->getCapacityLeftRange($PDOdb, $t_start, $t_end, true, $fk_task);

		// TODO Faire une PR sur le module workstation pour inclure cette fonction
		self::getCapacityLeftRangeAgenda($PDOdb,$ws,$Tab,$t_start, $t_end);

		return $Tab;

	}

	//TODO move to workstation module
	static function getCapacityLeftRangeAgenda(&$PDOdb,&$ws,&$TDate,$t_start, $t_end){

		$t_cur = $t_start;

		while($t_cur<=$t_end) {
			$date=date('Y-m-d', $t_cur);
			$capacity = $TDate[$date];
			if($capacity===false || $capacity==='NA') $TDate[$date] = 'NA';
			else {

				$sql = "SELECT a.id, aex.needed_ressource, a.datep AS dateo , a.datep2 AS datee
							FROM ".MAIN_DB_PREFIX."actioncomm a
								LEFT JOIN ".MAIN_DB_PREFIX."actioncomm_extrafields aex ON (aex.fk_object=a.id)
							WHERE ";
				$sql.="'".$date."' BETWEEN a.datep AND a.datep2 ";
				$sql.=' AND aex.fk_workstation = '.$ws->id.' ';

				$Tab = $PDOdb->ExecuteASArray($sql);

				foreach($Tab as &$row) {
					$capacity-= $row->needed_ressource;
				}

				$TDate[$date] = $capacity;

			}
			$t_cur=strtotime('+1day', $t_cur);
		}

		return $TDate;
	}

	static function gb_search_set_bound(&$task, &$t_start, &$t_end) {
		global $conf,$db, $TCacheProject,$TCacheTask, $TCacheOFSupplierOrder,$TCacheOFOrder;

		if(empty($TCacheProject))$TCacheProject=array();
		if(empty($TCacheOFSupplierOrder))$TCacheOFSupplierOrder=array();
		if(empty($TCacheOFOrder))$TCacheOFOrder=array();
		if(empty($TCacheProject))$TCacheProject=array();


		if($task->fk_task_parent>0) { // s'il y a une tâche parente
			if(isset($TCacheTask[$task->fk_task_parent])) $parent = $TCacheTask[$task->fk_task_parent];
			else {
				$parent = new Task($db);
				$parent->fetch($task->fk_task_parent);
				$TCacheTask[$task->fk_task_parent] = $parent;
			}
			$parent_duration = floor(($parent->date_end - $parent->date_start) / 86400 ) + 1;

			if($parent_duration>$duration) $t_start_bound = $parent->date_end - ($duration * 86400); // alors le début est soit la durée de la tâche en partant de la fin de la tâche parente
			else $t_start_bound= $parent->date_start; // où le début de la tâche parente

			$t_start_bound=strtotime('midnight',$t_start_bound);
			if($t_start_bound>$t_start) $t_start = $t_start_bound;

		//	var_dump(array($task->fk_task_parent,$task->ref, $parent->ref, $parent->id,date('YmdHis',$t_start)));

		}

		if(empty($conf->global->GANTT_DISABLE_SUPPLIER_ORDER_MILESTONE) && $task->array_options['options_fk_of']>0) {

			dol_include_once('/fourn/class/fournisseur.commande.class.php');
			dol_include_once('/of/class/ordre_fabrication_asset.class.php');

			if(isset($TCacheOFSupplierOrder[$task->array_options['options_fk_of']]))$TIdCommandeFourn = $TCacheOFSupplierOrder[$task->array_options['options_fk_of']];
			else {

				$PDOdb=new TPDOdb;
				$of=new TAssetOF();
				$of->load($PDOdb, $task->array_options['options_fk_of']);
				$TIdCommandeFourn = $TCacheOFSupplierOrder[$task->array_options['options_fk_of']] = $of->getElementElement($PDOdb);

			}

			if(count($TIdCommandeFourn)){
				foreach($TIdCommandeFourn as $idcommandeFourn){
					$cmd = new CommandeFournisseur($db);
					$cmd->fetch($idcommandeFourn);

					if($cmd->statut>0 && $cmd->statut<5 && $cmd->date_livraison>0 &&  $cmd->date_livraison > $t_start) {
						$t_start =  $cmd->date_livraison ;
					}
				}
			}

		}

		if(empty($conf->global->GANTT_BOUND_ARE_JUST_ALERT)) {

			if(empty($conf->global->GANTT_DISABLE_PROJECT_MILESTONE)) {

				if(isset($TCacheProject[$task->fk_project])) $project = $TCacheProject[$task->fk_project];
				else {
					$project= new Project($db);
					$project->fetch($task->fk_project);
					$TCacheProject[$task->fk_project] = $project;
				}
				if($project->date_start>$t_start) $t_start = $project->date_start;
				if($project->date_start>$t_start) $t_start = $project->date_start;
				if($project->date_end<$t_end) $t_end = $project->date_end;

			}

			if(empty($conf->global->GANTT_DISABLE_ORDER_MILESTONE) && $task->array_options['options_fk_of']>0) {
				dol_include_once('/of/class/ordre_fabrication_asset.class.php');
				dol_include_once('/commande/class/commande.class.php');

				if(isset($TCacheOFOrder[$task->array_options['options_fk_of']]))$order = $TCacheOFOrder[$task->array_options['options_fk_of']];
				else {

					$PDOdb=new TPDOdb;
					$of=new TAssetOF();
					$of->load($PDOdb, $task->array_options['options_fk_of']);
					$order = new Commande($db);
					if($of->fk_commande) $order->fetch($of->fk_commande);

					$TCacheOFOrder[$task->array_options['options_fk_of']] = $order;

				}

				if($order->date_livraison>0) {
					$t_end_bound = $order->date_livraison+ 84399; //23:59:59
					if($t_end_bound<$t_end) $t_end = $t_end_bound;

				}
			}

		}

	}

	static function gb_search_days(&$TDates, &$task, $t_start, $t_end ) {
		global $conf;

		$tolerance = empty($conf->global->GANTT_OVERLOAD_TOLERANCE) ? 0 : -(float)$conf->global->GANTT_OVERLOAD_TOLERANCE;

		$row = array('start'=>-1, 'duration'=>$task->duration);

		foreach($TDates as $date=>&$data) {

			$time = strtotime($date);
			if($time>$t_end || $time < $t_start) continue;

			$task->start = $time;
			$task->end = $time + (86400 * $task->duration) - 1;

			$timetest= $task->start;
			$datetest = $date;
			$ok = true;
			$DateOk=array();
			while(!empty($TDates[$datetest]) && $timetest<=$task->end && $ok) {

				$data = &$TDates[$datetest];

				if($data['capacityLeft'] - $task->hour_needed < $tolerance) {
					$ok =false;
					break;
				}
				$DateOk[] = $datetest; //juste pour éviter ensuite le reparcours calculé

				$timetest= strtotime('+1day', $timetest);
				$datetest = date('Y-m-d', $timetest);

			}

			if($ok) {
				foreach($DateOk as $date) {
					$data2 = &$TDates[$date];
					$data2['capacityLeft'] -= $task->hour_needed;
				}

				$row['start'] = $time;
				$row['hour_needed'] = $task->hour_needed; //juste pour debug TODO remove
				$row['date_start'] = date('Y-m-d H:i:s',$time); //juste pour debug TODO remove

				return $row;
			}

		}

		return $row;
	}

	static function gb_search(&$TDates, &$task, $t_start, $t_end, $duration = 1) {
		global $conf,$db;

		$row = array('start'=>-1, 'duration'=>-1);

		$needed_ressource = empty($task->array_options['options_needed_ressource']) ? 1 : (int)$task->array_options['options_needed_ressource'];

		$task->hour_needed = $task->planned_workload * $needed_ressource* ((100 - $task->progress) / 100) / 3600 / $duration;
		$task->duration = $duration;

		if($duration<50 && $task->hour_needed>0) {

			self::gb_search_set_bound($task, $t_start, $t_end);
			$row = self::gb_search_days($TDates, $task, $t_start, $t_end);

			if($row['start'] == -1) $row = self::gb_search($TDates, $task, $t_start, $t_end, $duration + 1);

		}

		return $row;
	}

	static function get_better_task(&$TWS, &$task, $t_start, $t_end) {

		$fk_workstation = (int)$task->array_options['options_fk_workstation'];

		if($fk_workstation>0 && $task->progress < 100) {
			if(empty($TWS[$fk_workstation])) $TWS[$fk_workstation] = self::get_ws_capacity($fk_workstation, $t_start, $t_end, $task->id);
			return self::gb_search($TWS[$fk_workstation], $task, $t_start, $t_end);

		}
		else {

			$note = 'UnableToFindAWay';
			if($fk_workstation <= 0)$note = 'NoWorkstation';
			if($task->progress == 100)$note='FinishedTask';

			return array('start'=>-1, 'duration'=>-1, 'note'=>$note);
		}
	}

	static function get_better($TTaskId, $t_start, $t_end) {
		global $db,$TCacheTask;

		if(empty($TCacheTask))$TCacheTask=array();

		if(!is_array($TTaskId))$TTaskId=array($TTaskId);

		if($t_start < time())$t_start = time();

		$TWS = $Tab = array();

		if(!empty($TTaskId)) {

			foreach($TTaskId as $fk_task) {

				$task = new Task($db);
				$task->fetch($fk_task);

				$fk_workstation = (int)$task->array_options['options_fk_workstation'];
				if(empty($TWS[$fk_workstation])) $TWS[$fk_workstation] = self::get_ws_capacity($fk_workstation, $t_start, $t_end, $TTaskId);
				$Tab[$task->id] = self::get_better_task($TWS, $task, $t_start, $t_end);

				if( $Tab[$task->id]['start']> 0) {
					$task->date_start = $Tab[$task->id]['start'];
					$task->date_end = $Tab[$task->id]['start'] + ($Tab[$task->id]['duration']*86400 ) - 1;
				}

				$TCacheTask[$task->id] = $task;

			}

		}

		return $Tab;

	}




}