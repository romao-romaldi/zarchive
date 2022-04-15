<?php

/*
 * Copyright (C) 2015 Maarch
 *
 * This file is part of bundle recordsManagement.
 *
 * Bundle recordsManagement is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Bundle recordsManagement is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with bundle recordsManagement.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace bundle\recordsManagement\Controller;

/**
 * Trait for archives modification
 */
trait archiveModificationTrait
{

    /**
     * Read the retention rule of an archive
     * @param string $archiveId The archive identifier
     *
     * @return recordsManagement/archiveRetentionRule The retention rule object
     */
    public function editArchiveRetentionRule($archiveId)
    {
        $archive = $this->sdoFactory->read('recordsManagement/archive', $archiveId);

        if ($archive->status !== 'preserved') {
            throw new \bundle\recordsManagement\Exception\retentionRuleException(
                "An archive that does not have the status 'preserved' can't be modified."
            );
        }

        return \laabs::castMessage($archive, 'recordsManagement/archiveRetentionRule');
    }

    /**
     * Read the access rule of an archive
     * @param string $archiveId The archive identifier
     *
     * @return recordsManagement/archiveAccessRule The access rule updated
     */
    public function editArchiveAccessRule($archiveId)
    {
        $archive = $this->sdoFactory->read('recordsManagement/archive', $archiveId);

        if ($archive->status !== 'preserved') {
            throw new \bundle\recordsManagement\Exception\accessRuleException(
                "An archive that does not have the status 'preserved' can't be modified."
            );
        }

        $this->getAccessRule($archive);

        return \laabs::castMessage($archive, 'recordsManagement/archiveAccessRule');
    }

    /**
     * Modify the archive retention rule
     *
     * @param recordsManagement/archiveRetentionRule $retentionRule The retention rule object
     * @param mixed                                  $archiveIds    The archives ids
     * @param null $comment
     * @param null $identifier
     * @param string $format
     *
     * @return array
     * @throws \Exception
     */
    public function modifyRetentionRule($retentionRule, $archiveIds, $comment = null, $identifier = null, $format = null)
    {
        // #10629 Get finalDisposition and duration from ref when empty and code is received
        if ((empty($retentionRule->finalDisposition)
                || empty($retentionRule->retentionDuration))
            && !empty($retentionRule->retentionRuleCode)
        ) {
            $refRetentionRule = $this->retentionRuleController->read($retentionRule->retentionRuleCode);

            if (empty($retentionRule->finalDisposition)) {
                $retentionRule->finalDisposition = $refRetentionRule->finalDisposition;
            }

            if (empty($retentionRule->retentionDuration)) {
                $retentionRule->retentionDuration = $refRetentionRule->duration;
            }
        }


        $retentionRuleReceived = $retentionRule;

        if (!is_array($archiveIds)) {
            $archiveIds = array($archiveIds);
        }

        $res = array('success' => array(), 'error' => array());

        $archives = array();

        if (!\laabs::getToken("ORGANIZATION")) {
            throw \laabs::newException(
                'recordsManagement/noOrgUnitException',
                "Permission denied: You have to choose a working organization unit to proceed this action."
            );
        }

        foreach ($archiveIds as $archiveId) {
            $archive = $this->retrieve($archiveId);
            $this->checkRights($archive);

            if (!in_array($archive->status, array("preserved"))) {
                array_push($res['error'], $archiveId);

                $operationResult = false;
            } else {
                $retentionRule = clone($retentionRuleReceived);

                $retentionRule->archiveId = $archiveId;

                // Update current object for caller
                if ($retentionRule->changeStartDate === false) {
                    $retentionRule->retentionStartDate = $archive->retentionStartDate;
                }

                if (empty($retentionRule->retentionRuleCode)) {
                    $retentionRule->retentionRuleCode = $archive->retentionRuleCode;
                    $retentionRule->retentionDuration = $archive->retentionDuration;
                }

                if ($retentionRule->finalDisposition === null) {
                    $retentionRule->finalDisposition = $archive->finalDisposition;
                } elseif ($retentionRule->finalDisposition === '') {
                    $retentionRule->finalDisposition = null;
                }

                if ($retentionRule->retentionDuration === '') {
                    $retentionRule->retentionDuration = null;
                } elseif (!empty($retentionRule->retentionDuration) && $retentionRule->retentionDuration->y >= 9999) {
                    $retentionRule->disposalDate = null;
                } else {
                    if (!empty($retentionRule->retentionDuration) && !empty($retentionRule->retentionStartDate)) {
                        $retentionRule->disposalDate = $this->calculateDate(
                            $retentionRule->retentionStartDate,
                            $retentionRule->retentionDuration
                        );
                    }
                }

                $retentionRule->retentionRuleStatus = "current";

                $retentionRule->lastModificationDate = \laabs::newTimestamp();

                $this->sdoFactory->update($retentionRule, 'recordsManagement/archive');

                $retentionRule->previousStartDate = $archive->retentionStartDate;
                $retentionRule->previousDuration = $archive->retentionDuration;
                $retentionRule->previousFinalDisposition = $archive->finalDisposition;

                $archive->retentionStartDate = $retentionRule->retentionStartDate;
                $archive->retentionDuration = $retentionRule->retentionDuration;
                $archive->finalDisposition = $retentionRule->finalDisposition;

                array_push($res['success'], $archiveId);

                $operationResult = true;

                $archives[] = $archive;

                // Life cycle journal
                $this->logRetentionRuleModification($archive, $retentionRule, $operationResult);
            }
        }

        if (isset(\laabs::configuration("medona")['transaction']) && \laabs::configuration("medona")['transaction']) {
            $this->sendModificationNotification($archives, $comment, $identifier, $format);
        }

        return $res;
    }

