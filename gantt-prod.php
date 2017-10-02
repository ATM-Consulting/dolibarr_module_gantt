<?php

require 'config.php';

set_time_limit(0);

dol_include_once('/projet/class/project.class.php');
dol_include_once('/projet/class/task.class.php');
dol_include_once('/commande/class/commande.class.php');
dol_include_once('/workstation/class/workstation.class.php');
dol_include_once('/of/class/ordre_fabrication_asset.class.php');

// Project -> Order -> OF -> Task
//<script src="../../codebase/locale/locale_fr.js" charset="utf-8"></script>
$row_height = 20;

llxHeader('', $langs->trans('GanttProd') , '', '', 0, 0, array(
		'/gantt/lib/dhx/codebase/dhtmlxgantt.js',
		'/gantt/lib/dhx/codebase/ext/dhtmlxgantt_smart_rendering.js',
		'/gantt/lib/dhx/codebase/ext/dhtmlxgantt_quick_info.js', // display info popin on click event
		'/gantt/lib/dhx/codebase/ext/dhtmlxgantt_tooltip.js',
		'/gantt/lib/dhx/codebase/locale/locale_fr.js'),
		array('/gantt/lib/dhx/codebase/dhtmlxgantt.css') );

dol_include_once('/core/lib/project.lib.php');
dol_include_once('/gantt/class/color_tools.class.php');

