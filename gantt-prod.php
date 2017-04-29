<?php

	require 'config.php';
	dol_include_once('/projet/class/project.class.php');
	dol_include_once('/projet/class/task.class.php');
	dol_include_once('/commande/class/commande.class.php');
	dol_include_once('/workstation/class/workstation.class.php');
	dol_include_once('/of/class/ordre_fabrication_asset.class.php');
	
	// Project -> Order -> OF -> Task
	//<script src="../../codebase/locale/locale_fr.js" charset="utf-8"></script>
	
	
	llxHeader('', $langs->trans('GanttProd') , '', '', 0, 0, array('/gantt/lib/dhx/codebase/dhtmlxgantt.js','/gantt/lib/dhx/codebase/locale/locale_fr.js'), array('/gantt/lib/dhx/codebase/dhtmlxgantt.css') );
	
	dol_include_once('/core/lib/project.lib.php');

	$langs->load("users");
	$langs->load("projects");
	
	$fk_project = (int)GETPOST('fk_project');
	
	if($fk_project>0) {
		
		$object = new Project($db);
		$object->fetch($fk_project);
		
		// Security check
		$socid=0;
		if ($user->societe_id > 0) $socid=$user->societe_id;
		$result = restrictedArea($user, 'projet', $id,'projet&project');
	
		$head=project_prepare_head($object);
		dol_fiche_head($head, 'anotherGantt', $langs->trans("Project"),0,($object->public?'projectpub':'project'));
		$linkback = '<a href="'.DOL_URL_ROOT.'/projet/list.php">'.$langs->trans("BackToList").'</a>';
		
		$morehtmlref='<div class="refidno">';
		// Title
		$morehtmlref.=$object->title;
		// Thirdparty
		if ($object->thirdparty->id > 0)
		{
			$morehtmlref.='<br>'.$langs->trans('ThirdParty') . ' : ' . $object->thirdparty->getNomUrl(1, 'project');
		}
		$morehtmlref.='</div>';
		
		// Define a complementary filter for search of next/prev ref.
		if (! $user->rights->projet->all->lire)
		{
			$objectsListId = $object->getProjectsAuthorizedForUser($user,0,0);
			$object->next_prev_filter=" rowid in (".(count($objectsListId)?join(',',array_keys($objectsListId)):'0').")";
		}
		
		dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);
		
	}
	else {
		
		dol_fiche_head();
		
	}
	
	
	$TElement = _get_task_for_of($fk_project);
//	pre($TElement[1]['orders'][1]['ofs'][1],1);
	?>
	
	<div id="gantt_here" style='width:100%; height:100%;'></div>
	<script type="text/javascript">
	function modSampleHeight(){

		var sch = document.getElementById("gantt_here");

		var h = parseInt(document.body.offsetHeight);
		if(h<500)h=500;
		
		sch.style.height = h+"px";
	
		gantt.setSizes();
	}
	var tasks = {
	    data:[
	
			<?php 
			$TData=array(); $TWS=array(); $TLink=array();
			
			$t_start  = $t_end = 0;
			foreach($TElement as &$projectData ) {
				$project = &$projectData['project'];
				
				$fk_parent_project = null;
				
				if($fk_project==0){
					$TData[] = ' {"id":"P'.$project->id.'", "text":"'.$project->title.'", "type":gantt.config.types.project, open: true}';
					$fk_parent_project= 'P'.$project->id;
				}
					
				foreach($projectData['orders'] as &$orderData) {
					$order = &$orderData['order'];
					
					$fk_parent_order = null;
					
					if($order->id >0 ){
						$TData[] = ' {"id":"O'.$order->id.'", "text":"'.$order->ref.'", "type":gantt.config.types.project'.(!is_null($fk_parent_project) ? ' ,parent:"'.$fk_parent_project.'" ' : '' ).', open: true}';
						$fk_parent_order = 'O'.$order->id;
					}
					
					foreach($orderData['ofs'] as &$ofData) {
						$of = $ofData['of'];	
						$fk_parent_of = null;
						
						if(!empty($conf->of->enabled) && $of->id>0) {
							$TData[] = ' {"id":"M'.$of->id.'", "text":"'.$of->numero.'", "type":gantt.config.types.project'.(!is_null($fk_parent_order) ? ' ,parent:"'.$fk_parent_order.'" ' : '' ).', open: true}';
							$fk_parent_of= 'M'.$of->id;
						}
						
						foreach($ofData['workstations'] as &$wsData) {
							
							$ws = $wsData['ws'];
							$TWS[$ws->id] = $ws;
							//$TData[] = ' {"id":"WS'.$ws->id.'", "text":"'.$ws->name.'", "type":gantt.config.types.project, parent:"M'.$of->id.'", open: true}';
							
							foreach($wsData['tasks'] as &$task) {

								if(empty($t_start) || $task->date_start<$t_start)$t_start=$task->date_start;
								if(empty($t_end) || $t_end<$task->date_end)$t_end=$task->date_end;
								
								$duration = $task->date_end>0 ? ceil( ($task->date_end - $task->date_start) / 86400 ) : ceil($task->planned_workload / (3600 * 7));
								
								$TData[] = ' {"id":"T'.$task->id.'", "text":"'.$task->label.'", "start_date":"'.date('d-m-Y',$task->date_start).'", "duration":"'.$duration.'", "order":"3"'.(!is_null($fk_parent_of) ? ' ,parent:"'.$fk_parent_of.'" ' : '' ).', progress: '.($task->progress / 100).', open: "true",owner:"'.$ws->id.'"}';
								
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

		var progress = task.progress;
		//var date_start = task.start_date.toISOString().substring(0,10);
		//var date_end = task.end_date.toISOString().substring(0,10);

		var start = task.start_date.getTime();
		var end = task.end_date.getTime();		

		$.ajax({
			url:"<?php echo dol_buildpath('/gantt/script/interface.php',1); ?>"
			,data:{
				ganttid:id
				,start:start
				,end:end
				,put:"gantt"
			}
			,method:"post"
		});
		
		return true;
	});
	
	gantt.init("gantt_here", new Date("<?php echo date('Y-m-d', $t_start) ?>"), new Date("<?php echo date('Y-m-d', $t_end+864000) ?>"));
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
	function _get_task_for_of($fk_project = 0) {
		
		global $db;
		
		
		$TCacheProject = $TCacheOrder  = $TCacheWS = array();
		
		$PDOdb=new TPDOdb;
		
		$sql = "SELECT t.rowid
		FROM ".MAIN_DB_PREFIX."projet_task t LEFT JOIN ".MAIN_DB_PREFIX."projet_task_extrafields tex ON (tex.fk_object=t.rowid)
			LEFT JOIN ".MAIN_DB_PREFIX."projet p ON (p.rowid=t.fk_projet)
		WHERE "; 
		
		if($fk_project>0) $sql.= " fk_projet=".$fk_project;
		else $sql.= "tex.fk_of IS NOT NULL AND tex.fk_of>0 AND t.progress<100
			AND p.fk_statut = 1
		";
		
		$res = $db->query($sql);
		
		if($res===false) {
			var_dump($db);exit;
		}
		
		$TTask=array();
		
		while($obj = $db->fetch_object($res)) {
			
			$task = new Task($db);
			$task->fetch($obj->rowid);
			$task->fetch_optionals($task->id);
			
			if($task->array_options['options_fk_of']>0) {
				
				$of=new TAssetOF();
				$of->load($PDOdb, $task->array_options['options_fk_of']);
			
			}
			else{
				
				$of=new stdClass;
				$of->id = 0;
				$of->numero = 'None';
				
			}
			
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