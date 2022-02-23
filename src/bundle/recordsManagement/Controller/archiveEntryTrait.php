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

use function laabs\hash_stream;

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
     * @return recordsManagement/archive An archive
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

            if ($this->currentArchivalProfile->retentionRuleCode) {
                $archive->retentionRuleCode = $this->currentArchivalProfile->retentionRuleCode;
            }
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
     * @param recordsManagement/archive $archive      The archive to receive
     * @param bool                      $zipContainer The archive is a zip container
     */
    public function receive($archive, $zipContainer = false)
    {
        if ($zipContainer) {
            $archive = \laabs::cast($archive, 'recordsManagement/archive');
            $zipResource = $archive->digitalResources[0];
            $archive = $this->processZipContainer($archive);
        } else {
            $this->receiveAttachments($archive);
            $archive = \laabs::cast($archive, 'recordsManagement/archive');
        }

        if (!isset($archive->archiveId)) {
            $archive->archiveId = \laabs::newId();
        }

        $archive->status = "received";
        $archive->depositDate = \laabs::newTimestamp();


        try {
            // Verify if deposit archive has proper hash
            $this->checkintegrity($archive);

            // Load archival profile, service level if specified
            // Instantiate description controller
            $this->useReferences($archive, 'deposit');

            // Complete management metadata from profile and service level
            $this->completeMetadata($archive);

            $this->useReferences($archive, 'deposit');

            // Validate archive metadata and resources
            $this->validateCompliance($archive);

            // Check format conversion
            $this->convertArchive($archive);

            // Generate PDI + package
            $this->generateAIP($archive);

            // Deposit
            $this->deposit($archive);

            // Send certificate
            $this->sendResponse($archive);
        } catch (\Exception $exception) {
            if ($zipContainer) {
                $this->deleteZipContainer($archive, $zipResource);
            }

            throw $exception;
        }

        if ($zipContainer) {
            $this->deleteZipContainer($archive, $zipResource);
        }

        return $archive->archiveId;
    }

    /**
     * Receives attachments
     * @param object $archive
     */
    protected function receiveAttachments($archive)
    {
        if (is_array($archive->digitalResources)) {
            foreach ($archive->digitalResources as $digitalResource) {
                $receivedHandler = $digitalResource->getHandler();
                switch (true) {
                    case is_string($receivedHandler)
                        && (filter_var(substr($receivedHandler, 0, 10), FILTER_VALIDATE_URL) || is_file($receivedHandler)):
                        $fromHandler = fopen($receivedHandler, 'r');
                        $handler = fopen("php://temp", 'w+');
                        stream_copy_to_stream($fromHandler, $handler);
                        rewind($handler);
                        break;

                    case is_string($receivedHandler) &&
                        preg_match('%^[a-zA-Z0-9\\\\/+]*={0,2}$%', $receivedHandler):
                        $handler = \laabs::createTempStream(base64_decode($receivedHandler));
                        break;

                    case is_resource($receivedHandler):
                        $handler = \core\Encoding\Base64::decode($receivedHandler);
                }

                $digitalResource->setHandler($handler);
            }
        }
        if (is_array($archive->contents)) {
            foreach ($archive->contents as $contentArchive) {
                $this->receiveAttachments($contentArchive);
            }
        }
    }

    /**
     * Process a zipContainer
     *
     * @param recordsManagement/archive $archive The archive
     *
     * @return recordsManagement/archive An archive
     */
    public function processZipContainer($archive)
    {
        $zipResource = $archive->digitalResources[0];

        $zipDirectory = $this->extractZip($zipResource);
        $zipResource->tmpdir = $zipDirectory;

        $archive->digitalResources = [];
        $cleanZipDirectory = array_diff(scandir($zipDirectory.DIRECTORY_SEPARATOR.'contents'), array('..', '.'));
        // Root must be a directory
        $archiveDirectory = realpath($zipDirectory.DIRECTORY_SEPARATOR.'contents'.DIRECTORY_SEPARATOR.reset($cleanZipDirectory));
        if (!is_dir($archiveDirectory)) {
            $this->deleteZipContainer($archive, $zipResource);

            throw new \core\Exception("The container file is non-compliant");
        }

        try {
            $this->extractDir($archiveDirectory, $archive);
        } catch (\Exception $exception) {
            $this->deleteZipContainer($archive, $zipResource);

            throw $exception;
        }

        return $archive;
    }

    protected function deleteZipContainer($archive, $zipResource)
    {
        unset($archive->contents);
        unset($archive->digitalResources);

        gc_collect_cycles();
        \laabs\rmdir($zipResource->tmpdir, true);
    }

    /**
     * Extract zip
     *
     * @param resource $zip
     *
     * @return string The direcotry path where the zip is extract
     */
    private function extractZip($zip)
    {
        $packageDir = \laabs\tempdir();

        if (!is_dir($packageDir)) {
            mkdir($packageDir, 0777, true);
        }

        $zipfile = $packageDir.DIRECTORY_SEPARATOR."container.zip";
        $zipdir = $packageDir.DIRECTORY_SEPARATOR."contents";
        if (!is_dir($zipdir)) {
            mkdir($zipdir, 0777, true);
        }

        file_put_contents($zipfile, base64_decode($zip->getContents()));

        $this->zip->extract($zipfile, $zipdir, false, null, "x");

        return $packageDir;
    }

    /**
     * Process a zip directory
     * @var string                    $directory
     * @var recordsManagement/archive $archive
     */
    protected function extractDir($directory, $archive)
    {
        $scannedDirectory = array_diff(scandir($directory), array('..', '.'));

        foreach ($scannedDirectory as $filename) {
            if (is_link($directory.DIRECTORY_SEPARATOR.$filename)) {
                throw new \core\Exception("The container file contains symbolic links");
            }

            if (is_file($directory.DIRECTORY_SEPARATOR.$filename)) {
                if (!empty($archive->archivalProfileReference) && \laabs::strStartsWith($filename, $archive->archivalProfileReference." ")) {
                    $resource = $this->digitalResourceController->createFromFile($directory.DIRECTORY_SEPARATOR.$filename, $filename);
                    $archive->digitalResources[] = $resource;
                } else {
                    $archiveUnit = $this->extractArchiveUnit($filename);
                    $archiveUnit->archiveId = \laabs::newId();
                    $archiveUnit->fileplanLevel = 'item';
                    $archive->contents[] = $archiveUnit;

                    $resource = $this->digitalResourceController->createFromFile($directory.DIRECTORY_SEPARATOR.$filename, $filename);
                    $archiveUnit->digitalResources[] = $resource;
                }
            }

            if (is_dir($directory.DIRECTORY_SEPARATOR.$filename)) {
                $archiveUnit = $this->extractArchiveUnit($filename);
                $archiveUnit->archiveId = \laabs::newId();
                $archive->contents[] = $archiveUnit;

                $this->extractDir($directory.DIRECTORY_SEPARATOR.$filename, $archiveUnit);
            }
        }
    }

    /**
     * Extract the archive unit
     *
     * @param string $filename The filename
     *
     * @return recordsManagement/archive The extracted archive from directory
     */
    private function extractArchiveUnit($filename)
    {
        if (!preg_match('//u', $filename)) {
            $filename = utf8_encode($filename);
        }

        $archive = \laabs::newInstance("recordsManagement/archive");
        $archive->archiveId = \laabs::newId();

        if (strpos($filename, " ") !== false) {
            $archivalProfileReference = strtok($filename, " ");

            try {
                $this->useArchivalProfile($archivalProfileReference);
                $archiveName = substr($filename, strlen($archivalProfileReference)+1);
                $archiveName = preg_replace('/\\.[^.\\s]{3,4}$/', '', $archiveName);
                $archive->archiveName = $archiveName." _ ".$this->currentArchivalProfile->name;
                $archive->archivalProfileReference = $archivalProfileReference;
            } catch (\Exception $e) {
                $archive->archiveName = $filename;
            }
        } else {
            $archive->archiveName = $filename;
        }

        return $archive;
    }

    /**
     * Extract a resource
     *
     * @param string $resourceDirectory The directory of the resource
     * @param string $filename          The filename
     *
     * @return digitalResource/digitalResource The extracted digital resource
     */
    private function extractResource($resourceDirectory, $filename)
    {
        $resource = $this->digitalResourceController->createFromFile($resourceDirectory.DIRECTORY_SEPARATOR.$filename, false);

        $this->digitalResourceController->getHash($resource, \laabs::configuration('auth')['passwordEncryption']);

        return $resource;
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
        if (!is_dir($batchDirectory)) {
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

        foreach ($archives as $archive) {
            $archive = \laabs::castMessage($archive, 'recordsManagement/archive');

            $this->loadDigitalResourcesBatch($archive, $batchDirectory);

            $this->receive($archive);
        }

        return true;
    }

    protected function loadDigitalResourcesBatch($archive, $batchDirectory) {
        if (isset($archive->digitalResources) && !empty($archive->digitalResources)) {
            foreach ($archive->digitalResources as $digitalResource) {
                $filePath = $batchDirectory.DIRECTORY_SEPARATOR.$digitalResource->fileName;

                $fileContent = file_get_contents($filePath);
                $digitalResource->handler = base64_encode($fileContent);
                $digitalResource->size = filesize($filePath);
            }
        }
        if (isset($archive->contents) && !empty($archive->contents)) {
            foreach ($archive->contents as $content) {
                $this->loadDigitalResourcesBatch($content, $batchDirectory);
            }
        }
    }

    protected function checkintegrity($archive)
    {
        if (!isset($archive->digitalResources)) {
            return;
        }
        foreach ($archive->digitalResources as $resource) {
            if (!in_array($resource->hashAlgorithm, ['sha3-224', 'sha3-256', 'sha3-384', 'sha3-512'])) {
                $resource->hashAlgorithm = str_replace('-', '', $resource->hashAlgorithm);
            }
            //Hash verification
            if ((isset($resource->hash) && !is_null($resource->hash))
                && (isset($resource->hashAlgorithm) && !is_null($resource->hashAlgorithm))
            ) {
                $hashCalculated = hash_stream($resource->hashAlgorithm, $resource->gethandler());

                if ($hashCalculated !== strtolower($resource->hash)) {
                    throw \laabs::newException("recordsManagement/invalidHashException", "Invalid hash.");
                }
            } elseif (!isset($resource->hash) && (!isset($resource->hashAlgorithm) || empty($resource->hashAlgorithm))) {
                continue;
            } else {
                throw \laabs::newException("recordsManagement/missingHashException", "Missing hash.");
            }

            //hash modification if hash algorithm different from conf value
            $confHashAlgorithm = 'sha256';
            if (isset(\laabs::configuration('recordsManagement')['hashAlgorithm']) && !is_null(\laabs::configuration('recordsManagement')['hashAlgorithm'])) {
                $confHashAlgorithm = \laabs::configuration('recordsManagement')['hashAlgorithm'];
            }

            if ($resource->hashAlgorithm != $confHashAlgorithm) {
                $newHash= hash_stream($confHashAlgorithm, $resource->gethandler());
                $resource->hash = $newHash;
                $resource->hashAlgorithm = $confHashAlgorithm;
            }
        }
    }

    /**
     * Complete the archive metadata
     *
     * @param recordsManagement/archive $archive The archive to complete
     */
    public function completeMetadata($archive)
    {
        // Set archive name when mono document
        if (empty($archive->archiveName)) {
            if (is_array($archive->digitalResources) && count($archive->digitalResources)) {
                foreach ($archive->digitalResources as $digitalResource) {
                    if (isset($digitalResource->fileName)) {
                        $archive->archiveName = pathinfo($digitalResource->fileName, \PATHINFO_FILENAME);

                        break;
                    }
                }
            } else {
                if (!empty($archive->archivalProfileReference)) {
                    $archivalProfile = $this->useArchivalProfile($archive->archivalProfileReference);
                    $archive->archiveName .= $archivalProfile->name;
                }
                if (!empty($archive->originatorArchiveId)) {
                    $archive->archiveName .= ' '.$archive->originatorArchiveId;
                }
                if (!empty($archive->originatingDate)) {
                    $archive->archiveName .= ' '.$archive->originatingDate;
                }

                $archive->archiveName = trim($archive->archiveName);

                if (empty($archive->archiveName)) {
                    throw new \core\Exception\BadRequestException('You must define at least the name, the identifier, the date of the document or a document', 400);
                }
            }
        }

        $this->completeManagementMetadata($archive);

        $this->completeProcessingStatus($archive);

        $this->manageFileplanPosition($archive);

        if (isset(\laabs::configuration('recordsManagement')['archiveIdGenerator']) && !empty(\laabs::configuration('recordsManagement')['archiveIdGenerator'])) {
            $generator = \laabs::configuration('recordsManagement')['archiveIdGenerator'];
            $generatorService = \laabs::newService($generator['service']);
            $generatorService->generate($archive);
        }

        if (empty($archive->descriptionClass) && isset($this->currentArchivalProfile->descriptionClass)) {
            $archive->descriptionClass = $this->currentArchivalProfile->descriptionClass;
        }

        if (!empty($archive->contents)) {
            $nbArchiveObjects = count($archive->contents);
            for ($i = 0; $i < $nbArchiveObjects; $i++) {
                $archive->contents[$i]->serviceLevelReference = $archive->serviceLevelReference;
                $this->useReferences($archive->contents[$i], 'deposit');
                $archive->contents[$i]->fullTextIndexation = $archive->fullTextIndexation;
                $this->completeMetadata($archive->contents[$i]);
            }
        }
    }

    /**
     * Complete fileplanPosition with a directoryPath
     *
     * @param recordsManagement/archive $archive The archive to complete
     */
    public function manageFileplanPosition($archive)
    {
        // Could classify automatically regarding archival profile rule
        if (empty($archive->filePlanPosition)) {
            return;
        }

        // File plan position is given as a path that must be opened (create if not exists)
        // to retrieve the folderId
        if ($archive->filePlanPosition[0] == '/') {
            $path = substr($archive->filePlanPosition, 1);
            $archive->filePlanPosition = $this->filePlanController->createFromPath($path, $archive->originatorOrgRegNumber, true);

            return;
        }
    }

    /**
     * Complete management metadata
     *
     * @param recordsManagement/archive $archive The archive to complete
     */
    public function completeManagementMetadata($archive)
    {
        $this->completeArchivalProfileCodes($archive);
        $this->completeRetentionRule($archive);
        $this->completeAccessRule($archive);
        $this->completeServiceLevel($archive);
        $this->completeOriginator($archive);
        $this->completeArchiver($archive);
    }

    public function completeOriginator($archive)
    {
        // Get originatorOrgRegNumber of parent if parentId exists
        if (isset($archive->parentArchiveId)) {
            $parentArchive = $this->sdoFactory->read('recordsManagement/archive', $archive->parentArchiveId);
            $archive->originatorOrgRegNumber = $parentArchive->originatorOrgRegNumber;
        }

        // Originator
        if (empty($archive->originatorOrgRegNumber)) {
            $currentOrg = \laabs::getToken("ORGANIZATION");
            if ($currentOrg) {
                $archive->originatorOrgRegNumber = $currentOrg->registrationNumber;
            }
        }

        if (!isset($this->originatorOrgs[$archive->originatorOrgRegNumber])) {
            $originator = $this->organizationController->getOrgByRegNumber($archive->originatorOrgRegNumber);
            $this->originatorOrgs[$archive->originatorOrgRegNumber] = $originator;
        } else {
            $originator = $this->originatorOrgs[$archive->originatorOrgRegNumber];
        }

        $archive->originatorOwnerOrgId = $originator->ownerOrgId;
        $originatorOrg = $this->organizationController->read($originator->ownerOrgId);
        $archive->originatorOwnerOrgRegNumber = $originatorOrg->registrationNumber;
    }

    protected function completeArchiver($archive)
    {
        if (empty($archive->archiverOrgRegNumber)) {
            $ownerOrgs = $this->organizationController->getOrgsByRole('owner');
            if (count($ownerOrgs) > 0) {
                $archive->archiverOrgRegNumber = $ownerOrgs[0]->registrationNumber;
            }
        }
    }

    /**
     * Complete management codes with the archival profile
     *
     * @param recordsManagement/archive $archive The archive to complete
     */
    public function completeArchivalProfileCodes($archive)
    {
        if ($archive->archivalProfileReference == "") {
            return;
        }

        $archivalProfile = $this->useArchivalProfile($archive->archivalProfileReference);

        if (!empty($archivalProfile->retentionRuleCode)) {
            $archive->retentionRuleCode = $archivalProfile->retentionRuleCode;
        }

        if (!empty($archivalProfile->accessRuleCode)) {
            $archive->accessRuleCode = $archivalProfile->accessRuleCode;
        }

        if (!empty($archivalProfile->retentionStartDate)) {
            $archive->retentionStartDate = $archivalProfile->retentionStartDate;
        }

        if (empty($archive->fileplanLevel)) {
            $archive->fileplanLevel = 'file';
            if (!empty($archivalProfile->fileplanLevel)) {
                $archive->fileplanLevel = $archivalProfile->fileplanLevel;
            }
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
            $dateInter = new \DateInterval($archive->accessRuleDuration);
            if ($dateInter->y == 9999) {
                return;
            }

            if (is_string($archive->accessRuleStartDate)) {
                $archive->accessRuleStartDate = \laabs::newDate($archive->accessRuleStartDate);
            }
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

            if (!$archive->finalDisposition) {
                $archive->finalDisposition =  $retentionRule->finalDisposition;
            }
        }

        if (is_string($archive->retentionStartDate)) {
            switch ($archive->retentionStartDate) {
                case 'originatingDate':
                    $archive->retentionStartDate = $archive->originatingDate;
                    break;

                case 'depositDate':
                    $archive->retentionStartDate = \laabs::newDate();
                    break;

                default:
                    $qname = \laabs\explode("/", $archive->retentionStartDate);
                    if ($qname[0] == "description" && isset($archive->descriptionObject->{$qname[1]})) {
                        $archive->retentionStartDate = \laabs::newDate($archive->descriptionObject->{$qname[1]});
                    } else {
                        $archive->retentionStartDate = null;
                    }
            }
        }

        $archive->disposalDate = null;
        if (!empty($archive->retentionStartDate) && !empty($archive->retentionDuration) && $archive->retentionDuration->y < 9999) {
            if (is_string($archive->retentionStartDate)) {
                $archive->retentionStartDate = \laabs::newDate($archive->retentionStartDate);
            }
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
        if (!empty($archive->archivalProfileReference) && empty($archive->serviceLevelReference)) {
            $currentOrg = \laabs::getToken("ORGANIZATION");
            $archivalProfileAccess = $this->organizationController->getOrgUnitArchivalProfile($currentOrg->orgId, $archive->archivalProfileReference);

            if (!empty($archivalProfileAccess)) {
                $archive->serviceLevelReference = $archivalProfileAccess->serviceLevelReference;
            }
        }

        $this->useServiceLevel('deposit', $archive->serviceLevelReference);

        $archive->serviceLevelReference = $this->currentServiceLevel->reference;
        if (strpos($this->currentServiceLevel->control, "fullTextIndexation") !== false && $archive->descriptionClass != "recordsManagement/log") {
            $archive->fullTextIndexation = "requested";
        } else {
            $archive->fullTextIndexation = "none";
        }
    }

    /**
     * Complete processing status
     *
     * @param recordsManagement/archive $archive The archive to complete
     */
    protected function completeProcessingStatus($archive)
    {
        if (!empty($archive->processingStatus) || !isset($archive->archivalProfileReference)) {
            return;
        }

        $processingStatuses = json_decode($this->currentArchivalProfile->processingStatuses);

        if (empty($processingStatuses)) {
            return;
        }

        // Recovery Initial and Default statuses if exists ...
        foreach ($processingStatuses as $code => $processingStatus) {
            if ($processingStatus->default == true) {
                $archive->processingStatus = $code;
            }
        }
    }

    /**
     * Convert resources of archive
     *
     * @param recordsManagement/archive $archive The archive to convert
     *
     * @return void
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

        if (isset($archive->digitalResources)) {
            $nbResources = count($archive->digitalResources);
        } else {
            $nbResources = 0;
        }

        if (isset($archive->contents)) {
            $nbArchiveObjects = count($archive->contents);
        } else {
            $nbArchiveObjects = 0;
        }

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
     * Update the retention rule start date of the parent of an archive
     *
     * @param recordsManagement/archive $archive The archive to receive
     */
    public function updateRetentionRuleStartDate($archive) {

        if (!empty($archive->parentArchiveId)) {
            $parentArchive = $this->sdoFactory->read('recordsManagement/archive', $archive->parentArchiveId);
            $parentArchivalProfile = $this->archivalProfileController->getByReference($parentArchive->archivalProfileReference);
            $currentDate = \laabs::newDate();

            if ($parentArchivalProfile->isRetentionLastDeposit) {
                $parentArchiveId = (string) $parentArchive->archiveId;
                $archiveIds = $this->getChildrenArchives($parentArchiveId);
                array_push($archiveIds, $parentArchiveId);

                try {
                    foreach($archiveIds as $archiveId) {
                        $archiveToModify = $this->sdoFactory->read('recordsManagement/archive', $archiveId);
                        
                        $retentionRule = $this->retentionRuleController->read($archiveToModify->retentionRuleCode);
                        $retentionRule = \laabs::castMessage($archiveToModify, 'recordsManagement/archiveRetentionRule');
                        $retentionRule->retentionStartDate = $currentDate;
                        $retentionRule->changeStartDate = true;

                        $this->modifyRetentionRule($retentionRule, $archiveId);   
                    }
                } catch (\Exception $exception) {
                    throw $exception;
                }

            }
        }
    }

    /**
     * Deposit a new archive
     *
     * @param recordsManagement/archive $archive The archive to deposit
     * @param string                    $path    The file plan position
     *
     * @return recordsManagement/archive The archive
     */
    public function deposit($archive, $path = null)
    {
        $accountController = \laabs::newController('auth/userAccount');
        $accountController->isAuthorized('user');

        $transactionControl = !$this->sdoFactory->inTransaction();

        if ($transactionControl) {
            $this->sdoFactory->beginTransaction();
        }

        if (!empty($archive->contents)) {
            $nbArchiveObjects = count($archive->contents);
        } else {
            $nbArchiveObjects = null;
        }

        try {
            if (!isset($archive->archiveId)) {
                $archive->archiveId = \laabs::newId();
            }
            $archive->status = 'preserved';
            $archive->depositDate = \laabs::newTimestamp();

            $this->openContainers($archive, $path);

            $this->sdoFactory->create($archive, 'recordsManagement/archive');

            if (!empty($archive->digitalResources)) {
                $this->storeResources($archive);
            }
            $this->storeDescriptiveMetadata($archive);

            for ($i = 0; $i < $nbArchiveObjects; $i++) {
                $archive->contents[$i]->parentArchiveId = $archive->archiveId;
                $archive->contents[$i]->originatorOwnerOrgId = $archive->originatorOwnerOrgId;
                $archive->contents[$i]->serviceLevelReference = $archive->serviceLevelReference;

                $this->deposit($archive->contents[$i], $archive->storagePath);
            }
        } catch (\Exception $exception) {
            $nbResources = 0;
            if (!is_null($archive->digitalResources)) {
                $nbResources = count($archive->digitalResources);
            }
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

        $this->updateRetentionRuleStartDate($archive);
        
        $this->logDeposit($archive);

        return $archive;
    }

    /**
     * Send response
     */
    public function sendResponse()
    {
    }

    protected function openContainers($archive, $path = null)
    {
        if (empty($path)) {
            if (isset($archive->parentArchiveId)) {
                $parentArchive = $this->sdoFactory->read('recordsManagement/archive', $archive->parentArchiveId);

                $path = $parentArchive->storagePath;
            } else {
                if (!$this->storePath) {
                    $path = $archive->originatorOwnerOrgId."/".$archive->originatorOrgRegNumber;
                } else {
                    $path = $this->storePath;
                }

                $path = $this->resolveStoragePath($archive, $path);
            }
        } else {
            $path = $this->resolveStoragePath($archive, $path);
        }

        // Add archiveId as container name in path
        $path .= "/".$archive->archiveId;

        if (!$this->currentServiceLevel) {
            if (isset($archive->serviceLevelReference)) {
                $this->useServiceLevel('deposit', $archive->serviceLevelReference);
            } else {
                $this->useServiceLevel('deposit');
            }
        }

        $metadata = get_object_vars($archive);
        unset($metadata['contents']);
        foreach ($metadata as $name => $value) {
            if (is_null($value)) {
                unset($metadata[$name]);
            }
        }

        $storagePath = $this->digitalResourceController->openContainers($this->currentServiceLevel->digitalResourceClusterId, $path, $metadata);

        $archive->storagePath = $storagePath;
    }

    /**
     * Store archive resources
     * @param recordsManagement/archive $archive The archive to deposit
     */
    protected function storeResources($archive)
    {
        $nbResources = count($archive->digitalResources);

        for ($i = 0; $i < $nbResources; $i++) {
            $digitalResource = $archive->digitalResources[$i];
            $digitalResource->archiveId = $archive->archiveId;

            $this->digitalResourceController->store($digitalResource);
        }
    }

    /**
     * Store archive resources
     *
     * @param recordsManagement/archive $archive The archive to deposit
     */
    protected function storeDescriptiveMetadata($archive)
    {
        $descriptionController = $this->useDescriptionController($archive->descriptionClass);

        $descriptionController->create($archive);
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

        if (preg_match_all("/\<[^\>]+\>/", $pattern, $variables)) {
            foreach ($variables[0] as $variable) {
                $token = substr($variable, 1, -1);
                switch (true) {
                    case $token == 'app':
                        $pattern = str_replace($variable, \laabs::getApp(), $pattern);
                        break;

                    case $token == 'instance':
                        if ($instanceName = \laabs::getInstanceName()) {
                            $pattern = str_replace($variable, $instanceName, $pattern);
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
}
