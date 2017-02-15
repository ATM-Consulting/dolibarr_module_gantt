<?php

	require '../config.php';
	dol_include_once('/projet/class/project.class.php');
	dol_include_once('/projet/class/task.class.php');
	
	
	$put = GETPOST('put');
	switch ($put) {
		
		case 'projects':
			
			_put_projects($_POST['TProject']);
			
			echo 1;
			
			break;
		
		
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