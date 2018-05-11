function initTaskDrag(task) {
	alertLimit = true;

	if(task.time_task_limit_no_before && task.time_task_limit_no_before>0) {
		leftLimit = task.time_task_limit_no_before * 1000;
		leftLimitON = true;
	}
	else {
		leftLimitON = false;
	}

	if(task.time_task_limit_no_after && task.time_task_limit_no_after>0) {
		rightLimit = task.time_task_limit_no_after * 1000;
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
			nb_hour_capacity=$ws.data('nb_hour_capacity');
			nb_ressource=$ws.data('nb_ressource');
	}

	$('#wsTimePlanner').remove();
	$div = $('<div id="wsTimePlanner"></div>');
	$div.append('<div><?php echo addslashes($langs->trans('NbHourCapacity')); ?> <input type="number" name="nb_hour_capacity" value="'+nb_hour_capacity+'" /></div>');
	$div.append('<div><?php echo addslashes($langs->trans('AvailaibleRessources')); ?> <input type="number" name="nb_ressource" value="'+nb_ressource+'" /></div>');
    $('body').append($div);

	var buttons = [{
              text: '<?php echo $langs->transnoentities('Set'); ?>',
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
     }];

	 if($ws.hasClass('starred')) {
    	 buttons.push({
              text: '<?php echo $langs->transnoentities('Remove'); ?>',
              click: function() {

                $.ajax({
                   url : "script/interface.php"
                   ,data:{
                       'put':'ws-remove-time'
                       ,'wsid':wsid
                       ,'date':dateOf

                   }
                }).done(function(data) {

                	var t_start = new Date(dateOf);
					var t_end = new Date(dateOf);

                	updateWSCapacity(wsid, +t_start/1000, +t_end/1000);

                });

                $( this ).dialog( "close" );
              }
            });
	}

    $('#wsTimePlanner').dialog({
		title:"<?php echo $langs->trans('setWSTime'); ?>"
		,modal:true
		,draggable: false
		,resizable: false
		,buttons:buttons
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
			$("#splitSlider label").html("Reste sur tâche actuelle : "+ val +"h<br />Sur la tâche créée : "+(Math.round((max - val)*100)/100)+"h"  );

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

function recursiveRefreshTask(taskid) {

	if(taskid !="" && taskid!=0) {

		gantt.refreshTask(taskid);
    	var t = gantt.getTask(taskid);

    	if(t.parent!=0) {

    		parent = gantt.getTask(t.parent);

    		if(parent.$no_end && +parent.end_date<+t.end_date)parent.end_date = t.end_date;
    		if(parent.$no_start && +parent.start_date>+t.start_date)parent.start_date = t.start_date;

    		recursiveRefreshTask(t.parent);

    	}
	}
}

function moveTasks(tasksid) {

	gantt.message('<?php echo addslashes($langs->trans('LookinForABetterPosition')) ?>');

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

				recursiveRefreshTask(t.id);
				gantt.message('<?php echo $langs->trans('TaskMovedTo') ?> '+t.start_date.toLocaleDateString());
				saveTask(t);

			}
			else {
				gantt.message(t.ref
					+ ' : <?php echo $langs->trans('TaskCannobBeMovedTo') ?> '
					+ (item.note ? " -> "+ item.note : '')
				,'error');

			}

		});

	});

}

function taskAutoMove(task) {

	var tasksid = [];
	tasksid.push(task.objId);

	<?php
	if(!empty($conf->global->GANTT_MOVE_CHILD_AS_PARENT)) {
		echo '_getChild(tasksid, task);';

	}
	?>

	moveTasks(tasksid.join(','));

}


function regularizeHour(task) {

<?php

    if($scale_unit!='week') {
        echo 'task.start_date.setHours(0,0,0,0);
    	task.end_date = new Date(+task.start_date + (task.duration * 86400000) - 1000);';
    }

?>

}

function dragTaskLimit(task, diff ,mode) {
	var modes = gantt.config.drag_mode;

	if(task.$target) {
		$.each(task.$target,function(i, linkid) {
			var link = gantt.getLink(linkid);
			var parent = gantt.getTask(link.source);

			if(parent.id && parent.objElement == 'project_task_delay') {

				delayTaskLimit = new Date(+parent.start_date + (parent.duration * 86400000) );

				if(+task.start_date < +delayTaskLimit){
			            task.start_date = new Date(+delayTaskLimit);
			            if(mode == modes.move) {
			                task.end_date = new Date(+task.start_date + diff);
			                if(alertLimit) {
			                	gantt.message('<?php echo addslashes($langs->trans('TaskCantBeMovedBeforeApproDelay')) ?> : '+delayTaskLimit.toLocaleDateString());
			                	alertLimit = false;
			                }
			            }
			            return -1;
		        }

			}
		});
	}

	<?php

	if(!empty($conf->global->GANTT_BOUND_ARE_JUST_ALERT)) {
		echo 'return 0;';
	}
	else {

	?>

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


        return 1;

		<?php

	}

		?>


	}

