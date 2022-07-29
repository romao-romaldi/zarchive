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

use core\Exception;

/**
 * Class of adminArchivalProfile
 *
 * @author Alexis Ragot <alexis.ragot@maarch.org>
 */
class archivalProfile
{
    protected $sdoFactory;
    protected $csv;

    protected $lifeCycleJournalController;

    protected $descriptionFields;

    protected $profilesDirectory;
    protected $descriptionSchemeController;

    /**
     * Constructor
     * @param \dependency\sdo\Factory $sdoFactory The sdo factory
     * @param bool                    $notifyModification The state of the fonction of notification modification
     * @param string                  $profilesDirectory  The profile directory
     */
    public function __construct(\dependency\sdo\Factory $sdoFactory, \dependency\csv\Csv $csv = null, $notifyModification, $profilesDirectory)
    {
        $this->sdoFactory = $sdoFactory;
        $this->csv = $csv;
        $this->lifeCycleJournalController = \laabs::newController('lifeCycle/journal');
        $this->descriptionSchemeController = \laabs::newController('recordsManagement/descriptionScheme');
        //$this->descriptionFields = \Laabs::newController('recordsManagement/descriptionField')->index();

        if (!is_dir($profilesDirectory) && !empty($profilesDirectory)) {
            mkdir($profilesDirectory, 0777, true);
        }

        $this->profilesDirectory = $profilesDirectory;

        $this->notifyModification = $notifyModification;
    }

    /**
     * List archival profiles
     *
     * @param integer $limit Maximal number of results to dispay
     * @param string  $query The query filter
     *
     * @return recordsManagement/archivalProfile[] The list of archival profiles
     */
    public function index($limit = null, $query = null)
    {
        $archivalProfiles = $this->sdoFactory->find('recordsManagement/archivalProfile', $query, null, null, null, $limit);

        foreach ($archivalProfiles as $archivalProfile) {
            $archivalProfile->containedProfiles = $this->getContentsProfiles($archivalProfile->archivalProfileId, true);
        }
        return $archivalProfiles;
    }

    /**
     * New empty archival profile with default values
     *
     * @return recordsManagement/archivalProfile The archival profile object
     */
    public function newProfile()
    {
        $archivalProfile = \laabs::newInstance("recordsManagement/archivalProfile");

        return $archivalProfile;
    }

    /**
     * Edit an archival profile
     * @param string $archivalProfileId   The archival profile's identifier
     * @param bool   $withRelatedProfiles Bring back the contents profiles
     * @param bool   $recursively         Get contained archival profiles children
     *
     * @return recordsManagement/archivalProfile The profile object
     */
    public function read($archivalProfileId, $withRelatedProfiles = true, $recursively = false)
    {
        $archivalProfile = $this->sdoFactory->read('recordsManagement/archivalProfile', $archivalProfileId);

        $this->readDetail($archivalProfile);

        if ($withRelatedProfiles) {
            $archivalProfile->containedProfiles = $this->getContentsProfiles($archivalProfileId, $recursively);
        }

        return $archivalProfile;
    }

    /**
     * get an archival profile by reference
     * @param string $reference The archival profile reference
     * @param bool   $withRelatedProfiles      Bring back the contents profiles
     *
     * @return recordsManagement/archivalProfile The profile object
     */
    public function getByReference($reference, $withRelatedProfiles = true)
    {
        if (is_null($reference)) {
            return null;
        }
        try {
            $archivalProfile = $this->sdoFactory->read('recordsManagement/archivalProfile', array('reference' => $reference));

            $this->readDetail($archivalProfile);

            if ($withRelatedProfiles) {
                $archivalProfile->containedProfiles = $this->getContentsProfiles($archivalProfile->archivalProfileId);
            }
        } catch (\Exception $exception) {
            throw new \core\Exception\BadRequestException("Profile %s not found", 404, null, (array) $reference);
        }

        return $archivalProfile;
    }

