<?php

class GanttPatern {

    static function getTasks($date_start, $date_end, $fk_project = 0, $restrictWS=0, $ref_of='',$ref_cmd='',$fk_user=0) {
        global $conf,$db;

        dol_include_once('/projet/class/task.class.php');

        $restrictWS = (int)$restrictWS;
        $fk_project = (int)$fk_project;

        /*if(!empty($conf->global->GANTT_USE_CACHE_FOR_X_MINUTES)) {

            $TCache = & $_SESSION['ganttcache']['getTasks'][$date_start][$date_end][$fk_project][$restrictWS];

            if(!empty($TCache) && $TCache['@time']>0 && $TCache['@time']>time() - 60 * $conf->global->GANTT_USE_CACHE_FOR_X_MINUTES) {

                return $TCache['@data'];

            }

        }*/

        if(empty($conf->of->enabled)) {
            $sql = "SELECT t.rowid
		FROM ".MAIN_DB_PREFIX."projet_task t LEFT JOIN ".MAIN_DB_PREFIX."projet_task_extrafields tex ON (tex.fk_object=t.rowid)
			LEFT JOIN ".MAIN_DB_PREFIX."projet p ON (p.rowid=t.fk_projet)
						";

        }
        else {
            if(!empty($conf->global->ASSET_CUMULATE_PROJECT_TASK)){
                $sql = "SELECT t.rowid,wof.nb_days_before_beginning
                FROM " . MAIN_DB_PREFIX . "projet_task t
                LEFT JOIN " . MAIN_DB_PREFIX . "element_element ee  ON (ee.fk_target=t.rowid AND ee.targettype='project_task' AND ee.sourcetype='tassetof')
                    LEFT JOIN " . MAIN_DB_PREFIX . "projet p ON (p.rowid=t.fk_projet)
                        LEFT JOIN " . MAIN_DB_PREFIX . "assetOf oft ON (oft.rowid = ee.fk_source AND oft.fk_project = t.fk_projet)
                            LEFT JOIN " . MAIN_DB_PREFIX . "asset_workstation_of wof ON (t.rowid=wof.fk_project_task)
                                LEFT JOIN " . MAIN_DB_PREFIX . "commande cmd ON (oft.fk_commande=cmd.rowid)
                                ";
            }else {
                $sql = "SELECT t.rowid,wof.nb_days_before_beginning
                FROM " . MAIN_DB_PREFIX . "projet_task t LEFT JOIN " . MAIN_DB_PREFIX . "projet_task_extrafields tex ON (tex.fk_object=t.rowid)
                    LEFT JOIN " . MAIN_DB_PREFIX . "projet p ON (p.rowid=t.fk_projet)
                        LEFT JOIN " . MAIN_DB_PREFIX . "assetOf oft ON (oft.rowid = tex.fk_of AND oft.fk_project = t.fk_projet)
                            LEFT JOIN " . MAIN_DB_PREFIX . "asset_workstation_of wof ON (t.rowid=wof.fk_project_task)
                                LEFT JOIN " . MAIN_DB_PREFIX . "commande cmd ON (oft.fk_commande=cmd.rowid)
                                ";
            }

        }

        if($fk_user>0) {

            $sql.=" LEFT JOIN ".MAIN_DB_PREFIX."element_contact ec ON (ec.element_id=t.rowid)";

        }


        $sql.="	WHERE t.dateo IS NOT NULL ";

        if($fk_user>0) {
            $sql.=" AND ec.fk_socpeople=".$fk_user." AND ec.fk_c_type_contact IN (180,181) ";
        }

        if(!empty($ref_of)) { $sql.=" AND (oft.numero LIKE '%".$ref_of."%' AND oft.entity=".$conf->entity." ) "; }
        if(!empty($ref_cmd)) { $sql.=" AND (cmd.ref LIKE '%".$ref_cmd."%' AND cmd.entity=".$conf->entity.") "; }

        if($fk_project>0) $sql.= " AND t.fk_projet=".$fk_project;
        else {

            if(empty($conf->global->GANTT_SHOW_TASK_FROM_ANY_PROJECT_STATUS)) $sql.=" AND p.fk_statut = 1 ";

            if(!empty($conf->of->enabled)) {
                if(!empty($conf->global->ASSET_CUMULATE_PROJECT_TASK)){
                    $sql .= " AND ee.fk_source IS NOT NULL AND ee.fk_source>0 AND (t.progress<100 OR t.progress IS NULL)
                    AND oft.status IN ('VALID','OPEN','ONORDER','NEEDOFFER')
                    ";
                }else {
                    $sql .= " AND tex.fk_of IS NOT NULL AND tex.fk_of>0 AND (t.progress<100 OR t.progress IS NULL)
                    AND oft.status IN ('VALID','OPEN','ONORDER','NEEDOFFER')
                    ";
                }
            }
            $sql.=" AND t.dateo <= '".$date_end."' AND t.datee >=  '".$date_start."' ";

            if(!empty($conf->global->GANTT_MANAGE_SHARED_PROJECT)) $sql.=" AND p.entity IN (".getEntity('project',1).")";
            else $sql.=" AND p.entity=".$conf->entity;

        }

        if(!empty($conf->workstation->enabled)) {
            if($restrictWS>0) {
                $sql.=" AND tex.fk_workstation=".(int)$restrictWS;
            }
            else if($restrictWS === '0' ) {
                $sql.=" AND (tex.fk_workstation IS NULL) ";
            }
        }

        $sql.=" ORDER BY t.dateo ASC,t.rowid ASC";

        $res = $db->query($sql);
        if($res===false) {
            var_dump($db);exit;
        }

        $Tab=array();
        while($obj = $db->fetch_object($res)) {

            $task = new Task($db);
            $task->fetch($obj->rowid);

            if($task->id <=0) continue;

            if(empty($task->array_options)) $task->fetch_optionals($task->id);

            $Tab[] = $task;
        }

       /* if(!empty($conf->global->GANTT_USE_CACHE_FOR_X_MINUTES)) {
            unset( $_SESSION['ganttcache']['getTasks'] );
            $_SESSION['ganttcache']['getTasks'][$date_start][$date_end][$fk_project][$restrictWS]['@time'] = time();
            $_SESSION['ganttcache']['getTasks'][$date_start][$date_end][$fk_project][$restrictWS]['@data'] = $Tab;
        }*/

        return $Tab;
    }




