<?php

/*
 *  Copyright (C) 2017 Maarch
 * 
 *  This file is part of bundle recordsManagement.
 *  Bundle recordsManagement is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 * 
 *  Bundle recordsManagement is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 * 
 *  You should have received a copy of the GNU General Public License
 *  along with bundle recordsManagement.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace bundle\recordsManagement\Controller;

/**
 * Archive entry controller
 *
 * @author Alexis Ragot <alexis.ragot@maarch.org>
 */
trait archiveEntryTrait
{
    protected $originatorOrgs;

    /**
     * Instanciate a new archive
     *
     * @return recordsManagement/archive
     */
    public function newArchive()
    {
        // Use archive xml parser for archive creation
        $archive = \laabs::newInstance('recordsManagement/archive');

        // Generate archive id
        if (!isset($archive->archiveId)) {
            $archive->archiveId = \laabs::newId();
        }

        $archive->timestamp = \laabs::newTimestamp();
        $archive->status = 'received';

        // Use current profile
        if (isset($this->currentArchivalProfile)) {
            $archive->archivalProfileReference = $this->currentArchivalProfile->reference;

            $archive->descriptionClass = $this->currentArchivalProfile->descriptionClass;

            $archive->accessRuleCode = $this->currentArchivalProfile->accessRuleCode;
            $archive->retentionRuleCode = $this->currentArchivalProfile->retentionRuleCode;
        }

        // Use current service level
        if (isset($this->currentServiceLevel)) {
            $archive->serviceLevelReference = $this->currentServiceLevel->reference;
        }

        return $archive;
    }

    /**
     * Receive an archive
     *
     * @param recordsManagement/archive $archive The archive to receive
     */
    public function receive($archive)
    {
        $archive = \laabs::cast($archive, 'recordsManagement/archive');

        if (!isset($archive->archiveId)) {
            $archive->archiveId = \laabs::newId();
        }
        $archive->status = "received";

        // Load archival profile, service level if specified
        // Instantiate description controller
        $this->useReferences($archive, 'deposit');

        // Complete management metadata from profile and service level
        $this->completeMetadata($archive);

        // Validate metadata
        $this->validateCompliance($archive);

        // Check format conversion
        $this->convertArchive($archive);

        // Generate PDI + package
        $this->generateAIP($archive);

        // Deposit
        $this->deposit($archive);

        // Send certificate
        $this->sendResponse($archive);
    }

    /**
     * Receive an archive
     *
     * @param string $batchDirectory      The path of the folder that contains archives
     * @param string $descriptionFilePath The path of the description file
     *
     * @return bool The result of the operation
     */
    public function receiveArchiveBatch($batchDirectory, $descriptionFilePath)
    {
        if (!is_dir($batchDirectory)){
            throw new \core\Exception\NotFoundException("The batch folder does not exist.");
        }

        $descriptionFile = file_get_contents($descriptionFilePath);

        if (!$descriptionFile) {
            throw new \core\Exception("The description file can not be read.");
        }

        $archives = json_decode($descriptionFile);
        if (!$descriptionFile) {
            throw new \core\Exception("The description file is malformed.");
        }

        $filePlanController = \laabs::newController('filePlan/filePlan');
        $filePlanFoldersByName = [];

        foreach ($archives as $archive) {
            foreach ($archive->digitalResources as $digitalResource) {
                $filePath = $batchDirectory . DIRECTORY_SEPARATOR . $digitalResource->fileName;

                $fileContent = file_get_contents($filePath);
                $digitalResource->handler = base64_encode($fileContent);
                $digitalResource->size = filesize($filePath);
            }

            if ($archive->filePlanFolder) {
                if (!isset($filePlanFoldersByName[$archive->filePlanFolder])) {
                    $filePlanFoldersByName[$archive->filePlanFolder] = $filePlanController->readByName($archive->filePlanFolder);
                }
                
                $archive->filePlanPosition = $filePlanFoldersByName[$archive->filePlanFolder]->folderId;
                    var_dump($filePlanFoldersByName);
            }

            $this->receive($archive);
        }

        return true;
    }

    /**
     * Validate the archive compliance
     *
     * @param recordsManagement/archive $archive The archive to validate
     */
    public function validateCompliance($archive)
    {
        $this->validateArchiveDescriptionObject($archive);
        $this->validateManagementMetadata($archive);
        $this->validateAttachments($archive);
    }