$langs->load("users");
$langs->load("projects");
$langs->load("gantt@gantt");

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

	#gantt_here {
		margin-bottom:500px;
	}

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

	div.ws_container,div.ws_container_label {
		position: fixed;
		bottom:0px;
		overflow: hidden;
	}
	div.ws_container {
		overflow: scroll;
	}
	div.ws_container div.gantt_task_cell {
		width:70px;
		text-align:center;
	}

    .gantt_task_progress{
    	text-align:left;
    	padding-left:10px;
        box-sizing: border-box;
    	color:white;
    	font-weight: bold;
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
		if(h<2000)h=2000;

		sch.style.height = h+"px";

		gantt.setSizes();
	}
	var tasks = {
	    data:[
			<?php
			$TData=array(); $TWS=array(); $TLink=array();

			$close_init_status = empty($fk_project) ? 'false': 'true';

			$t_start  = $t_end = 0;
			foreach($TElement as &$projectData ) {
				
			    if(!empty($projectData['project'])) {
    			    $project = &$projectData['project'];
    
    				$fk_parent_project = null;
    
    				if(empty($fk_project)) {
    					
    					$taskColor='';
    					$projectColor='';
    					if(ColorTools::validate_color($project->array_options['options_gantt_color']))
    					{
    						$taskColor= ColorTools::adjustBrightness($project->array_options['options_gantt_color'], -50);
    						$projectColor= ',color:"'.$project->array_options['options_gantt_color'].'"';
    					}
    					
    					$TData[] = ' {"id":"'.$project->ganttid.'", "text":"'.$project->title.'", "type":gantt.config.types.project, open: '.$close_init_status.$projectColor.'}';
    					$fk_parent_project= $project->ganttid;
    					
    					_format_task_for_gantt($projectData['tasks'], $TData,$TLink,$project->rowid,$t_start,$t_end, $taskColor);
    					
    				}
    				
    				
    
    				if(!empty($projectData['orders'])) {
        				foreach($projectData['orders'] as &$orderData) {
        					$order = &$orderData['order'];
        
        					$fk_parent_order = null;
        
        					$TData[] = ' {"id":"'.$order->ganttid.'", "text":"'.$order->title.'", "type":gantt.config.types.order'.(!is_null($fk_parent_project) ? ' ,parent:"'.$fk_parent_project.'" ' : '' ).', open: '.$close_init_status.'}';
        					$fk_parent_order = $order->ganttid;
        
        					// Add order child tasks
        					$taskColor='';
        					_format_task_for_gantt($orderData['tasks'], $TData,$TLink,$order->rowid,$t_start,$t_end, $taskColor);
        					
        					
        					
        					if(!empty($orderData['ofs'])) {
        					    
            					foreach($orderData['ofs'] as &$ofData) {
            						$of = $ofData['of'];
            						$fk_parent_of = null;
            
            						if(!empty($conf->of->enabled)) {
            							$TData[] = ' {"id":"'.$of->ganttid.'", "text":"'.$of->title.'", "type":gantt.config.types.of'.(!is_null($fk_parent_order) ? ' ,parent:"'.$fk_parent_order.'" ' : '' ).', open: '.$close_init_status.'}';
            							$fk_parent_of= $of->ganttid;
            						}
            						else{
            							$fk_parent_of = $fk_parent_order;
            						}
            						
            						// Add order child tasks
            						$taskColor='';
            						_format_task_for_gantt($ofData['tasks'], $TData,$TLink,$of->rowid,$t_start,$t_end, $taskColor);
            						
            						
            						if(!empty($ofData['workstations'])) {
                						foreach($ofData['workstations'] as &$wsData) {
                
                							$ws = $wsData['ws']; 
                							if($ws->id>0) $TWS[$ws->id] = $ws;
                							//$TData[] = ' {"id":"WS'.$ws->id.'", "text":"'.$ws->name.'", "type":gantt.config.types.project, parent:"M'.$of->id.'", open: true}';
                							
                							// Add order child tasks
                							$taskColor='';
                							
                							foreach($wsData['tasks'] as &$task) {
                
                								if(empty($t_start) || $task->date_start<$t_start)$t_start=$task->date_start;
                								if(empty($t_end) || $t_end<$task->date_end)$t_end=$task->date_end;
                
                								$duration = $task->date_end>0 ? ceil( ($task->date_end - $task->date_start) / 86400 ) : ceil($task->planned_workload / (3600 * 7));
                								if($duration<1)$duration = 1;
                
                								/*if($task->planned_workload == 0) { // c'est un milestone
                									$TData[] = ' {"id":"'.$task->ganttid.'", "text":"'.$task->title.'", "start_date":"'.date('d-m-Y',$task->date_start).'", type:gantt.config.types.milestone '.(!is_null($fk_parent_of) ? ' ,parent:"'.$fk_parent_of.'" ' : '' ).',owner:"'.$ws->id.'"}';
                
                								}
                								else {*/
                								$TData[] = ' {"id":"'.$task->ganttid.'", workstation:'.$ws->rowid.', ws_nb_hour_capacity:'.$ws->nb_hour_capacity.' , "text":"'.$task->title.'", "start_date":"'.date('d-m-Y',$task->date_start).'", "duration":"'.$duration.'"'.(!is_null($fk_parent_of) ? ' ,parent:"'.$fk_parent_of.'" ' : '' ).', progress: '.($task->progress / 100).',owner:"'.$ws->rowid.'", type:gantt.config.types.task}';
                								//}
                								
                												
                								//$TData[] = ' {"id":"'.$task->ganttid.'double", workstation:'.$ws->rowid.', ws_nb_hour_capacity:'.$ws->nb_hour_capacity.' , "text":"'.$task->title.'", "start_date":"'.date('d-m-Y',$task->date_start).'", "duration":"'.$duration.'"'.(!is_null($fk_parent_of) ? ' ,parent:"'.$fk_parent_of.'" ' : '' ).', progress: '.($task->progress / 100).',owner:"'.$ws->rowid.'", type:gantt.config.types.task}';
                								
                								if($task->fk_task_parent>0) {
                									$TLink[] = ' {id:'.(count($TLink)+1).', source:"T'.$task->fk_task_parent.'", target:"'.$task->ganttid.'", type:"0"}';
                								}
                
                							}
                
                						}
            						}
            					}
        					}
        				}
    				}
			    }
			}
			
			if(!empty($TElement['tasks']))
			{
			    _format_task_for_gantt($TElement['tasks'], $TData,$TLink,0,$t_start,$t_end);
			}
			echo implode(',',$TData);

			// prevent unix timestamp start date
			if($t_start == 0)
			{
			    $t_start = time() -864000;
			    $t_end =time();
			}
			
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
			return "workstation_"+obj.workstation;
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

	    {name:"add",        label:"",           width:44 },
	];

	// Define local lang
    gantt.locale.labels["section_progress"] = "<?php echo $langs->transnoentities('Progress') ?>";
    gantt.locale.labels["section_workstation"] = "<?php echo $langs->transnoentities('Workstation') ?>";

	
	gantt.config.lightbox.sections = [
        //{name: "description", height: 26, map_to: "text", type: "textarea", focus: true},
        {name: "workstation", label:"Workstation", height: 22, type: "select", map_to: "workstation",options: [
            <?php echo _get_workstation(); ?>
        ]},
        {name: "progress", height: 22, map_to: "progress", type: "select", options: [
            {key:"0", label: "<?php echo $langs->transnoentities('NotStarted') ?>"},
            {key:"0.1", label: "10%"},
            {key:"0.2", label: "20%"},
            {key:"0.3", label: "30%"},
            {key:"0.4", label: "40%"},
            {key:"0.5", label: "50%"},
            {key:"0.6", label: "60%"},
            {key:"0.7", label: "70%"},
            {key:"0.8", label: "80%"},
            {key:"0.9", label: "90%"},
            {key:"1", label: "<?php echo $langs->transnoentities('Complete') ?>"}
        ]},



        {name: "time", type: "time", map_to: "auto", time_format:["%d", "%m", "%Y"]} //{name: "time", type: "duration", map_to: "auto", time_format:["%d", "%m", "%Y", "%H:%i"]}
    ];
	

	gantt.config.grid_width = 390;
	gantt.config.date_grid = "%F %d"

	gantt.config.scale_height  = 40;
	gantt.config.row_height = <?php echo $row_height; ?>;

	gantt.config.start_date = new Date();
	gantt.config.end_date = new Date();
	
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

	// add text progress information
	/*gantt.templates.progress_text = function(start, end, task){
		return "<span style='text-align:left;'>"+Math.round(task.progress*100)+ "% </span>";
	};*/

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
		/*updateAllCapacity();*/
	});
	gantt.attachEvent("onTaskClosed", function(id){
		/*updateAllCapacity();*/
	});
	gantt.attachEvent("onGanttScroll", function (left, top) {
//		updateAllCapacity();
//console.log($("div.gantt_task_line[task_id^=T]"));
	/*	$("div.gantt_task_line[task_id^=T]").each(function(i,item) {

		});
*/
//console.log('onGanttScroll',$('div.gantt_hor_scroll').scrollLeft());
		$('div.ws_container ').scrollLeft( $('div.gantt_hor_scroll').scrollLeft() );
	});

	/*gantt.attachEvent("onBeforeLightbox", function(id) {
	    var task = gantt.getTask(id);

		gantt.getLightboxSection('workstation').setValue(task.workstation);
	    return true;
	});*/
	
	gantt.attachEvent("onAfterTaskAdd", function(id,task){
		//console.log('createTask',id, task);
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
				,parent:task.parent
				,put:"task"
			}
			,method:"post"
		}).done( function(newid) {
			gantt.changeTaskId(id, newid);
			/*updateAllCapacity();*/

			// TODO set workstation and update capacity
		});
	});


	/*
	gantt.attachEvent("onTaskDblClick", function(id,e){

		if(id[0] == 'T') {
			pop_edit_task(id.substring(1));
			//document.location.href="<?php echo dol_buildpath('/projet/tasks/task.php',1) ?>?id="+id.substring(1)+"&withproject=1";
		}
		else {
			return false;
		}
	});*/


	gantt.attachEvent("onTaskCreated", function(task){
		task.workstation = 0;
		
		console.log('onTaskCreated',task);
	    return true;
	});


	
	gantt.attachEvent("onTaskClick", function(id,e){
		if(id[0] == 'T') {
			//ask_delete_task(id);
		} 
		return true;
	});

	gantt.attachEvent("onBeforeTaskDelete", function(id,item){
		var task = gantt.getTask(id);
		return delete_task(task.id,1,0);
	});


	