    /**
     * Modify the archive access
     *
     * @param recordsManagement/archiveAccessCode $accessRule The access rule object
     * @param array                               $archiveIds The archives ids
     * @param null $comment
     * @param null $identifier
     * @param string $format
     *
     * @return array
     */
    public function modifyAccessRule($accessRule, $archiveIds, $comment = null, $identifier = null, $format = null)
    {
        if (!is_array($archiveIds)) {
            $archiveIds = array($archiveIds);
        }

        $res = array('success' => array(), 'error' => array());

        $archives = array();
        $operationResult = null;

        // #10629 Get duration from ref when empty and code is received
        if (empty($accessRule->accessRuleDuration) && !empty($accessRule->accessRuleCode)) {
            $refAccessRule = $this->accessRuleController->edit($accessRule->accessRuleCode);

            $accessRule->accessRuleDuration = $refAccessRule->duration;
        }

        $accessRuleReceived = $accessRule;

        foreach ($archiveIds as $archiveId) {
            $archive = $this->retrieve($archiveId);
            $this->checkRights($archive);

            if (!in_array($archive->status, array("preserved"))) {
                array_push($res['error'], $archiveId);

                $operationResult = false;
            } else {
                $accessRule = clone($accessRuleReceived);

                $accessRule->archiveId = $archiveId;

                if ($accessRule->changeStartDate === false) {
                    $accessRule->accessRuleStartDate = $archive->accessRuleStartDate;
                }

                if (empty($accessRule->accessRuleCode)) {
                    $accessRule->accessRuleCode = $archive->accessRuleCode;
                    $accessRule->accessRuleDuration = $archive->accessRuleDuration;
                }

                if ($accessRule->accessRuleDuration === '') {
                    $accessRule->accessRuleDuration = null;
                } elseif (!empty($accessRule->accessRuleDuration) && $accessRule->accessRuleDuration->y >= 9999) {
                    $accessRule->accessRuleComDate = null;
                } elseif (!empty($accessRule->accessRuleDuration) && !empty($accessRule->accessRuleStartDate)) {
                    $accessRule->accessRuleComDate = $this->calculateDate(
                        $accessRule->accessRuleStartDate,
                        $accessRule->accessRuleDuration
                    );
                }

                $accessRule->lastModificationDate = \laabs::newTimestamp();

                $this->sdoFactory->update($accessRule, 'recordsManagement/archive');

                $accessRule->previousAccessRuleStartDate = $archive->accessRuleStartDate;
                $accessRule->previousAccessRuleDuration = $archive->accessRuleDuration;

                $archive->accessRuleStartDate = $accessRule->accessRuleStartDate;
                $archive->accessRuleDuration = $accessRule->accessRuleDuration;
                $archive->accessRuleComDate = $accessRule->accessRuleComDate;

                array_push($res['success'], $archiveId);

                $operationResult = true;

                $archives[] = $archive;

                // Life cycle journal
                $this->logAccessRuleModification($archive, $accessRule, $operationResult);
            }
        }

        if (isset(\laabs::configuration("medona")['transaction']) && \laabs::configuration("medona")['transaction']) {
            $this->sendModificationNotification($archives, $comment, $identifier, $format);
        }

        return $res;
    }