function moveParentIfNeccessary(task) {

	<?php
	if(empty($conf->global->GANTT_MODIFY_PARENT_DATES_AS_CHILD)) {
		echo 'return true;';
	}
	else {
	?>

	if(task.$target) {
		$.each(task.$target,function(i, linkid) {
			var link = gantt.getLink(linkid);

			var parent = gantt.getTask(link.source);

			var modes = gantt.config.drag_mode;

			if(parent.id && parent.objElement=="project_task") {

				var diff = +parent.end_date - parent.start_date ;

				var flagOk = true;
				if(parent.workstation_type && parent.workstation_type == "STT" && +parent.end_date > +task.start_date) {

					parent.end_date = new Date(+task.start_date - 1000);
					parent.start_date = new Date(+parent.end_date - diff + 1000);
				}
				else if(parent.duration>=task.duration && +parent.end_date > +task.end_date ) {

					parent.end_date = task.end_date;
					parent.start_date = new Date(+parent.end_date - diff + 1000);

				}
				else if(parent.duration<task.duration &&  +parent.start_date > +task.start_date ) {

					parent.start_date = task.start_date ;
					parent.end_date = new Date(+parent.start_date + diff - 1000 );
				}
				else {
					flagOk = false;
				}

				if(flagOk) {
					TAnotherTaskToSave[parent.id] = true;

				    if(dragTaskLimit(parent, +parent.duration * 86400000,modes.move) < 0) {
				    	return false;
				    }

				    gantt.refreshTask(parent.id, true);
				}

				if(!moveParentIfNeccessary(parent)) {
					return false;
				}
			}
		});
	}

	<?php
	}
	?>

	return true;
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
			if(child.id && child.objElement=="project_task") {
				TAnotherTaskToSave[child.id] = true;

				var diff_child = +child.duration * 86400000 - 1000;
			    child.start_date = new Date(+child.start_date + diff);
			    child.end_date = new Date(+child.start_date + diff_child);

			    var modes = gantt.config.drag_mode;
			    dragTaskLimit(child, diff_child,modes.move);

		       // gantt.refreshTask(child.id, true);
			recursiveRefreshTask(child.id);

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
			/*gantt.message(task.title + ' <?php echo $langs->trans('Saved') ?>');*/

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
		title: "<?php echo $langs->transnoentities('EditTask') ?>"
			,width:"80%"
			,modal:true
		});

	}


	var TPipeUWSC={};

	function updateWSCapacity(wsid, t_start, t_end,forceRefresh) { //, nb_hour_capacity = 0

		var nb_hour_capacity = 0;
		var nb_ressource = 0;
		if(workstations[wsid])
		{
			nb_hour_capacity = parseFloat(workstations[wsid].nb_hour_capacity);
			nb_ressource = parseFloat(workstations[wsid].nb_ressource);
		}

		var total_hour_capacity = nb_hour_capacity * nb_ressource;

//console.log('updateWSCapacity', wsid, t_start, t_end, nb_hour_capacity);
		var deferred = $.Deferred();

		if(TPipeUWSC[wsid]) {
			TPipeUWSC[wsid].abort();
			console.log('updateWSCapacity::Cloture appel '+wsid);
		}

		var xhr = $.ajax({
			url:"<?php echo dol_buildpath('/gantt/script/interface.php',1) ?>"
			,data:{
				get:"workstation-capacity"
				,t_start:t_start
				,t_end:t_end
				,wsid:wsid
				,scale_unit:"<?php echo $scale_unit ?>"
			}
		,dataType:"json"
		}).done(function(data) {
//console.log('nb_hour_capacity', data);
			for(wsid in data) {
			var data2 = data[wsid];
			
			for(d in data2) {
				row = data2[d];
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


                                if(wsid>0) {

			<?php
					if($scale_unit=='week') {
					    null;
					}
					else {

					    ?>
        				$('div#workstations_'+wsid).unbind().click(function(e) {

        				    var $target = $(e.target);
        				   if($target.is('.gantt_task_cell[date]')) {
        					setWSTime($target.data('wsid'), $target.attr('date'));
        				   }
        				});

					    <?php
					}
                       ?>
				}

				$ws .html(p)
					.data('dispo',dispo)
					.data('wsid',wsid)
					.data('nb_hour_capacity',row.nb_hour_capacity)
					.data('nb_ressource',row.nb_ressource)
					.removeClass('pasassez justeassez onestlarge closed normal')

				if(wsid>0) {
					$ws.addClass(bg)

					<?php
					if($scale_unit=='week') {
					    null;
					}
					else {

    					?>
    					if(row.customized==1) {
    						$ws.addClass('starred').attr('title','<?php echo $langs->transnoentities('DayCapacityModify'); ?>');
    					}
    					else {
    						$ws.removeClass('starred').removeAttr('title');
    					}
					<?php
					}
					?>
			}

			deferred.resolve();
		}
		}
	});
	
	TPipeUWSC[wsid] = xhr;

	return deferred.promise();
}


