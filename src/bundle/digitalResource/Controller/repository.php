<?php

/*
 * Copyright (C) 2015 Maarch
 *
 * This file is part of bundle digitalResource.
 *
 * Bundle digitalResource is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Bundle digitalResource is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with bundle digitalResource.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace bundle\digitalResource\Controller;

/**
 * Class of adminRepository
 *
 * @author Alexis Ragot <alexis.ragot@maarch.org>
 */
class repository
{

    protected $sdoFactory;

    /**
     * Constructor
     * @param \dependency\sdo\Factory $sdoFactory The sdo factory
     */
    public function __construct(\dependency\sdo\Factory $sdoFactory)
    {
        $this->sdoFactory = $sdoFactory;
    }

    /**
     * Allow to display all repositories
     *
     * @return bool
     */
    public function index()
    {
        return $this->sdoFactory->find("digitalResource/repository");
    }

    /**
     * Edit a repository
     * @param string $repositoryId
     *
     * @return object Repository object
     */
    public function edit($repositoryId = null)
    {
        if ($repositoryId) {
            $repository = $this->getById($repositoryId);
        } else {
            $repository = \laabs::newInstance("digitalResource/repository");
        }

        return $repository;
    }

    /**
     * create a repository
     * @param digitalResource/repository $repository The repository object
     *
     * @return boolean
     */
    public function create($repository)
    {
        if ($this->sdoFactory->exists("digitalResource/repository", array('repositoryUri' => $repository->repositoryUri))) {
            throw \laabs::newException("digitalResource/repositoryException", "Repository URI already exist.");
        }

        $repository->repositoryId = \laabs::newId();

        // Try to instanciate service to validate repo
        try {
            $this->getService($repository);
        } catch (\Exception $e) {
            throw \laabs::newException("digitalResource/repositoryException", "Service not found.");
        }

        $repository->parameters = json_encode($repository->parameters);
        $this->sdoFactory->create($repository, 'digitalResource/repository');

        return true;
    }

    /**
     * update a repository
     * @param digitalResource/repository $repository The repository object
     *
     * @return boolean
     */
    public function update($repository)
    {
        if (count($this->sdoFactory->find("digitalResource/repository", "repositoryUri = '$repository->repositoryUri'")) > 1) {

            throw \laabs::newException("digitalResource/repositoryException", "Repository URI already exist.");
        }

        // Try to instanciate service to validate repo
        try {
            $this->getService($repository);
        } catch (\Exception $e) {

            throw \laabs::newException("digitalResource/repositoryException", "Service not found.");
        }

        $repository->parameters = json_encode($repository->parameters);
        $this->sdoFactory->update($repository, 'digitalResource/repository');

        return true;
    }

    /**
     * Get by id
     * @param string $repositoryId
     *
     * @return object
     */
    public function getById($repositoryId)
    {
        $repository = $this->sdoFactory->read("digitalResource/repository", $repositoryId);
        $repository->parameters = json_decode($repository->parameters);

        return $repository;
    }

    /**
     * Open repository service for given mode
     * @param string $repositoryId
     *
     * @return object The repository with its service
     */
    public function openRepository($repositoryId)
    {
        $repository = $this->sdoFactory->read("digitalResource/repository", $repositoryId);
        $repository->parameters = json_decode($repository->parameters);

        $repositoryService = $this->getService($repository);
        $repository->setService($repositoryService);

        return $repository;
    }

    /**
     * Create a ressource container on the repository (after opening it)
     * @param digitalResource/repository $repository
     * @param string                     $path
     * @param mixed                      $metadata
     * 
     * @return string
     */
    public function openContainer($repository, $path, $metadata=null)
    {
        $repositoryService = $repository->getService();

        $uri = $repositoryService->createContainer($path, $metadata);
        $repository->currentContainer = $uri;

        if (!$uri) {
            throw \laabs::newException("digitalResource/repositoryException", "No address return for creation of container in repository %s.", 404, null, [$repository->repositoryId]);
        }

        return $uri;
    }