    /**
     * Suspend archives
     * @param mixed $archiveIds Array of archive identifier
     * @param null $comment
     * @param null $identifier
     * @param string $format
     *
     * @return array
     */
    public function freeze($archiveIds, $comment = null, $identifier = null, $format = null)
    {
        if (!is_array($archiveIds)) {
            $archiveIds = array($archiveIds);
        }

        $archives = array();

        foreach ($archiveIds as $archiveId) {
            $archive = $this->retrieve($archiveId);
            $this->checkRights($archive);

            $archives[$archiveId] = $archive;
        }

        $res = $this->setStatus($archiveIds, 'frozen');
        $this->setStatus($archiveIds, 'preserved', false, false, true);


        for ($i = 0, $count = count($res['success']); $i < $count; $i++) {
            if (array_key_exists($res['success'][$i], $archives)) {
                $archive = $archives[$res['success'][$i]];
            } else {
                $archive = $this->retrieve($res['success'][$i]);
            }
            $this->logFreeze($archive, true);
        }

        for ($i = 0, $count = count($res['error']); $i < $count; $i++) {
            $archive = $archives[$res['error'][$i]];
            $this->logFreeze($archive, false);
        }

        if (isset(\laabs::configuration("medona")['transaction']) && \laabs::configuration("medona")['transaction']) {
            $this->sendModificationNotification($archives, $comment, $identifier, $format);
        }

        return $res;
    }

    /**
     * Liberate archives
     * @param mixed $archiveIds Array of archive identifier
     * @param null $comment
     * @param null $identifier
     * @param string $format
     *
     * @return array
     */
    public function unfreeze($archiveIds, $comment = null, $identifier = null, $format = null)
    {
        if (!is_array($archiveIds)) {
            $archiveIds = array($archiveIds);
        }

        $archives = array();

        foreach ($archiveIds as $archiveId) {
            $archive = $this->retrieve($archiveId);
            $this->checkRights($archive);

            $archives[$archiveId] = $archive;
        }

        $res = $this->setStatus($archiveIds, 'preserved', true);


        for ($i = 0, $count = count($res['success']); $i < $count; $i++) {
            if (array_key_exists($res['success'][$i], $archives)) {
                $archive = $archives[$res['success'][$i]];
            } else {
                $archive = $this->retrieve($res['success'][$i]);
            }
            $this->logUnfreeze($archive, true);
        }

        for ($i = 0, $count = count($res['error']); $i < $count; $i++) {
            $archive = $archives[$res['error'][$i]];
            $this->logUnfreeze($archive, false);
        }

        if (isset(\laabs::configuration("medona")['transaction']) && \laabs::configuration("medona")['transaction']) {
            $this->sendModificationNotification($archives, $comment, $identifier, $format);
        }

        return $res;
    }