    /**
     * get array of archival profile by description class
     * @param string $archivalProfileDescriptionClass The archival profile reference
     *
     * @return array $archivalProfiles Array of recordsManagement/archivalProfile object
     */
    public function getByDescriptionClass($archivalProfileDescriptionClass)
    {
        $archivalProfiles = $this->sdoFactory->find('recordsManagement/archivalProfile', "descriptionClass='$archivalProfileDescriptionClass'");

        foreach ($archivalProfiles as $archivalProfile) {
            $this->readDetail($archivalProfile);
        }

        return $archivalProfiles;
    }

    /**
     * Read the agragates
     * @param recordsManagement/archivalProfile $archivalProfile
     */
    public function readDetail($archivalProfile)
    {
        // Read retention rule
        if ($archivalProfile->retentionRuleCode) {
            $archivalProfile->retentionRule = $this->sdoFactory->read('recordsManagement/retentionRule', $archivalProfile->retentionRuleCode);
        }

        // Read access rule
        if (!empty($archivalProfile->accessRuleCode)) {
            $archivalProfile->accessRule = $this->sdoFactory->read('recordsManagement/accessRule', $archivalProfile->accessRuleCode);
        }

        // Read profile description
        $archivalProfile->archiveDescription = $this->sdoFactory->readChildren('recordsManagement/archiveDescription', $archivalProfile, null, 'position');
        usort($archivalProfile->archiveDescription, function ($a, $b) {
            return $a->position > $b->position ? 1 : -1;
        });
        
        $descriptionFields = $this->descriptionSchemeController->getDescriptionFields($archivalProfile->descriptionClass);
        foreach ($archivalProfile->archiveDescription as $archiveDescription) {
            if (isset($descriptionFields[$archiveDescription->fieldName])) {
                $archiveDescription->descriptionField = $descriptionFields[$archiveDescription->fieldName];
            }
        }
        
        $profileFile = $this->profilesDirectory.DIRECTORY_SEPARATOR.$archivalProfile->reference;
        if (file_exists($profileFile.'.rng') || file_exists($profileFile.'.xsd')) {
            $archivalProfile->profileFile = $profileFile;
        }
    }

    /**
     * Get the contents profiles list
     * @param string $archivalProfileId The parent profile identifier
     * @param bool   $recursively       Get contained archival profiles children
     *
     * @return array The list of contents archival profile
     */
    public function getContentsProfiles($archivalProfileId, $recursively = false)
    {
        $containedProfiles = []; 
        $contents = $this->sdoFactory->find('recordsManagement/archivalProfileContents', "parentProfileId ='$archivalProfileId'");

        if (count($contents)) {
            foreach ($contents as $content) {
                $containedProfile = $this->sdoFactory->read('recordsManagement/archivalProfile', $content->containedProfileId);

                $this->readDetail($containedProfile);

                if ($recursively) {
                    $containedProfile->containedProfiles = $this->getContentsProfiles($content->containedProfileId, $recursively);
                }

                $containedProfiles[] = $containedProfile;
            }
        }

        return $containedProfiles;
    }

    /**
     * create an archival profile
     * @param recordsManagement/archivalProfile $archivalProfile The archival profile object
     *
     * @return boolean The result of the request
     */
    public function create($archivalProfile)
    {
        $transactionControl = !$this->sdoFactory->inTransaction();

        if ($transactionControl) {
            $this->sdoFactory->beginTransaction();
        }

        try {
            $archivalProfile->archivalProfileId = \laabs::newId();

            $this->sdoFactory->create($archivalProfile, 'recordsManagement/archivalProfile');

            $this->createDetail($archivalProfile);
            
            // Life cycle journal
            $eventItems = array('archivalProfileReference' => $archivalProfile->reference);
            $this->lifeCycleJournalController->logEvent('recordsManagement/profileCreation', 'recordsManagement/archivalProfile', $archivalProfile->archivalProfileId, $eventItems);
        } catch (\Exception $exception) {
            if ($transactionControl) {
                $this->sdoFactory->rollback();
            }

            throw new \core\Exception("Profile already exist");
        }

        if ($transactionControl) {
            $this->sdoFactory->commit();
        }

        return  $archivalProfile->archivalProfileId;
    }

