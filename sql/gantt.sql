ALTER TABLE  llx_asset_workstation_task ADD INDEX ( fk_workstation );
ALTER TABLE  llx_projet_task_extrafields ADD INDEX (  fk_of );
ALTER TABLE  llx_projet_task_extrafields ADD INDEX (  fk_gantt_parent_task );
ALTER TABLE  llx_projet_task ADD INDEX (  dateo );
ALTER TABLE  llx_projet_task ADD INDEX (  datee );
ALTER TABLE  llx_projet_task ADD INDEX ( progress );
ALTER TABLE  llx_projet_task ADD INDEX ( planned_workload );
ALTER TABLE  llx_projet ADD INDEX (  fk_statut );
ALTER TABLE  `llx_assetOf` ADD INDEX (  `status` );
ALTER TABLE  llx_projet ADD INDEX (  dateo );
ALTER TABLE  llx_projet ADD INDEX (  datee );