    /**
     * Update metadata of archive
     * @param string $archiveId
     * @param string $originatorArchiveId
     * @param string $archiverArchiveId
     * @param string $archiveName
     * @param object $description
     * @param date   $originatingDate
     *
     * @return boolean The result of the operation
     */
    public function modifyMetadata(
        $archiveId,
        $originatorArchiveId = null,
        $archiverArchiveId = null,
        $archiveName = null,
        $originatingDate = null,
        $description = null,
        $checkAccess = true
    ) {
        $archive = $this->retrieve($archiveId, false, $checkAccess);

        if ($checkAccess) {
            $this->checkRights($archive);
        }

        if (!empty($archive->archivalProfileReference)) {
            $archivalProfileController = \laabs::newController('recordsManagement/archivalProfile');
            $archivalProfileDescription = $archivalProfileController->getByReference(
                $archive->archivalProfileReference
            )->archiveDescription;
        }

        if ($archiveName) {
            $archive->archiveName = $archiveName;
        }

        $archive->originatorArchiveId = $originatorArchiveId;

        if ($archiverArchiveId) {
            $archive->archiverArchiveId = $archiverArchiveId;
        }

        $archive->originatingDate = $originatingDate;

        $archive->lastModificationDate = \laabs::newTimestamp();

        $descriptionController = $this->useDescriptionController($archive->descriptionClass);
        $serviceLevel = $this->serviceLevelController->getByReference($archive->serviceLevelReference);
        if (strpos($serviceLevel->control, 'fullTextIndexation') !== false) {
            $text = $descriptionController->read($archiveId, false)->text;
            $fullText = substr($text, strpos($text, PHP_EOL));
        }

        if (!empty($description)) {
            $descriptionObject = $description;

            if (!empty($archivalProfileDescription)) {
                foreach ($archivalProfileDescription as $descriptionImmutable) {
                    if ($descriptionImmutable->isImmutable) {
                        $fieldName = (string)$descriptionImmutable->fieldName;
                        if (isset($descriptionObject->$fieldName)) {
                            if (is_array($descriptionObject->$fieldName)) {
                                $archiveNewField = $descriptionObject->$fieldName;
                                $archiveOldField = $archive->descriptionObject->$fieldName;
                                sort($archiveNewField);
                                sort($archiveOldField);
                                if (is_object($descriptionObject->{$fieldName}[0])) {
                                    foreach ($archiveNewField as $index => $object) {
                                        if ($archiveOldField[$index] != $object) {
                                            throw new \bundle\recordsManagement\Exception\invalidArchiveException(
                                                'Attempt to modify readonly field(s)'
                                            );
                                        }
                                    }
                                } else {
                                    if (count($archiveNewField) != count($archiveOldField)
                                        || array_diff($archiveNewField, $archiveOldField)
                                    ) {
                                        throw new \bundle\recordsManagement\Exception\invalidArchiveException(
                                            'Attempt to modify readonly field(s)'
                                        );
                                    }
                                }
                            } elseif (!isset($archive->descriptionObject->$fieldName)) {
                                throw new \bundle\recordsManagement\Exception\invalidArchiveException(
                                    'Attempt to modify readonly field(s)'
                                );
                            } elseif ($descriptionObject->$fieldName != $archive->descriptionObject->$fieldName) {
                                throw new \bundle\recordsManagement\Exception\invalidArchiveException(
                                    'Attempt to modify readonly field(s)'
                                );
                            }
                        }
                    }
                }
            }

            if (!empty($archive->archivalProfileReference)
                && is_object($descriptionObject)
                && \laabs::configuration("recordsManagement")['archivalProfileType'] != 1
            ) {
                $this->useArchivalProfile($archive->archivalProfileReference);

                $this->validateDescriptionModel($descriptionObject, $this->currentArchivalProfile);
            }

            $archive->descriptionObject = $descriptionObject;

            if (strpos($serviceLevel->control, 'fullTextIndexation') !== false) {
                $descriptionController->update($archive, $fullText);
            } else {
                $descriptionController->update($archive);
            }
        }

        $this->sdoFactory->update($archive, 'recordsManagement/archive');

        $operationResult = true;
        $res = true;

        $this->logMetadataModification($archive, $operationResult);

        if (isset(\laabs::configuration("medona")['transaction']) && \laabs::configuration("medona")['transaction']) {
            $this->sendModificationNotification([$archive]);
        }

        return $res;
    }

    /**
     * Add a relationship to the archive
     * @param recordsManagement/archiveRelationship $archiveRelationship The relationship of the archive
     *
     * @return bool The result of the operation
     */
    public function addRelationship($archiveRelationship)
    {
        $this->archiveRelationshipController->create($archiveRelationship);

        $archive = $this->retrieve($archiveRelationship->archiveId, false, false);

        // Life cycle journal
        $this->logRelationshipAdding($archive, $archiveRelationship);

        if (isset(\laabs::configuration("medona")['transaction']) && \laabs::configuration("medona")['transaction']) {
            $this->sendModificationNotification([$archive]);
        }

        return true;
    }

    /**
     * delete a relationship
     * @param recordsManagement/archiveRelationship $archiveRelationship The archive relationship object
     *
     * @return recordsManagement/archiveRelationship
     */
    public function deleteRelationship($archiveRelationship)
    {
        $this->archiveRelationshipController->delete($archiveRelationship);

        $archive = $this->retrieve($archiveRelationship->archiveId, $withBinary = false, $checkAccess = false);

        // Life cycle journal
        $this->logRelationshipDeleting($archive, $archiveRelationship);

        if (isset(\laabs::configuration("medona")['transaction']) && \laabs::configuration("medona")['transaction']) {
            $this->sendModificationNotification([$archive]);
        }

        return true;
    }

