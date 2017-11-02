
function initTaskDrag(task) {
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

	TAnotherTaskToSave = {};

}

function setWSTime(wsid, dateOf) {
	var nb_hour_capacity = 0;
	var nb_ressource = 0;
	if(workstations[wsid])
	{
		nb_hour_capacity = parseFloat(workstations[wsid].nb_hour_capacity);
		nb_ressource = parseFloat(workstations[wsid].nb_ressource);
	}

	$ws = $('div#workstations_'+wsid+' div[date='+dateOf+']');
	if($ws.length>0) {
		console.log($ws);
			nb_hour_capacity=$ws.data('nb_hour_capacity');
			nb_ressource=$ws.data('nb_ressource');
	}

	$('#wsTimePlanner').remove();
	$div = $('<div id="wsTimePlanner"></div>');
	$div.append('<div><?php echo $langs->trans('NbHourCapacity'); ?> <input type="number" name="nb_hour_capacity" value="'+nb_hour_capacity+'" /></div>');
	$div.append('<div><?php echo $langs->trans('AvailaibleRessources'); ?> <input type="number" name="nb_ressource" value="'+nb_ressource+'" /></div>');
    $('body').append($div);

    $('#wsTimePlanner').dialog({
		title:"<?php echo $langs->trans('setWSTime'); ?>"
		,modal:true
		,draggable: false
		,resizable: false
		,buttons:[
            {
              text: '<?php echo $langs->trans('Set'); ?>',
              click: function() {

                $.ajax({
                   url : "script/interface.php"
                   ,data:{
                       'put':'ws-time'
                       ,'wsid':wsid
                       ,'date':dateOf
                       ,'nb_hour_capacity':$('#wsTimePlanner input[name=nb_hour_capacity]').val()
                       ,'nb_ressource':$('#wsTimePlanner input[name=nb_ressource]').val()

                   }
                }).done(function(data) {

                	var t_start = new Date(dateOf);
					var t_end = new Date(dateOf);

                	updateWSCapacity(wsid, +t_start/1000, +t_end/1000);

                });

                $( this ).dialog( "close" );
              }
            }
          ]
	});

}

function splitTask(task) {

	var min = task.planned_workload * task.progress / 3600;
	var max = task.planned_workload / 3600;

	$('#splitSlider').remove();
    $('body').append('<div id="splitSlider"><div><label></label></div><div style="padding:20px;position:relative;" ><div rel="slide"></div></div></div>');

	$('#splitSlider').dialog({
		title:"Sélectionnez comment diviser la tâche"
		,modal:true
		,draggable: false
		,resizable: false
		,buttons:[
            {
              text: 'Split',
              click: function() {

                $.ajax({
                   url : "script/interface.php"
                   ,data:{
                       'put':'split'
                       ,'taskid':task.objId
                       ,'tache1':$("#splitSlider label").attr("tache1")
                       ,'tache2':$("#splitSlider label").attr("tache2")

                   }
                }).done(function(data) {

					$('#formDate').submit();

                });

                $( this ).dialog( "close" );
              }
            }
          ]
	});

	 $( "div[rel=slide]" ).slider({
		min:min
		,max:max
		,step:0.25
		,slide:function(event,ui) {
			var val = Math.round( ui.value * 100 ) / 100;
			$("#splitSlider label").html("Reste sur tâche actuelle : "+ val +"h<br />Sur la tâche créée : "+(max - val)+"h"  );

			$("#splitSlider label").attr("tache1", val);
			$("#splitSlider label").attr("tache2", max - val);
		}
	});

}

function _getChild(tasksid, task) {
	if(task.$source) {
		//console.log(task.$source);

		$.each(task.$source,function(i, linkid) {
			var link = gantt.getLink(linkid);

			child = gantt.getTask(link.target);
			if(child.id) {
				tasksid.push(child.objId);

				_getChild(tasksid,child);
			}
		});
	}

}