/*
	var start_task_drag = 0;
	var end_task_drag =  0;

	gantt.attachEvent("onBeforeTaskDrag", function(sid, parent, tindex){
		var task = gantt.getTask(sid);

		start_task_drag = task.start_date.getTime()
		end_task_drag = task.end_date.getTime();

		return true;
	});
*/
	gantt.attachEvent("onBeforeTaskChanged", function(id, mode, old_event){

		var task = gantt.getTask(id);
        return saveTask(task, old_event);
		
		
/*
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
			//updateAllCapacity();
			t_start = Math.min(start, old_event.start_date.getTime()) / 1000;
			t_end = Math.max(end, old_event.end_date.getTime()) / 1000;

			updateWSCapacity(task.workstation, t_start, t_end, task.ws_nb_hour_capacity);

		});

		return true;
*/
	});

    gantt.attachEvent("onLightboxSave", function(id, task, is_new){
        var old_event=gantt.getTask(id);

        //task.workstation = gantt.getLightboxSection('workstation ').getValue();
        
        return saveTask(task, old_event,is_new);
    })

// Add more button to lightbox
	gantt.config.buttons_left=["dhx_save_btn","dhx_cancel_btn","edit_task_button"];

	gantt.locale.labels["edit_task_button"] = "Modifier la tâche";

	gantt.attachEvent("onLightboxButton", function(button_id, node, e){
	    if(button_id == "edit_task_button"){
	        var id = gantt.getState().lightbox;
	        gantt.getTask(id).progress = 1;
	        gantt.updateTask(id);
	        gantt.hideLightbox();
	        pop_edit_task(id.substring(1));
	    }
	});


	gantt.config.autoscroll = false;
	//gantt.config.autosize = "x";

	gantt.init("gantt_here", new Date("<?php echo date('Y-m-d', $t_start) ?>"), new Date("<?php echo date('Y-m-d', $t_end) ?>"));
	modSampleHeight();
	gantt.parse(tasks);



	function modHeight(){
        var headHeight = 35;
        var sch = document.getElementById("gantt_here");
        sch.style.height = (parseInt(document.body.offsetHeight)-headHeight)+"px";
        gantt.setSizes();
	}

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

	function saveTask(task, old_event=false,is_new = false)
	{
		//console.log(task,old_event);
		var progress = task.progress ;
		
		var start = task.start_date.getTime();
		var end = task.end_date.getTime();

		//to get the value
		task.workstation = gantt.getLightboxSection('workstation').getValue();
		gantt.getLightboxSection('workstation').setValue(task.workstation);
		
		console.log(task);
		$.ajax({
			url:"<?php echo dol_buildpath('/gantt/script/interface.php',1); ?>"
			,data:{
				ganttid:task.id
				,start:start
				,end:end
				,progress:progress
				,put:"gantt"
				,workstation:task.workstation
			},
			method:"post",
		    success: function(data){
				gantt.message('<?php echo $langs->trans('Saved') ?>');

				gantt.refreshTask(task.id);
				/*updateAllCapacity();*/

				if(old_event)
				{
					t_start = Math.min(start, old_event.start_date.getTime()) / 1000;
					t_end = Math.max(end, old_event.end_date.getTime()) / 1000;
				}
				else
				{
					t_start = start / 1000;
					t_end = end / 1000;
				}

				updateWSCapacity(task.workstation, t_start, t_end, task.ws_nb_hour_capacity);
		    	//console.log(task);
		        return true;
			},
		    error: function(error){
		    	$.jnotify('SaveAjaxError ',"error");
		    	return false;
		    }
		});

        return true;
	}
	
	function ask_delete_task(id) {
		var task = gantt.getTask(id);

		gantt.confirm({
            text:"<?php echo $langs->trans('DeleteTask') ?>",
            callback: function(res){
                if(res){
                    delete_task(task.id,1);
                }
            }
        });
	}

	function delete_task(id,prevent_child_deletion,deleteFromGantt=1) {
		
		$.ajax({
		    url: "<?php echo dol_buildpath('/gantt/script/interface.php',1) ?>",
		    type: "POST",
		    dataType: "json",
		    data: {
				put:"delete_task"
					,task_id:id
					,prevent_child_deletion:prevent_child_deletion
				},
		    success: function(data){
		        if (data.result) {
		        	gantt.message('<?php echo $langs->trans('TaskDeleteFromBase') ?>');
			        if(deleteFromGantt){ return gantt.deleteTask(id); }else{return true;}
		        }
		        else
		        {
		        	$.jnotify('Error: '+ data.msg,"error");
		        	return false;
		        }
		    },
		    error: function(error){
		    	$.jnotify('AjaxError ',"error");
		    	console.log(error);
		    	return false;
		    }
		});

	}
	
	
	function pop_open_task(fk_task, callback) {

		pop_callback = callback;

		if($('#dialog-edit-task').length==0) {
			$('body').append('<div id="dialog-edit-task"></div>');
		}
		var url_in_pop ="<?php echo  dol_buildpath('/projet/tasks/task.php?id=',1) ?>"+fk_task

		$('#dialog-edit-task').load(url_in_pop+"  div.fiche",pop_event);

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

//console.log('updateWSCapacity', wsid, t_start, t_end, nb_hour_capacity);
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

				if(c == 'NA') {
					p='N/A'; bg='#ccc';
				}
				else {
					//p = Math.round(((nb_hour_capacity - c) / nb_hour_capacity)*100);
					p = Math.round(c * 100) / 100;

					if(p<0) bg='#ff0000';
					else if(p<=nb_hour_capacity/10) bg='#ffa500';
					else if(p>nb_hour_capacity/2) bg='#7cec43';

					//p+='%';
				}

				$('div#workstations_'+wsid+' div[date='+d+']').html(p).css({'background-color':bg});

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

		echo 'function updateAllCapacity() {

		if($("div.ws_container_label").length == 0) {
			$("body").append(\'<div class="ws_container_label"></div>\');
			$("body").append(\'<div class="ws_container"><div></div></div>\');

			$("div.ws_container").scroll(function(e) {
/*console.log($(this).scrollLeft(),e);*/
				gantt.scrollTo($(this).scrollLeft(),null);
			});
		}

		';

		foreach($TWS as &$ws) {

			?>
			if($("div#workstations_<?php echo $ws->id; ?>.gantt_row").length == 0 ) {

				$('div.ws_container_label').append('<div class="gantt_row workstation_<?php echo $ws->id; ?>" style="text-align:right; width:'+w_workstation_title+'px;height:20px;padding-right:5px;"><?php echo $ws->name . ' ('.$ws->nb_hour_capacity.'h - '.$ws->nb_ressource.')'; ?></div>');
				$('div.ws_container>div').append('<div class="workstation gantt_task_row gantt_row" id="workstations_<?php echo $ws->id ?>" style="width:'+w_workstation+'px;"><?php echo $cells; ?></div>');

			}

			updateWSCapacity(<?php echo $ws->id ?>, <?php echo (int)$t_start ?>, <?php echo (int)$t_end?>,<?php echo (double)$ws->nb_hour_capacity; ?>);
			<?php

		}
		echo '
		$("div.ws_container").css({
			width : $("#gantt_here div.gantt_task").width()
			, left:$("#gantt_here div.gantt_task").offset().left
		});

		$("div.ws_container_label").css({
			left:$("#gantt_here div.gantt_grid").offset().left
			,width:$("#gantt_here div.gantt_grid").width()
			,height : $("div.ws_container").outerHeight()
		}); ';

		/*
		echo '$(".gantt_task_line.gantt_milestone").css({
			width:"'.$row_height.'px"
			,height:"'.$row_height.'px"
		});';
		*/
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

				$darkest = ColorTools::adjustBrightness($color, -30);
				$border= ColorTools::adjustBrightness($color, -50);

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

	function _get_task_for_of($fk_project = 0) {

		global $db,$langs;

		$day_range = empty($conf->global->GANTT_DAY_RANGE_FROM_NOW) ? 90 : $conf->global->GANTT_DAY_RANGE_FROM_NOW;

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

		$sql.=" AND t.dateo BETWEEN NOW() - INTERVAL ".$day_range." DAY AND NOW() + INTERVAL ".$day_range." DAY ";
		
		$res = $db->query($sql);
		if($res===false) {
			var_dump($db);exit;
		}
		//echo $sql;
		$TTask=array();

		while($obj = $db->fetch_object($res)) {

			$task = new Task($db);
			$task->fetch($obj->rowid);
			$task->fetch_optionals($gantt_milestonetask->id);

			$task->ganttid = 'T'.$task->id;
			$task->title = $task->ref.' '.$task->label;
			if($task->planned_workload>0) {
				$task->title.=' '.dol_print_date($task->planned_workload,'hour');
			}

			if($task->array_options['options_fk_of']>0) {

				$of=new TAssetOF();
				$of->load($PDOdb, $task->array_options['options_fk_of']);

			}
			else{

				$of=new stdClass;
				$of->id = 0;
				$of->numero = 'None';
				$of->fk_commande = 0;
			}

			$of->ganttid = 'M'.(int)$of->id;
			$of->title = $of->numero;

			if($of->fk_commande>0) {

				if(!empty($TCacheOrder[$of->fk_commande])) {

					$order=$TCacheOrder[$of->fk_commande];

				}
				else{
					$order=new Commande($db);
					$order->fetch($of->fk_commande);
					$order->fetch_thirdparty();

					if($order->id>0)$order->title = $order->ref.' '.$order->thirdparty->name;
					else $order->title = $langs->trans('UndefinedOrder');

					$TCacheOrder[(int)$order->id] = $order;
				}


			}
			else {

				$order = new Commande($db);
				$order->title = $langs->trans('UndefinedOrder');

			}

			$order->ganttid = 'O'.(int)$order->id;

			if(!empty($TCacheProject[$task->fk_project])) {

				$project=$TCacheProject[$task->fk_project];

			}
			else{
				$project = new Project($db);
				$project->fetch($task->fk_project);

				if($project->id>0)$project->title = $project->ref.' '.$project->title;
				else $project->title = $langs->trans('UndefinedProject');

				$TCacheProject[$project->id] = $project;
			}

			$project->ganttid = 'P'.(int)$project->id;

			if(!empty($TCacheWS[$task->array_options['options_fk_workstation']])) {

				$ws=$TCacheWS[$task->array_options['options_fk_workstation']];

			}
			else{
				$ws = new TWorkstation();
				$ws->load($PDOdb,$task->array_options['options_fk_workstation']);
				$TCacheWS[$ws->id] = $ws;

			}

			$ws->ganttid = 'W'.(int)$ws->id;

			_complete_task_array($TTask, '');
			

			if(empty($TTask[$project->id])) {

				$TTask[$project->id]=array(
						'orders'=>array()
						,'project'=>$project
				);

				//_complete_task_array($TTask[$project->id], $project->ganttid);
				_load_child_tasks( $TTask[$project->id] , $project );
			
			}

			$order->id=(int)$order->id;

			if(empty($TTask[$project->id]['orders'][$order->id])) {

				$TTask[$project->id]['orders'][$order->id]=array(
					'ofs'=>array()
					,'order'=>$order
				);

				//_complete_task_array($TTask[$project->id]['orders'][$order->id], $order->ganttid);
				_load_child_tasks( $TTask[$project->id]['orders'][$order->id], $order);
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

			_load_child_tasks( $TTask[$project->id]['orders'][$order->id]['ofs'][$of->id]['workstations'][$ws->id],$task);
		}
		_load_child_tasks( $TTask);
		return $TTask;

	}

	/*
	 * Complete avec les tâches previ du parent
	 *
	 */
	function _complete_task_array(&$TTask, $parentid) {


	}
	
	
	function _load_child_tasks(&$TData, $gantt_parent_objet = false, $level = 0, $maxDeep = 3) {
		global $db;
		
		if($level>$maxDeep) return;

		$sql = "SELECT t.rowid
				FROM ".MAIN_DB_PREFIX."projet_task t LEFT JOIN ".MAIN_DB_PREFIX."projet_task_extrafields tex ON (tex.fk_object=t.rowid)
				WHERE ";
		
		
		$sqlWhere = " t.fk_task_parent = 0 AND ( tex.fk_gantt_parent_task < 1 OR ISNULL(tex.fk_gantt_parent_task)) ";
		if($gantt_parent_objet)
		{
    		if($level > 0)
    		{
    			$sqlWhere = " t.fk_task_parent = ".(int)$gantt_parent_objet->id;
    		}
    		else
    		{
    		    $sqlWhere = " tex.fk_gantt_parent_task = '".$gantt_parent_objet->ganttid."'";
    		}
		}
		//echo $sql.$sqlWhere;
		$res = $db->query($sql.$sqlWhere);
		if($res===false) {
			var_dump($db);exit;
		}
		
		while($obj = $db->fetch_object($res)) {
			$task = new Task($db);
			$task->fetch($obj->rowid);
			$task->title = 'PREVI '.$task->label;
			$task->ganttid = 'T'.$task->id;
			$task->fk_task_parent = $gantt_parent_objet?$gantt_parent_objet->id:0;
			
			$TData['tasks'][$task->id] = $task;
			
			_load_child_tasks( $TData,$task,($level+1) , $maxDeep) ;
		}
	}
	
	
	function _format_task_for_gantt(&$tasksList, &$TData,&$TLink,$owner=0,$t_start=false,$t_end=false, $taskColor=false)
	{
	    if(!empty($tasksList))
	    {
	        foreach($tasksList as &$task) {
	            if(empty($t_start) || $task->date_start<$t_start)$t_start=$task->date_start;
	            if(empty($t_end) || $t_end<$task->date_end)$t_end=$task->date_end;
	            $duration = $task->date_end>0 ? ceil( ($task->date_end - $task->date_start) / 86400 ) : ceil($task->planned_workload / (3600 * 7));
	            if($duration<1)$duration = 1;
	            
	            $type = ',type:gantt.config.types.task';
	            if(empty($task->fk_task_parent)) {
	               // $type = ',type:gantt.config.types.project';
	            }
	            
	            // Check if a color is define for this task
	            if(!empty($task->array_options['options_gantt_color']) && ColorTools::validate_color($task->array_options['options_gantt_color']))
	            {
	                $taskColor = $task->array_options['options_gantt_color'];
	            }
	      
	            $taskColorCode='';
	            if(ColorTools::validate_color($taskColor))
	            {
	                $taskColorCode= ',color:"'.$taskColor.'"';
	            }
	            
	            $workstation = ',workstation:0';
	            if(!empty($task->array_options['options_fk_workstation']))
	            {
	            	$workstation = ',workstation:'.$task->array_options['options_fk_workstation'];
	            }
	            
	            $TData[] = ' {"id":"'.$task->ganttid.'", source:"'.$task->array_options['options_fk_gantt_parent_task'].'", "text":"'.$task->title.'", "start_date":"'.date('d-m-Y',$task->date_start).'", "duration":"'.$duration.'"'.(!is_null($task->array_options['options_fk_gantt_parent_task']) ? ' ,parent:"'.$task->array_options['options_fk_gantt_parent_task'].'" ' : '' ).', progress: '.($task->progress / 100).',owner:"'.$owner.'" '.$type.' '.$taskColorCode.$workstation.'}';
	            if($task->fk_task_parent>0) {
	               // $TLink[] = ' {id:'.(count($TLink)+1).', source:"'.$task->array_options['options_fk_gantt_parent_task'].'", target:"'.$task->ganttid.'", type:"0"}';
	            }
	        }
	    }
	}
	
	function _get_workstation()
	{
		global $db,$langs;
		$sql = "SELECT w.rowid, w.name FROM ".MAIN_DB_PREFIX."workstation w  ";

		//echo $sql.$sqlWhere;
		$res = $db->query($sql);
		if($res===false) {
			var_dump($db);exit;
		}
		
		$TData= array();
		
		$TData[] = '{key:"0", label: " "}';
		while($obj = $db->fetch_object($res)) {
			
			$TData[] = '{key:"'.$obj->rowid.'", label: "'.$obj->name.'"}';

		}
		return implode(',',$TData);
	}
	
	