    /**
     * update an archival profile
     * @param recordsManagement/archivalProfile $archivalProfile The archival profile object
     *
     * @return boolean The request of the request
     */
    public function update($archivalProfile)
    {
        $transactionControl = !$this->sdoFactory->inTransaction();

        if ($transactionControl) {
            $this->sdoFactory->beginTransaction();
        }

        try {
            $oldArchivalProfile = $this->read($archivalProfile->archivalProfileId);
            $archivalProfile->reference = $oldArchivalProfile->reference;

            $this->deleteDetail($archivalProfile);
            $this->createDetail($archivalProfile);

            // archival profile
            $this->sdoFactory->update($archivalProfile, "recordsManagement/archivalProfile");

            // Life cycle journal
            $eventItems = array('archivalProfileReference' => $archivalProfile->reference);
            $this->lifeCycleJournalController->logEvent('recordsManagement/archivalProfileModification', 'recordsManagement/archivalProfile', $archivalProfile->archivalProfileId, $eventItems);
        } catch (\Exception $exception) {
            if ($transactionControl) {
                $this->sdoFactory->rollback();
            }
            throw new \core\Exception("Profile already exist");
        }

        if ($transactionControl) {
            $this->sdoFactory->commit();
        }

        return true;
    }
 
    /**
     * delete an archival profile
     * @param string $archivalProfileId The identifier of the archival profile
     *
     * @return boolean The request of the request
     */
    public function delete($archivalProfileId)
    {
        $archivalProfile = $this->sdoFactory->read('recordsManagement/archivalProfile', $archivalProfileId);

        if ($this->isUsed($archivalProfile)) {
            throw new \core\Exception\ForbiddenException("The archival profile %s currently in use.", 403, null, [$archivalProfile->reference]);
        }

        $this->deleteDetail($archivalProfile);

        $archivalProfileContents = $this->sdoFactory->find('recordsManagement/archivalProfileContents', "parentProfileId='$archivalProfileId' OR containedProfileId='$archivalProfileId'");
        if (!empty($archivalProfileContents)) {
            $this->sdoFactory->deleteCollection($archivalProfileContents, 'recordsManagement/archivalProfileContents');
        }
        
        $organizationController = \laabs::newController('organization/organization');
        $archivalProfileAccesses = $organizationController->getArchivalProfileAccess($orgId=null, $archivalProfileId);
        foreach ($archivalProfileAccesses as $archivalProfileAccess) {
            $organizationController->deleteArchivalProfileAccess($archivalProfileAccess->orgId, $archivalProfile->reference);
        }

        $this->sdoFactory->delete($archivalProfile);

        // Life cycle journal
        $eventItems = array('archivalProfileReference' => $archivalProfile->reference);
        $this->lifeCycleJournalController->logEvent('recordsManagement/profileDestruction', 'recordsManagement/archivalProfile', $archivalProfile->archivalProfileId, $eventItems);

        return true;
    }

    /**
     * Get form of teh description class
     * @param string $archivalProfileReference The reference of the archival profile
     *
     * @return recordsManagement/descriptionClass Object The description class object parsed with the profile descriptions
     */
    public function descriptionForm($archivalProfileReference)
    {
        $archivalProfile = $this->getByReference($archivalProfileReference);
        $descriptionObject = \laabs::newController($archivalProfile->descriptionClass)->form($archivalProfile->archiveDescription);
        $descriptionObject->descriptionClass =  $archivalProfile->descriptionClass;

        return $descriptionObject;
    }