    /**
     * Complete the archive metadata
     *
     * @param recordsManagement/archive $archive The archive to complete
     */
    public function completeMetadata($archive)
    {
        // Set archive name when mono document
        if (empty($archive->archiveName) && count($archive->digitalResources) == 1 && isset($archive->digitalResources[0]->fileName)) {
            $archive->archiveName = pathinfo($archive->digitalResource[0]->fileName, \PATHINFO_FILENAME);
        }

        $this->completeManagementMetadata($archive);

        if (empty($archive->descriptionClass) && isset($this->currentArchivalProfile->descriptionClass)) {
            $archive->descriptionClass = $this->currentArchivalProfile->descriptionClass;
        }
    }

    /**
     * Complete management metadata
     *
     * @param recordsManagement/archive $archive The archive to complete
     */
    protected function completeManagementMetadata($archive)
    {
        if (!empty($this->currentArchivalProfile)) {
            $this->completeArchivalProfileCodes($archive);
        }

        $this->completeRetentionRule($archive);
        $this->completeAccessRule($archive);
        $this->completeServiceLevel($archive);

        // Originator
        if (empty($archive->originatorOrgRegNumber)) {
            $currentOrg = \laabs::getToken("ORGANIZATION");
            if ($currentOrg) {
                $archive->originatorOrgRegNumber = $currentOrg->registrationNumber;
            }
        }

        // Parent
        if (!empty($archive->parentArchiveId)) {
            $parentArchive = $this->read($archive->parentArchiveId);

            if ($archive->originatorOrgRegNumber != $parentArchive->originatorOrgRegNumber) {
                $archive->parentOriginatorOrgRegNumber = $parentArchive->originatorOrgRegNumber;
            }
        }

        if (!isset($this->originatorOrgs[$archive->originatorOrgRegNumber])) {
            $originatorOrg = $this->organizationController->getOrgByRegNumber($archive->originatorOrgRegNumber);
            $this->originatorOrgs[$archive->originatorOrgRegNumber] = $originatorOrg;
        } else {
            $originatorOrg = $this->originatorOrgs[$archive->originatorOrgRegNumber];
        }

        $archive->originatorOwnerOrgId = $originatorOrg->ownerOrgId;
    }

    /**
     * Complete management codes with the archival profile
     *
     * @param recordsManagement/archive $archive The archive to complete
     */
    public function completeArchivalProfileCodes($archive)
    {
        $archive->archivalProfileReference = $this->currentArchivalProfile->reference;

        if (!empty($this->currentArchivalProfile->retentionRuleCode)) {
            $archive->retentionRuleCode = $this->currentArchivalProfile->retentionRuleCode;
        }
        if (!empty($this->currentArchivalProfile->accessRuleCode)) {
            $archive->accessRuleCode = $this->currentArchivalProfile->accessRuleCode;
        }

        if (isset($this->currentArchivalProfile->retentionStartDate) && $this->currentArchivalProfile->retentionStartDate != "definedLater") {
            $archive->retentionStartDate = $this->currentArchivalProfile->retentionStartDate;
        }
    }
    /**
     * Complete the access rule metadata
     *
     * @param recordsManagement/archive $archive The archive to complete
     */
    public function completeAccessRule($archive)
    {
        if (!empty($archive->accessRuleCode)) {
            $accessRule = $this->accessRuleController->edit($archive->accessRuleCode);
            $archive->accessRuleDuration = $accessRule->duration;
        }

        if (!empty($archive->accessRuleStartDate) && !empty($archive->accessRuleDuration)) {
            $archive->accessRuleComDate = $archive->accessRuleStartDate->shift($archive->accessRuleDuration);
        }
    }

    /**
     * Complete the retention rule metadata
     *
     * @param recordsManagement/archive $archive The archive to complete
     */
    public function completeRetentionRule($archive)
    {
        if (!empty($archive->retentionRuleCode)) {
            $retentionRule = $this->retentionRuleController->read($archive->retentionRuleCode);

            $archive->retentionDuration =  $retentionRule->duration;
            $archive->finalDisposition =  $retentionRule->finalDisposition;

            if ($archive->retentionStartDate == "depositDate") {
                    $archive->retentionStartDate = $archive->depositDate;
            }

            if (is_string($archive->retentionStartDate)) {
                $qname = \laabs\explode("/", $archive->retentionStartDate);
                if ($qname[0] == "description") {
                    if (isset($archive->descriptionObject->{$qname[1]})) {
                        $archive->retentionStartDate = \laabs::newDate($archive->descriptionObject->{$qname[1]});
                    }
                } else {
                    // todo
                }
            }
        }

        $archive->disposalDate = null;
        if (!empty($archive->retentionStartDate) && !empty($archive->retentionDuration) && $archive->retentionDuration->y < 999) {
            $archive->disposalDate = $archive->retentionStartDate->shift($archive->retentionDuration);
        }
    }

