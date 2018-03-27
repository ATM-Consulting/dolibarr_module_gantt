<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file    class/actions_gantt.class.php
 * \ingroup gantt
 * \brief   This file is an example hook overload class file
 *          Put some comments here
 */

/**
 * Class ActionsGantt
 */
class ActionsGantt
{
	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var array Errors
	 */
	public $errors = array();

	/**
	 * Constructor
	 */
	public function __construct()
	{
	}

	/**
	 * Overloading the doActions function : replacing the parent's function with the one below
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    &$object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          &$action        Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */

	function getCalendarEvents($parameters, &$object, &$action, $hookmanager)
	{
	    $TContext = explode(':', $parameters['context']);

	    if (in_array('agenda', $TContext) || in_array('projectcard', $TContext))
	    {
            global $conf,$db;

            if(!empty($conf->global->GANTT_SHOW_TASK_INTO_CALENDAR_VIEW)) {

                $month = GETPOST('month');
                $year = GETPOST('year');

                if(empty($month)) {
                    $time = time();
                }
                else {
                    $time = strtotime($year.'-'.$month.'-01');
                }

                $start = date('Y-m-01',$time);
                $end = date('Y-m-t',$time);

                $fk_project = (int)GETPOST('projectid');

                dol_include_once('/gantt/class/gantttask.class.php');
                dol_include_once('/gantt/class/gantt.class.php');
                $TTaskObject = GanttPatern::getTasks($start, $end, $fk_project);

                if(!empty($TTaskObject)) {

                    foreach($TTaskObject as $task) {

                        if($task->date_end<$task->date_start)$task->date_end = $task->date_start;

                        $task->userassigned=array();
                        $TContact = $task->getListContactId();
                        if(!empty($TContact)) {
                            foreach($TContact as $fk_contact) {
                                $task->userassigned[$fk_contact] = array('id'=>$fk_contact);
                            }


                        }

                        $gantttask = unserialize(strtr(serialize($task),array('O:4:"Task"'=>'O:9:"GanttTask"'))); //hop hop y a un lapin dans le chapeau

                        $daycursor=$gantttask->date_start;


                        while($daycursor<=$task->date_end) {

                            $annee = date('Y',$daycursor);
                            $mois = date('m',$daycursor);
                            $jour = date('d',$daycursor);
                            $daykey=dol_mktime(0,0,0,$mois,$jour,$annee);


                            $this->results['eventarray'][$daykey][] = $gantttask;

                            $daycursor=strtotime('+1day',$daycursor);
                        }


                    }

                    return 1;

                }


            }



	    }

	}

	function formObjectOptions($parameters, &$object, &$action, $hookmanager)
	{

		$TContext = explode(':', $parameters['context']);
		if (in_array('projecttaskcard', $TContext) || in_array('projectcard', $TContext))
		{
			if($action === 'edit') {
				?>
				<script type="text/javascript">
				$(document).ready(function() {
					$input = $('input[name=options_color]');
					if($input.val() == '') $input.val('#ffffff');
					$input.attr('type','color');
				});
				</script>

				<?php

			}

		}

	}
}