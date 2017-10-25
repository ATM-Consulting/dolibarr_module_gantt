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
		_getCapacityLeftRangeAgenda($PDOdb,$ws,$Tab,$t_start, $t_end);

		return $Tab;

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

	static function get_better(&$TTaskId, $t_start, $t_end) {
		global $db;

		if($t_start < time())$t_start = time();

		$TWS = $Tab = array();

		if(!empty($TTaskId)) {

			foreach($TTaskId as $fk_task) {

				$task = new Task($db);
				$task->fetch($fk_task);

				$fk_workstation = (int)$task->array_options['options_fk_workstation'];

				if($fk_workstation>0 && $task->progress < 100) {
					if(empty($TWS[$fk_workstation])) $TWS[$fk_workstation] = self::get_ws_capacity($fk_workstation, $t_start, $t_end, $task->id);
					$Tab[$task->id] = self::gb_search($TWS[$fk_workstation], $task, $t_start, $t_end);

				}
				else {
					$Tab[$task->id] = array('start'=>-1, 'duration'=>-1, 'note'=>'NoWorkstation');
				}




			}

		}

		return $Tab;

	}




}