function moveTasks(tasksid) {

	var t_start = <?php echo (int)$range->date_start ?>;
	var t_end = <?php echo (int)$range->date_end ?>;

	$.ajax({
		url:"script/interface.php"
		,data:{
			get:"better-pattern"
			,tasksid:tasksid
			,t_start : t_start
			,t_end : t_end
		}
		,dataType:"json"

	}).done(function(data) {

		$.each(data, function(i, item) {
			var t = gantt.getTask('T'+i);

			if(item.duration>0) {

				t.duration = item.duration;
				t.start_date = new Date(item.start * 1000);
				t.end_date = new Date((item.start + (86400 * t.duration ) - 1) * 1000 );

				gantt.refreshTask(t.id);
				gantt.message('<?php echo $langs->trans('TaskMovedTo') ?> '+t.start_date.toLocaleDateString());
					saveTask(t);

			}
			else {
				gantt.message(t.ref + ' : <?php echo $langs->trans('TaskCannobBeMovedTo') ?> ','error');

			}

		});

	});

}

function taskAutoMove(task) {

	var tasksid = [];
	tasksid.push(task.objId);
	_getChild(tasksid, task);

	moveTasks(tasksid.join(','));

}


function regularizeHour(task) {
	task.start_date.setHours(0,0,0,0);

	task.end_date = new Date(+task.start_date + task.duration * 86400000 - 1000);
}

function dragTaskLimit(task, diff ,mode) {

	<?php

	if(!empty($conf->global->GANTT_BOUND_ARE_JUST_ALERT)) {
		echo 'return 0;';
	}
	else {

	?>

		var modes = gantt.config.drag_mode;

		if(leftLimitON && +task.start_date < +leftLimit){
            task.start_date = new Date(leftLimit);
            if(mode == modes.move) {
                task.end_date = new Date(+task.start_date + diff);
                if(alertLimit) {
                	gantt.message('<?php echo $langs->trans('TaskCantBeMovedOutOfThisDate') ?> : '+task.end_date.toLocaleDateString());
                	alertLimit = false;
                }
            }
            return -1;
        }

        if(rightLimitON && +task.end_date > +rightLimit){
            task.end_date = new Date(rightLimit);
            if(mode == modes.move) {
            	task.start_date = new Date(+task.end_date - diff);
                if(alertLimit) {
                	gantt.message('<?php echo $langs->trans('TaskCantBeMovedOutOfThisDate') ?> : '+task.end_date.toLocaleDateString());
                	alertLimit = false;
                }
            }
            return -1;
        }
		<?php

	}

		?>
	}

function moveParentIfNeccessary(task) {

		<?php
	if(empty($conf->global->GANTT_MODIFY_PARENT_DATES_AS_CHILD)) {
		echo 'return 0;';
	}
	else {
	?>

	if(task.$target) {
		$.each(task.$target,function(i, linkid) {
			var link = gantt.getLink(linkid);

			var parent = gantt.getTask(link.source);

			if(parent.id) {

				var diff = +parent.end_date - parent.start_date ;

				if(parent.duration>=task.duration &&  parent.end_date > task.end_date ) {

					parent.end_date = task.end_date;
					parent.start_date = new Date(+parent.end_date - diff + 1000);

					TAnotherTaskToSave[parent.id] = true;

					var modes = gantt.config.drag_mode;
				    dragTaskLimit(parent, +parent.duration * 86400000,modes.move);

				    gantt.refreshTask(parent.id, true);
				}
				else if(parent.duration<task.duration &&  parent.start_date > task.start_date ) {

					parent.start_date = task.start_date ;
					parent.end_date = new Date(+parent.start_date + diff - 1000 );

					TAnotherTaskToSave[parent.id] = true;

					var modes = gantt.config.drag_mode;
				    dragTaskLimit(parent, +parent.duration * 86400000,modes.move);

				    gantt.refreshTask(parent.id, true);
				}

				moveParentIfNeccessary(parent);
			}
		});
	}

	<?php
	}
	?>
}

