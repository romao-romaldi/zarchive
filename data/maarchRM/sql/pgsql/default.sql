-- AUTH
INSERT INTO auth.account ("accountId", "accountName", "displayName", "accountType", "emailAddress", enabled, password, "passwordChangeRequired", "passwordLastChange", locked, "lockDate", "badPasswordCount", "lastLogin", "lastIp", "replacingUserAccountId", "firstName", "lastName", title, salt, "tokenDate", authentication, preferences, "ownerOrgId", "isAdmin") VALUES 
('superadmin', 'superadmin', 'super admin', 'user', 'support@zeliaconsultings.com', true, '186cf774c97b60a1c106ef718d10970a6a06e06bef89553d9ae65d938a886eae', false, NULL, false, NULL, 0, NULL, NULL, NULL, 'Admin', 'Super', 'M.', NULL, NULL, NULL, NULL, NULL, true);

-- ROLE
INSERT INTO "auth"."role"("roleId", "roleName", "description", "securityLevel", "enabled") VALUES
('ADMING', 'Administrateur Général', 'Groupe administrateur général', 'gen_admin', true),
('ADMINF', 'Administrateur Fonctionnel', 'Groupe administrateur fonctionnel', 'func_admin', true);

-- roleMember
INSERT INTO "auth"."roleMember"("roleId", "userAccountId") VALUES
('ADMING', 'superadmin');

-- privilege
INSERT INTO "auth"."privilege"("roleId", "userStory") VALUES
('ADMING', 'adminTech/*'),
('ADMING', 'adminFunc/adminOrganization'),
('ADMING', 'adminFunc/adminUseraccount'),
('ADMING', 'adminFunc/adminServiceaccount'),
('ADMING', 'adminFunc/batchScheduling'),
('ADMING', 'adminFunc/adminAuthorization'),
('ADMING', 'journal/audit'),
('ADMING', 'journal/searchLogArchive'),
('ADMINF', 'adminFunc/adminOrganization'),
('ADMINF', 'adminFunc/adminUseraccount'),
('ADMINF', 'adminFunc/adminServiceaccount'),
('ADMINF', 'adminFunc/adminOrgUser');

