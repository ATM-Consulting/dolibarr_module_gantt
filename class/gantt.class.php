<?php

class GanttPatern {

	static function get_ws_capacity($wsid, $t_start, $t_end, $fk_task = 0) {

		global $conf;

		if(empty($conf->workstation->enabled)) return array();

		dol_include_once('/workstation/class/workstation.class.php');

		$PDOdb=new TPDOdb;

		$ws=new TWorkstation;
		$ws->load($PDOdb, $wsid);

		$Tab = $ws->getCapacityLeftRange($PDOdb, $t_start, $t_end,array($fk_task));

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

	static function gb_search(&$TDates, &$task, $t_start, $t_end, $duration = 1) {

		$row = array('start'=>-1, 'duration'=>$duration);

		$hour_needed = $task->planned_workload * ((100 - $task->progress) / 100) / 3600 / $duration;

		if($duration<10 || $hour_needed<=0) {
			$find = false;
			foreach($TDates as $date=>&$data) {
			//	var_dump(array($date,$task->id,  $data['capacityLeft'],$hour_needed));
				if($data['capacityLeft'] - $hour_needed >0) {

					$data['capacityLeft'] -= $hour_needed;

					$find = true;

					$row['start'] = strtotime($date);

					break;
				}

			}

			if(!$find) $row = self::gb_search($TDates, $task, $t_start, $t_end, $duration + 1);

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
		global $db;

		if(!is_array($TTaskId))$TTaskId=array($TTaskId);

		if($t_start < time())$t_start = time();

		$TWS = $Tab = array();

		if(!empty($TTaskId)) {

			foreach($TTaskId as $fk_task) {

				$task = new Task($db);
				$task->fetch($fk_task);

				$Tab[$task->id] = self::get_better_task($TWS, $task, $t_start, $t_end);

			}

		}

		return $Tab;

	}




}