    /**
     * Store a resource on repository (after opening it)
     * @param digitalResource/repository      $repository The repository id
     * @param digitalResource/digitalResource $resource   The digital resource to store
     *
     * @return digitalResource/address adress object
     */
    public function storeResource($repository, $resource)
    {
        $repositoryService = $repository->getService();

        $uri = $repositoryService->createObject($resource->getHandler(), $repository->currentContainer.'/'.$resource->resId);
        
        if (!$uri) {
            throw \laabs::newException("digitalResource/repositoryException", "No address return for storage of resource in repository %s.", 404, null, [$repository->repositoryId]);
        }

        $address = \laabs::newInstance("digitalResource/address");
        $address->resId = $resource->resId;
        $address->repositoryId = $repository->repositoryId;
        $address->path = $uri;
        $address->created = \laabs::newTimestamp();

        // Store repository and service on address for rollback purpose
        $address->repository = $repository;

        $this->sdoFactory->create($address);

        return $address;
    }

    /**
     * Rollback storage transaction
     * @param digitalResource/address $address
     */
    public function rollbackStorage($address)
    {
        $repositoryService = $address->repository->getService();
        try {
            $repositoryService->deleteObject($address->path);
        } catch (\Exception $e) {

        }
    }

    /**
     * Returns a resource handler on repo (after opening it)
     * @param digitalResource/repository $repository
     * @param digitalResource/address    $address
     *
     * @return resource
     */
    public function retrieveContents($repository, $address)
    {
        try {
            $repositoryService = $repository->getService();
            $address->path = str_replace('\\', '/', $address->path);
            $handler = $repositoryService->readObject($address->path);
        } catch (\Exception $e) {
            $address->lastIntegrityCheck = \laabs::newTimestamp();
            $address->integrityCheckResult = false;

            $this->sdoFactory->update($address, 'digitalResource/address');
            throw \laabs::newException("digitalResource/repositoryException", "Resource contents not available at address %s.", 404, null, [$repository->repositoryUri.DIRECTORY_SEPARATOR.$address->path]);
        }

        return $handler;
    }

    /**
     * Check if a resource exists in a repository
     * @param digitalResource/repository $repository
     * @param digitalResource/address    $address of the resource
     *
     * @return boolean The digitalResource verify
     */
    public function isResource($repository, $address)
    {
        try {
            $repositoryService = $repository->getService();
            $address->path = str_replace('\\', '/', $address->path);
            $result = $repositoryService->readObject($address->path,0);

        } catch (\Exception $e) {
            throw \laabs::newException("digitalResource/repositoryException", "Could not check resource at address %s.", 500, null, [$repository->repositoryUri.DIRECTORY_SEPARATOR.$address->path]);
        }

        if (!$result) {
            $address->integrityCheckResult = false;
            $this->sdoFactory->update($address, 'digitalResource/address');
        }

        return $result;
    }

    /**
     * Check the integrity of all resources in a repository
     * @param string  $repositoryReference The reference of the repository to check
     * @param bool    $init                Start an new integrity check or continue from last check
     * @param integer $addressLimit        The maximum address to check
     * @param integer $maxError            The maximum number of error before the end of the process
     *
     * @return array The number of addresses to chack , the number of checked addresses and the number of failure
     */
    public function checkRepositoryIntegrity($repositoryReference, $init = true, $addressLimit = 1000, $maxError = 5)
    {
        $res = [];
        $lifeCycleJournalController = \laabs::newController("lifeCycle/journal");

        // Retrieve repository from reference
        $repository = $this->sdoFactory->find('digitalResource/repository', "repositoryReference = '$repositoryReference'");

        if (count($repository) == 0) {
            throw \laabs::newException("digitalResource/repositoryException", "No repository found with reference %s", 404, null, [$repositoryReference]);

            return -1;
        }

        $repository = $this->openRepository($repository[0]->repositoryId);

        // initialisation for a new integrity check
        if ($init) {
            $addresses = $this->sdoFactory->find('digitalResource/address', "repositoryId = '$repository->repositoryId'");

            foreach ($addresses as $address) {
                $address->lastIntegrityCheck = null;
                $address->integrityCheckResult = null;

                $this->sdoFactory->update($address, 'digitalResource/address');
            }
        }

        // count the number of unchecked addresses
        $res['addressesToCheck'] = $this->sdoFactory->count('digitalResource/address', "repositoryId = '$repository->repositoryId'");
        $res['checkedAddresses'] = 0;
        $res['failed'] = 0;

        if ($res['addressesToCheck'] == 0) {
            return $res;
        }

        // start the checking process
        $addresses = $this->sdoFactory->find('digitalResource/address', "repositoryId = '$repository->repositoryId' AND (lastIntegrityCheck = null OR integrityCheckResult = false)", null, '<lastIntegrityCheck', 0, $addressLimit);

        foreach ($addresses as $address) {

            $res['checkedAddresses']++;

            if (!$this->validateAddressIntegrity($address)) {
                $res['failed']++;
            }

            if ($res['failed'] >= $maxError) {
                $eventInfo = [];
                $eventInfo['repositoryReference'] = $repository->repositoryReference;
                $eventInfo['addressesToCheck'] = $res['addressesToCheck'];
                $eventInfo['checkedAddresses'] = $res['checkedAddresses'];
                $eventInfo['failed'] = $res['failed'];
                $event = $lifeCycleJournalController->logEvent('digitalResource/integrityCheck', 'digitalResource/repository', $repository->repositoryId, $eventInfo);

                return $res;
            }
        }

        $eventInfo = [];
        $eventInfo['repositoryReference'] = $repository->repositoryReference;
        $eventInfo['addressesToCheck'] = $res['addressesToCheck'];
        $eventInfo['checkedAddresses'] = $res['checkedAddresses'];
        $eventInfo['failed'] = $res['failed'];
        $event = $lifeCycleJournalController->logEvent('digitalResource/integrityCheck', 'digitalResource/repository', $repository->repositoryId, $eventInfo);

        return $res;
    }