function moveChild(task,diff) {

	<?php
	if(empty($conf->global->GANTT_MOVE_CHILD_AS_PARENT)) {
		echo 'return 0;';
	}
	else {
	?>

	if(task.$source) {
		//console.log(task.$source);

		$.each(task.$source,function(i, linkid) {
			var link = gantt.getLink(linkid);

			child = gantt.getTask(link.target);
			if(child.id) {
				TAnotherTaskToSave[child.id] = true;

				var diff_child = +child.duration * 86400000 - 1000;
			    child.start_date = new Date(+child.start_date + diff);
			    child.end_date = new Date(+child.start_date + diff_child);

			    var modes = gantt.config.drag_mode;
			    dragTaskLimit(child, diff_child,modes.move);

		        gantt.refreshTask(child.id, true);

        		moveChild(child, diff);
			}
		});
	}
	<?php
	}
	?>
}


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
	/*console.log(task,old_event);*/
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

		    // TODO : gérer un vrai message avec des retour en json
			gantt.message(task.title + ' <?php echo $langs->trans('Saved') ?>');

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
	};

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
				row = data[d];
				var c = row.capacityLeft;

				total_hour_capacity = row.nb_hour_capacity * row.nb_ressource;

				var p;
				var dispo = 0;
				var bg = 'normal';

				if(c == 'NA') {
					p='N/A'; bg='closed'; dispo = 0;
				}
				else {
					//p = Math.round(((nb_hour_capacity - c) / nb_hour_capacity)*100);
					p = Math.round(c * 100) / 100;

					if(p<0) bg='pasassez';
					else if(p<=total_hour_capacity/10) bg='justeassez';
					else if(p>total_hour_capacity/2) bg='onestlarge';

					dispo = p;
				}

				if(wsid == 0) {
					p = -p;
					if(p == 0) p='';
				}
				else if(p<0) {
					var nb_people = Math.round(-p * 10 / row.nb_hour_capacity ) / 10;
					p = p + ' ['+nb_people+']';
				}

				$ws = $('div#workstations_'+wsid+' div[date='+d+']');

				$ws .html(p)
					.data('dispo',dispo)
					.data('wsid',wsid)
					.data('nb_hour_capacity',row.nb_hour_capacity)
					.data('nb_ressource',row.nb_ressource)
					.removeClass('pasassez justeassez onestlarge closed normal')

				if(wsid>0) {
					$ws.addClass(bg)
					.click(function() {
						setWSTime($(this).data('wsid'), $(this).attr('date'))
					});

					if(row.capacityLeft!='NA' && (nb_hour_capacity!=row.nb_hour_capacity || nb_ressource!=row.nb_ressource)) {
						$ws.css({
							'background-image': 'url(img/star.png)'
							,'background-repeat':'no-repeat'
						}).attr('title','<?php echo $langs->transnoentities('DayCapacityModify'); ?>');
				}

			}

		}

	});
}


var start_refresh_ws = 0;
var end_refresh_ws = 0;

function updateWSRangeCapacity(sl) {
	var sr = sl + $('#gantt_here div.gantt_task').width();

	var date_start = gantt.dateFromPos(sl).setHours(0,0,0,0) / 1000 - (86400 * 2);
	var date_end = gantt.dateFromPos(sr).setHours(23,59,59,0) / 1000 + (86400 * 2);

	if(date_start < start_refresh_ws - (86400*2) || date_start > start_refresh_ws + (86400*2)) {

		start_refresh_ws = date_start;
		end_refresh_ws = date_end;

		updateWSCapacity(0, date_start, date_end);
		<?php

			foreach($TWS as &$ws) {

				?> updateWSCapacity(<?php echo $ws->id ?>,  date_start, date_end); <?php

			}

		?>

	}
}

updateAllCapacity();