	/**
	 * @param int    $wsid
	 * @param int    $t_start
	 * @param int    $t_end
	 * @param int    $fk_task
	 * @param string $scale_unit
	 * @param  array $TTaskCapacityExclude
	 * @return array
	 */
	static function get_ws_capacity($wsid, $t_start, $t_end, $fk_task = 0,$scale_unit='day', $TTaskCapacityExclude = array()) {

		global $conf;

		if(empty($conf->workstation->enabled)) return array();

		dol_include_once('/workstation/class/workstation.class.php');

		$PDOdb=new TPDOdb;

		$ws=new TWorkstation;
		$ws->load($PDOdb, $wsid);

		if($scale_unit=='week') {
		    $day_of_week = date('N',$t_start);
		    $t_start=strtotime('-'.$day_of_week.'days + 1 day', $t_start);
		}
		$TTaskCapacityExclude[] = $fk_task;
		$TTaskCapacityExclude = array_unique($TTaskCapacityExclude);
		$Tab = $ws->getCapacityLeftRange($PDOdb, $t_start, $t_end, true, $TTaskCapacityExclude,$scale_unit);

		if($scale_unit == 'week') {
            $i = 0;
		    foreach($Tab as $k=>$data) {

   		        if($i == 0) {
                    $k_hold = $k;
                }
		        else {
		            if($data['capacityLeft']!='NA') {
    		            $Tab[$k_hold]['capacity']+=$data['capacity'];
    		            $Tab[$k_hold]['nb_hour_capacity']+=$data['nb_hour_capacity'];
    		            $Tab[$k_hold]['capacityLeft']+=$data['capacityLeft'];
		            }

		            unset($Tab[$k]);
		        }

                $i++;
                if($i == 7)$i = 0;

		    }

            return $Tab;
		}
		else {
		    return $Tab;
		}


	}

	/**
	 * get task from cache
	 *
	 * @param int $fk_task
	 * @param bool $cache
	 * @return Task|false
	 */
	public static function loadTask($fk_task, $cache = true){
		global $TCacheTask, $db;

		/**
		 * @var Task[] $TCacheTask
		 */
		if(!is_array($TCacheTask)) $TCacheTask = array();

		if($cache && isset($TCacheTask[$task->fk_task_parent])) {
			return $TCacheTask[$fk_task];
		}
		else {
			$task = new Task($db);
			if($task->fetch($fk_task)>0){
				$TCacheTask[$fk_task] = $task;
				return $task;
			}
		}

		self::clearTaskCache($fk_task);
		return false;
	}

