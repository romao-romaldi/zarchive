<?php

/*
 * Copyright (C) 2017 Maarch
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
 * Trait for archives life cycle logging
 */
trait archiveLifeCycleTrait
{
    /**
     * Log an archive life cycle event
     * @param string                          $type      	   The eventType
     * @param recordsManagement/archive       $archive   	   The archive
     * @param bool                            $operationResult The event result
     * @param digitalResource/digitalResource $digitalResource The resouce
     * @param array                           $eventInfo 	   The event information
     *
     * @return mixed The created event or the list of created event
     */
    protected function logLifeCycleEvent($type, $archive, $operationResult = true, $digitalResource = null, $eventInfo = null)
    {
        $eventItems = !empty($eventInfo) ? $eventInfo : [];
        $res = null;

        $eventItems = array_merge($eventItems, get_object_vars($archive));

        //$eventItems["originatorOwnerOrgRegNumber"] = $archive->originatorOwnerOrgRegNumber;

        if ($digitalResource) {
            $eventItems = array_merge($eventItems, get_object_vars($digitalResource));
            $eventItems['address'] = $digitalResource->address[0]->path;

            $res = $this->lifeCycleJournalController->logEvent($type, 'recordsManagement/archive', $archive->archiveId, $eventItems, $operationResult);

        } else if (!empty($archive->digitalResources)) {
            $res = [];

            foreach ($archive->digitalResources as $digitalResource) {
                $eventItems = array_merge($eventItems, get_object_vars($digitalResource));
                $eventItems['address'] = $digitalResource->address[0]->path;

                $res[] = $this->lifeCycleJournalController->logEvent($type, 'recordsManagement/archive', $archive->archiveId, $eventItems, $operationResult);
            }

        } else {

            $eventItems['address'] = $archive->storagePath;

            $res = $this->lifeCycleJournalController->logEvent($type, 'recordsManagement/archive', $archive->archiveId, $eventItems, $operationResult);
        }

        return $res;
    }

    /**
     * Log an archive deposit
     * @param recordsManagement/archive $archive   	 The archive
     * @param bool                      $operationResult The operation result
     *
     * @return mixed The created event or the list of created event
     */
    public function logDeposit($archive, $operationResult = true)
    {
        return $this->logLifeCycleEvent('recordsManagement/deposit', $archive, $operationResult);
    }

    /**
     * Log an archive consultation
     * @param recordsManagement/archive       $archive         The archive
     * @param digitalResource/digitalResource $resource        The resouce
     * @param bool                            $operationResult The operation result
     *
     * @return mixed The created event or the list of created event
     */
    public function logConsultation($archive, $resource, $operationResult = true)
    {
        if (empty($archive->serviceLevelReference)) {
            return;
        }

        $serviceLevel = $this->serviceLevelController->getByReference($archive->serviceLevelReference);

        if (strrpos($serviceLevel->control, "logConsultation") === false) {
            return;
        }

        if (empty($resource)) {
            $operationResult = false;
        }

        return $this->logLifeCycleEvent('recordsManagement/consultation',$archive, $operationResult, $resource);
    }


    /**
     * Log an archive delivery
     * @param recordsManagement/archive $archive         The archive
     * @param bool                      $operationResult The operation result
     *
     * @return mixed The created event or the list of created event
     */
    public function logDelivery($archive, $operationResult = true)
    {
        return $this->logLifeCycleEvent('recordsManagement/delivery',$archive, $operationResult);
    }


    /**
     * Log an archive resource conversion
     * @param digitalResource/digitalResource $originalResource  The resouce
     * @param digitalResource/digitalResource $convertedResource The resouce
     * @param recordsManagement/archive       $archive           The archive
     * @param bool                            $operationResult   The operation result
     *
     * @return mixed The created event or the list of created event
     */
    public function logConvertion($originalResource, $convertedResource, $archive, $operationResult = true)
    {
        $eventInfo = [];
        if ($convertedResource) {
            if (!empty($convertedResource->resId)) {
                $eventInfo['convertedResId'] = $convertedResource->resId;
            }

            if (!empty($convertedResource->address)) {
                $eventInfo['convertedAddress'] = $convertedResource->address[0]->path;
            }

            $eventInfo['convertedHashAlgorithm'] = $convertedResource->hashAlgorithm;
            $eventInfo['convertedHash'] = $convertedResource->hash;
            $eventInfo['software'] = $convertedResource->softwareName.' '.$convertedResource->softwareVersion;
            $eventInfo['convertedSize'] = $convertedResource->size;
            $eventInfo['convertedPuid'] = $convertedResource->puid;
        }

        return $this->logLifeCycleEvent('recordsManagement/conversion', $archive, $operationResult, $originalResource, $eventInfo);
    }

