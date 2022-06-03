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
 * Class for Records Management archives
 */
class archive
{

    use archiveEntryTrait,
        archiveValidationTrait,
        archiveAccessTrait,
        archiveModificationTrait,
        archiveRestitutionTrait,
        archiveComplianceTrait,
        archiveConversionTrait,
        archiveDestructionTrait,
        archiveOutgoingTransferTrait,
        archiveLifeCycleTrait;

    /**
     * Sdo Factory for management of archive persistance
     * @var dependency/sdo/Factory
     */
    protected $sdoFactory;

    /**
     * Controller for digital resource
     * @var digitalResource/Controller/digitalResource
     */
    protected $digitalResourceController;

    /**
     * Controller for format
     * @var digitalResource/Controller/pronomFormat
     */
    protected $pronomFormatController;

    /**
     * Controller for access rules
     * @var recordsManagement/Controller/accessRule
     */
    protected $accessRuleController;

    /**
     * Controller for archive relationships
     * @var recordsManagement/Controller/archiveRelationship
     */
    protected $archiveRelationshipController;

    /**
     * Controller for archival profiles
     * @var recordsManagement/Controller/archivalProfile
     */
    protected $archivalProfileController;

    /**
     * Controller for description schemes
     * @var recordsManagement/Controller/descriptionScheme
     */
    protected $descriptionSchemeController;

    /**
     * Controller for service levels
     * @var recordsManagement/Controller/serviceLevel
     */
    protected $serviceLevelController;

    /**
     * Controller for life cycle journal events
     * @var recordsManagement/Controller/lifeCycleJournal
     */
    protected $lifeCycleJournalController;

    /**
     * Controller for life cycle journal events
     * @var recordsManagement/Controller/lifeCycleJournal
     */
    protected $organizationController;

    /**
     * Controller for user position
     * @var recordsManagement/Controller/userPosition
     */
    protected $userPositionController;

    /**
     * Controller for user position
     * @var recordsManagement/Controller/servicePosition
     */
    protected $servicePositionController;

    /**
     * Controller for user filePlan
     * @var filePlan/Controller/FilePlan
     */
    protected $filePlanController;

    /**
     * Previously loaded archival profiles, indexed by reference
     * @var array
     */
    protected $archivalProfiles;

    /**
     * Currently used archival profile
     * @var recordsManagement/archivalProfile
     */
    protected $currentArchivalProfile;

    /**
     * Previously loaded service levels, indexed by reference
     * @var array
     */
    protected $serviceLevels;

    /**
     * Currently used service level
     * @var recordsManagement/serviceLevel
     */
    protected $currentServiceLevel;

    /**
     * Previously loaded description object controllers, indexed by reference
     * @var array
     */
    protected $descriptionControllers;

    /**
     * Currently description object controller
     * @var object
     */
    protected $currentDescriptionController;

    /**
     * The hash algo for resources
     * @var string
     */
    protected $hashAlgorithm;

    /**
     * Delete description when an archive is deleted
     * @var object
     */
    protected $deleteDescription;

    /**
     * The configuration to accept an archive when there is a conversion error
     * @var bool
     */
    protected $conversionError;

    /**
     * The configuration of the storage path
     * @var string
     */
    protected $storePath;

    /**
     * The compression utility
     * @var object
     */
    protected $zip;


    /**
     * Constructor
     * @param \dependency\sdo\Factory $sdoFactory        The dependency sdo factory service
     * @param string                  $hashAlgorithm     The hash algorithm for digital archives
     * @param string                  $deleteDescription The delete description
     * @param string                  $conversionError   The conversion error
     * @param string                  $storePath         The storage path
     */
    public function __construct(\dependency\sdo\Factory $sdoFactory, $hashAlgorithm = 'SHA256', $deleteDescription = true, $conversionError = false, $storePath = null)
    {

        $this->hashAlgorithm = $hashAlgorithm;

        $this->deleteDescription = $deleteDescription;

        $this->sdoFactory = $sdoFactory;

        $this->digitalResourceController = \laabs::newController("digitalResource/digitalResource");

        $this->formatController = \laabs::newController("digitalResource/pronomFormat");

        $this->archiveRelationshipController = \laabs::newController("recordsManagement/archiveRelationship");

        $this->archivalProfileController = \laabs::newController("recordsManagement/archivalProfile");

        $this->descriptionSchemeController = \laabs::newController("recordsManagement/descriptionScheme");

        $this->serviceLevelController = \laabs::newController("recordsManagement/serviceLevel");

        $this->lifeCycleJournalController = \laabs::newController("lifeCycle/journal");

        $this->accessRuleController = \laabs::newController('recordsManagement/accessRule');

        $this->organizationController = \laabs::newController('organization/organization');

        $this->userPositionController = \laabs::newController('organization/userPosition');

        $this->servicePositionController = \laabs::newController('organization/servicePosition');

        $this->retentionRuleController = \laabs::newController("recordsManagement/retentionRule");

        $this->filePlanController = \laabs::newController("filePlan/filePlan");

        $this->zip = \laabs::newService("dependency/fileSystem/plugins/zip");

        $this->conversionError = (bool) $conversionError;

        $this->storePath = $storePath;
    }