function updateWSRangeCapacityButton() {

	var sl = $("div.ws_container").scrollLeft();
    updateWSRangeCapacity(sl, 1);

}

var start_refresh_ws = 0;
var end_refresh_ws = 0;

function updateWSRangeCapacity(sl, forceRefresh) {

	<?php 

        if(!empty($conf->global->GANTT_DONT_AUTO_REFRESH_WS)) {
               echo ' if(!forceRefresh) return; ';
                
        }
    
    ?>


	var sr = sl + $('#gantt_here div.gantt_task').width();

	var date_start = gantt.dateFromPos(sl).setHours(0,0,0,0) / 1000 - (86400 * 2);
	var date_end = gantt.dateFromPos(sr).setHours(23,59,59,0) / 1000 + (86400 * 2);

	if(date_start < start_refresh_ws - (86400*2) || date_start > start_refresh_ws + (86400*2)) {
		start_refresh_ws = date_start;
		end_refresh_ws = date_end;

		$('div.workstation').each(function(ii, row) {
			$row = $(row);

			$row.css('position','relative');

			var TPosition=[];

			$row.find('div.gantt_task_cell[date]:not([opti])').each(function(i, item) {
				$item = $(item);

				var position = $item.position();

				$item.attr('opti',1);


				TPosition.push([$item, position.left]);

			});

			for(x in TPosition) {
				var $item =TPosition[x][0];
				$item.css({
					'position':'absolute'
					,'top':0
					,'left':TPosition[x][1]+'px'
				});
			}

			$row.find('div.gantt_task_cell[date]').each(function(i, item) {
				$item = $(item);

				var d = new Date($item.attr('date'));

				if(+d <= date_end * 1000 && +d >= date_start * 1000) {
				//	$item.css('visibility','visible');
					$item.show();
				}
				else {
					//$item.css('visibility','hidden');
					$item.hide();
				}

			});
		});

<?php 
$TabToUpdateWSCapacity=array();
foreach($TWS as &$ws) {
    if($ws->type!='STT' && !is_null($ws->id) && $ws->id>0 ) {
        $TabToUpdateWSCapacity[] = $ws->id;
    }
}

echo 'updateWSCapacity("0,'.implode(',',$TabToUpdateWSCapacity).'", date_start, date_end);';

?>

	}
}

updateAllCapacity();

$(document).ajaxStart(function() {
	$("#ajax-waiter").show();
});

$(document).ajaxStop(function() {
	$("#ajax-waiter").hide();
});

function downloadThisGanttAsCSV() {
//console.log(tasks);
	let csvContent = "data:text/csv;charset=utf-8,";

	csvContent+="Gantt Id,Parent Id,Element,Ref,Texte,Nb ressource,Poste,Début,Fin,Limite basse, Limite haute,\r\n"

	for(x in tasks.data) {
		task = tasks.data[x];
		//console.log(task);

		if(task.objElement == 'project_task') {
			date_debut =task.start_date > 0 ? task.start_date.toLocaleDateString() : "";
			date_fin =task.end_date > 0 ? task.end_date.toLocaleDateString() : "";
		}
		else {
			date_fin = task.date_max > 0 ? (new Date(task.date_max*1000)).toLocaleDateString() : "";
			date_debut="";
		}

		date_limit_basse = task.time_task_limit_no_before>0 ? (new Date(task.time_task_limit_no_before*1000)).toLocaleDateString() : "";
		date_limit_haute = task.time_task_limit_no_after>0 ? (new Date(task.time_task_limit_no_after*1000)).toLocaleDateString() : "";

		csvContent += task.id + ","
					+task.parent+","
					+task.objElement+","
					+(task.ref ? task.ref : "")+","
					+he.decode(task.text)+","
					+(task.needed_ressource ? task.needed_ressource : "") +","
					+(task.workstation ? workstations[task.workstation].name : "")+","
					+date_debut+","
					+date_fin+","
					+date_limit_basse+","
					+date_limit_haute+","

					+"\r\n";

	}

//	var encodedUri = encodeURI(csvContent);
//	window.open(encodedUri);

	csvdata = csvContent;

        var byteNumbers = new Uint8Array(csvdata.length);

		for (var i = 0; i < csvdata.length; i++)
		{
			byteNumbers[i] = csvdata.charCodeAt(i);
		}
		var blob = new Blob([byteNumbers], {type: "text/csv"});

        // Construct the uri
		var uri = URL.createObjectURL(blob);

		// Construct the <a> element
		var link = document.createElement("a");
		link.download = 'planning.csv';
		link.href = uri;

		document.body.appendChild(link);
		link.click();

		// Cleanup the DOM
		document.body.removeChild(link);
		delete link;

}