    /**
     * Check if archival
     * @param recordsManagement/archivalProfile $archivalProfile The archival profile object
     *
     * @return bool The result of the operation
     */
    public function isUsed($archivalProfile)
    {
        $archivalAgreementController = \laabs::newController('medona/archivalAgreement');
        $archivalAgreement = $archivalAgreementController->getByProfileReference($archivalProfile->reference);

        if (!empty($archivalAgreement)) {
            return false;
        }

        return (bool) $this->sdoFactory->count('recordsManagement/archive', "archivalProfileReference = '$archivalProfile->reference'");
    }

    /**
     * Create a detail
     * @param recordsManagement/archivalProfile $archivalProfile The archival profile object
     *
     */
    protected function createDetail($archivalProfile)
    {
        if (!empty($archivalProfile->archiveDescription)) {
            $position = 0;
            foreach ($archivalProfile->archiveDescription as $description) {
                $description->archivalProfileId = $archivalProfile->archivalProfileId;
                $description->position = $position;
                $position++;

                $this->sdoFactory->create($description, 'recordsManagement/archiveDescription');
            }
        }

        // Contents profiles       
        if (!empty($archivalProfile->containedProfiles)) {
            foreach ($archivalProfile->containedProfiles as $containedProfileId) {
                try {
                    $profile = $this->sdoFactory->read("recordsManagement/archivalProfile", $containedProfileId);
                } catch (\Exception $e) {
                    throw new \core\Exception\NotFoundException("%s can't be found.", 404, null, [$containedProfileId]);
                }
                
                $archivalProfileContents = \laabs::newInstance('recordsManagement/archivalProfileContents');
                $contentProfiles = $this->getContentsProfiles($containedProfileId);
                if (!empty($contentProfiles)) {
                    foreach ($contentProfiles as $contentProfile) {
                        if ($contentProfile->archivalProfileId == $archivalProfile->archivalProfileId) {
                            throw new \core\Exception\BadRequestException("%s cannot be recursively called.", 404, null, [$contentProfile->name]);
                        }
                    }
                }
                $archivalProfileContents->parentProfileId = $archivalProfile->archivalProfileId;
                $archivalProfileContents->containedProfileId = $containedProfileId;

                $this->sdoFactory->create($archivalProfileContents, 'recordsManagement/archivalProfileContents');
            }
        }
    }

    /**
     * Delete a detail
     * @param recordsManagement/archivalProfile $archivalProfile The archival profile object
     *
     */
    protected function deleteDetail($archivalProfile)
    {
        $this->sdoFactory->deleteChildren('recordsManagement/archiveDescription', $archivalProfile, 'recordsManagement/archivalProfile');

        $this->sdoFactory->deleteChildren('recordsManagement/archivalProfileContents', $archivalProfile, 'recordsManagement/archivalProfile');
    }

    /**
     * Upload a profile file
     * @param string $profileReference The profile reference
     * @param string $archivalProfile  The profile binary file
     * @param string $format           The profile file format
     *
     * @return boolean The result of the operation
     */
    public function uploadArchivalProfile($profileReference, $archivalProfile, $content, $format = 'rng')
    {
        if (empty($format)) {
            $format = 'rng';
        }

        $profilesDirectory = $this->profilesDirectory;
        $profilesDirectory .= DIRECTORY_SEPARATOR.$profileReference.'.'.$format ;
        $content = base64_decode($content);

        if (!$archivalProfile->archivalProfileId) {
            $archivalProfileController = \laabs::newController('recordsManagement/archivalProfile');
            $archivalProfileController->create($archivalProfile);
        }

        file_put_contents($profilesDirectory, $content);

        $archivalProfile = $this->getByReference($profileReference);

        if (strpos($content, "fr:gouv:culture:archivesdefrance:seda:v1") > -1) {
            $archivalProfile->descriptionSchema = "seda";
        } elseif (strpos($content, "fr:gouv:culture:archivesdefrance:seda:v2") > -1) {
            $archivalProfile->descriptionSchema = "seda2";
        }

        $this->sdoFactory->update($archivalProfile, "recordsManagement/archivalProfile");

        return true;
    }

