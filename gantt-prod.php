<?php

	require 'config.php';
	dol_include_once('/projet/class/project.class.php');
	dol_include_once('/projet/class/task.class.php');
	dol_include_once('/commande/class/commande.class.php');
	dol_include_once('/workstation/class/workstation.class.php');
	dol_include_once('/of/class/ordre_fabrication_asset.class.php');
	
	// Project -> Order -> OF -> Task
	//<script src="../../codebase/locale/locale_fr.js" charset="utf-8"></script>
	$row_height = 20;
	
	llxHeader('', $langs->trans('GanttProd') , '', '', 0, 0, array('/gantt/lib/dhx/codebase/dhtmlxgantt.js','/gantt/lib/dhx/codebase/ext/dhtmlxgantt_smart_rendering.js','/gantt/lib/dhx/codebase/ext/dhtmlxgantt_tooltip.js','/gantt/lib/dhx/codebase/locale/locale_fr.js'), array('/gantt/lib/dhx/codebase/dhtmlxgantt.css') );
	
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
	//pre($TElement,1);
	?>
	
	<div id="gantt_here" style='width:100%; height:100%;'></div>
	<style type="text/css">
	.gantt_task_line.gantt_milestone {
	    visibility: hidden;
	    background-color: #d33daf;
	    border: 0 solid #61164f;
	    box-sizing: content-box;
	    -moz-box-sizing: content-box;
	    height: 30px;
		line-height: 30px;
		width: 30px;
		font-size: 1px;
	    content:'';
	}
	
	.gantt_task_line.gantt_of {
	    background-color: #d3af3d;
	    border: 0 solid #d3af3d;
	    box-sizing: content-box;
	    -moz-box-sizing: content-box;
	    
	}
			
	.gantt_task_line.gantt_order {
	    background-color: #d3d13a;
	    border: 0 solid #d3d13a;
	    box-sizing: content-box;
	    -moz-box-sizing: content-box;
	    
	}
			
	#gantt_here div.gantt_task {
		overflow: scroll;
	}	
		
	</style>
	<script type="text/javascript">

	gantt.config.types.of = "of";
	gantt.locale.labels.type_of = "<?php echo $langs->trans('OF'); ?>";
	
	gantt.config.types.order = "order";
	gantt.locale.labels.type_order = "<?php echo $langs->trans('Order'); ?>";
	
	gantt.config.types.milestone = "milestone";
	gantt.locale.labels.type_milestone = "<?php echo $langs->trans('Release'); ?>";
	
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
				
				if(empty($fk_project)) {
					if($project->id>0){
						$TData[] = ' {"id":"P'.$project->id.'", "text":"P '.$project->ref.' '.$project->title.'", "type":gantt.config.types.project, open: '.(empty($fk_project) ? 'true': 'true').'}';
						$fk_parent_project= 'P'.$project->id;
					}
					else {
						$TData[] = ' {"id":"P0", "text":"'.$langs->trans('UndefinedProject').'", "type":gantt.config.types.project, open: false}';
						$fk_parent_project= 'P0';
					}
					
				}
					
				foreach($projectData['orders'] as &$orderData) {
					$order = &$orderData['order'];
					
					$fk_parent_order = null;
					
					if($order->id >0 ){
						$TData[] = ' {"id":"O'.$order->id.'", "text":"O '.$order->ref.'", "type":gantt.config.types.order'.(!is_null($fk_parent_project) ? ' ,parent:"'.$fk_parent_project.'" ' : '' ).', open: true}';
						$fk_parent_order = 'O'.$order->id;
					}
					else{
						$TData[] = ' {"id":"O0", "text":"'.$langs->trans('UndefinedOrder').'", "type":gantt.config.types.order'.(!is_null($fk_parent_project) ? ' ,parent:"'.$fk_parent_project.'" ' : '' ).', open: true}';
						$fk_parent_order = 'O0';
					}
					
					foreach($orderData['ofs'] as &$ofData) {
						$of = $ofData['of'];	
						$fk_parent_of = null;
						
						if(!empty($conf->of->enabled)) {
							if($of->id>0) {
								$TData[] = ' {"id":"M'.$of->id.'", "text":"OF '.$of->numero.'", "type":gantt.config.types.of'.(!is_null($fk_parent_order) ? ' ,parent:"'.$fk_parent_order.'" ' : '' ).', open: true}';
								$fk_parent_of= 'M'.$of->id;
							}
							else{
								$TData[] = ' {"id":"M0", "text":"'.$langs->trans('UndefinedMakingOrder').'", "type":gantt.config.types.of'.(!is_null($fk_parent_order) ? ' ,parent:"'.$fk_parent_order.'" ' : '' ).', open: true}';
								$fk_parent_of= 'M0';
							}
						}
						else{
							$fk_parent_of = $fk_parent_order;
						}
						
						foreach($ofData['workstations'] as &$wsData) {
							
							$ws = $wsData['ws'];
							if($ws->id>0) $TWS[$ws->id] = $ws;
							//$TData[] = ' {"id":"WS'.$ws->id.'", "text":"'.$ws->name.'", "type":gantt.config.types.project, parent:"M'.$of->id.'", open: true}';
							
							foreach($wsData['tasks'] as &$task) {

								if(empty($t_start) || $task->date_start<$t_start)$t_start=$task->date_start;
								if(empty($t_end) || $t_end<$task->date_end)$t_end=$task->date_end;
								
								$duration = $task->date_end>0 ? ceil( ($task->date_end - $task->date_start) / 86400 ) : ceil($task->planned_workload / (3600 * 7));
								if($duration<1)$duration = 1;
								
								if($task->planned_workload == 0) { // c'est un milestone
									$TData[] = ' {"id":"T'.$task->id.'", "text":"'.$task->label.'", "start_date":"'.date('d-m-Y',$task->date_start).'", type:gantt.config.types.milestone '.(!is_null($fk_parent_of) ? ' ,parent:"'.$fk_parent_of.'" ' : '' ).',owner:"'.$ws->id.'"}';
									
								}
								else {
									$TData[] = ' {"id":"T'.$task->id.'", "text":"T '.$task->label.' '.dol_print_date($task->planned_workload,'hour').'", "start_date":"'.date('d-m-Y',$task->date_start).'", "duration":"'.$duration.'"'.(!is_null($fk_parent_of) ? ' ,parent:"'.$fk_parent_of.'" ' : '' ).', progress: '.($task->progress / 100).',owner:"'.$ws->id.'", type:gantt.config.types.task}';
								}
								
								if($task->fk_task_parent>0) {
									$TLink[] = ' {id:'.(count($TLink)+1).', source:"T'.$task->fk_task_parent.'", target:"T'.$task->id.'", type:"0"}';
								}
								
							}
							
						}
						
					}
					
				}
				
			}
			echo implode(',',$TData);
			
			$t_end = $t_end+864000; // on ajoute 10jours de rab
			
			?>
		       
	    ],
	    links:[
	       <?php echo implode(',',$TLink); ?>
	    ]
	};

	gantt.templates.task = function(obj1){
		console.log(obj1);
	}
		
	gantt.templates.task_class = function(start, end, obj){


		if(obj.type == gantt.config.types.of){
			return "gantt_of";
		}
		if(obj.type == gantt.config.types.order){
			return "gantt_order";
		}
		else if(obj.type == gantt.config.types.milestone){
			return "gantt_milestone";
		}
		else if(obj.owner) {
			return "workstation_"+obj.owner;
		}

		return '';
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
	
	gantt.templates.task_text = function(start, end, task){
		if(task.type == gantt.config.types.milestone){
			return "";
		}
		return task.text;
	}
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
	    }, align: "center", width:70 },
	    /*{name:"progress",   label:"<?php echo $langs->transnoentities('Progression') ?>",  template:function(obj){
			return obj.progress ? Math.round(obj.progress*100)+"%" : "";
	    }, align: "center", width:60 },*/
	    {name:"duration",   label:"<?php echo $langs->transnoentities('Duration') ?>", align:"center", width:60},
	    
	    {name:"add",        label:"",           width:44 }
	];

	gantt.config.grid_width = 390;
	gantt.config.date_grid = "%F %d"

	gantt.config.scale_height  = 40;
	gantt.config.row_height = <?php echo $row_height; ?>;
	
	<?php
	
	if(GETPOST('scale')=='week') {
		echo 'gantt.config.scale_unit = "week"; gantt.config.date_scale = "'.$langs->trans('WeekShort').' %W";';	
	}
	else {
		echo 'gantt.config.subscales = [
				{ unit:"week", step:1, date:"'.$langs->transnoentities('Week').' %W"}
			];
		';	
	}
	
	?>

	gantt.templates.tooltip_text = function(start,end,task){

		if(task.text) {
	    	return "<strong>"+task.text+"</strong><br/><?php echo $langs->trans('Duration') ?> " + task.duration + " <?php echo $langs->trans('days') ?>";
		}
		else{
			return '';
		}
	};
	
	gantt.attachEvent("onBeforeLinkAdd", function(id,link){
		
		return false; // on empêche d'ajouter du lien
		
	});

	gantt.attachEvent("onLinkDblClick", function(id){
		return false;
	});

	gantt.attachEvent("onTaskOpened", function(id){
		updateAllCapacity();
	});
	gantt.attachEvent("onTaskClosed", function(id){
		updateAllCapacity();
	});
	gantt.attachEvent("onGanttScroll", function (left, top) {
//		updateAllCapacity();
console.log($("div.gantt_task_line[task_id^=T]"));
		$("div.gantt_task_line[task_id^=T]").each(function(i,item) {
			
		});

	});

	
	gantt.attachEvent("onAfterTaskAdd", function(id,task){
		//console.log('createTask',id, task);return 0;
		var start = task.start_date.getTime();
		var end = task.end_date.getTime();		
		$.ajax({
			url:"<?php echo dol_buildpath('/gantt/script/interface.php',1); ?>"
			,data:{
				ganttid:id
				,start:start
				,end:end
				,label:task.text
				,duration:task.duration
				,progress:0
				,put:"task"
			}
			,method:"post"
		}).done( function(newid) {
			gantt.changeTaskId(id, newid); 
			updateAllCapacity();
		});
	});
	
	gantt.attachEvent("onTaskDblClick", function(id){

		if(id[0] == 'T') {
			pop_edit_task(id.substring(1));
			//document.location.href="<?php echo dol_buildpath('/projet/tasks/task.php',1) ?>?id="+id.substring(1)+"&withproject=1";
		}
		else {
			return false;
		}
	});
	gantt.attachEvent("onBeforeTaskChanged", function(id, mode, old_event){
		var task = gantt.getTask(id);

		var progress = task.progress ;
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
				,progress:progress
				,put:"gantt"
			}
			,method:"post"
		}).done( function() {
			gantt.refreshTask(id);
			updateAllCapacity();
		});

		if(start>old_event.start_date.getTime()) start = old_event.start_date.getTime();
		if(end<old_event.end_date.getTime()) start = old_event.end_date.getTime();
			
		
		return true;
	});

	gantt.config.autoscroll = true;
	//gantt.config.autosize = "x";
	
	gantt.init("gantt_here", new Date("<?php echo date('Y-m-d', $t_start) ?>"), new Date("<?php echo date('Y-m-d', $t_end) ?>"));
	modSampleHeight();
	gantt.parse(tasks);