    /**
     * Log an archive elimination
     * @param recordsManagement/archive $archive         The archive
     * @param bool                      $operationResult The operation result
     *
     * @return mixed The created event or the list of created event
     */
    public function logElimination($archive, $operationResult = true)
    {
        return $this->logLifeCycleEvent('recordsManagement/elimination', $archive, $operationResult);
    }

    /**
     * Log an archive destruction request
     * @param recordsManagement/archive $archive   	     The archive
     * @param bool  					$operationResult The operation result
     *
     * @return mixed The created event or the list of created event
     */
    public function logDestructionRequest($archive, $operationResult = true)
    {
        return $this->logLifeCycleEvent('recordsManagement/destructionRequest', $archive, $operationResult);
    }

    /**
     * Log an archive destruction request cancel
     * @param recordsManagement/archive $archive   	     The archive
     * @param bool  					$operationResult The operation result
     *
     * @return mixed The created event or the list of created event
     */
    public function logDestructionRequestCancel($archive, $operationResult = true)
    {
        return $this->logLifeCycleEvent('recordsManagement/destructionRequestCanceling', $archive, $operationResult);
    }

    /**
     * Log an archive destruction
     * @param recordsManagement/archive $archive   	     The archive
     * @param bool  					$operationResult The operation result
     *
     * @return mixed The created event or the list of created event
     */
    public function logDestruction($archive, $operationResult = true)
    {
        return $this->logLifeCycleEvent('recordsManagement/destruction', $archive, $operationResult);
    }

    /**
     * Log an archive restitution
     * @param recordsManagement/archive $archive         The archive
     * @param bool                      $operationResult The operation result
     *
     * @return mixed The created event or the list of created event
     */
    public function logRestitution($archive, $operationResult = true)
    {
        return $this->logLifeCycleEvent('recordsManagement/restitution', $archive, $operationResult);
    }

    /**
     * Log an archive retention rule modification
     * @param recordsManagement/archive $archive         The archive
     * @param object                    $retentionRule   The retention rule object
     * @param bool                      $operationResult The operation result
     *
     * @return mixed The created event or the list of created event
     */
    public function logRetentionRuleModification($archive, $retentionRule, $operationResult = true)
    {
        $eventInfo = array(
            'originatorOrgRegNumber' => $archive->originatorOrgRegNumber,
            'archiverOrgRegNumber' => $archive->archiverOrgRegNumber,
            'retentionStartDate' => (string) $retentionRule->retentionStartDate,
            'retentionDuration' => (string) $retentionRule->retentionDuration,
            'finalDisposition' => (string) $retentionRule->finalDisposition,
            'previousStartDate' => (string) $retentionRule->previousStartDate,
            'previousDuration' => (string) $retentionRule->previousDuration,
            'previousFinalDisposition' => (string) $retentionRule->previousFinalDisposition,
        );

        return $this->logLifeCycleEvent('recordsManagement/retentionRuleModification', $archive, $operationResult, false, $eventInfo);
    }

    /**
     * Log an archive access rule modification
     * @param recordsManagement/archive                 $archive         The archive
     * @param object 					$accessRule      The access rule object
     * @param bool  					$operationResult The operation result
     *
     * @return mixed The created event or the list of created event
     */
    public function logAccessRuleModification($archive, $accessRule, $operationResult = true)
    {
        $eventInfo = array(
            'originatorOrgRegNumber' => $archive->originatorOrgRegNumber,
            'archiverOrgRegNumber' => $archive->archiverOrgRegNumber,
            'accessRuleStartDate' => (string) $accessRule->accessRuleStartDate,
            'accessRuleDuration' => (string) $accessRule->accessRuleDuration,
            'previousAccessRuleStartDate' => (string) $accessRule->previousAccessRuleStartDate,
            'previousAccessRuleDuration' => (string) $accessRule->previousAccessRuleDuration,
        );

        return $this->logLifeCycleEvent('recordsManagement/accessRuleModification', $archive, $operationResult, false, $eventInfo);
    }