    /**
     * Index full text
     * @param int $limit The maximum number of archive to index
     *
     * @return array The result of the operation
     */
    public function indexFullText($limit = 200)
    {
        $res = [];
        $res['success'] = [];
        $res['fail'] = [];
        $archivesToIndex = $this->sdoFactory->find(
            'recordsManagement/archive',
            "fullTextIndexation='requested'",
            [],
            null,
            null,
            $limit
        );

        if (isset(\laabs::configuration('recordsManagement')['stopWordsFilePath'])) {
            $stopWords = \laabs::configuration('recordsManagement')['stopWordsFilePath'];
            $stopWords = utf8_encode(file_get_contents($stopWords));
            $stopWords = preg_replace('/[\r\n]/', " ", $stopWords);
            $stopWords = explode(" ", $stopWords);
        }

        if (count($archivesToIndex)) {
            $descriptionController = $this->useDescriptionController('recordsManagement/description');

            foreach ($archivesToIndex as $archive) {
                $archive = $this->retrieve($archive->archiveId);

                try {
                    $fullText = $this->digitalResourceController->getFullTextByArchiveId($archive->archiveId);
                    $fullText = strtolower($fullText);
                    $fullText = preg_replace('/[.,\/#!?$%\^&\*;:{}=\-_\'`~()\r\n]|\s+/', " ", $fullText);

                    if (isset($stopWords)) {
                        $fullTextArray = explode(" ", $fullText);
                        $fullTextArray = array_diff($fullTextArray, $stopWords);
                        $fullText = implode(" ", $fullTextArray);
                    } else {
                        $fullText = preg_replace('/\b[a-z]{1,2}\b/', "", $fullText);
                    }

                    $descriptionController->create($archive, $fullText);
                    $archive->fullTextIndexation = "indexed";
                    $this->sdoFactory->update($archive, 'recordsManagement/archiveIndexationStatus');

                    $operationResult = true;
                } catch (\Exception $e) {
                    $operationResult = false;
                    $archive->fullTextIndexation = "failed";
                    $this->sdoFactory->update($archive, 'recordsManagement/archiveIndexationStatus');
                }

                $this->logMetadataModification($archive, $operationResult);

                if ($operationResult) {
                    $res['success'][] = (string)$archive->archiveId;
                } else {
                    $res['error'][] = (string)$archive->archiveId;
                }
            }
        }

        return $res;
    }

    /**
     * Update archive with changed retention rule
     * @param int $limit The maximum number of archive to update
     */
    public function updateArchiveRetentionRule($limit = 1000)
    {
        $archives = $this->sdoFactory->find(
            'recordsManagement/archive',
            'retentionRuleCode != null AND retentionStartDate != null AND retentionDuration !=null AND retentionRuleStatus = "changed"',
            null,
            null,
            null,
            $limit
        );
        $retentionRules = [];

        foreach ($archives as $archive) {
            // avoid parallel processing
            if ($this->sdoFactory->read('recordsManagement/archive')->retentionRuleStatus != "changed") {
                continue;
            }
            $archive->retentionRuleStatus = "processing";
            $this->sdoFactory->update($archive, 'recordsManagement/archiveRetentionRule');

            $retentionRule = new \stdClass();
            $retentionRule->archiveId = $archive->archiveId;
            $retentionRule->previousStartDate = $archive->retentionStartDate;
            $retentionRule->previousDuration = $archive->retentionDuration;
            $retentionRule->previousFinalDisposition = $archive->finalDisposition;

            if (!isset($retentionRules[$archive->retentionRuleCode])) {
                $retentionRules[$archive->retentionRuleCode] =
                    $this->retentionRuleController->read($archive->retentionRuleCode);
            }

            $archive->retentionDuration =  $retentionRules[$archive->retentionRuleCode]->duration;
            $archive->disposalDate = $this->calculateDate($archive->retentionStartDate, $archive->retentionDuration);

            $retentionRule->retentionStartDate = $archive->retentionStartDate;
            $retentionRule->retentionDuration = $archive->retentionDuration;
            $retentionRule->finalDisposition = $archive->finalDisposition;

            $archive->retentionRuleStatus = "current";
            $this->sdoFactory->update($archive, 'recordsManagement/archiveRetentionRule');

            // Life cycle journal
            $this->logRetentionRuleModification($archive, $retentionRule, true);
        }
    }