//TODO add scrollbar locked at top or bottom
	
/*	(function() {
	function scrollHorizontally(e) {
	    e = window.event || e;

	    var delta = Math.max(-1, Math.min(1, (e.wheelDelta || -e.detail)));
	
	    var sPos = gantt.getScrollState(); 
	    
	    gantt.scrollTo(sPos.x - delta*40, null);
	    
	    e.preventDefault();
	}
	if (window.addEventListener) {
	    // IE9, Chrome, Safari, Opera
	    window.addEventListener("mousewheel", scrollHorizontally, false);
	    // Firefox
	    window.addEventListener("DOMMouseScroll", scrollHorizontally, false);
	} else {
	    // IE 6/7/8
	    window.attachEvent("onmousewheel", scrollHorizontally);
	}
	})();
*/

	/*$divScroll = $('<div />');
	$divScroll.width(  );

	$("#gantt_here").before($divScroll);
	*/
	var url_in_pop = '';var pop_callback = null;
	function pop_edit_task(fk_task, callback) {

		pop_callback = callback;
		
		if($('#dialog-edit-task').length==0) {
			$('body').append('<div id="dialog-edit-task"></div>');
		}
		var url_in_pop ="<?php echo  dol_buildpath('/projet/tasks/task.php?action=edit&id=',1) ?>"+fk_task
			
		$('#dialog-edit-task').load(url_in_pop+" div.fiche form",pop_event);
		
	}

	function pop_event(callback) {

		$('#dialog-edit-task a').click(function(){
			url_in_pop = $(this).attr('href');
			$('#dialog-edit-task').load(url_in_pop+" div.fiche", pop_event);
			
			return false;
			
		});
		
		$('#dialog-edit-task input[name=cancel]').remove();
		$('#dialog-edit-task form').unbind().submit(function() {

			$.post($(this).attr('action'), $(this).serialize(), function() {
				
				if(pop_callback) {
					eval(pop_callback);
				}
				
			//	$('#dialog-edit-task').load(url_in_pop+" div.fiche", pop_event);
				
								
			});
		
			$('#dialog-edit-task').dialog('close');			
			
			return false;
	
			
		});
		
		$(this).dialog({
			title: "<?php echo $langs->trans('EditTask') ?>"
			,width:"80%"
			,modal:true
		});
		
	}
	
	function updateWSCapacity(wsid, t_start, t_end, nb_hour_capacity) {


		$.ajax({
			url:"<?php echo dol_buildpath('/gantt/script/interface.php',1) ?>"
			,data:{
				get:"workstation-capacity"
				,t_start:t_start
				,t_end:t_end
				,wsid:wsid
			}
		,dataType:"json"
		}).done(function(data) {
//console.log(nb_hour_capacity, data);
			for(d in data) {
				c = data[d];

				var p; 
				var bg = '#fff';
				
				if(c == 0) { p='N/A'; bg='#000'; }
				else {
					//p = Math.round(((nb_hour_capacity - c) / nb_hour_capacity)*100);
					p = Math.round(c * 10) / 10;

					if(p<0) bg='#ff0000';
					else if(p<=nb_hour_capacity/10) bg='#ffa500';
					else if(p>nb_hour_capacity/2) bg='#7cec43';
					
					//p+='%';
				}
				
				$('div.gantt_bars_area div#workstations_'+wsid+' div[date='+d+']').html(p).css({'background-color':bg});
				
			}

		});
	}
	
	<?php 
	if($fk_project == 0) {
		
		?>
		var w_workstation = $('div.gantt_bars_area').width();
		var w_workstation_title = $('div.gantt_grid_data').width();
		var w_cell = $('div.gantt_task_bg div.gantt_task_cell').first().outerWidth();

		$('style[rel=drawLine]').remove();
		        
        var html_style = '<style rel="drawLine" type="text/css">'
                           +' div.gantt_bars_area div.workstation div.gantt_task_cell { width:'+w_cell+'px; text-align:center; } '
                           +' div.gantt_bars_area div.workstation { height:20px; font-size:10px; } '
                         +'</style>';
                                                  
        $(html_style).appendTo("head");
		
		<?php 
		
		$cells = '';
		$t_cur = $t_start;
		while($t_cur<=$t_end) {
			$cells.='<div class="gantt_task_cell" date="'.date('Y-m-d', $t_cur).'">N/A</div>';
			$t_cur = strtotime('+1day',$t_cur);
		}
		
		echo 'function updateAllCapacity() { ';

		foreach($TWS as &$ws) {

			?>
			if($("div#workstations_<?php echo $ws->id; ?>.gantt_row").length == 0 ) {
			
				$('div.gantt_grid_data').append('<div class="gantt_row workstation_<?php echo $ws->id; ?>" style="text-align:right; width:'+w_workstation_title+'px;height:20px;padding-right:5px;"><?php echo $ws->name . ' ('.$ws->nb_hour_capacity.'h - '.$ws->nb_ressource.')'; ?></div>');
				$('div.gantt_bars_area').append('<div class="workstation gantt_row" id="workstations_<?php echo $ws->id ?>" style="width:'+w_workstation+'px;"><?php echo $cells; ?></div>');

			}
			
			updateWSCapacity(<?php echo $ws->id ?>, <?php echo (int)$t_start ?>, <?php echo (int)$t_end?>,<?php echo (double)$ws->nb_hour_capacity; ?>);
			<?php 	
			
		}

		echo '$(".gantt_task_line.gantt_milestone").css({
			width:"'.$row_height.'px"
			,height:"'.$row_height.'px"
		});';
		
		echo ' }

		updateAllCapacity(); ';
		
		
	}
	
	?>

	</script>
	
	
	<style type="text/css" media="screen">
		.weekend{ background: #f4f7f4 !important;}
		.gantt_selected .weekend{
			background:#FFF3A1 !important;
		}
		
		/*.gantt_dependent_task .gantt_task_content {
			background:#006600 ;
		}*/
		
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
		
		$projet_previ=new Project($db);
		$projet_previ->fetch(0,'PREVI');
		$fk_projet_previ = $projet_previ->id;
		
		$TCacheProject = $TCacheOrder  = $TCacheWS = array();
		
		$PDOdb=new TPDOdb;
		
		$sql = "SELECT t.rowid
		FROM ".MAIN_DB_PREFIX."projet_task t LEFT JOIN ".MAIN_DB_PREFIX."projet_task_extrafields tex ON (tex.fk_object=t.rowid)
			LEFT JOIN ".MAIN_DB_PREFIX."projet p ON (p.rowid=t.fk_projet)
		WHERE "; 
		
		if($fk_project>0) $sql.= " fk_projet=".$fk_project;
		else $sql.= "tex.fk_of IS NOT NULL AND tex.fk_of>0 AND (t.progress<100 OR t.progress IS NULL)
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
			$task->fetch_optionals($gantt_milestonetask->id);
			
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
					$TCacheOrder[(int)$order->id] = $order;
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
			
			$order->id=(int)$order->id;
			
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