    /**
     * Log an archive freeze
     * @param recordsManagement/archive $archive         The archive
     * @param bool                      $operationResult The operation result
     *
     * @return mixed The created event or the list of created event
     */
    public function logFreeze($archive, $operationResult = true)
    {
        return $this->logLifeCycleEvent('recordsManagement/freeze', $archive, $operationResult);
    }

    /**
     * Log an archive unfreeze
     * @param recordsManagement/archive $archive         The archive
     * @param bool                      $operationResult The operation result
     *
     * @return mixed The created event or the list of created event
     */
    public function logUnfreeze($archive, $operationResult = true)
    {
        return $this->logLifeCycleEvent('recordsManagement/unfreeze', $archive, $operationResult);
    }

    /**
     * Log an archive metadata modification
     * @param recordsManagement/archive $archive         The archive
     * @param bool                      $operationResult The operation result
     *
     * @return mixed The created event or the list of created event
     */
    public function logMetadataModification($archive, $operationResult = true)
    {
        $eventInfo = array(
            'originatorOrgRegNumber' => $archive->originatorOrgRegNumber,
            'archiverOrgRegNumber' => $archive->archiverOrgRegNumber,
        );

        return $this->logLifeCycleEvent('recordsManagement/metadataModification', $archive, $operationResult, false, $eventInfo);
    }

    /**
     * Log an archive relatationship adding
     * @param recordsManagement/archive $archive             The archive
     * @param object                    $archiveRelationship The access rule object
     * @param bool                      $operationResult     The operation result
     *
     * @return mixed The created event or the list of created event
     */
    public function logRelationshipAdding($archive, $archiveRelationship, $operationResult = true)
    {
        $eventInfo = array(
            'originatorOrgRegNumber' => $archive->originatorOrgRegNumber,
            'archiverOrgRegNumber' => $archive->archiverOrgRegNumber,
            'relatedArchiveId' => $archiveRelationship->relatedArchiveId
        );

        return $this->logLifeCycleEvent('recordsManagement/addRelationship', $archive, $operationResult, false, $eventInfo);
    }

    /**
     * Log an archive relatationship deleting
     * @param recordsManagement/archive $archive             The archive
     * @param object                    $archiveRelationship The access rule object
     * @param bool                      $operationResult     The operation result
     *
     * @return mixed The created event or the list of created event
     */
    public function logRelationshipDeleting($archive, $archiveRelationship, $operationResult = true)
    {
        $eventInfo = array(
            'originatorOrgRegNumber' => $archive->originatorOrgRegNumber,
            'archiverOrgRegNumber' => $archive->archiverOrgRegNumber,
            'relatedArchiveId' => $archiveRelationship->relatedArchiveId
        );

        return $this->logLifeCycleEvent('recordsManagement/deleteRelationship',$archive, $operationResult, false, $eventInfo);
    }

        /**
     * Log an archive integrity checking
     * @param recordsManagement/archive       $archive         The archive
     * @param string                          $info            The information
     * @param digitalResource/digitalResource $resource        The resouce
     * @param bool                            $operationResult The operation result
     *
     * @return mixed The created event or the list of created event
     */
    public function logIntegrityCheck($archive, $info, $resource = null, $operationResult = true)
    { 
        $currentOrganization = \laabs::getToken("ORGANIZATION");
        $archive->digitalResources = null;

        $eventInfo = array(
            'requesterOrgRegNumber' => $currentOrganization->registrationNumber,
            'info' => $info,
        );

        return $this->logLifeCycleEvent('recordsManagement/integrityCheck',$archive, $operationResult, $resource, $eventInfo);
    }
}