    /**
     * Complete the service level
     *
     * @param recordsManagement/archive $archive The archive to complete
     */
    public function completeServiceLevel($archive)
    {
        if (empty($this->currentServiceLevel)) {
            $this->useServiceLevel('deposit', $archive->serviceLevelReference);
        }

        $archive->serviceLevelReference = $this->currentServiceLevel->reference;
    }
    /**
     * Convert resources of archive
     *
     * @param recordsManagement/archive $archive The archive to convert
     */
    public function convertArchive($archive)
    {
        if (empty($this->currentServiceLevel)) {
            $this->useServiceLevel('deposit', $archive->serviceLevelReference);
        }

        if (strrpos($this->currentServiceLevel->control, "convertOnDeposit") == false) {
            return;
        }

        $conversionRules = \laabs::newController("digitalResource/conversionRule")->index();

        if (empty($conversionRules)) {
            return;
        }

        $nbResources = count($archive->digitalResources);
        $nbArchiveObjects = count($archive->contents);

        for ($i = 0; $i < $nbResources; $i++) {
            $convertedResource = $this->convertResource($archive, $archive->digitalResources[$i]);
            if ($convertedResource != false) {
                $archive->digitalResources[] = $convertedResource;
            }
        }

        for ($i = 0; $i < $nbArchiveObjects; $i++) {
            $this->convertArchive($archive->contents[$i]);
        }
    }

    /**
     * Generate the archival information package
     * @param recordsManagement/archive $archive The archive use to generate the AIP
     */
    public function generateAIP($archive)
    {
    }

    /**
     * Deposit a new archive
     *
     * @param recordsManagement/archive $archive          The archive to deposit
     * @param string                    $filePlanPosition The file plan position
     *
     * @return recordsManagement/archive The archive
     */
    public function deposit($archive, $filePlanPosition = null)
    {
        $transactionControl = !$this->sdoFactory->inTransaction();

        if ($transactionControl) {
            $this->sdoFactory->beginTransaction();
        }

        $nbArchiveObjects = count($archive->contents);

        try {
            if (!empty($archive->digitalResources)) {
                $this->storeResources($archive, $filePlanPosition);
            }

            $archive->status = 'preserved';
            $archive->depositDate = \laabs::newTimestamp();

            $this->sdoFactory->create($archive, 'recordsManagement/archive');

            $this->storeDescriptiveMetadata($archive);

            for ($i = 0; $i < $nbArchiveObjects; $i++) {
                $archive->contents[$i]->parentArchiveId = $archive->archiveId;
                if ($archive->contents[$i]->originatorOrgRegNumber != $archive->originatorOrgRegNumber) {
                    $archive->contents[$i]->parentOriginatorOrgRegNumber = $archive->$originatorOrgRegNumber;
                }

                $this->deposit($archive->contents[$i], $filePlanPosition."/".(string) $archive->contents[$i]->archiveId);
            }
        } catch (\Exception $exception) {
            /*if (\laabs::hasDependency('fulltext') && isset($this->fulltextController)) {
                $this->fulltextController->delete($index, $baseIndex);
            }*/

            $nbResources = count($archive->digitalResources);
            for ($i = 0; $i < $nbResources; $i++) {
                $this->digitalResourceController->rollbackStorage($archive->digitalResources[$i]);
            }

            if ($transactionControl) {
                $this->sdoFactory->rollback();
            }

            throw $exception;
        }

        if ($transactionControl) {
            $this->sdoFactory->commit();
        }

        $this->loggingDeposit($archive);

        return $archive;
    }

    /**
     * Send response
     */
    public function sendResponse()
    {
    }

    /**
     * Validate archive description object
     *
     * @param recordsManagement/archive $archive The archive object
     */
    protected function validateArchiveDescriptionObject($archive)
    {
        if (isset($this->currentArchivalProfile)) {
            if (!empty($archive->descriptionClass) && !empty($archive->descriptionObject)) {
                $this->validateDescriptionObject($archive->descriptionObject, $this->currentArchivalProfile);
            }
            // Validate fulltext
        }
    }