    /**
     * Convert and store the resource
     *
     * @param string $archiveId   The archive identifier
     * @param string $contents    The resource contents
     * @param string $filename    The optional filename
     * @param string $checkAccess Check access to archive. If false caller MUST check access before.
     *
     * @return The new resource identifier
     */
    public function addResource($archiveId, $contents, $filename = null, $checkAccess = true)
    {
        switch (true) {
            case is_string($contents)
                && (filter_var(substr($contents, 0, 10), FILTER_VALIDATE_URL) || is_file($contents)):
                $handler = fopen($contents, 'r');
                break;

            case is_string($contents) &&
                preg_match('%^[a-zA-Z0-9\\\\/+]*={0,2}$%', $contents):
                $handler = \laabs::createTempStream(base64_decode($contents));
                break;

            case is_resource($contents):
                $handler = \core\Encoding\Base64::decode($contents);
        }

        $digitalResource = $this->digitalResourceController->createFromStream($handler, $filename);
        $digitalResource->archiveId = $archiveId;
        $digitalResource->resId = \laabs::newId();

        $archive = $this->sdoFactory->read("recordsManagement/archive", $archiveId);

        if ($archive->status !== 'preserved') {
            throw \laabs::newException('recordsManagement/accessDeniedException', "Permission denied");
        }

        // Check rights ?
        if ($checkAccess) {
            $this->checkRights($archive);
        }

        $this->useServiceLevel('deposit', $archive->serviceLevelReference);

        $this->validateDigitalResource($digitalResource);

        $transactionControl = !$this->sdoFactory->inTransaction();

        if ($transactionControl) {
            $this->sdoFactory->beginTransaction();
        }

        try {
            $this->digitalResourceController->openContainers(
                $this->currentServiceLevel->digitalResourceClusterId,
                $archive->storagePath
            );
            $this->digitalResourceController->store($digitalResource);


            $serviceLevel = $this->serviceLevelController->getByReference($archive->serviceLevelReference);
            if (strpos($serviceLevel->control, 'fullTextIndexation') !== false) {
                $archive->fullTextIndexation = "requested";
                $this->sdoFactory->update($archive, 'recordsManagement/archiveIndexationStatus');
            }

            $this->logAddResource($archive, $digitalResource, true);
        } catch (\Exception $e) {
            if ($transactionControl) {
                $this->sdoFactory->rollback();
            }

            $this->logAddResource($archive, $digitalResource, false);

            throw $e;
        }

        if ($transactionControl) {
            $this->sdoFactory->commit();
        }

        if (isset(\laabs::configuration("medona")['transaction'])
            && \laabs::configuration("medona")['transaction']) {
            $this->sendModificationNotification([$archive]);
        }

        return $digitalResource->resId;
    }

    /**
     * Update user access
     * @param string $archiveId         The archive unit identifier
     * @param array  $userOrgRegNumbers The user org registration numbers
     * @param string $checkAccess       Check access to archive. If false caller MUST check access before.
     *
     * @return bool
     */
    public function updateUserOrgRegNumbers($archiveId, array $userOrgRegNumbers, $checkAccess = true)
    {
        $archive = $this->sdoFactory->read("recordsManagement/archive", $archiveId);

        // Check rights ?
        if ($checkAccess) {
            $this->checkRights($archive);
        }

        $archiveUserOrgRegNumbers = \laabs::newInstance('recordsManagement/archiveUserOrgRegNumbers');
        $archiveUserOrgRegNumbers->archiveId = $archiveId;
        $archiveUserOrgRegNumbers->userOrgRegNumbers = \laabs::newTokenList($userOrgRegNumbers);
        $archiveUserOrgRegNumbers->lastModificationDate = \laabs::newTimestamp();

        $this->sdoFactory->update($archiveUserOrgRegNumbers);
    }

    protected function sendModificationNotification($archives, $comment = null, $identifier = null, $format = null)
    {
        $currentOrg = \laabs::getToken("ORGANIZATION");

        $archivesByOriginator = array();
        foreach ($archives as $archive) {
            if ($currentOrg->registrationNumber != $archive->originatorOrgRegNumber) {
                if (!isset($archivesByOriginator[$archive->originatorOrgRegNumber])) {
                    $archivesByOriginator[$archive->originatorOrgRegNumber] = array();
                }
                $archivesByOriginator[$archive->originatorOrgRegNumber][] = $archive;
            }
        }

        $archiveModificationNotificationController = \laabs::newController("medona/ArchiveModificationNotification");

        if (!$identifier) {
            $identifier = "archiveModificationNotification_".date("Y-m-d-H-i-s");
        }

        $reference = $identifier;
        foreach ($archivesByOriginator as $originatorOrgRegNumber => $archives) {
            $i = 1;
            $senderOrg = $archives[0]->archiverOrgRegNumber;
            $recipientOrg = $originatorOrgRegNumber;

            $unique = array(
                'type' => 'ArchiveModificationNotification',
                'senderOrgRegNumber' => $senderOrg,
                'reference' => $reference,
            );

            while ($this->sdoFactory->exists("medona/message", $unique)) {
                $i++;
                $unique['reference'] = $reference = $identifier.'_'.$i;
            }

            $archiveModificationNotificationController->send(
                $reference,
                $archives,
                $senderOrg,
                $recipientOrg,
                $comment,
                $format
            );
        }
    }