	/**
	 * clear task cache
	 * @param int|false $fk_task if false delete all cache
	 */
	public static function clearTaskCache($fk_task = false){
		global $TCacheTask;

		if($fk_task === false){
			$TCacheTask = array();
		}
		elseif (isset($TCacheTask[$fk_task])){
			unset($TCacheTask[$fk_task]);
		}

	}

	/**
	 *    Return id of task children
	 *
	 * @param      $fk_task
	 * @param bool $recursive
	 * @param bool $cache
	 * @return    int[]
	 */
	public static function getTaskChildren($fk_task, $recursive = false, $cache =true)
	{
		global $db, $TCacheGetTaskChildren;
		if (empty($TCacheGetTaskChildren))$TCacheGetTaskChildren=array();

		if ($cache && isset($TCacheGetTaskChildren[$fk_task])){

			if($recursive){
				$res = $TCacheGetTaskChildren[$fk_task];
				foreach ($TCacheGetTaskChildren[$fk_task] as $fk_child){
					$res = array_merge ($res, self::getTaskChildren($fk_child, $recursive, $cache));
				}
				return array_unique($res);
			}
			else{
				return $TCacheGetTaskChildren[$fk_task];
			}
		}
		elseif (!$cache && isset($TCacheGetTaskChildren[$fk_task])){
			unset ($TCacheGetTaskChildren[$fk_task]);
		}

		$res = array();

		$sql = "SELECT rowid as id";
		$sql.= " FROM ".MAIN_DB_PREFIX."projet_task";
		$sql.= " WHERE fk_task_parent=".intval($fk_task);

		$resql = $db->query($sql);
		if ($resql)
		{
			while($obj=$db->fetch_object($resql))
			{
				$res[] = $obj->id;
			}

			if ($cache){
				$TCacheGetTaskChildren[$fk_task] = $res;
			}

			if($recursive){
				foreach ($res as $fk_child){
					$res = array_merge ($res, self::getTaskChildren($fk_child, $recursive, $cache));
				}
				$res = array_unique($res);
			}

			$db->free($resql);
		}

		return $res;
	}