    /**
     * Check if an object matches an archival profile definition
     *
     * @param mixed                             $object          The metadata object to check
     * @param recordsManagement/archivalProfile $archivalProfile The reference of the profile
     *
     * @return boolean The result of the validation
     */
    protected function validateDescriptionObject($object, $archivalProfile)
    {
        if (\laabs::getClass($object)->getName() != $archivalProfile->descriptionClass) {
            // todo : error
            return;
        }

        foreach ($archivalProfile->archiveDescription as $description) {
            $fieldName = explode(LAABS_URI_SEPARATOR, $description->fieldName);
            $propertiesList = array($object);

            foreach ($fieldName as $name) {
                $newPropertiesList = array();
                foreach ($propertiesList as $propertyValue) {
                    if (isset($propertyValue->{$name})) {
                        if (is_array($propertyValue->{$name})) {
                            foreach ($propertyValue->{$name} as $value) {
                                $newPropertiesList[] = $value;
                            }
                        } else {
                            $newPropertiesList[] = $propertyValue->{$name};
                        }
                    } else {
                        $newPropertiesList[] = null;
                    }
                }
                $propertiesList = $newPropertiesList;
            }

            foreach ($propertiesList as $propertyValue) {
                if ($description->required && $propertyValue == null) {
                    throw new \bundle\recordsManagement\Exception\archiveDoesNotMatchProfileException('The description class does not match with the archival profile.');
                }
            }
        }
    }

    /**
     * Validate the archive management metadata
     *
     * @param \bundle\recordsManagement\Controller\recordsManagement/archive $archive
     */
    protected function validateManagementMetadata($archive)
    {
        if (isset($archive->archivalProfileReference) && !$this->sdoFactory->exists("recordsManagement/archivalProfile", $archive->archivalProfileReference)) {
            // todo : error
        }

        if (isset($archive->retentionRuleCode) && !$this->sdoFactory->exists("recordsManagement/retentionRule", $archive->retentionRuleCode)) {
            // todo : error
        }

        if (isset($archive->accessRuleCode) && !$this->sdoFactory->exists("recordsManagement/retentionRule", $archive->accessRuleCode)) {
            // todo : error
        }
    }

    /**
     * Validate the archive management metadata
     *
     * @param \bundle\recordsManagement\Controller\recordsManagement/archive $archive
     */
    protected function validateAttachments($archive)
    {
        if (!$archive->digitalResources) {
            return;
        }

        $formatDetection = strrpos($this->currentServiceLevel->control, "formatDetection") === false ? false : true;
        $droid = \laabs::newService('dependency/fileSystem/plugins/fid');

        foreach ($archive->digitalResources as $digitalResource) {
            if (empty($digitalResource->archiveId)) {
                $digitalResource->archiveId = $archive->archiveId;
            }

            if (empty($digitalResource->resId)) {
                $digitalResource->resId = \laabs::newId();
            }

            $contents = base64_decode($digitalResource->getContents());

            $digitalResource->setContents($contents);

            $filename = tempnam(sys_get_temp_dir(), 'digitalResource.format');
            file_put_contents($filename, $contents);

            if ($formatDetection) {
                if ($format = $droid->match($filename)) {
                    $digitalResource->puid = $format->puid;
                }
            }

            if (empty($digitalResource->puid)) {
                // todo : error
            }

            if (empty($digitalResource->hash) || empty($digitalResource->hashAlgorithm)) {
                $this->digitalResourceController->getHash($digitalResource, $this->hashAlgorithm);
            }
        }
    }

