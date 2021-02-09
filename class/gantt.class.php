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

	static function get_ws_capacity($wsid, $t_start, $t_end, $fk_task = 0,$scale_unit='day') {

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

		$Tab = $ws->getCapacityLeftRange($PDOdb, $t_start, $t_end, true, $fk_task,$scale_unit);

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

	static function gb_search_set_bound(&$task, &$t_start, &$t_end,&$TInfo) {
		global $conf,$db, $TCacheProject,$TCacheTask, $TCacheOFSupplierOrder,$TCacheOFOrder;

		if(empty($TCacheProject))$TCacheProject=array();
		if(empty($TCacheOFSupplierOrder))$TCacheOFSupplierOrder=array();
		if(empty($TCacheOFOrder))$TCacheOFOrder=array();
		if(empty($TCacheProject))$TCacheProject=array();

		if(!empty($conf->of->enabled)) {
		$res = $db->query("SELECT nb_days_before_beginning FROM ".MAIN_DB_PREFIX."asset_workstation_of WHERE fk_project_task=".$task->id);
		if($res === false) {
			var_dump($db);exit;
		}
		if($obj = $db->fetch_object($res)) {
			if($obj->nb_days_before_beginning>0) {
			    if(!empty($conf->global->GANTT_DELAY_IS_BETWEEN_TASK)) {
			        $time_ref = time();

			        if($task->fk_task_parent>0) { // s'il y a une tâche parente
			            if(isset($TCacheTask[$task->fk_task_parent])) $parent = $TCacheTask[$task->fk_task_parent];
			            else {
			                $parent = new Task($db);
			                $parent->fetch($task->fk_task_parent);
			                $TCacheTask[$task->fk_task_parent] = $parent;
			            }

			            if($parent->progress<100) {
			                $time_ref = strtotime('midnight',$parent->date_end);
			                if(GETPOST('_givemesolution')=='yes') {
			                    echo 'GANTT_DELAY_IS_BETWEEN_TASK '.date('Y-m-d H:i:s', $time_ref).' '.$obj->nb_days_before_beginning.'<br>';
			                }
			                $TInfo[] = 'GANTT_DELAY_IS_BETWEEN_TASK '.date('Y-m-d H:i:s', $time_ref).' '.$obj->nb_days_before_beginning;
			            }
			        }

			        $t_start_bound=strtotime('+'.((int)$obj->nb_days_before_beginning).' days midnight', $time_ref);
			    }
			    else {
			        $t_start_bound=strtotime('+'.((int)$obj->nb_days_before_beginning+1).' days midnight');
			    }

    			if(GETPOST('_givemesolution')=='yes') {
    				echo 'start bound delai '.date('Y-m-d H:i:s', $t_start_bound).' '.$obj->nb_days_before_beginning.'<br>';
    			}
    			$TInfo[] = 'start bound delai '.date('Y-m-d', $t_start_bound);
			}

			if($t_start_bound>$t_start)$t_start = $t_start_bound;
		}
		}

		if($task->fk_task_parent>0) { // s'il y a une tâche parente
			if(isset($TCacheTask[$task->fk_task_parent])) $parent = $TCacheTask[$task->fk_task_parent];
			else {
				$parent = new Task($db);
				$parent->fetch($task->fk_task_parent);
				$TCacheTask[$task->fk_task_parent] = $parent;
			}

			if($parent->progress < 100) {

				$parent_duration = floor(($parent->date_end - $parent->date_start) / 86400 ) + 1;

				if($parent_duration>$duration) $t_start_bound = $parent->date_end - ($duration * 86400); // alors le début est soit la durée de la tâche en partant de la fin de la tâche parente
				else $t_start_bound= $parent->date_start; // où le début de la tâche parente

				$t_start_bound=strtotime('midnight',$t_start_bound);
				if($t_start_bound>$t_start) {
					$t_start = $t_start_bound;
					if(GETPOST('_givemesolution')=='yes') {
						echo 'start bound fk_task_parent ('.$parent->id.' / '.$parent->ref.') '.date('Y-m-d', $parent->date_start).' - '.date('Y-m-d', $parent->date_end).' --> '.date('Y-m-d', $t_start).'<br />';
					}

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

				if(!empty($tolerance) && $capacityLeft - $task->hour_needed < $tolerance) {
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
		global $conf,$db, $langs;

		$TInfo=array();
		$row = array('start'=>-1, 'duration'=>-1);

		$needed_ressource = empty($task->array_options['options_needed_ressource']) ? 1 : (int)$task->array_options['options_needed_ressource'];

		$task->hour_needed = $task->planned_workload * $needed_ressource* ((100 - $task->progress) / 100) / 3600 / $duration;
		$task->duration = $duration;

		if($task->hour_needed<=0) $row['note']=$langs->trans('NoHourPlanned');

if(GETPOST('_givemesolution')=='yes') {
	echo ' task : '.$task->id.'('.$task->ref.') '.$task->duration.' '.$task->hour_needed.'<br />';
}
		if($duration<50 && $task->hour_needed>0) {
			self::gb_search_set_bound($task, $t_start, $t_end, $TInfo);
if(GETPOST('_givemesolution')=='yes') {
echo 'Bounds '.date('Y-m-d H:i:s', $t_start).' --> '.date('Y-m-d H:i:s', $t_end).'<br />';
}
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