    /**
     * Export profile file
     * @param string $profileReference
     *
     * @return string
     */
    public function exportFile($profileReference)
    {
        $profilesDirectory = $this->profilesDirectory;
        $fileDirectory = $profilesDirectory.DIRECTORY_SEPARATOR.$profileReference.".rng";

        $file = file_get_contents($fileDirectory);

        return $file;
    }

    public function exportCsv($limit = null)
    {
        $archivalProfiles = $this->sdoFactory->find('recordsManagement/archivalProfile', null, null, null, null, $limit);
        foreach ($archivalProfiles as $key => $archivalProfile) {
            $containedProfiles = $this->getContentsProfiles($archivalProfile->archivalProfileId);
            $archiveDescription = $this->sdoFactory->readChildren('recordsManagement/archiveDescription', $archivalProfile, null, 'position');

            $archivalProfile = \laabs::castMessage($archivalProfile, 'recordsManagement/archivalProfileImportExport');
            if ($containedProfiles) {
                $lastIndex = count($containedProfiles) -1;
                foreach ($containedProfiles as $index => $containedProfile) {
                    $archivalProfile->childrenProfiles .= $containedProfile->reference;

                    if ($lastIndex !== $index) {
                        $archivalProfile->childrenProfiles .= ";";
                    }
                }
            }

            $archivalProfile->archiveDescriptions = json_encode($archiveDescription);

            $archivalProfiles[$key] = $archivalProfile;
        }

        $handler = fopen('php://temp', 'w+');
        $this->csv->writeStream($handler, (array) $archivalProfiles, 'recordsManagement/archivalProfileImportExport', true);
        return $handler;
    }

    public function import($data, $isReset = false)
    {
        $transactionControl = !$this->sdoFactory->inTransaction();

        if ($transactionControl) {
            $this->sdoFactory->beginTransaction();
        }

        try {
            if ($isReset) {
                $archivalProfiles = $this->index();

                foreach ($archivalProfiles as $archivalProfile) {
                    $this->sdoFactory->deleteChildren('recordsManagement/archivalProfileContents', $archivalProfile, 'recordsManagement/archivalProfile');
                    $this->sdoFactory->deleteChildren('recordsManagement/archiveDescription', $archivalProfile, 'recordsManagement/archivalProfile');
                    $archivalProfileContents = $this->sdoFactory->find('recordsManagement/archivalProfileContents', "parentProfileId='$archivalProfile->archivalProfileId' OR containedProfileId='$archivalProfile->archivalProfileId'");
                    if (!empty($archivalProfileContents)) {
                        $this->sdoFactory->deleteCollection($archivalProfileContents, 'recordsManagement/archivalProfileContents');
                    }
                    $this->sdoFactory->delete($archivalProfile, 'recordsManagement/archivalProfile');
                }
            }

            $archivalProfiles = $this->csv->readStream($data, 'recordsManagement/archivalProfileImportExport', true);
            $archivalProfileContents = [];
            $archivalProfilesReferences = [];
            foreach ($archivalProfiles as $key => $archivalProfile) {
                if ($isReset
                    || !$this->sdoFactory->exists('recordsManagement/archivalProfile', array('reference' => $archivalProfile->reference))
                ) {
                    $archiveProfileId = $this->importArchivalProfile($archivalProfile, 'create');
                } else {
                    $archiveProfileId = $this->importArchivalProfile($archivalProfile, 'update');
                }

                if ($archivalProfile->childrenProfiles) {
                    $archivalProfileContents[$archivalProfile->reference] = explode(';', $archivalProfile->childrenProfiles);
                }
                $archivalProfilesReferences[$archivalProfile->reference] = (string) $archiveProfileId;
            }

            foreach ($archivalProfileContents as $parentProfileReference => $containedProfileReferences) {
                foreach ($containedProfileReferences as $containedProfileReference) {
                    $archivalProfileContent = \laabs::newInstance('recordsManagement/archivalProfileContents');
                    $archivalProfileContent->parentProfileId = $archivalProfilesReferences[$parentProfileReference];
                    $archivalProfileContent->containedProfileId = $archivalProfilesReferences[$containedProfileReference];

                    $this->sdoFactory->create($archivalProfileContent, 'recordsManagement/archivalProfileContents');
                }
            }
        } catch (\Exception $e) {
            if ($transactionControl) {
                $this->sdoFactory->rollback();
            }
            throw $e;
        }

        if ($transactionControl) {
            $this->sdoFactory->commit();
        }

        return true;
    }