    /**
     * Store archive resources
     *
     * @param recordsManagement/archive $archive          The archive to deposit
     * @param string                    $filePlanPosition The file plan position
     */
    protected function storeResources($archive, $filePlanPosition = null)
    {
        $nbResources = count($archive->digitalResources);

        if (empty($filePlanPosition)) {
            if (!$this->storePath) {
                $filePlanPosition = $archive->originatorOwnerOrgId."/".$archive->originatorOrgRegNumber."/".$archive->archiveId;
            } else {
                $filePlanPosition = $this->storePath;
            }
        }

        $filePlanPosition = $this->resolveStoragePath($archive, $filePlanPosition);

        if (!$this->currentServiceLevel) {
            if (isset($archive->serviceLevelReference)) {
                $this->useServiceLevel('deposit', $archive->serviceLevelReference);
            } else {
                $this->useServiceLevel('deposit');
            }
        }

        $archive->storagePath = $filePlanPosition;

        for ($i = 0; $i < $nbResources; $i++) {
            $digitalResource = $archive->digitalResources[$i];
            $digitalResource->archiveId = $archive->archiveId;
            $this->digitalResourceController->store($digitalResource, $this->currentServiceLevel->digitalResourceClusterId, $filePlanPosition);
        }
    }

    /**
     * Store archive resources
     *
     * @param recordsManagement/archive $archive The archive to deposit
     */
    protected function storeDescriptiveMetadata($archive)
    {
        if (!empty($archive->descriptionClass) && isset($archive->descriptionObject)) {
            $descriptionController = $this->useDescriptionController($archive->descriptionClass);
        } else {
            $descriptionController = $this->useDescriptionController('recordsManagement/description');
        }

        $descriptionController->create($archive);

        /*} elseif (\laabs::hasDependency('fulltext')) {
            $fulltextController = \laabs::newController("recordsManagement/fulltext");

            $index = isset($archive->archivalProfileReference) ? $archive->archivalProfileReference : 'archives';

            $baseIndex = $fulltextController->getArchiveIndex($archive);

            if (isset($archive->descriptionObject)) {
                $archiveIndex = clone($baseIndex);
                $fulltextController->mergeIndex($archive->descriptionObject, $archiveIndex);
                $fulltextController->addDocument($index, $archiveIndex);
            }
        }*/
    }

    /**
     * Resolve the storage path
     *
     * @param array  $values  Array of value to resolve the path
     * @param string $pattern The pattern to resolve
     *
     * @return string The storage path
     */
    public function resolveStoragePath($values, $pattern = null)
    {
        if (!$pattern) {
            $pattern = $this->storePath;
        }

        $values = is_array($values) ? $values : get_object_vars($values);

        $matches = array();
        if (preg_match_all("/\<[^\>]+\>/", $pattern, $variables)) {
            foreach ($variables[0] as $variable) {
                $token = substr($variable, 1, -1);
                switch (true) {
                    case $token == 'app':
                        $pattern = str_replace($variable, \laabs::getApp(), $pattern);
                        break;

                    case $token == 'instance':
                        if ($instanceName = \laabs::getInstanceName()) {
                            $pattern = str_replace($variable, \laabs::getInstanceName(), $pattern);
                        } else {
                            $pattern = "instance";
                        }
                        break;

                    case isset($values[$token]):
                        $pattern = str_replace($variable, (string) $values[$token], $pattern);
                        break;
                }
            }
        }

        return $pattern;
    }

    /**
     * Log the archive entry
     *
     * @param recordsManagement/archive $archive The archive logged
     */
    protected function loggingDeposit($archive)
    {
        $eventInfo = array(
            'originatorOrgRegNumber' => $archive->originatorOrgRegNumber,
            'depositorOrgRegNumber' => $archive->depositorOrgRegNumber,
            'archiverOrgRegNumber' => $archive->archiverOrgRegNumber,
        );

        $logged = false;
        if (isset($archive->digitalResources) && count($archive->digitalResources)) {
            foreach ($archive->digitalResources as $digitalResource) {
                $address = $digitalResource->address[0];

                $eventInfo['resId'] = (string) $digitalResource->resId;
                $eventInfo['hashAlgorithm'] = $digitalResource->hashAlgorithm;
                $eventInfo['hash'] = $digitalResource->hash;
                $eventInfo['address'] = $address->path;

                $event = $this->lifeCycleJournalController->logEvent('recordsManagement/deposit', 'recordsManagement/archive', $archive->archiveId, $eventInfo);
                $archive->lifeCycleEvent[] = $event;

                $logged = true;
            }
        }

        if (!$logged) {
            $eventInfo['resId'] = $eventInfo['hashAlgorithm'] = $eventInfo['hash'] = null;
            $eventInfo['address'] = $archive->storagePath;
            $event = $this->lifeCycleJournalController->logEvent('recordsManagement/deposit', 'recordsManagement/archive', $archive->archiveId, $eventInfo);
            $archive->lifeCycleEvent[] = $event;
        }
    }
}