-- LIFECYCLE
INSERT INTO "lifeCycle"."eventFormat" (type, format, message, notification) VALUES
('digitalResource/integrityCheck', 'repositoryReference addressesToCheck checkedAddresses failed', 'Contrôle d''intégrité des ressources présentes dans %6$s', false),
('recordsManagement/accessRuleModification', 'resId hashAlgorithm hash address accessRuleStartDate accessRuleDuration previousAccessRuleStartDate previousAccessRuleDuration originatorOrgRegNumber archiverOrgRegNumber originatorArchiveId', 'Modification de la règle de communicabilité de l''archive %6$s', false),
('recordsManagement/addRelationship', 'resId hashAlgorithm hash address originatorOrgRegNumber archiverOrgRegNumber relatedArchiveId originatorArchiveId', 'Relation ajoutée avec l''archive %6$s', false),
('recordsManagement/archivalProfileModification', 'archivalProfileReference', 'Modification du profil %6$s.', false),
('recordsManagement/consultation', 'resId hash hashAlgorith address size originatorArchiveId', 'Consultation de la ressource %9$s', false),
('recordsManagement/conversion', 'resId hashAlgorithm hash address convertedResId convertedHashAlgorithm convertedHash convertedAddress software docId size originatorArchiveId', 'Conversion du document %18$s', false),
('recordsManagement/deleteRelationship', 'resId hashAlgorithm hash address originatorOrgRegNumber archiverOrgRegNumber relatedArchiveId originatorArchiveId', 'Relation avec l''archive %6$s supprimée', false),
('recordsManagement/delivery', 'resId hashAlgorithm hash address requesterOrgRegNumber archiverOrgRegNumber size originatorArchiveId', 'Communication de l''archive %6$s', false),
('recordsManagement/deposit', 'resId hashAlgorithm hash address originatorOrgRegNumber depositorOrgRegNumber archiverOrgRegNumber format size originatorArchiveId', 'Dépôt de l''archive %6$s', false),
('recordsManagement/destructionRequest', 'resId hashAlgorithm hash address originatorOrgRegNumber archiverOrgRegNumber size originatorArchiveId', 'Demande de destruction de l''archive %6$s', false),
('recordsManagement/destructionRequestCanceling', 'resId hashAlgorithm hash address originatorOrgRegNumber archiverOrgRegNumber size originatorArchiveId', 'Annulation de la demande de destruction de l''archive %6$s', false),
('recordsManagement/destruction', 'resId hashAlgorithm hash address originatorOrgRegNumber archiverOrgRegNumber size originatorArchiveId', 'Destruction de l''archive %6$s', false),
('recordsManagement/elimination', 'resId hashAlgorithm hash address originatorOrgRegNumber archiverOrgRegNumber size originatorArchiveId', 'Élimination de l''archive %6$s', false),
('recordsManagement/freeze', 'resId hashAlgorithm hash address originatorOrgRegNumber archiverOrgRegNumber originatorArchiveId', 'Gel de l''archive %6$s', false),
('recordsManagement/integrityCheck', 'resId hash hashAlgorithm address requesterOrgRegNumber info originatorArchiveId', 'Validation d''intégrité', false),
('recordsManagement/metadataModification', 'resId hashAlgorithm hash address originatorOrgRegNumber archiverOrgRegNumber originatorArchiveId', 'Modification des métadonnées de l''archive %6$s', false),
('recordsManagement/profileCreation', 'archivalProfileReference', 'Création du profil %6$s', false),
('recordsManagement/profileDestruction', 'archivalProfileReference', 'Destruction du profil %6$s', false),
('recordsManagement/periodicIntegrityCheck', 'startDatetime endDatetime nbArchivesToCheck nbArchivesInSample archivesChecked originatorArchiveId', 'Validation périodique de l''intégrité', false),
('recordsManagement/restitution', 'resId hashAlgorithm hash address originatorOrgRegNumber archiverOrgRegNumber size originatorArchiveId', 'Restitution de l''archive %6$s', false),
('recordsManagement/retentionRuleModification', 'resId hashAlgorithm hash address retentionStartDate retentionDuration finalDisposition previousStartDate previousDuration previousFinalDisposition originatorOrgRegNumber archiverOrgRegNumber originatorArchiveId', 'Modification de la règle de conservation de l''archive %6$s', false),
('recordsManagement/unfreeze', 'resId hashAlgorithm hash address originatorOrgRegNumber archiverOrgRegNumber originatorArchiveId', 'Dégel de l''archive %6$s', false),
('recordsManagement/resourceDestruction', 'resId hashAlgorithm hash address originatorOrgRegNumber archiverOrgRegNumber originatorArchiveId', 'Destruction de la ressource %9$s', FALSE),
('recordsManagement/depositNewResource', 'resId hashAlgorithm hash address originatorOrgRegNumber depositorOrgRegNumber archiverOrgRegNumber format size originatorArchiveId', 'Dépôt d''une ressource dans l''archive', FALSE),
('medona/sending', 'type senderOrgRegNumber senderOrgName recipientOrgRegNumber recipientOrgName reference code info', 'Envoi du message %14$s de type %9$s de %11$s (%10$s) à %13$s (%12$s)', false),
('medona/reception', 'type senderOrgRegNumber senderOrgName recipientOrgRegNumber recipientOrgName reference', 'Réception du message %14$s de type %9$s de %11$s (%10$s) par %13$s (%12$s)', false),
('medona/validation', 'type senderOrgRegNumber senderOrgName recipientOrgRegNumber recipientOrgName reference code info', 'Validation du message %14$s : %16$s (%15$s)', false),
('medona/acknowledgement', 'type senderOrgRegNumber senderOrgName recipientOrgRegNumber recipientOrgName reference code info', 'Acquittement du message %14$s : %16$s (%15$s)', false),
('medona/authorization', 'type senderOrgRegNumber senderOrgName recipientOrgRegNumber recipientOrgName reference', 'Traitement du message %14$s de type %9$s de %11$s (%10$s) par %13$s (%12$s)', false),
('medona/processing', 'type senderOrgRegNumber senderOrgName recipientOrgRegNumber recipientOrgName reference', 'Traitement du message %14$s de type %9$s de %11$s (%10$s) par %13$s (%12$s)', false),
('medona/acceptance', 'type senderOrgRegNumber senderOrgName recipientOrgRegNumber recipientOrgName reference', 'Message %14$s de type %9$s accepté par %13$s (%12$s)', false),
('medona/rejection', 'type senderOrgRegNumber senderOrgName recipientOrgRegNumber recipientOrgName reference', 'Message %14$s de type %9$s rejeté par %13$s (%12$s)', false),
('medona/retry', 'type senderOrgRegNumber senderOrgName recipientOrgRegNumber recipientOrgName reference', 'Message %14$s de type %9$s réinitialisé par %13$s (%12$s)', false),
('organization/counting', 'orgName ownerOrgId', 'Compter le nombre d''objets numériques dans l''activité %6$s', false),
('organization/listing', 'orgName ownerOrgId', 'Lister les identifiants d''objets numériques de l''activité %6$s', false),
('organization/journal', 'orgName ownerOrgId', 'Lecture du journal de l''organisation %6$s', false),
('recordsManagement/completenessCheck', 'lastCheckedResId lastCheckedResCreated repositoryReference resourcesToCheck checkedResources failed timeout timeoutError', 'Contrôle d''exhaustivité des ressources', false);
