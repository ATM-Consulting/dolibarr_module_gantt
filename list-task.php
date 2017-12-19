<?php 

	require 'config.php';
	
	
	llxHeader();
	
	$l=new Listview($db, 'listTask');
	
	$sql="SELECT t.rowid,tex.fk_of,tex.fk_workstation FROM ".MAIN_DB_PREFIX."projet_task t
				LEFT JOIN ".MAIN_DB_PREFIX."projet_task_extrafields tex ON (tex.fk_object = t.rowid) ";
	
	echo $l->render($sql,array(
			'title'=>array(
				'fk_of'=>$langs->trans('AssetOf')	
				,'fk_workstation'=>$langs->trans('Workstation')
			)
			,'eval'=>array(
				'fk_of'=>'_getOFlabel(@val@)'
					
			)
			
	));
	
	llxFooter();
	
	function _getOFlabel($fk_of) {
		
		global $langs;
		
		dol_include_once('/of/class/ordre_fabrication_asset.class.php');
		
		$PDOdb = new TPDOdb;
		$of=new TAssetOF();
		if($of->load($PDOdb, (int)$fk_of)) {
			
			return $of->getNomUrl(1);
		
		}
		else {
			return $langs->trans('OFDeleted');
		}
		
	}