	/**
	 * @param Task $task
	 * @param int $t_start timestamp
	 * @param int $t_end 	timestamp
	 * @param $TInfo
	 */
	static function gb_search_set_bound(&$task, &$t_start, &$t_end,&$TInfo) {
		global $conf,$db, $TCacheProject,$TCacheTask, $TCacheOFSupplierOrder,$TCacheOFOrder;

		if(empty($TCacheProject))$TCacheProject=array();
		if(empty($TCacheOFSupplierOrder))$TCacheOFSupplierOrder=array();
		if(empty($TCacheOFOrder))$TCacheOFOrder=array();
		if(empty($TCacheProject))$TCacheProject=array();

		if(!empty($conf->of->enabled)) {
		$res = $db->query("SELECT nb_days_before_beginning FROM ".MAIN_DB_PREFIX."asset_workstation_of WHERE fk_project_task=".$task->id);
		if($obj = $db->fetch_object($res)) {
			if($obj->nb_days_before_beginning>0) {
			    if(!empty($conf->global->GANTT_DELAY_IS_BETWEEN_TASK)) {
			        $time_ref = time();
			        if(!empty($conf->global->ASSET_TASK_HIERARCHIQUE_BY_RANK_REVERT)){
			        	// DANS LE CAS OU LA HIERARCHIE DES TACHES EST INVERSE POUR LA PLANIF : LES ENFANTS SONT PRIORITAIRES
						$taskChildren = self::getTaskChildren($task->id, true);
						if(!empty($taskChildren)){
							foreach ($taskChildren as $childid){
								$child = self::loadTask($childid);
								if($child
									&& $child->progress<100
									&& $time_ref < strtotime('midnight',$child->date_end))
								{
									$time_ref = strtotime('midnight', $child->date_end);
									$TInfo[] = 'GANTT_DELAY_IS_BETWEEN_TASK '.date('Y-m-d H:i:s', $time_ref).' '.$obj->nb_days_before_beginning;
								}
							}
						}

					}
			        else{
			        	//  LES PARENTS SONT PRIORITAIRES
						if($task->fk_task_parent>0) { // s'il y a une tâche parente
							$parent = self::loadTask($task->fk_task_parent);
							if($parent && $parent->progress<100) {
								$time_ref = strtotime('midnight',$parent->date_end);
								$TInfo[] = 'GANTT_DELAY_IS_BETWEEN_TASK '.date('Y-m-d H:i:s', $time_ref).' '.$obj->nb_days_before_beginning;
							}
						}
					}

			        $t_start_bound=strtotime('+'.((int)$obj->nb_days_before_beginning).' days midnight', $time_ref);
			    }
			    else {
			        $t_start_bound=strtotime('+'.((int)$obj->nb_days_before_beginning+1).' days midnight');
			    }

    			$TInfo[] = 'start bound delai '.date('Y-m-d', $t_start_bound);
			}

			if(isset($t_start_bound) && $t_start_bound>$t_start) $t_start = $t_start_bound;
		}
		}



		if(!empty($conf->global->ASSET_TASK_HIERARCHIQUE_BY_RANK_REVERT)){
			// DANS LE CAS OU LA HIERARCHIE DES TACHES EST INVERSE POUR LA PLANIF : LES ENFANTS SONT PRIORITAIRES
			$taskChildren = self::getTaskChildren($task->id, true);

			if(!empty($taskChildren)){
				foreach ($taskChildren as $childId){
					$child = self::loadTask($childId);
					if($child && $child->progress<100)
					{
						$t_start_bound= $child->date_end; // où la fin de la tâche enfant
						if(!empty($conf->global->GANTT_DELAY_IS_BETWEEN_TASK)) {
							$t_start_bound=strtotime('midnight',$t_start_bound + 86400);
						}
						else{
							$t_start_bound=strtotime('midnight',$t_start_bound);
						}

						if($t_start_bound>$t_start) {
							$t_start = $t_start_bound;
							$TInfo[] = 'start bound fk_task_child ('.$child->id.' / '.$child->ref.') '.date('Y-m-d', $child->date_start).' - '.date('Y-m-d', $child->date_end).' --> '.date('Y-m-d', $t_start);
						}
					}
				}
			}

		}elseif ($task->fk_task_parent>0) { // s'il y a une tâche parente

			$parent = self::loadTask($task->fk_task_parent);
			if($parent && $parent->progress < 100) {

				$parent_duration = floor(($parent->date_end - $parent->date_start) / 86400 ) + 1;

				if($parent_duration>$task->duration_effective) $t_start_bound = $parent->date_end - ($task->duration_effective * 86400); // alors le début est soit la durée de la tâche en partant de la fin de la tâche parente
				else $t_start_bound= $parent->date_start; // où le début de la tâche parente

				$t_start_bound=strtotime('midnight',$t_start_bound);
				if($t_start_bound>$t_start) {
					$t_start = $t_start_bound;
					$TInfo[] = 'start bound fk_task_parent ('.$parent->id.' / '.$parent->ref.') '.date('Y-m-d', $parent->date_start).' - '.date('Y-m-d', $parent->date_end).' --> '.date('Y-m-d', $t_start);
				}
			}

		}

        if(!empty($conf->of->enabled) && !empty($conf->global->ASSET_CUMULATE_PROJECT_TASK)) {
            if(!isset($conf->tassetof)) $conf->tassetof = new \stdClass(); // for warning
            $conf->tassetof->enabled = 1; // pour fetchobjectlinked
            $task->fetchObjectLinked(0, 'tassetof', $task->id, $task->element, 'OR', 1, 'sourcetype', 0);
        }

		if(empty($conf->global->GANTT_DISABLE_SUPPLIER_ORDER_MILESTONE) &&
            ((!empty($conf->global->ASSET_CUMULATE_PROJECT_TASK) && !empty($task->linkedObjectsIds['tassetof'])) ||$task->array_options['options_fk_of']>0) &&
            !empty($conf->of->enabled) ) {

			dol_include_once('/fourn/class/fournisseur.commande.class.php');
			dol_include_once('/of/class/ordre_fabrication_asset.class.php');

            if(empty($conf->global->ASSET_CUMULATE_PROJECT_TASK))$TIdCommandeFourn = GanttPatern::_getIdCommandeFournByOf($TCacheOFSupplierOrder,$task->array_options['options_fk_of']);
            else {
                $TIdCommandeFourn = array();
                foreach($task->linkedObjectsIds['tassetof'] as $fk_of){
                    $TIdCommandeFourn = array_merge($TIdCommandeFourn, GanttPatern::_getIdCommandeFournByOf($TCacheOFSupplierOrder,$fk_of));
                }
            }

			if(count($TIdCommandeFourn)){
				foreach($TIdCommandeFourn as $idcommandeFourn){
					$cmd = new CommandeFournisseur($db);
					$cmd->fetch($idcommandeFourn);

					if($cmd->statut>0 && $cmd->statut<5 && $cmd->date_livraison>0 &&  $cmd->date_livraison > $t_start) {
						$t_start =  strtotime('+1day', $cmd->date_livraison );
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

			if(empty($conf->global->GANTT_DISABLE_ORDER_MILESTONE)
                && ((!empty($conf->global->ASSET_CUMULATE_PROJECT_TASK) && !empty($task->linkedObjectsIds['tassetof'])) ||$task->array_options['options_fk_of']>0)
                &&  !empty($conf->of->enabled)) {

                dol_include_once('/of/class/ordre_fabrication_asset.class.php');
                dol_include_once('/commande/class/commande.class.php');
                $TOrders = array();
                if(empty($conf->global->ASSET_CUMULATE_PROJECT_TASK)) $TOrders[] = GanttPatern::_getOrderByOf($TCacheOFOrder, $task->array_options['options_fk_of'] > 0);
                else {
                    foreach($task->linkedObjectsIds['tassetof'] as $fk_of) {
                        $TOrders[] = GanttPatern::_getOrderByOf($TCacheOFOrder, $fk_of);
                    }
                }

                foreach($TOrders as $orders) {
                    if(is_array($orders)) {
                        foreach($orders as $order)
                            if($order->date_livraison > 0) {
                                $t_end_bound = $order->date_livraison + 84399; //23:59:59
                                if($t_end_bound < $t_end) $t_end = $t_end_bound;
                            }
                    }
                    else if($orders->date_livraison > 0) {
                        $t_end_bound = $orders->date_livraison + 84399; //23:59:59
                        if($t_end_bound < $t_end) $t_end = $t_end_bound;
                    }
                }
            }
		}
	}

	static function gb_search_days(&$TDates, &$task, $t_start, $t_end ) {
		global $conf;

		$tolerance = empty($conf->global->GANTT_OVERLOAD_TOLERANCE) ? 0 : -(float)$conf->global->GANTT_OVERLOAD_TOLERANCE;

		$row = array('start'=>-1, 'duration'=>ceil($task->planned_workload) / 86400);

		foreach($TDates as $date=>&$data) {

		    $time = strtotime($date);
			if($time>$t_end || $time < $t_start) continue;

			$task->start = $time;
			$task->end = $time + ($task->planned_workload) - 1;

			$timetest= $task->start;
			$datetest = $date;
			$ok = true;
			$DateOk=array();
			while(!empty($TDates[$datetest]) && $timetest<=$task->end && $ok) {

				$data = &$TDates[$datetest];
				$capacityLeft = $data['capacityLeft'];
				if($data['capacity']!=='NA' && $data['nb_ressource']>0 && $data['capacity']>0 && empty($data['is_parallele'])) {
				    $capacityLeft=min($capacityLeft,$data['nb_hour_capacity']);
				    //var_dump('la',$datetest,$task->hour_needed,$data,$capacityLeft);exit;
				}

				if(!empty($tolerance) && doubleval($capacityLeft) - doubleval($task->hour_needed) < $tolerance) {
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

	/**
	 * @param array $TDates
	 * @param Task $task
	 * @param int    $t_start
	 * @param int   $t_end
	 * @param int $duration
	 * @return array
	 */
	static function gb_search(&$TDates, &$task, $t_start, $t_end, $duration = 1) {
		global $conf,$db, $langs;

		$TInfo=array();
		$row = array('start'=>-1, 'duration'=>-1);

		$needed_ressource = empty($task->array_options['options_needed_ressource']) ? 1 : (int)$task->array_options['options_needed_ressource'];

		$task->hour_needed = $task->planned_workload * $needed_ressource* ((100 - $task->progress) / 100) / 3600 / $duration;
		$task->duration = $duration;

		if($task->hour_needed<=0){
			$row['note']=$langs->trans('NoHourPlanned');
		}

		$row['debugInfo'][] = ' task : '.$task->id.'('.$task->ref.') '.$task->duration.' '.$task->hour_needed;

		if($duration<50 && $task->hour_needed>0) {
			self::gb_search_set_bound($task, $t_start, $t_end, $TInfo);

			$row['debugInfo'][] = 'Bounds '.date('Y-m-d H:i:s', intval($t_start)).' --> '.date('Y-m-d H:i:s', intval($t_end));

			$row = self::gb_search_days($TDates, $task, $t_start, $t_end);

			if($row['start'] == -1) $row = self::gb_search($TDates, $task, $t_start, $t_end, $duration + 1);

		}

		return array_merge($row,$TInfo);
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

	/**
	 * @deprecated see set_better_task_ordo
	 */
	static function get_better($TTaskId, $t_start, $t_end) {
		global $db,$TCacheTask;

		if(GETPOST('_givemesolution')=='yes') {
			echo date('Y-m-d H:i:s', $t_start).' --> '.date('Y-m-d H:i:s', $t_end).'<br />';
		}

		if(empty($TCacheTask))$TCacheTask=array();

		if(!is_array($TTaskId))$TTaskId=array($TTaskId);

		$midnight = strtotime('midnight');
		if($t_start < $midnight)$t_start = $midnight;

		$TWS = $Tab = array();

		if(!empty($TTaskId)) {

			foreach($TTaskId as $fk_task) {

				$task = new Task($db);
				$task->fetch($fk_task);
				if($task->id>0) {
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

		}

		return $Tab;

	}


	/**
	 * based on get_better
	 * search and set best start date for plan
	 * @param       $TTaskId
	 * @param       $t_start
	 * @param       $t_end
	 * @return array
	 */
	static function set_better_task_ordo($TTaskId, $t_start, $t_end) {
		global $db,$TCacheTask, $TCacheOf, $conf, $user;

		if(empty($TCacheTask))$TCacheTask=array();

		if(!is_array($TTaskId))$TTaskId=array($TTaskId);

		$TTaskId = array_map('intval', $TTaskId);

		$midnight = strtotime('midnight');
		if($t_start < $midnight)$t_start = $midnight;

		$TWS = $Tab = array();

		if(!empty($TTaskId)) {

			// PRISE EN COMPTE DES BESOINS D'OF POUR PRIORISATION DES TACHES
			if (!empty($conf->of->enabled) && !empty($conf->global->BETTER_TASK_POSITION_INCLUDE_OF_PRIORITY)) {

				// Init du cache
				if (!is_array($TCacheOf)) { $TCacheOf = array(); }

				// Pre-tri des tâches par date de besoin de l'OF pour priorisation de la liste de traitement
				$sql = "SELECT elel.fk_target as fk_task, elel.fk_source as fk_of, assetOf.date_besoin"
					." FROM ".MAIN_DB_PREFIX."element_element elel"
					." JOIN ".MAIN_DB_PREFIX."assetOf assetOf ON (elel.fk_source = assetOf.rowid) "
					." WHERE elel.sourcetype = 'tassetof' "
					." AND elel.targettype = 'project_task' "
					." AND elel.fk_target IN (".implode(',', $TTaskId).") "
					." ORDER BY assetOf.date_besoin ASC ";

				$resql = $db->query($sql);
				if ($resql) {
					while ($obj = $db->fetch_object($resql)) {
						$NewTTaskId[] = $obj->fk_task;
						$task = self::loadTask($obj->fk_task);
						if($task){
							$TCacheTask[$obj->fk_task]->of_date_besoin = $db->jdate($obj->date_besoin);
						}
					}
				}
			}

			// DETECTION DES ENFANTS COURANT
			foreach ($TTaskId as $fk_task) {
				$task = self::loadTask($fk_task);
				if($task){
					$TCacheTask[$fk_task]->TChildren = self::getTaskChildren($fk_task);
					$TCacheTask[$fk_task]->TChildrenRecursive = self::getTaskChildren($fk_task, true);
				}
			}

			// TRI DES PRIORITE DE TACHES DANS LA FILE DE TRAITEMENT
			$resSort = uasort ( $TTaskId , function ($a, $b){
					global $conf;
					$taskA = GanttPatern::loadTask($a);
					$taskB = GanttPatern::loadTask($b);

					$rank = 0;
					$multiplePow = 0;
					// rappel si a prioritaire -1 si b prio 1 si a et b sont equivalent 0

					// PRIORISATION OF SUR DATE DE BESOIN
					$multiplePow++;
					$multiple = pow(10, $multiplePow);
					if (!empty($taskA->of_date_besoin) && !empty($taskB->of_date_besoin)) {
						$rank+= $multiple*(($taskA->of_date_besoin < $taskB->of_date_besoin) ? -1 : 1);
					}
					elseif (!empty($taskA->of_date_besoin)) {
						$rank+= -1*$multiple;
					}
					elseif (!empty($taskB->of_date_besoin)) {
						$rank+= 1*$multiple;
					}

					// PRIORISATION ENFANT / PARENTS dans la file de traitement
					// Une tâche parent ne peut pas commencer avant une tache enfant si la cong OF ASSET_TASK_HIERARCHIQUE_BY_RANK_REVERT est active
					// Dans le cas contraire une tâche enfant ne peut pas commencer avant une tache Parent
					$multiplePow++;
					$multiple = pow(10, $multiplePow)*(empty($conf->global->ASSET_TASK_HIERARCHIQUE_BY_RANK_REVERT)?-1:1);
					if ($taskB->fk_task_parent == $taskA->id){ // quick test for perf to detech first ancestor
						$rank+= 1*$multiple; // B est un enfant de A
					}
					elseif ($taskA->fk_task_parent == $taskB->id){ // quick test for perf to detech first ancestor
						$rank+= -1*$multiple; // A est un enfant de B
					}
					elseif (is_array($taskA->TChildrenRecursive) && in_array($taskB->id, $taskA->TChildrenRecursive)){  // quick test not detect parent ancestor so perform an slow test to be sure
						$rank+= 1*$multiple; // B est un enfant de A
					}
					elseif (is_array($taskB->TChildrenRecursive) && in_array($taskA->id, $taskB->TChildrenRecursive)){  // quick test not detect parent ancestor so perform an slow test to be sure
						$rank+= 1*$multiple; // A est un enfant de B
					}

					return $rank;
				}
			);

			$TCapacityExclude = $TTaskId; // ne pas prendre en compte l'impacte des taches en attentes d'attribution

			foreach ($TTaskId as $fk_task) {
				$task = self::loadTask($fk_task);

				if($task && $task->id>0) {

					$fk_workstation = (int)$task->array_options['options_fk_workstation'];
					if(empty($TWS[$fk_workstation])) $TWS[$fk_workstation] = self::get_ws_capacity($fk_workstation, $t_start, $t_end, $TTaskId, 'day', $TCapacityExclude);

					$Tab[$task->id] = self::get_better_task($TWS, $task, $t_start, $t_end);

					if( $Tab[$task->id]['start']> 0) {
						$task->date_start = $Tab[$task->id]['start'];
						$task->date_end = ceil($Tab[$task->id]['start'] + $Tab[$task->id]['duration']*86400);

						// une date de début à été trouvé, reste à étendre la tâche pour quel couvre ces besoins (charge)
						$date_start_key = date('Y-m-d', $task->date_start);
						$curDateToTest = $task->date_start;

						$needed_ressource = intval($task->array_options['options_needed_ressource']);

						$needToAssign = $task->planned_workload * $needed_ressource / 3600;
						$needToAssign = $needToAssign * (100 - $task->progress)/100;

						while ($needToAssign > 0){

							if(!empty($TWS[$fk_workstation][$date_start_key])){
								// Bon le syteme est de base mal pensé car il n'est pas possible de savoir avec capacityLeft le nombre de ressources correspondantes
								// par conséquent à defaut d'une bonne info je me base sur le capacityLeft tout court
								// TODO : voir aussi pour la gestion du is_parallele

								if($TWS[$fk_workstation][$date_start_key]['capacityLeft'] > 0){
									if(($TWS[$fk_workstation][$date_start_key]['capacityLeft'] - $needToAssign) >= 0){
										$needToAssign = 0;
										break;
									}
									else{
										$needToAssign = abs($needToAssign - $TWS[$fk_workstation][$date_start_key]['capacityLeft']);
									}
								}

								// Test du jour suivant
								$curDateToTest+=86400;
								$date_start_key = date('Y-m-d', $curDateToTest);
							}
							else{
								// c'est mort...
								break;
							}
						}

						if( $curDateToTest > $task->date_end){
							$task->date_end = strtotime('midnight', $curDateToTest)-1; // 23h59:59
						}
						$Tab[$task->id]['end'] = $task->date_end;

						// BUG FIX : Because workstation execute sql query to check charge it's important to save the task position
						$task->update($user);
						self::clearTaskCache($task->id); // because workstation execute sql query to check charge
					}

					unset ($TCapacityExclude[$task->id]); // maintenant l'impact de cette tâche doit être pris en compte je la retire de l'exclusion
				}

			}

		}

		return $Tab;

	}

static function _getIdCommandeFournByOf(&$TCacheOFSupplierOrder, $fk_of){

    if(isset($TCacheOFSupplierOrder[$fk_of]))return $TCacheOFSupplierOrder[$fk_of];
    else {

        $PDOdb=new TPDOdb;
        $of=new TAssetOF();
        $of->load($PDOdb, $fk_of);
        $TIdCommandeFourn = $TCacheOFSupplierOrder[$fk_of] = $of->getElementElement($PDOdb);
        return $TIdCommandeFourn;

    }
}

    static function _getOrderByOf(&$TCacheOFOrder, $fk_of) {
        global $db, $conf;

        if(isset($TCacheOFOrder[$fk_of])) $order = $TCacheOFOrder[$fk_of];
        else {

            $PDOdb = new TPDOdb;
            $of = new TAssetOF();
            $of->load($PDOdb, $fk_of);
            $order = new Commande($db);
            if(empty($conf->global->OF_MANAGE_ORDER_LINK_BY_LINE)) {
                if($of->fk_commande) $order->fetch($of->fk_commande);

                $TCacheOFOrder[$fk_of] = $order;
            }
            else {
                $TLine = $of->getLinesProductToMake();
                if(!empty($TLine)) {
                    foreach($TLine as $line) {
                        if(!empty($line->fk_commandedet)) {
                            $orderLine = new OrderLine($db);
                            $orderLine->fetch($line->fk_commandedet);
                            $order->fetch($orderLine->fk_commande);
                            $order[$orderLine->fk_commande]=$order;
                        }
                    }
                }
            }
        }

        return $order;
    }


    /**
     *  TODO ce fonctionnement est un coupé/collé de ce qu'il y avait dans un appel de trigger sur TASK_CREATE, l'objectif est déporter ce comportement dans OF et laisser ici une methodologie bien plus simple qui devra être appelé que si OF n'est pas actif (hook ?)
     * @param Task $object
     * @param User $user
     */
    public function calculDatesProjectTasks($object, $user, $date_min_start=null)
    {
        global $conf;

        require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
        $db = &$object->db;
        $project = new Project($db);
        $project->fetch($object->fk_project);

        $t_current = time();

        if (!empty($object->fk_task_parent)) // si la tâche a un parent elle ne peut débuter qu'après la fin de celui-ci
        {
            $parent = new Task($db);
            $parent->fetch($object->fk_task_parent);

            $t_current = $parent->date_end;
        }
// 			var_dump($t_current);
        $t_start =  max( $project->date_start, $t_current, $date_min_start);

        $day_range = empty($conf->global->GANTT_DAY_RANGE_FROM_NOW) ? 90 : $conf->global->GANTT_DAY_RANGE_FROM_NOW;

        $t_end =  $project->date_end > $t_current ? $project->date_end : strtotime('+'.$day_range.' day', $t_start);

        if($t_end>=$t_current) {
            $TWS=array();

            $Tab = GanttPatern::get_better_task($TWS, $object,$t_start, $t_end);
//            var_dump($Tab);
            if($Tab['start']>0 && $Tab['duration']>=1) {

                $object->date_start = $Tab['start'];
                $object->date_end = $object->date_start + ( $Tab['duration'] * 86400 ) - 1;

                $res = $object->update($user);
                if($res<=0) {

                    var_dump($object);exit;
                }
            }


        }
    }
}