    /**
     * Extract full text script
     *
     * @param  int $maxResults Max results number to display
     * @param  int $timeLimit  Time limit in seconds
     *
     * @return [type]              [description]
     */
    public function extractFulltext($maxResults = null, $timeLimit = null)
    {
        if (!is_null($maxResults) && $maxResults != "null") {
            $maxResults = intval($maxResults);
        }

        if (!is_null($timeLimit)) {
            $timeLimit = intval($timeLimit);
        }

        $archiveIds = $this->sdoFactory->index(
            'recordsManagement/archive',
            'archiveId',
            'fullTextIndexation=:requested',
            [
                'requested' => 'requested'
            ],
            null,
            0,
            $maxResults
        );

        $selectedRequested = count($archiveIds);

        $logMessage = ["message" => "%s archives requested selected", "variables"=> $selectedRequested];
        \laabs::notify(\bundle\audit\AUDIT_ENTRY_OUTPUT, $logMessage);

        if($selectedRequested < $maxResults) {
            $archiveIds = array_merge(
                $archiveIds,
                $this->sdoFactory->index(
                    'recordsManagement/archive',
                    'archiveId',
                    'fullTextIndexation=:skipped',
                    [
                        'skipped' => 'skipped'
                    ],
                    null,
                    0,
                    $maxResults - $selectedRequested
                )
            );
        }

        $logMessage = ["message" => "%s archives previously skipped selected", "variables"=> count($archiveIds) - $selectedRequested];
        \laabs::notify(\bundle\audit\AUDIT_ENTRY_OUTPUT, $logMessage);

        $fullTextServices = \laabs::configuration('dependency.fileSystem')['fullTextServices'];

        $archiveExtractedCount = 0;
        $errors = [];
        $endTimeScript = microtime(true) + $timeLimit;

        $skipped = 0;
        $indexed = 0;

        foreach ($archiveIds as $archiveId) {
            if (!is_null($timeLimit) && ($endTimeScript - microtime(true)) <= 0) {
                $logMessage = ["message" => "Time Limit reached"];
                \laabs::notify(\bundle\audit\AUDIT_ENTRY_OUTPUT, $logMessage);
                break;
            }
            $fullText = "";
            $status = "";
            $digitalResources = $this->digitalResourceController->getResourcesByArchiveId($archiveId);
            foreach ($digitalResources as $digitalResource) {
                $puid = $digitalResource->puid;

                if (empty($puid)) {
                    $errors[] = "File puid has not been detected for " . $digitalResource->filename;
                    $status = "skipped";
                    continue;
                }

                foreach ($fullTextServices as $fulltextServiceConf) {
                    $options = null;
                    if (in_array($puid, $fulltextServiceConf['inputFormats'])) {
                        $fulltextService = \laabs::newService($fulltextServiceConf['serviceName']);
                        $options = isset($fulltextServiceConf['options']) ? $fulltextServiceConf['options'] : null;
                    }
                }

                if (!isset($fulltextService)) {
                    $status = "skipped";
                    $errors[] = "File type does not exists has not been configured for extraction for file " . $digitalResource->filename;
                    continue;
                }

                $tmpFile = \laabs\tempnam();
                $tmpStream = fopen($tmpFile, 'w+');
                $handler = $this->digitalResourceController->contents($digitalResource->resId);
                stream_copy_to_stream($handler, $tmpStream);
                rewind($tmpStream);
                fclose($tmpStream);
                try {
                    $fullText .= $fulltextService->getText($tmpFile, $options) . " ";
                } catch (\Exception $exception) {}
                
                unlink($tmpFile);
            }
            $archive = $this->retrieve($archiveId);
            $descriptionController = $this->useDescriptionController($archive->descriptionClass);

            try {
                $descriptionController->update($archive, $fullText);
                if (!empty($status)) {
                    $archive->fullTextIndexation = $status;
                    $skipped++;
                } else {
                    $archive->fullTextIndexation = "indexed";
                    $indexed++;
                }
                $this->sdoFactory->update($archive, 'recordsManagement/archiveIndexationStatus');
            } catch (\Exception $e) {
                throw new Exception("Error Processing Request", 1);
            }

            // $logMessage = ["message" => "Archive %s extracted", "variables"=> $archive->archiveName];
            // \laabs::notify(\bundle\audit\AUDIT_ENTRY_OUTPUT, $logMessage);

            $this->logMetadataModification($archive, true);
            $archiveExtractedCount++;
        }

        // $logMessage = ["message" => "%s archive(s) processed", "variables"=> $archiveExtractedCount];
        // \laabs::notify(\bundle\audit\AUDIT_ENTRY_OUTPUT, $logMessage);

        $logMessage = ["message" => "%s archive(s) indexed", "variables"=> $indexed];
        \laabs::notify(\bundle\audit\AUDIT_ENTRY_OUTPUT, $logMessage);

        $logMessage = ["message" => "%s archive(s) skipped", "variables"=> $skipped];
        \laabs::notify(\bundle\audit\AUDIT_ENTRY_OUTPUT, $logMessage);

        return true;
    }