    private function importArchivalProfile($importArchivalProfile, $type) {
        if ($type === 'create') {
            $archivalProfile = \laabs::newInstance('recordsManagement/archivalProfile');
            $archivalProfile->archivalProfileId = \laabs::newId();
        } else {
            $archivalProfile = $this->sdoFactory->find('recordsManagement/archivalProfile', "reference ='".$importArchivalProfile->reference."'")[0];
        }

        $archivalProfile->reference = $importArchivalProfile->reference;
        $archivalProfile->name = $importArchivalProfile->name;
        $archivalProfile->description = $importArchivalProfile->description;
        $archivalProfile->descriptionSchema = $importArchivalProfile->descriptionSchema;
        $archivalProfile->descriptionClass = $importArchivalProfile->descriptionClass;
        $archivalProfile->retentionStartDate = $importArchivalProfile->retentionStartDate;
        $archivalProfile->retentionRuleCode = $importArchivalProfile->retentionRuleCode;
        $archivalProfile->accessRuleCode = $importArchivalProfile->accessRuleCode;
        $archivalProfile->acceptUserIndex = $importArchivalProfile->acceptUserIndex;
        $archivalProfile->acceptArchiveWithoutProfile = $importArchivalProfile->acceptArchiveWithoutProfile;
        $archivalProfile->fileplanLevel = $importArchivalProfile->fileplanLevel;

        if ($type === 'create') {
            $this->sdoFactory->create($archivalProfile, 'recordsManagement/archivalProfile');
        } else {
            $this->sdoFactory->update($archivalProfile, 'recordsManagement/archivalProfile');

            $this->sdoFactory->deleteChildren('recordsManagement/archivalProfileContents', $archivalProfile, 'recordsManagement/archivalProfile');
            $this->sdoFactory->deleteChildren('recordsManagement/archiveDescription', $archivalProfile, 'recordsManagement/archivalProfile');
        }

        if ($importArchivalProfile->archiveDescriptions) {
            $archiveDescriptions = json_decode($importArchivalProfile->archiveDescriptions);
            foreach ($archiveDescriptions as $archiveDescription) {
                $newArchivalDescription = \laabs::newInstance('recordsManagement/archiveDescription');
                $newArchivalDescription->archivalProfileId = (string) $archivalProfile->archivalProfileId;
                $newArchivalDescription->fieldName = $archiveDescription->fieldName;
                $newArchivalDescription->required = $archiveDescription->required;
                $newArchivalDescription->position = $archiveDescription->position;
                $newArchivalDescription->isImmutable = $archiveDescription->isImmutable;
                $newArchivalDescription->isRetained = $archiveDescription->isRetained;
                $newArchivalDescription->isInList = $archiveDescription->isInList;

                $this->sdoFactory->create($newArchivalDescription,'recordsManagement/archiveDescription');
            }
        }
            
        return $archivalProfile->archivalProfileId;
    }

    /**
     * Get identifiers of used archival profiles
     * 
     * @return recordsManagement/archivalProfile[] Array of archival profiles
     */
    public function getUsedArchivalProfilesReference() {
        $usedArchivalProfilesReference = [];
        $archivalProfileUses = $this->sdoFactory->summarise("organization/archivalProfileAccess","archivalProfileReference");
        foreach($archivalProfileUses as $archivalProfileReference => $uses) {
            if (($uses > 0) && ($archivalProfileReference!='*')) {
                $usedArchivalProfilesReference[] = $archivalProfileReference;
            }
        }
        return $usedArchivalProfilesReference;
    }
}
