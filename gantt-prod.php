<?php

require 'config.php';

set_time_limit(0);

dol_include_once('/projet/class/project.class.php');
dol_include_once('/projet/class/task.class.php');
dol_include_once('/commande/class/commande.class.php');
dol_include_once('/workstation/class/workstation.class.php');
dol_include_once('/of/class/ordre_fabrication_asset.class.php');
dol_include_once('/comm/action/class/actioncomm.class.php');

// Project -> Order -> OF -> Task
//<script src="../../codebase/locale/locale_fr.js" charset="utf-8"></script>
$row_height = 20;

llxHeader('', $langs->trans('GanttProd') , '', '', 0, 0, array(
		'/gantt/lib/dhx/codebase/dhtmlxgantt.js',
		'/gantt/lib/dhx/codebase/ext/dhtmlxgantt_smart_rendering.js',
		/*'/gantt/lib/dhx/codebase/ext/dhtmlxgantt_quick_info.js', // display info popin on click event*/
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

	$day_range = empty($conf->global->GANTT_DAY_RANGE_FROM_NOW) ? 90 : $conf->global->GANTT_DAY_RANGE_FROM_NOW;

	$range = new stdClass();
	$range->date_start = 0;
	$range->date_end= 0;
	$range->sql_date_start = date('Y-m-d',strtotime('-'.$day_range.' days'));
	$range->sql_date_end = date('Y-m-d',strtotime('+'.$day_range.' days'));

	if($fk_project>0) {
		$range->date_start = $project->date_start;
	        $range->date_end= $project->date_end;
        	$range->sql_date_start = date('Y-m-d',$project->date_start);
	        $range->sql_date_end = date('Y-m-d',$project->date_end);
	}

	$range->autotime = true;

	if(GETPOST('range_start')!='') {
		$range->date_start = dol_mktime(0, 0, 0, GETPOST('range_startmonth'), GETPOST('range_startday'), GETPOST('range_startyear'));
		$range->sql_date_start = date('Y-m-d',$range->date_start);
		$range->autotime = false;
	}

	if(GETPOST('range_end')!='') {
		$range->date_end= dol_mktime(0, 0, 0, GETPOST('range_endmonth'), GETPOST('range_endday'), GETPOST('range_endyear'));
		$range->sql_date_end = date('Y-m-d',$range->date_end);
		$range->autotime = false;
	}

	$TElement = _get_task_for_of($fk_project);
//pre($TElement,1);exit;

?>
	<style type="text/css">

	#gantt_here {
		margin-bottom:500px;
	}
	.gantt_task_line.gantt_milestone,.gantt_task_line.gantt_release {

	    background-color: #d33daf;
	    border: 0 solid #61164f;
	    /*visibility: hidden;
	    box-sizing: content-box;
	    -moz-box-sizing: content-box;
	    height: 30px;
		line-height: 30px;
		width: 30px;
		font-size: 1px;
	    content:'';*/
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
		background-color:#fff;
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
	 .gantt_cal_light_wide .gantt_cal_lsection {
		width:120px;
	}

	.modalwaiter {
	    display:    none;
	    position:   fixed;
	    z-index:    1000;
	    top:        0;
	    left:       0;
	    height:     100%;
	    width:      100%;
	    background: rgba( 255, 255, 255, .8 )
	                url('img/ajax-loader.gif')
	                50% 50%
	                no-repeat;
	}

	/* When the body has the loading class, we turn
	   the scrollbar off with overflow:hidden */
	body.loading {
	    overflow: hidden;
	}

	/* Anytime the body has the loading class, our
	   modal element will be visible */
	body.loading .modalwaiter {
	    display: block;
	}

	.lateDay {
		background-color:#ffdddd;
	}

	</style>

			<?php
			_get_workstation(); // init tableau de WS

			$TData=array(); $TWS=array(); $TLink=array();

			$open = false;
			if(GETPOST('open')) $open = true;
			else if(GETPOST('close')) $open = false;
			else if(GETPOST('open_status')) $open = true;
			
			$close_init_status = !empty($fk_project) || $open ? 'true': 'false';

			$t_start  = $t_end = 0;
			foreach($TElement as &$projectData ) {

			    if(!empty($projectData['object'])) {

			    	$project = &$projectData['object'];

    				$fk_parent_project = null;

    				if(empty($fk_project)) {

    					$TData[] = _get_json_data($project, $close_init_status);
    					$fk_parent_project= $project->ganttid;

    					_get_events( $TData,$TLink,$project->id);
    				}

    				$time_task_limit_no_after = 0;

    				if(!empty($projectData['childs'])) {
        				foreach($projectData['childs'] as &$orderData) {

        					$order= &$orderData['object'];

        					$fk_parent_order = null;

        					if($order->element =='milestone') {
        						$time_task_limit_no_after = $order->date;
        					}

        					if(empty($conf->global->GANTT_HIDE_INEXISTANT_PARENT) || $order->id>0 || $order->element!='commande') {
        						$TData[] = _get_json_data($order, $close_init_status, $fk_parent_project, $time_task_limit_no_before,$time_task_limit_no_after);
        						$fk_parent_order = $order->ganttid;
        					}
        					else {
        						$fk_parent_order = $fk_parent_project;
        					}


        					if(!empty($orderData['childs'])) {

            					foreach($orderData['childs'] as &$ofData) {

            						$of = &$ofData['object'];


            						$fk_parent_of = null;

            						if(!empty($conf->of->enabled) && (empty($conf->global->GANTT_HIDE_INEXISTANT_PARENT) || $of->id>0) ) {
            							$TData[] = _get_json_data($of, $close_init_status, $fk_parent_order, $time_task_limit_no_before,$time_task_limit_no_after);
            							$fk_parent_of= $of->ganttid;
            						}
            						else{
            							$fk_parent_of = $fk_parent_order;
            						}

            						// Add order child tasks
            						$taskColor='';

            						$time_task_limit_no_before= 0;
            						if(!empty($ofData['childs'])) {
                						foreach($ofData['childs'] as &$wsData) {

                							$ws = $wsData['object'];

                							if(empty($ws->element)) $ws->element = 'workstation';
                							//var_dump($ws->element);

                							$ws->ganttid = $fk_parent_of.$ws->ganttid;

                							if($ws->element =='milestone') {
                								$time_task_limit_no_before = $ws->date;
                							}

                							if((!empty($ws->id) && empty($conf->global->GANTT_HIDE_WORKSTATION)) || ($ws->element!='workstation')) {
                								$TData[] = _get_json_data($ws, $close_init_status, $fk_parent_of, $time_task_limit_no_before,$time_task_limit_no_after);
                								$fk_parent_ws = $ws->ganttid;
                							}
											else{
												$fk_parent_ws = $fk_parent_of;
											}

                							// Add order child tasks
                							$taskColor='';

                							if(!empty($wsData['childs'])) {
	                							foreach($wsData['childs'] as &$task) {

	                								$task->ws = &$ws;

	                								$TData[] = _get_json_data($task, $close_init_status, $fk_parent_ws, $time_task_limit_no_before,$time_task_limit_no_after);

													if($task->fk_task_parent>0) {
														//$TLink[] = ' {id:'.(count($TLink)+1).', source:"T'.$task->fk_task_parent.'", target:"'.$task->ganttid.'", type:"0"}';
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
			}

			_get_events($TData,$TLink);
		//	var_dump(dol_print_date($t_start),dol_print_date($t_end));exit;

			if($range->autotime){
				if(empty($range->date_start)) {
					$range->date_start = $range->date_end = time()-86400;
				}
				$range->date_end+=864000;
			}
if($fk_project == 0){
			$formCore=new TFormCore('auto','formDate');

			if(!$open) echo $formCore->btsubmit($langs->trans('OpenAllTask'), 'open');
			else  echo $formCore->btsubmit($langs->trans('ClosedTask'), 'close');

			echo $formCore->hidden('open_status',(int)$open);
			echo $formCore->hidden('fk_project',$fk_project);
			
			$form = new Form($db);
			echo $form->select_date($range->date_start, 'range_start');
			echo $form->select_date($range->date_end,'range_end');

			echo $formCore->btsubmit($langs->trans('ok'), 'bt_select_date');

			$formCore->end();
}
			?>
			<div id="gantt_here" style='width:100%; height:100%;'></div>

			<script type="text/javascript">
			$body = $("body");
			$body.addClass("loading");
/*
			$(document).on({
			     ajaxStart: function() { $body.addClass("loading");    },
			     ajaxStop: function() { $body.removeClass("loading"); }
			});
*/
			<?php

				echo 'var workstations = '.json_encode($workstationList).';';
			?>

			gantt.config.types.of = "of";
			gantt.locale.labels.type_of = "<?php echo $langs->trans('OF'); ?>";

			gantt.config.types.order = "order";
			gantt.locale.labels.type_order = "<?php echo $langs->trans('Order'); ?>";

			gantt.config.types.milestone = "milestone";
			gantt.locale.labels.type_milestone = "<?php echo $langs->trans('Release'); ?>";

			gantt.config.types.actioncomm = "actioncomm";
			gantt.locale.labels.type_milestone = "<?php echo $langs->trans('Agenda'); ?>";


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

			echo implode(",\n",$TData);

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
		else if(obj.type == gantt.config.types.release){
			return "gantt_release";
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
	    else if(date.getTime() < <?php echo strtotime('-1day')*1000; ?>) {
	    	return "lateDay" ;
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
    gantt.locale.labels["section_needed_ressource"] = "<?php echo $langs->transnoentities('needed_ressource') ?>";
    gantt.locale.labels["section_planned_workload"] = "<?php echo $langs->transnoentities('planned_workload') ?>";

	gantt.config.lightbox.sections = [
        {name: "description", height: 26, map_to: "text", type: "textarea", focus: true},
        {name: "workstation", label:"Workstation", height: 22, type: "select", width:"60%", map_to: "workstation",options: [
            <?php echo _get_workstation_list(); ?>
        ]},

        {name: "needed_ressource", height: 26, map_to: "needed_ressource", type: "textarea"},
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
            {key:"1", label: "<?php echo $langs->transnoentities('Complete') ?>", width:"60%"}
        ]},

        {name: "time", type: "time", map_to: "auto", time_format:["%d", "%m", "%Y"]},
        {name: "planned_workload", height: "duration", map_to: "planned_workload", type:"select", options:[
			<?php
				dol_include_once('/core/lib/date.lib.php');

				for($i=0;$i<1000000;$i+=900) {
					echo '{key:"'.$i.'", label:"'.convertSecondToTime($i,'all').'"},';
				}

			?>
        ]}
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

		var r ='';
		if(task.text) {
		    	r = "<strong>"+task.text+"</strong><br/><?php echo $langs->trans('Duration') ?> " + task.duration + " <?php echo $langs->trans('days') ?>";
			if(task.start_date) r+= "<br /><?php echo $langs->trans('FromDate') ?> "+task.start_date.toLocaleDateString()
			if(task.end_date) r+= " <?php echo $langs->trans('ToDate') ?> "+task.end_date.toLocaleDateString();
		}

		return r;

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



	gantt.attachEvent("onTaskDblClick", function(id,e){
		if(id[0] == 'T') {
			var task = gantt.getTask(id);

			task.text = task.title;

			if(task.planned_workload>0) {
				var m = task.planned_workload%900;

				if(m>0) task.planned_workload = task.planned_workload - m + 900;
			}

			return true;
		}
		else {
			return false;
		}
	});


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

	var start_task_drag = 0;
	var end_task_drag =  0;
	var rightLimit = null;
	var leftLimit = null;
	var alertLimit = false;
	var leftLimitON = false;
	var rightLimitON = false;

	gantt.attachEvent("onBeforeTaskDrag", function(sid, parent, tindex){
		var task = gantt.getTask(sid);
		if(task.id[0]!='T' && task.id[0]!='A') {
			gantt.message('<?php echo $langs->trans('OnlyTaskCanBeMoved') ?>');

			return false;
		}

		if(task.time_task_limit_no_before && task.time_task_limit_no_before>0) {
			leftLimit = task.time_task_limit_no_before * 1000;
			alertLimit = true;
			leftLimitON = true;
		}
		else {
			leftLimitON = false;
		}

		if(task.time_task_limit_no_after && task.time_task_limit_no_after>0) {
			rightLimit = task.time_task_limit_no_after * 1000;
			alertLimit = true;
			rightLimitON = true;
		}
		else {
			rightLimitON = false;
		}

		return true;
	});

	gantt.attachEvent("onTaskDrag", function(id, mode, task, original){
	    var modes = gantt.config.drag_mode;
	    if(mode == modes.move || mode == modes.resize){

	        var diff = original.duration*(1000*60*60*24);

	        if(leftLimitON && +task.start_date < +leftLimit){
	            task.start_date = new Date(leftLimit);
	            if(mode == modes.move) {
	                task.end_date = new Date(+task.start_date + diff);
	                if(alertLimit) {
	                	gantt.message('<?php echo $langs->trans('TaskCantBeMovedOutOfThisDate') ?> : '+task.end_date.toLocaleDateString());
	                	alertLimit = false;
	                }
	            }
	        }

	        if(rightLimitON && +task.end_date > +rightLimit){
	            task.end_date = new Date(rightLimit);
	            if(mode == modes.move) {
	            	task.start_date = new Date(task.end_date - diff);
	                if(alertLimit) {
	                	gantt.message('<?php echo $langs->trans('TaskCantBeMovedOutOfThisDate') ?> : '+task.start_date.toLocaleDateString());
	                	alertLimit = false;
	                }
	            }
	        }
	    }
	    return true;
	});

	gantt.attachEvent("onBeforeTaskChanged", function(id, mode, old_event){

		var task = gantt.getTask(id);

		return saveTask(task, old_event);

	});

    gantt.attachEvent("onLightboxSave", function(id, task, is_new){
        var old_event=gantt.getTask(id);


		//to get the value
		task.workstation = gantt.getLightboxSection('workstation').getValue();
		gantt.getLightboxSection('workstation').setValue(task.workstation);
        //task.workstation = gantt.getLightboxSection('workstation ').getValue();
		task.title = task.text;

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

	gantt.init("gantt_here", new Date("<?php echo date('Y-m-d', $range->date_start) ?>"), new Date("<?php echo date('Y-m-d', $range->date_end) ?>"));
	modSampleHeight();
	gantt.parse(tasks);



	function modHeight(){
        var headHeight = 35;
        var sch = document.getElementById("gantt_here");
        sch.style.height = (parseInt(document.body.offsetHeight)-headHeight)+"px";
        gantt.setSizes();
	}

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
		console.log(task,old_event);
		var progress = task.progress ;

		var start = task.start_date.getTime();
		var end = task.end_date.getTime();


		//console.log('beforsave',task);

		$.ajax({
			url:"<?php echo dol_buildpath('/gantt/script/interface.php',1); ?>"
			,data:{
				ganttid:task.id
				,id:task.objId
				,start:start
				,end:end
				,progress:progress
				,put:"gantt"
				,workstation:task.workstation
				,objElement:task.objElement
				,description:task.title
				,needed_ressource:task.needed_ressource
				,planned_workload:task.planned_workload
			},
			method:"post",
		    success: function(data){
		    	console.log(data);
			    // TODO : gérer un vrai message avec des retour en json
				gantt.message('<?php echo $langs->trans('Saved') ?>');

				//gantt.refreshTask(task.id);
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


	function updateWSCapacity(wsid, t_start, t_end) { //, nb_hour_capacity = 0

		var nb_hour_capacity = 0;
		var nb_ressource = 0;
		if(workstations[wsid])
		{
			nb_hour_capacity = parseFloat(workstations[wsid].nb_hour_capacity);
			nb_ressource = parseFloat(workstations[wsid].nb_ressource);
		}

		var total_hour_capacity = nb_hour_capacity * nb_ressource;
		
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
//console.log('nb_hour_capacity', data);
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
					else if(p<=total_hour_capacity/10) bg='#ffa500';
					else if(p>total_hour_capacity/2) bg='#7cec43';

					//p+='%';
				}

				if(p<0) {
					var nb_people = Math.round(-p * 10 / nb_hour_capacity) / 10;
					p = p + ' ['+nb_people+']';
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
		$t_cur = $range->date_start;
		while($t_cur<=$range->date_end) {
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

				$('div.ws_container_label').append('<div class="gantt_row workstation_<?php echo $ws->id; ?>" style="text-align:right; width:'+w_workstation_title+'px;height:20px;padding-right:5px;"><?php echo addslashes($ws->name) . ' ('.$ws->nb_hour_capacity.'h - '.$ws->nb_ressource.')'; ?></div>');
				$('div.ws_container>div').append('<div class="workstation gantt_task_row gantt_row" id="workstations_<?php echo $ws->id ?>" style="width:'+w_workstation+'px;"><?php echo $cells; ?></div>');

			}

			updateWSCapacity(<?php echo $ws->id ?>, <?php echo (int)$range->date_start?>, <?php echo (int)$range->date_end?>,<?php echo (double)$ws->nb_hour_capacity; ?>);
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

	/*
	*	Recalcul la taille des colonnes du workflow
	*/
	$( document ).ready(function(){
		$body.removeClass("loading");
		var colWidth = $( ".gantt_task_row .gantt_task_cell" ).first().width();
		/*window.alert(colWidth);*/
		$( ".ws_container .gantt_task_cell" ).width(colWidth);
	});

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
	echo '<div class="modalwaiter"></div>';
	llxFooter();

	function _get_task_for_of($fk_project = 0) {

		global $db,$langs,$range;

		$TCacheProject = $TCacheOrder  = $TCacheWS = array();

		$PDOdb=new TPDOdb;

		$idNoAffectation = 1;

		$sql = "SELECT t.rowid
		FROM ".MAIN_DB_PREFIX."projet_task t LEFT JOIN ".MAIN_DB_PREFIX."projet_task_extrafields tex ON (tex.fk_object=t.rowid)
			LEFT JOIN ".MAIN_DB_PREFIX."projet p ON (p.rowid=t.fk_projet)
		WHERE t.dateo IS NOT NULL ";

		if($fk_project>0) $sql.= " AND fk_projet=".$fk_project;
		else {
			$sql.= " AND tex.fk_of IS NOT NULL AND tex.fk_of>0 AND (t.progress<100 OR t.progress IS NULL)
			AND p.fk_statut = 1
			";

			$sql.=" AND t.dateo BETWEEN '".$range->sql_date_start."' AND '".$range->sql_date_end."'";

			$sql.=" AND p.entity IN (".getEntity('project',1).")";
		}

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
			$task->title = $task->label;
			$task->text = $task->ref.' '.$task->label;
			if($task->planned_workload>0) {
				$task->text.=' '.dol_print_date($task->planned_workload,'hour');
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

			if($of->id>0) {
				$of->ganttid = 'M'.(int)$of->id;
			}
			else {
				$of->ganttid = 'MNA'.$idNoAffectation; $idNoAffectation++;
			}

			$of->title = $of->numero;

			if($of->fk_commande>0) {

				if(!empty($TCacheOrder[$of->fk_commande])) {

					$order=$TCacheOrder[$of->fk_commande];

				}
				else{
					$order=new Commande($db);
					$order->fetch($of->fk_commande);
					$order->fetch_thirdparty();

					if($order->id>0){
						$order->title = $order->ref.' '.$order->thirdparty->name;
					}
					else {
						$order->title = $langs->trans('UndefinedOrder');
					}
					$TCacheOrder[(int)$order->id] = $order;
				}


			}
			else {

				$order = new Commande($db);

				$order->title = $langs->trans('UndefinedOrder');

			}
			if($order->id >0){
				$order->ganttid = 'O'.$order->id;
			}
			else{
				$order->ganttid= 'ONA'.$idNoAffectation; $idNoAffectation++;
			}


			if(!empty($TCacheProject[$task->fk_project])) {

				$project=$TCacheProject[$task->fk_project];

			}
			else{
				$project = new Project($db);
				$project->fetch($task->fk_project);

				if($project->id>0)$project->title = $project->ref.' '.$project->title;
				else {
					$project->title = $langs->trans('UndefinedProject');
				}

				$TCacheProject[$project->id] = $project;
			}

			if($project->id>0) {
				$project->ganttid = 'P'.$project->id;
			}
			else {
				$project->ganttid = 'PNA'.$idNoAffectation; $idNoAffectation++;
			}

			if(!empty($conf->workstation->enabled)) {
			if(!empty($TCacheWS[$task->array_options['options_fk_workstation']])) {

				$ws=$TCacheWS[$task->array_options['options_fk_workstation']];

			}
			else{
				$ws = new TWorkstation();
				$ws->load($PDOdb,$task->array_options['options_fk_workstation']);
				$ws->text = $ws->title = $ws->name;
				$TCacheWS[$ws->id] = $ws;

			}
			}
			else{
				$ws=new StdClass;
				$ws->element = 'workstation';
				$ws->id = 0;
			}

			if($ws->id>0) {
				$ws->ganttid = 'W'.(int)$ws->id;
			}
			else{
				$ws->ganttid = 'WNA'.$idNoAffectation; $idNoAffectation++;
				$ws->title = $langs->trans('UndefinedWorkstation');
			}


			if(empty($TTask[$project->id])) {

				$TTask[$project->id]=array(
						'childs'=>array()
						,'object'=>$project
				);
				_adding_task_project_end($project, $TTask[$project->id]['childs']);
				_load_child_tasks( $TTask[$project->id]['childs'] , $project);

			}

			$order->id=(int)$order->id;

			if(empty($TTask[$project->id]['childs'][$order->id])) {

				$TTask[$project->id]['childs'][$order->id]=array(
					'childs'=>array()
					,'object'=>$order
				);

				_load_child_tasks( $TTask[$project->id]['childs'][$order->id]['childs'], $order);
			}

			if(empty($TTask[$project->id]['childs'][$order->id]['childs'][$of->id])) {

				$TTask[$project->id]['childs'][$order->id]['childs'][$of->id]=array(
						'childs'=>array()
						,'object'=>$of
				);
			}
			_adding_task_supplier_order($PDOdb,$of, $TTask[$project->id]['childs'][$order->id]['childs'][$of->id]['childs']);

			if(empty($TTask[$project->id]['childs'][$order->id]['childs'][$of->id]['childs'][$ws->id])) {

				$TTask[$project->id]['childs'][$order->id]['childs'][$of->id]['childs'][$ws->id]=array(
						'childs'=>array()
						,'object'=>$ws
				);
			}

			$TTask[$project->id]['childs'][$order->id]['childs'][$of->id]['childs'][$ws->id]['childs'][$task->id] = $task;

			_load_child_tasks( $TTask[$project->id]['childs'][$order->id]['childs'][$of->id]['childs'][$ws->id]['childs'],$task);
		}
		_load_child_tasks( $TTask);
		return $TTask;

	}

	function _adding_task_project_end(&$project,&$TData) {
		global $db, $langs;
		if(!empty($conf->global->GANTT_DISABLE_PROJECT_MILESTONE)) {
			return false;
		}

		if($project->date_end>0) {
			$object=new stdClass();
			$object->element = 'milestone';
			$object->title = $object->text = $langs->trans('EndOfProject', $project->ref, dol_print_date($project->date_end));
			$object->date= $project->date_end + 84399; //23:59:59
			$object->ganttid = 'RELEASE'.$project->id;

			$TData[$object->ganttid]['object'] = $object;

		}

	}

	function _adding_task_supplier_order(&$PDOdb, &$assetOf,&$TData) {
		global $db, $langs, $conf;

		if(!empty($conf->global->GANTT_DISABLE_SUPPLIER_ORDER_MILESTONE) || $assetOf->id<=0) {
			return false;
		}

		dol_include_once('/fourn/class/fournisseur.commande.class.php');
		$TIdCommandeFourn = $assetOf->getElementElement($PDOdb);

		if(count($TIdCommandeFourn)){
			foreach($TIdCommandeFourn as $idcommandeFourn){
				$cmd = new CommandeFournisseur($db);
				$cmd->fetch($idcommandeFourn);

				if($cmd->statut>0 && $cmd->statut<5 && $cmd->date_livraison>0) {
					$object=new stdClass();
					$object->element = 'milestone';
					$object->title = $object->text = $langs->trans('AwaitingDelivery', $cmd->ref, dol_print_date($cmd->date_livraison));
					$object->date= $cmd->date_livraison;
					$object->ganttid = 'DELIVERY'.$cmd->id;

					$TData[$object->ganttid]['object'] = $object;

				}


			}
		}

	}

	/*
	 * Complete avec les tâches previ du parent
	 *
	 */
	function _load_child_tasks(&$TData, $gantt_parent_objet = false, $level = 0, $maxDeep = 3) {
		global $db,$range;

		if($level>$maxDeep) return;

		$projet_previ=new Project($db);
		$projet_previ->fetch(0,'PREVI');
		$fk_projet_previ = (int)$projet_previ->id;

		$sql = "SELECT t.rowid
				FROM ".MAIN_DB_PREFIX."projet_task t LEFT JOIN ".MAIN_DB_PREFIX."projet_task_extrafields tex ON (tex.fk_object=t.rowid)
						LEFT JOIN ".MAIN_DB_PREFIX."projet p ON (p.rowid=t.fk_projet)
				WHERE t.fk_projet=$fk_projet_previ AND ";


		if($level > 0)
		{
			$sql.= " t.fk_task_parent = ".(int)$gantt_parent_objet->id;
		}
		elseif(empty($gantt_parent_objet)) {
			$sql.= " (tex.fk_gantt_parent_task IS NULL OR tex.fk_gantt_parent_task='0')";
		}
		else {
			$sql.= " tex.fk_gantt_parent_task = '".$gantt_parent_objet->ganttid."'";
		}

		$sql.=" AND t.dateo BETWEEN '".$range->sql_date_start."' AND '".$range->sql_date_end."'";
		
		$sql.=" AND p.entity IN (".getEntity('project',1).")";
		
		//echo $sql.$sqlWhere;
		$res = $db->query($sql);
		if($res===false) {
			var_dump($db);exit;
		}

		$hasChild = false;
		while($obj = $db->fetch_object($res)) {
			$task = new Task($db);
			$task->fetch($obj->rowid);
			$task->title = $task->label;
			$task->text = 'PREVI '.$task->label;
			$task->ganttid = 'T'.$task->id;
			$task->fk_task_parent = $gantt_parent_objet?$gantt_parent_objet->id:0;

			$TData['PREVI'.$task->id]['object'] = $task;

			$hasChild = true;

			if(_load_child_tasks( $TData['PREVI'.$task->id]['childs'] ,$task,($level+1) , $maxDeep)) {

				$TData['PREVI'.$task->id]['object']->element = ( $level==0 ? 'project' : 'commande');

			}


		}

		return $hasChild;

	}

	function _get_json_data(&$object, $close_init_status, $fk_parent_object=null, $time_task_limit_no_before=0,$time_task_limit_no_after=0) {

		if($object->element == 'commande') {
			return ' {"id":"'.$object->ganttid.'",objElement:"'.$object->element.'", "text":"'.$object->title.'", "type":gantt.config.types.order'.(!is_null($fk_parent_object) ? ' ,parent:"'.$fk_parent_object.'" ' : '' ).', open: '.$close_init_status.'}';
		}
		else if($object->element == 'workstation') {

			return ' {"id":"'.$object->ganttid.'",objElement:"'.$object->element.'", "text":"'.$object->title.'", "type":gantt.config.types.project'.(!is_null($fk_parent_object) ? ' ,parent:"'.$fk_parent_object.'" ' : '' ).', open: true}';
		}
		else if($object->element == 'project') {

			$taskColor='';
			$projectColor='';
			if(ColorTools::validate_color($object->array_options['options_color']))
			{
				$taskColor= ColorTools::adjustBrightness($object->array_options['options_color'], -50); //TODO récupérer la taskColor du projet...
				$projectColor= ',color:"'.$object->array_options['options_color'].'"';
			}

			return ' {"id":"'.$object->ganttid.'",objElement:"'.$object->element.'", "text":"'.$object->title.'", "type":gantt.config.types.project, open: '.$close_init_status.$projectColor.'}';

		}
		else if($object->element == 'of') {
			return ' {"id":"'.$object->ganttid.'",objElement:"'.$object->element.'", "text":"'.$object->title.'", "type":gantt.config.types.of'.(!is_null($fk_parent_object) ? ' ,parent:"'.$fk_parent_object.'" ' : '' ).', open: '.$close_init_status.'}';
		}
		elseif($object->element == 'project_task') {
			global $range,$TWS,$workstationList;

			if($range->autotime) {
				if(empty($range->date_start) || $object->date_start<$range->date_start)$range->date_start=$object->date_start;
				if(empty($range->date_end) || $range->date_end<$object->date_end)$range->date_end=$object->date_end;
			}

			$duration = $object->date_end>0 ? ceil( ($object->date_end - $object->date_start) / 86400 ) : ceil($object->planned_workload / (3600 * 7));
			if($duration<1)$duration = 1;

			$fk_workstation = (int) $object->array_options['options_fk_workstation'];
			if($fk_workstation>0) $TWS[$fk_workstation] = $workstationList[$fk_workstation]; //TODO ouh que c'est moche !
		//	var_dump($workstationList,$fk_workstation,$TWS);exit;
			return ' {"id":"'.$object->ganttid.'",time_task_limit_no_before:'.(int)$time_task_limit_no_before.',time_task_limit_no_after:'.(int)$time_task_limit_no_after.',planned_workload:'.(int)$object->planned_workload.' ,objElement:"'.$object->element.'",objId:"'.$object->id.'", workstation:'.$fk_workstation.' , "text":"'.$object->text.'" , "title":"'.$object->title.'", "start_date":"'.date('d-m-Y',$object->date_start).'", "duration":"'.$duration.'"'.(!is_null($fk_parent_object) ? ' ,parent:"'.$fk_parent_object.'" ' : '' ).', progress: '.($object->progress / 100).',owner:"'.$fk_workstation.'", type:gantt.config.types.task , open: '.$close_init_status.'}';

		}
		else if($object->element== 'milestone' || $object->element == 'release') {
			global $range;

			if($range->autotime) {
				if(empty($range->date_start) || $object->date<$range->date_start)$range->date_start=$object->date;
				if(empty($range->date_end) || $range->date_end<$object->date)$range->date_end=$object->date;
			}

			return ' {"id":"'.$object->ganttid.'",objElement:"'.$object->element.'", "text":"'.$object->text.'", "start_date":"'.date('d-m-Y',$object->date).'", "duration":1 '.(!is_null($fk_parent_object) ? ' ,parent:"'.$fk_parent_object.'" ' : '' ).', type:gantt.config.types.release}';

		}

		return '{ nonObjectManaged:"'.$object->element.'" }';
	}

	/*
	 * @param taskColor		web hexa color format like #FFFFFF
	 */
	function _format_task_for_gantt(&$tasksList, &$TData,&$TLink,$owner=0,$t_start=false,$t_end=false, $taskColor=false)
	{
		return false;

	    if(!empty($tasksList))
	    {
	        foreach($tasksList as &$task) {
	            if(empty($t_start) || $task->date_start<$t_start)$t_start=$task->date_start;
	            if(empty($t_end) || $t_end<$task->date_end)$t_end=$task->date_end;
	            $duration = $task->date_end>0 ? ceil( ($task->date_end - $task->date_start) / 86400 ) : ceil($task->planned_workload / (3600 * 7));
	            if($duration<1)$duration = 1;

	            $type = ',type:gantt.config.types.task';
	            if(empty($task->fk_task_parent) && empty($task->array_options['options_fk_gantt_parent_task'])) {
	               $type = ',type:gantt.config.types.project';
	            }

	            // Check if a color is define for this task
	            if(!empty($task->array_options['options_color']) && ColorTools::validate_color($task->array_options['options_color']))
	            {
	                $taskColor = $task->array_options['options_color'];
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

	            $needed_ressource= ',needed_ressource:0';
	            if(!empty($event->array_options['options_needed_ressource']))
	            {
	            	$needed_ressource= ',needed_ressource:'.(int)$event->array_options['options_needed_ressource'];
	            }

	            if( $type == ',type:gantt.config.types.project') {
	            	$TData[] = ' {id:"'.$task->ganttid.'",objId:"'.$task->id.'",objElement:"'.$task->element .'",text:"'.$task->text.'", "title":"'.$task->title.'"'.$type.' '.$taskColorCode.'}';
	            }
	            else {
	            	$TData[] = ' {"id":"'.$task->ganttid.'"'.$needed_ressource.',objId:"'.$task->id.'",objElement:"'.$task->element.(empty($task->array_options['options_fk_gantt_parent_task']) ? '' : '", source:"'.$task->array_options['options_fk_gantt_parent_task'].'"').', "text":"'.$task->text.'", "title":"'.$task->title.'", "start_date":"'.date('d-m-Y',$task->date_start).'", "duration":"'.$duration.'"'.(!is_null($task->array_options['options_fk_gantt_parent_task']) ? ' ,parent:"'.$task->array_options['options_fk_gantt_parent_task'].'" ' : '' ).', progress: '.($task->progress / 100).',owner:"'.$owner.'" '.$type.' '.$taskColorCode.$workstation.'}';
	            }


	            if($task->fk_task_parent>0) {
	               // $TLink[] = ' {id:'.(count($TLink)+1).', source:"'.$task->array_options['options_fk_gantt_parent_task'].'", target:"'.$task->ganttid.'", type:"0"}';
	            }
	        }
	    }
	}

	/*
	 * stock les posts de travail dans une variable globale
	 * return int 			count of result
	 */
	function _get_workstation()
	{
		global $db,$langs, $workstationList;
		$sql = "SELECT w.rowid as id , w.name, w.nb_hour_capacity, w.nb_hour_capacity, w.nb_ressource FROM ".MAIN_DB_PREFIX."workstation w  ";

		//echo $sql.$sqlWhere;
		$res = $db->query($sql);
		if($res===false) {
			var_dump($db);exit;
		}

		$workstationList = array();

		while($obj = $db->fetch_object($res)) {
			$workstationList[$obj->id] = $obj;
		}
		return count($workstationList);
	}

	/*
	 * formate la liste des workstations pour le select de la lightbox
	 */
	function _get_workstation_list()
	{
		global $db,$langs,$workstationList;

		if(empty($workstationList)){ _get_workstation(); }

		$TData[] = '{key:"0", label: " "}';
		foreach($workstationList as $ws) {

			$TData[] = '{key:"'.$ws->id.'", label: "'.$ws->name.'"}';

		}
		return implode(',',$TData);
	}
	/*
	 * Récuperation des evenements type agenda
	 */
	function _get_events( &$TData,&$TLink,$fk_project=0,$owner=0,$taskColor= '#f7d600')
	{
		global $db,$range;

		$day_range = empty($conf->global->GANTT_DAY_RANGE_FROM_NOW) ? 90 : $conf->global->GANTT_DAY_RANGE_FROM_NOW;

		$sql = "SELECT a.id
		FROM ".MAIN_DB_PREFIX."actioncomm a
			LEFT JOIN ".MAIN_DB_PREFIX."actioncomm_extrafields aex ON (aex.fk_object=a.id)
		WHERE ";

		$sql.=" ( a.datep BETWEEN '".$range->sql_date_start."' AND '".$range->sql_date_end."' ";
		$sql.=" OR a.datep2 BETWEEN '".$range->sql_date_start."' AND '".$range->sql_date_end."' )";
		$sql.=" AND aex.fk_workstation > 0 ";

		if($fk_project > 0)
		{
			$sql.= " AND a.fk_project=".(int)$fk_project;
		}
		else
		{
			$sql.= " AND ( a.fk_project=0 OR ISNULL(a.fk_project) )";
		}
		
		$sql.=" AND a.entity IN (".getEntity('actioncomm',1).")";
		
		//echo $sql;
		//exit();
		$res = $db->query($sql);
		if($res===false) {
			var_dump($db);exit;
		}

		while($obj = $db->fetch_object($res))
		{

			$event = new ActionComm($db);
			$event->fetch($obj->id);
			if(empty($event->array_options))
			{
				$event->fetch_optionals();
			}

			$event->ganttid = 'A'.$event->id;
			$event->title = 'AGENDA'.' '. $event->label;

			if($range->autotime) {
				if(empty($range->date_start) || $event->datep<$range->date_start) $range->date_start=$event->datep;
				if(empty($range->date_end) || $range->date_end<$event->datef ) $range->date_end=$event->datef;
			}

			$duration = $event->datef>0 ? ceil( ($event->datef- $event->datep) / 86400 ) : ceil($event->planned_workload / (3600 * 7));

			if($duration<1)$duration = 1;

			$type = ',type:gantt.config.types.actioncomm';


			$taskColorCode= '';
			if(ColorTools::validate_color($taskColor))
			{
				$taskColorCode= ',color:"'.$taskColor.'"';
			}


			$workstation = ',workstation:0';
			if(!empty($event->array_options['options_fk_workstation']))
			{
				$workstation = ',workstation:'.$event->array_options['options_fk_workstation'];
			}

			$parent = $source = '';
			if($fk_project>0)
			{
				$parent = ',source:"P'.$fk_project.'"';
				$source = ',parent:"P'.$fk_project.'"';
			}

			$needed_ressource= ',needed_ressource:0';
			if(!empty($event->array_options['options_needed_ressource']))
			{
				$needed_ressource= ',needed_ressource:'.$event->array_options['options_needed_ressource'];
			}


			$TData[] = ' {"id":"'.$event->ganttid.'"'.$needed_ressource.',objId:"'.$event->id.'",objElement:"'.$event->element.'", "text":"'.$event->title.'", "start_date":"'.date('d-m-Y',$event->datep).'", "duration":"'.$duration.'" , progress:'.$event->percentage.' '.$type.' '.$taskColorCode.$workstation.$parent.$source.'}';


		}
	}



