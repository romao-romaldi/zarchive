INSERT INTO "lifeCycle"."eventFormat" ("type","format","message","notification") VALUES
('recordsManagement/completenessCheck', 'lastCheckedResId lastCheckedResCreated repositoryReference resourcesToCheck checkedResources failed timeout timeoutError', 'Contrôle d''exhaustivité des ressources', false);

INSERT INTO "batchProcessing"."scheduling" ("schedulingId","name","taskId","frequency","parameters","executedBy","lastExecution","nextExecution","status") VALUES
('completeness', 'Exhaustivité', '14', '00;08;;;;;;;', '30 3600', 'System', NULL, NULL, 'paused');