    /**
     * Load archive references
     * @param object $archive   The archive
     * @param string $operation The requested operation: deposit, communication, modification, restitution, destruction
     */
    public function useReferences($archive, $operation)
    {
        if (!empty($archive->archivalProfileReference)) {
            $this->useArchivalProfile($archive->archivalProfileReference);
        }

        if (!empty($archive->serviceLevelReference)) {
            $this->useServiceLevel($operation, $archive->serviceLevelReference);
        } else {
            $this->useServiceLevel($operation);
        }

        $this->useDescriptionController($archive->descriptionClass);
    }

    /**
     * Select an archival profile for use
     * @param string $archivalProfileReference
     *
     * @return recordsManagement/archivalProfile An archival profile
     */
    public function useArchivalProfile($archivalProfileReference)
    {
        if (!isset($this->archivalProfiles[$archivalProfileReference])) {
            $this->currentArchivalProfile = $this->archivalProfileController->getByReference($archivalProfileReference);
            $this->archivalProfiles[$archivalProfileReference] = $this->currentArchivalProfile;
        } else {
            $this->currentArchivalProfile = $this->archivalProfiles[$archivalProfileReference];
        }

        return $this->currentArchivalProfile;
    }

    /**
     * Select a service level for use
     * @param string $operation
     * @param string $serviceLevelReference
     *
     * @return recordsManagement/serviceLevel A service level
     */
    public function useServiceLevel($operation, $serviceLevelReference = null)
    {
        if (!$serviceLevelReference) {
            $this->currentServiceLevel = $this->serviceLevelController->getDefault();

            $this->serviceLevels[$this->currentServiceLevel->reference] = $this->currentServiceLevel;
        } else {
            if (!isset($this->serviceLevels[$serviceLevelReference])) {
                $this->currentServiceLevel = $this->serviceLevelController->getByReference($serviceLevelReference);

                $this->serviceLevels[$serviceLevelReference] = $this->currentServiceLevel;
            } else {
                $this->currentServiceLevel = $this->serviceLevels[$serviceLevelReference];
            }
        }

        switch ($operation) {
            case 'deposit':
                $mode = 'write';
                $limit = true;
                break;

            case 'destruction':
                $mode = 'delete';
                $limit = false;
                break;

            case 'restitution':
            case 'communication':
            default:
                $mode = "read";
                $limit = true;
                break;
        }

        $digitalResourceCluster = $this->digitalResourceController->useCluster($this->currentServiceLevel->digitalResourceClusterId, $mode, $limit);

        $control = explode(" ", $this->currentServiceLevel->control);

        if (in_array("storeMetadata", $control) && !$digitalResourceCluster->storeMetadata) {
            throw \laabs::newException('recordsManagement/serviceLevelException', "The Service level requires the digital resource cluster to store metadata, but the selected cluster does not.");
        }

        if (in_array("storeMetadata", $control) && count($digitalResourceCluster->clusterRepository) < 2) {
            throw \laabs::newException('recordsManagement/serviceLevelException', "The Service level requires a redundant storage, but the selected cluster does not have sufficiant repositories.");
        }

        return $this->currentServiceLevel;
    }

    /**
     * Select a description controller
     * @param string $descriptionScheme
     *
     * @return object descriptionClass controller
     */
    public function useDescriptionController($descriptionScheme)
    {
        $controllerClass = $this->getDescriptionControllerClass($descriptionScheme);

        if (!isset($this->descriptionControllers[$descriptionScheme])) {
            $this->currentDescriptionController = \laabs::newController($controllerClass);

            $this->descriptionControllers[$descriptionScheme] = $this->currentDescriptionController;
        } else {
            $this->currentDescriptionController = $this->descriptionControllers[$descriptionScheme];
        }

        return $this->currentDescriptionController;
    }

    protected function getDescriptionControllerClass($descriptionScheme)
    {
        // Default description class
        if (empty($descriptionScheme)) {
            return 'recordsManagement/description';
        } 

        $descriptionSchemeConfig = $this->descriptionSchemeController->read($descriptionScheme);
        if (!empty($descriptionSchemeConfig) && isset($descriptionSchemeConfig->controller)) {
            $controllerClass = $descriptionSchemeConfig->controller;
        } else {
            $controllerClass = $descriptionScheme;
        }

        try {
            $bundle = \laabs::bundle(strtok($controllerClass, LAABS_URI_SEPARATOR));
            $controller = $bundle->getController(strtok(''));
        } catch (\exception $exception) {
            return 'recordsManagement/description';
        }
        
        return $controllerClass;
    }

    /**
     * Read an archive by its id
     * @param string $archiveId The archive identifier
     *
     * @return recordsManagement/archive object
     */
    public function read($archiveId)
    {
        return $this->sdoFactory->read('recordsManagement/archive', $archiveId);
    }

    /**
     * Check if the new status of an archive is possible depending on current status
     * @param string $archiveId  Identifier of the archives to check
     * @param string $status     New status to check
     *
     * @return boolean           if the new status is possible on this archive (depends on the current status of the archive)
     */
    public function checkStatus($archiveId, $status)
    {
        $statusList = [];
        $statusList['restituable'] = array('preserved', 'restituted', 'disposed');
        
        if (!isset($statusList[$status])) {
            return false;
        }
        $archive = $this->sdoFactory->read('recordsManagement/archiveStatus', $archiveId);

        if (!in_array($archive->status, $statusList[$status])) {
            return false;
        }

        return true;
    }
}