    /**
     * Get available originators for an archive
     * @param string $archiveId The archive identifier
     *
     * @return array $availableOriginatingServices array of organization
     */
    public function indexAvailableOriginators($archiveIds)
    {
        $availableOriginatingServices = null;

        foreach($archiveIds as $archiveId) {
            $archive = $this->sdoFactory->read('recordsManagement/archive', $archiveId);
            if (is_null($availableOriginatingServices)){
                $availableOriginatingServices = $this->getDescendantServices($archive->originatorOwnerOrgId, $archive->archivalAgreementReference);
            } else {
                array_uintersect($availableOriginatingServices, $this->getDescendantServices($archive->originatorOwnerOrgId, $archive->archivalAgreementReference), function($a, $b) {
                    return strcmp(spl_object_hash($a), spl_object_hash($b));
                });
            }
        }
        
        return $availableOriginatingServices;
    }

    protected function getDescendantServices($orgId, $archivalAgreementReference)
    {
        $archivalAgreementController = \laabs::newController('medona/archivalAgreement');
        $organizationController = \laabs::newController('organization/organization');
        $orgs = [];
        if (!is_null($archivalAgreementReference)) {
            $archivalAgreement = $archivalAgreementController->getByReference($archivalAgreementReference);
            foreach ($archivalAgreement->originatorOrgIds as $originatorOrgId) {
                $orgs[] = $organizationController->read((string) $originatorOrgId);
                array_merge($organizationController->readDescendantServices((string) $originatorOrgId), $orgs);
            }
        } else {
            $orgs = $organizationController->readDescendantServices($orgId);
        }

        $descendantServices = [];
        foreach ($orgs as $key => $org) {
            // May be empty if archivalAgreement originatorOrgId has no descendant service
            if (!empty($org)) {
                $descendantServices[$key] = new \stdClass();
                $descendantServices[$key]->orgId = (string) $org->orgId;
                $descendantServices[$key]->displayName = $org->displayName;
            }
        }

        // sort by alphabetical order of displayName (php 8)
        usort($descendantServices, function ($a, $b) {
            return $a->displayName <=> $b->displayName;
        });

        return $descendantServices;
    }

    /**
     * Update originator service of an array of archives
     *
     * @param  array  $archiveIds Array of archive identifiers
     * @param  string $orgId      Organization identified destined to be new originator of archive
     *
     */
    public function updateOriginator($archiveIds, $orgId)
    {
        if (!is_array($archiveIds)) {
            $archiveIds = [$archiveIds];
        }

        $result = [];
        $result["success"] = [];
        $result["error"] = [];


        $newOriginatorOrg = $this->sdoFactory->read('organization/organization', $orgId);
        if ($newOriginatorOrg->enabled == false) {
            throw new \bundle\recordsManagement\Exception\organizationException(
                "This organization is disabled."
            );
        }

        foreach ($archiveIds as $archiveId) {
            $archive = $this->sdoFactory->read('recordsManagement/archive', $archiveId);
            $currentOwnerOrgId = $archive->originatorOwnerOrgId;

            if ($currentOwnerOrgId != $newOriginatorOrg->ownerOrgId) {
                $result["error"][] = $archiveId;
                continue;
            }
            
            $isAvailableOriginator = array_search($newOriginatorOrg->orgId, array_column($this->getDescendantServices($archive->originatorOwnerOrgId, $archive->archivalAgreementReference), 'orgId'));
            if (!$isAvailableOriginator) {
                $result["error"][] = $archiveId;
            }

            $archive->originatorOrgRegNumber = $newOriginatorOrg->registrationNumber;
            $archive->lastModificationDate = \laabs::newTimestamp();
            $this->sdoFactory->update($archive,'recordsManagement/archive');

            $result["success"][] = $archiveId;

        }

        return $result;
    }
}