    /**
     * Validate an address integrity
     * @param digitalResource/address    $address    The address to check
     * @param digitalResource/repository $repository The repository of the address
     *
     * @return boolean The result of the validation
     */
    public function validateAddressIntegrity($resId, $repositoryId)
    {
        try {
            $digitalResource = $this->sdoFactory->read('digitalResource/digitalResource', $resId);
            $address = $this->sdoFactory->read('digitalResource/address', ['resId' => $resId, 'repositoryId' => $repositoryId]);
            $repository = $this->openRepository($repositoryId);

            $address->lastIntegrityCheck = \laabs::newTimestamp();

            $handler = $this->retrieveContents($repository, $address);

            $hash = strtolower(\laabs\hash_stream($digitalResource->hashAlgorithm, $handler));
            
            $address->integrityCheckResult = true;

            if ($hash != strtolower($digitalResource->hash)) {
                throw \laabs::newException("digitalResource/repositoryException", "");
            }
        } catch (\Exception $exception) {
            $address->integrityCheckResult = false;
        }
        
        $archiveController = \laabs::newController("recordsManagement/archive");
        $archive = $this->sdoFactory->read('recordsManagement/archive',$digitalResource->archiveId);
        $archiveController->logIntegrityCheck($archive, "Check from flawed addresses", $digitalResource, $address->integrityCheckResult);

        $this->sdoFactory->update($address);

        return $address->integrityCheckResult;
    }

    /**
     *  Get addesses wich fail the integrity test
     *
     * @return digitalResource/address The list of addresses with error
     */
    public function getFlawedAddresses()
    {
        $addressList = $this->sdoFactory->find('digitalResource/address', 'integrityCheckResult = false');

        $repositories = [];

        foreach ($addressList as $address) {
            if (!isset($repositories[(string) $address->repositoryId])) {
                $repository = $this->edit($address->repositoryId);
                $repositories[(string) $address->repositoryId] = $repository->repositoryReference;
            }

            $address->repositoryReference = $repositories[(string) $address->repositoryId];
        }

        return $addressList;
    }

    /**
     * Get repo service
     * @param digitalResource/repository $repository The repository object
     *
     * @return object The repository service object (implements dependency/repository/repositoryInterface)
     */
    protected function getService($repository)
    {
        $repositoryDependency = \laabs::dependency('repository');

        $repositoryServiceName = LAABS_ADAPTER.LAABS_URI_SEPARATOR.$repository->repositoryType.LAABS_URI_SEPARATOR."Repository";

        $repositoryServiceArgs['name'] = $repository->repositoryUri;

        if (!empty($repository->parameters)) {
            $repositoryServiceArgs['options'] = $repository->parameters;
        }

        return $repositoryDependency->callService($repositoryServiceName, $repositoryServiceArgs);
    }
}
