<?php

	require 'config.php';
	dol_include_once('/projet/class/project.class.php');
	dol_include_once('/core/lib/project.lib.php');
	
	$id = GETPOST('fk_project');
	
	$object = new Project($db);
	$object->fetch($id);
	
	// Security check
	$socid=0;
	if ($user->societe_id > 0) $socid=$user->societe_id;
	$result = restrictedArea($user, 'projet', $id,'projet&project');
	
	$langs->load("users");
	$langs->load("projects");
	
	
	llxHeader();
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
	
	echo '<iframe id="frmGantt" src="gantt.php?fk_project='.$object->id.'" width="100%" frameborder="0" ></iframe>';
	
	dol_fiche_end();
	
?>
<script type="text/javascript">
	$(document).ready(function() {

		$window = $(window);
	    $('iframe').height(function(){

			var h = $window.height()-$(this).offset().top;

			if(h<1000) h = 1000;
		    
	        return h;   
	    });
		
	});

</script>
<?php 
	
	llxFooter();
	