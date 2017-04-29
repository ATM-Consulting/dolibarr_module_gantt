<?php

	require 'config.php';
	dol_include_once('/projet/class/project.class.php');
	dol_include_once('/projet/class/task.class.php');
	dol_include_once('/commande/class/commande.class.php');
	dol_include_once('/workstation/class/workstation.class.php');
	dol_include_once('/of/class/ordre_fabrication_asset.class.php');
	
	// Project -> Order -> OF -> Task
	
	llxHeader('', $langs->trans('GanttProd') , '', '', 0, 0, array('/gantt/lib/dhx/codebase/dhtmlxgantt.js'), array('/gantt/lib/dhx/codebase/dhtmlxgantt.css') );
	dol_fiche_head();
	
	$TElement = _get_task_for_of();
//	pre($TElement[1]['orders'][1]['ofs'][1],1);
	?>
	
	<div id="gantt_here" style='width:100%; height:100%;'></div>
	<script type="text/javascript">
	function modSampleHeight(){
		var headHeight = 100;
		var sch = document.getElementById("gantt_here");
		sch.style.height = (parseInt(document.body.offsetHeight)-headHeight)+"px";
	
		gantt.setSizes();
	}
	var tasks = {
	    data:[
	
			<?php 
			//$TTask[$project->id]['orders'][$order->id]['ofs'][$of->id]['ws'][$ws->id]['tasks'][$task->id] = $task;
			
			/*
			 * 
			 {"id":1, "text":"Office itinerancy", "type":gantt.config.types.project, "order":"10", progress: 0.4, open: false},

		{"id":2, "text":"Office facing", "type":gantt.config.types.project, "start_date":"02-04-2013", "duration":"8", "order":"10", progress: 0.6, "parent":"1", open: true},
		{"id":3, "text":"Furniture installation", "type":gantt.config.types.project, "start_date":"11-04-2013", "duration":"8", "order":"20", "parent":"1", progress: 0.6, open: true},
		{"id":4, "text":"The employee relocation", "type":gantt.config.types.project, "start_date":"13-04-2013", "duration":"6", "order":"30", "parent":"1", progress: 0.5, open: true},

        {"id":5, "text":"Interior office", "start_date":"02-04-2013", "duration":"7", "order":"3", "parent":"2", progress: 0.6, open: true},
        {"id":6, "text":"Air conditioners check", "start_date":"03-04-2013", "duration":"7", "order":"3", "parent":"2", progress: 0.6, open: true},
        {"id":7, "text":"Workplaces preparation", "start_date":"11-04-2013", "duration":"8", "order":"3", "parent":"3", progress: 0.6, open: true},
        {"id":8, "text":"Preparing workplaces", "start_date":"14-04-2013", "duration":"5", "order":"3", "parent":"4", progress: 0.5, open: true},
        {"id":9, "text":"Workplaces importation", "start_date":"14-04-2013", "duration":"4", "order":"3", "parent":"4", progress: 0.5, open: true},
        {"id":10, "text":"Workplaces exportation", "start_date":"14-04-2013", "duration":"3", "order":"3", "parent":"4", progress: 0.5, open: true},

        {"id":11, "text":"Product launch", "type":gantt.config.types.project, "order":"5", progress: 0.6, open: true},

        {"id":12, "text":"Perform Initial testing", "start_date":"03-04-2013", "duration":"5", "order":"3", "parent":"11", progress: 1, open: true},
        {"id":13, "text":"Development", "type":gantt.config.types.project, "start_date":"02-04-2013", "duration":"7", "order":"3", "parent":"11", progress: 0.5, open: true},
        {"id":14, "text":"Analysis", "start_date":"02-04-2013", "duration":"6", "order":"3", "parent":"11", progress: 0.8, open: true},
        {"id":15, "text":"Design", "type":gantt.config.types.project, "start_date":"02-04-2013", "duration":"5", "order":"3", "parent":"11", progress: 0.2, open: false},
        {"id":16, "text":"Documentation creation", "start_date":"02-04-2013", "duration":"7", "order":"3", "parent":"11", progress: 0, open: true},

        {"id":17, "text":"Develop System", "start_date":"03-04-2013", "duration":"2", "order":"3", "parent":"13", progress: 1, open: true},

		{"id":25, "text":"Beta Release", "start_date":"06-04-2013", "order":"3","type":gantt.config.types.milestone, "parent":"13", progress: 0, open: true},

        {"id":18, "text":"Integrate System", "start_date":"08-04-2013", "duration":"2", "order":"3", "parent":"13", progress: 0.8, open: true},
        {"id":19, "text":"Test", "start_date":"10-04-2013", "duration":"4", "order":"3", "parent":"13", progress: 0.2, open: true},
        {"id":20, "text":"Marketing", "start_date":"10-04-2013", "duration":"4", "order":"3", "parent":"13", progress: 0, open: true},

        {"id":21, "text":"Design database", "start_date":"03-04-2013", "duration":"4", "order":"3", "parent":"15", progress: 0.5, open: true},
        {"id":22, "text":"Software design", "start_date":"03-04-2013", "duration":"4", "order":"3", "parent":"15", progress: 0.1, open: true},
        {"id":23, "text":"Interface setup", "start_date":"03-04-2013", "duration":"5", "order":"3", "parent":"15", progress: 0, open: true},
        {"id":24, "text":"Release v1.0", "start_date":"15-04-2013", "order":"3","type":gantt.config.types.milestone, "parent":"11", progress: 0, open: true}
			 * 
			 */
			$TData=array(); $TWS=array(); $TLink=array();
			foreach($TElement as &$projectData ) {
				$project = &$projectData['project'];
				
				$TData[] = ' {"id":"P'.$project->id.'", "text":"'.$project->title.'", "type":gantt.config.types.project, open: true}';
				
				foreach($projectData['orders'] as &$orderData) {
					$order = &$orderData['order'];
					$TData[] = ' {"id":"O'.$order->id.'", "text":"'.$order->ref.'", "type":gantt.config.types.project, parent:"P'.$project->id.'", open: true}';
					
					foreach($orderData['ofs'] as &$ofData) {
						$of = $ofData['of'];	
						$TData[] = ' {"id":"OF'.$of->id.'", "text":"'.$of->numero.'", "type":gantt.config.types.project, parent:"O'.$order->id.'", open: true}';
						
						foreach($ofData['workstations'] as &$wsData) {
							
							$ws = $wsData['ws'];
							$TWS[$ws->id] = $ws;
							//$TData[] = ' {"id":"WS'.$ws->id.'", "text":"'.$ws->name.'", "type":gantt.config.types.project, parent:"OF'.$of->id.'", open: true}';
							
							foreach($wsData['tasks'] as &$task) {
							//	var_dump(ceil($task->planned_duration / (3600 * 7)),$task);
								$TData[] = ' {"id":"T'.$task->id.'", "text":"'.$task->label.'", "start_date":"'.date('d-m-Y',$task->date_start).'", "duration":"'.ceil($task->planned_workload / (3600 * 7)).'", "order":"3", "parent":"OF'.$of->id.'", progress: '.($task->progress / 100).', open: "true",owner:"'.$ws->id.'"}';
								
								if($task->fk_task_parent>0) {
									$TLink[] = ' {id:'.(count($TLink)+1).', source:"T'.$task->fk_task_parent.'", target:"T'.$task->id.'", type:"0"}';
								}
								
							}
							
						}
						
					}
					
				}
				
			}
			
			echo implode(',',$TData);
			
			?>
		       
	    ],
	    links:[
	       <?php echo implode(',',$TLink); ?>
	    ]
	};

	gantt.templates.task_class = function(start, end, obj){
		return "workstation_"+obj.owner;
	}
	
	gantt.templates.scale_cell_class = function(date){
	    if(date.getDay()==0||date.getDay()==6){
	        return "weekend";
	    }
	};
	gantt.templates.task_cell_class = function(item,date){
	    if(date.getDay()==0||date.getDay()==6){ 
	        return "weekend" ;
	    }
	};

	gantt.templates.rightside_text = function(start, end, task){
		if(task.type == gantt.config.types.milestone){
			return task.text;
		}
		return "";
	}

	gantt.config.columns = [
	    {name:"text",       label:"<?php echo $langs->transnoentities('Label') ?>",  width:"*", tree:true },
	    {name:"start_time",   label:"<?php echo $langs->transnoentities('DateStart') ?>",  template:function(obj){
			return gantt.templates.date_grid(obj.start_date);
	    }, align: "center", width:60 },
	    /*{name:"progress",   label:"<?php echo $langs->transnoentities('Progression') ?>",  template:function(obj){
			return obj.progress ? Math.round(obj.progress*100)+"%" : "";
	    }, align: "center", width:60 },*/
	    {name:"duration",   label:"<?php echo $langs->transnoentities('Duration') ?>", align:"center", width:60},
	    
	    /*{name:"add",        label:"",           width:44 }*/
	];

	gantt.config.grid_width = 390;
	gantt.config.date_grid = "%F %d"

	gantt.config.scale_height  = 40;
	gantt.config.subscales = [
		{ unit:"week", step:1, date:"<?php echo $langs->transnoentities('Week') ?> #%W"}
	];

	gantt.attachEvent("onBeforeLinkAdd", function(id,link){
		
		return false; // on empêche d'ajouter du lien
		
	});

	gantt.attachEvent("onLinkDblClick", function(id){
		return false;
	});

	gantt.attachEvent("onTaskDblClick", function(id){

		if(id[0] == 'T') {
		
			document.location.href="<?php echo dol_buildpath('/projet/tasks/task.php',1) ?>?id="+id.substring(1)+"&withproject=1";
		}
		else {
			return false;
		}
	});
	gantt.attachEvent("onBeforeTaskChanged", function(id, mode, old_event){
		var task = gantt.getTask(id);
		/*if(mode == gantt.config.drag_mode.progress){
			if(task.progress < old_event.progress){
				gantt.message(task.text + " progress can't be undone!");
				return false;
			}
		}*/

console.log(task);
		
		return true;
	});
	
	gantt.init("gantt_here");
	modSampleHeight();
	gantt.parse(tasks);
	</script>
	
	
	<style type="text/css" media="screen">
		.weekend{ background: #f4f7f4 !important;}
		.gantt_selected .weekend{
			background:#FFF3A1 !important;
		}
		
		.gantt_dependent_task .gantt_task_content {
			background:#006600 ;
		}
		
		<?php 
		foreach($TWS as &$ws) {
			
			$color = $ws->background;
			if(strlen($color) == 7) {
				
				$darkest = adjustBrightness($color, -30);
				$border= adjustBrightness($color, -50);
				
				echo '.workstation_'.$ws->id.'{
					background:'.$color.';
					border-color:'.$darkest.';
				}
				.workstation_'.$ws->id.' .gantt_task_progress{
					background:'.$border.';
				}';
			}
		}
		
		?>
		
		
	</style>
	<?php 
	
	dol_fiche_end();
	llxFooter();
	
	//pre($TTask,1);
	function adjustBrightness($hex, $steps) {
		// Steps should be between -255 and 255. Negative = darker, positive = lighter
		$steps = max(-255, min(255, $steps));
		
		// Normalize into a six character long hex string
		$hex = str_replace('#', '', $hex);
		if (strlen($hex) == 3) {
			$hex = str_repeat(substr($hex,0,1), 2).str_repeat(substr($hex,1,1), 2).str_repeat(substr($hex,2,1), 2);
		}
		
		// Split into three parts: R, G and B
		$color_parts = str_split($hex, 2);
		$return = '#';
		
		foreach ($color_parts as $color) {
			$color   = hexdec($color); // Convert to decimal
			$color   = max(0,min(255,$color + $steps)); // Adjust color
			$return .= str_pad(dechex($color), 2, '0', STR_PAD_LEFT); // Make two char hex code
		}
		
		return $return;
	}
	function _get_task_for_of() {
		
		global $db;
		
		
		$TCacheProject = $TCacheOrder  = $TCacheWS = array();
		
		$PDOdb=new TPDOdb;
		
		$res = $db->query("SELECT t.rowid 
		FROM ".MAIN_DB_PREFIX."projet_task t LEFT JOIN ".MAIN_DB_PREFIX."projet_task_extrafields tex ON (tex.fk_object=t.rowid)
			LEFT JOIN ".MAIN_DB_PREFIX."projet p ON (p.rowid=t.fk_projet)
		WHERE tex.fk_of IS NOT NULL AND t.progress<100
			AND p.fk_statut = 1
		");
		
		if($res===false) {
			var_dump($db);exit;
		}
		
		$TTask=array();
		
		while($obj = $db->fetch_object($res)) {
			
			$task = new Task($db);
			$task->fetch($obj->rowid);
			$task->fetch_optionals($task->id);
			
			$of=new TAssetOF();
			$of->load($PDOdb, $task->array_options['options_fk_of']);
			
			if($of->fk_commande>0) {
				
				if(!empty($TCacheOrder[$of->fk_commande])) {
					
					$order=$TCacheOrder[$of->fk_commande];
					
				}
				else{
					$order=new Commande($db);
					$order->fetch($of->fk_commande);
					$TCacheOrder[$order->id] = $order;
				}
				
				
			}
			else {
				
				$order = new Commande($db);
				
			}
			
			if(!empty($TCacheProject[$task->fk_project])) {
				
				$project=$TCacheProject[$task->fk_project];
				
			}
			else{
				$project = new Project($db);
				$project->fetch($task->fk_project);
				$TCacheProject[$project->id] = $project;
				
			}
			
			if(!empty($TCacheWS[$task->array_options['options_fk_workstation']])) {
				
				$ws=$TCacheWS[$task->array_options['options_fk_workstation']];
				
			}
			else{
				$ws = new TWorkstation();
				$ws->load($PDOdb,$task->array_options['options_fk_workstation']);
				$TCacheWS[$ws->id] = $ws;
				
			}
			
			
			if(empty($TTask[$project->id])) {
				
				$TTask[$project->id]=array(
						'orders'=>array()
						,'project'=>$project
				);
				
			}
			
			if(empty($TTask[$project->id]['orders'][$order->id])) {
				
				$TTask[$project->id]['orders'][$order->id]=array(
					'ofs'=>array()	
					,'order'=>$order	
				);
			}
			
			if(empty($TTask[$project->id]['orders'][$order->id]['ofs'][$of->id])) {
				
				$TTask[$project->id]['orders'][$order->id]['ofs'][$of->id]=array(
						'workstations'=>array()
						,'of'=>$of
				);
			}
			
			if(empty($TTask[$project->id]['orders'][$order->id]['ofs'][$of->id]['workstations'][$ws->id])) {
				
				$TTask[$project->id]['orders'][$order->id]['ofs'][$of->id]['workstations'][$ws->id]=array(
						'tasks'=>array()
						,'ws'=>$ws
				);
			}
			
			$TTask[$project->id]['orders'][$order->id]['ofs'][$of->id]['workstations'][$ws->id]['tasks'][$task->id] = $task;
			
		}
		
		return $TTask;
		
	}