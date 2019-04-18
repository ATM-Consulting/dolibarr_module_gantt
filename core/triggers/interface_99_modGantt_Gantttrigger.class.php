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
 * 	\file		core/triggers/interface_99_modMyodule_Gantttrigger.class.php
 * 	\ingroup	gantt
 * 	\brief		Sample trigger
 * 	\remarks	You can create other triggers by copying this one
 * 				- File name should be either:
 * 					interface_99_modMymodule_Mytrigger.class.php
 * 					interface_99_all_Mytrigger.class.php
 * 				- The file must stay in core/triggers
 * 				- The class name must be InterfaceMytrigger
 * 				- The constructor method must be named InterfaceMytrigger
 * 				- The name property name must be Mytrigger
 */

/**
 * Trigger class
 */
class InterfaceGantttrigger
{

    private $db;

    /**
     * Constructor
     *
     * 	@param		DoliDB		$db		Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;

        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = "demo";
        $this->description = "Triggers of this module are empty functions."
            . "They have no effect."
            . "They are provided for tutorial purpose only.";
        // 'development', 'experimental', 'dolibarr' or version
        $this->version = 'development';
        $this->picto = 'gantt@gantt';
    }

    /**
     * Trigger name
     *
     * 	@return		string	Name of trigger file
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Trigger description
     *
     * 	@return		string	Description of trigger file
     */
    public function getDesc()
    {
        return $this->description;
    }

    /**
     * Trigger version
     *
     * 	@return		string	Version of trigger file
     */
    public function getVersion()
    {
        global $langs;
        $langs->load("admin");

        if ($this->version == 'development') {
            return $langs->trans("Development");
        } elseif ($this->version == 'experimental')

                return $langs->trans("Experimental");
        elseif ($this->version == 'dolibarr') return DOL_VERSION;
        elseif ($this->version) return $this->version;
        else {
            return $langs->trans("Unknown");
        }
    }

    /**
     * Function called when a Dolibarrr business event is done.
     * All functions "run_trigger" are triggered if file
     * is inside directory core/triggers
     *
     * 	@param		string		$action		Event action code
     * 	@param		Object		$object		Object
     * 	@param		User		$user		Object user
     * 	@param		Translate	$langs		Object langs
     * 	@param		conf		$conf		Object conf
     * 	@return		int						<0 if KO, 0 if no triggered ran, >0 if OK
     */
    public function run_trigger($action,$object, $user, $langs, $conf)
    {

    	if($action === 'TASK_CREATE') {
			dol_include_once('/gantt/class/gantt.class.php');
			dol_include_once('/projet/class/project.class.php');
			$db = &$object->db;
			$project = new Project($db);
			$project->fetch($object->fk_project);

			$t_current = time();
			if (!empty($object->fk_task_parent)) // si la tâche a un parent elle ne peut débuter qu'après la fin de celui-ci
			{
			    $parent = new Task($db);
			    $parent->fetch($object->fk_task_parent);
			    $t_current = $parent->date_end;
			}
/*
			if(!empty($object->array_options['options_fk_of']) && date('Ymd',$t_current) === date('Ymd')){
			    $t_current = $object->date_start;
            }
*/

			$t_start =  max( $project->date_start, $t_current);
			
			$day_range = empty($conf->global->GANTT_DAY_RANGE_FROM_NOW) ? 90 : $conf->global->GANTT_DAY_RANGE_FROM_NOW;

			$t_end =  $project->date_end > $t_current ? $project->date_end : strtotime('+'.$day_range.' day', $t_start);

			if($t_end>=$t_current) {
				$TWS=array();
				$Tab = GanttPatern::get_better_task($TWS, $object,$t_start, $t_end);

				if($Tab['start']>0 && $Tab['duration']>0) {

					$object->date_start = $Tab['start'];
					$object->date_end = !empty($Tab['end'])?$Tab['end']:$object->date_start + ( $Tab['duration'] * 86400 ) - 1;
					$res = $object->update($user);
					if($res<=0) {

						var_dump($object);exit;
					}
				}
			}
    	}

        return 0;
    }
}
