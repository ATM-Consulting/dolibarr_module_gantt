<?php

	require '../config.php';
	dol_include_once('/projet/class/project.class.php');
	dol_include_once('/projet/class/task.class.php');
	
	$get=GETPOST('get');
	$put = GETPOST('put');
	switch ($put) {
		
		case 'gantt':
			
			echo _put_gantt($_POST);
			
			break;
		
		case 'projects':
			
			_put_projects($_POST['TProject']);
			
			echo 1;
			
			break;
		
		
	}
	
	switch ($get) {
		
		case 'workstation-capacity':
			__out(_get_ws_capactiy(  GETPOST('wsid'),GETPOST('t_start'),GETPOST('t_end') ),'json' );
			
			break;
		
	}
	
	function _put_gantt($data) {
		global $db, $user;
		
		switch($data['ganttid'][0]) {
			case 'T':
				
				$o=new Task($db);
				$o->fetch(substr($data['ganttid'],1));
				$o->date_start = $data['start'] / 1000;
				$o->date_end = ($data['end'] / 1000) - 1; //Pour que cela soit à 23:59:59 de la vieille
				$o->progress = $data['progress'] * 100;
				
				return $o->update($user);
				
				break;
			
			
		}
		
	}
	
	function _get_ws_capactiy($wsid, $t_start, $t_end) {
		
		dol_include_once('/workstation/class/workstation.class.php');
		
		$PDOdb=new TPDOdb;
		
		$t_cur = $t_start ;
		
		$ws=new TWorkstation;
		$ws->load($PDOdb, $wsid);
		
		$Tab = array();
		while($t_cur<=$t_end) {
			$date = date('Y-m-d', $t_cur);
			$Tab[$date] = $ws->getCapacityLeft($PDOdb, $date);
			
			$t_cur = strtotime('+1day', $t_cur);
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