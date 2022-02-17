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
 * Class of cluster
 *
 * @author Alexis Ragot <alexis.ragot@maarch.org>
 */
class cluster
{

    protected $sdoFactory;
    protected $repositoryController;

    const MODE_READ = "read";
    const MODE_WRITE = "write";
    const MODE_DELETE = "delete";

    /**
     * Constructor
     * @param \dependency\sdo\Factory $sdoFactory The sdo factory
     */
    public function __construct(\dependency\sdo\Factory $sdoFactory)
    {
        $this->sdoFactory = $sdoFactory;

        $this->repositoryController = \laabs::newController('digitalResource/repository');
    }

    /**
     * Allow to display all clusters
     *
     * @return digitalResource/cluster[] Array of digitalResource/cluster object
     */
    public function index()
    {
        $clusters = $this->sdoFactory->find('digitalResource/cluster');
        foreach ($clusters as $cluster) {
            $cluster->clusterRepository = $this->sdoFactory->readChildren("digitalResource/clusterRepository", $cluster);
        }

        return $clusters;
    }

    /**
     * New empty cluster with default values
     *
     * @return digitalResource/cluster The cluster object
     */
    public function newCluster()
    {
        return \laabs::newInstance("digitalResource/cluster");
    }

    /**
     * Edit a cluster
     * @param string $clusterId The identifier of cluster
     *
     * @return digitalResource/cluster The cluster object
     */
    public function edit($clusterId = null)
    {
        // pre_load values
        if ($clusterId) {
            if (!$this->sdoFactory->exists("digitalResource/cluster", $clusterId)) {
                throw \laabs::newException("digitalResource/clusterException", "Cluster %s not found.", 404, null, [$clusterId]);
            }
            $cluster = $this->sdoFactory->read("digitalResource/cluster", $clusterId);
            if (!$cluster) {
                throw \laabs::newException("digitalResource/clusterException", "Cluster %s not found.", 404, null, [$clusterId]);
            }

            $cluster->clusterRepository = $this->sdoFactory->readChildren("digitalResource/clusterRepository", $cluster);
        } else {
            $cluster = $this->newCluster();
        }

        return $cluster;
    }

    /**
     * create a cluster
     * @param digitalResource/cluster $cluster The cluster object
     *
     * @return boolean The result of the operation
     */
    public function create($cluster)
    {
        $cluster->clusterId = \laabs::newId();

        if (!$cluster->clusterName) {
            throw \laabs::newException("digitalResource/clusterException", "Cluster name is empty");
        }

        if (!$cluster->clusterRepository) {
            throw \laabs::newException("digitalResource/clusterException", "Cluster repository is empty");
        }

        foreach ($cluster->clusterRepository as $repo) {
            $repo->clusterId = $cluster->clusterId;
        }

        $this->sdoFactory->beginTransaction();
        try {
            $this->sdoFactory->create($cluster, "digitalResource/cluster");
            $this->sdoFactory->createCollection($cluster->clusterRepository, "digitalResource/clusterRepository");
        } catch (\Exception $e) {
            $this->sdoFactory->rollback();
            throw \laabs::newException(
                "digitalResource/clusterException",
                "Cluster %s not created.",
                404,
                null,
                [$cluster->clusterId]
            );
        }
        $this->sdoFactory->commit();

        return true;
    }

    /**
     * update a repository
     * @param digitalResource/cluster $cluster The cluster object
     *
     * @return boolean The result of the operation
     */
    public function update($cluster)
    {
        $this->sdoFactory->beginTransaction();
        try {
            $this->sdoFactory->update($cluster, "digitalResource/cluster");
            $this->sdoFactory->deleteChildren("digitalResource/clusterRepository", $cluster, "digitalResource/cluster");
            if (is_array($cluster->clusterRepository) && !empty($cluster->clusterRepository)) {
                $this->sdoFactory->createCollection($cluster->clusterRepository, "digitalResource/clusterRepository");
            }
        } catch (\core\Route\Exception $e) {
            $this->sdoFactory->rollback();
            throw \laabs::newException("digitalResource/clusterException", "Cluster %s not updated.", 404, null, [$cluster->clusterId]);
        }
        $this->sdoFactory->commit();

        return true;
    }

    /**
     * Open cluster and repository services for given mode
     * @param string $clusterId The cluster identifier
     * @param string $mode      The mode: read, write, delete
     * @param bool   $limit     Limit the repo services to the lowest priority
     *
     * @return object The cluster with repositories and repo services
     */
    public function openCluster($clusterId, $mode = Cluster::MODE_READ, $limit = false)
    {
        $cluster = $this->sdoFactory->read("digitalResource/cluster", $clusterId);

        $cluster->clusterRepository = $this->sdoFactory->readChildren("digitalResource/clusterRepository", $cluster);

        $this->sortClusterRepositories($cluster, $mode, $limit);

        foreach ($cluster->clusterRepository as $clusterRepository) {
            try {
                $clusterRepository->repository = $this->repositoryController->openRepository($clusterRepository->repositoryId);
            } catch (\Exception $e) {
                if ($mode != Cluster::MODE_READ) {
                    throw \laabs::newException("digitalResource/clusterException", "Repository '%s' must be accessible", 404, $e, [$clusterRepository->repositoryId]);
                } else {
                    $clusterRepository->repository = null;
                    continue;
                }
            }
        }

        /*
        $operationnalClusterRepository = [];
        foreach ($cluster->clusterRepository as $clusterRepository) {
            try {
                $clusterRepository->repository = $this->repositoryController->openRepository($clusterRepository->repositoryId);
            } catch (\Exception $e) {
                // to do (log etc..)
                continue;
            }
            $operationnalClusterRepository[] = $clusterRepository;
        }

        $cluster->clusterRepository = $operationnalClusterRepository;
        */

        return $cluster;
    }

    /**
     * Sort repositories with their priority for the given operation
     * @param object  $cluster The cluster definition
     * @param string  $mode    The operation: read, write, delete
     * @param boolean $limit   Only keep repositories with the lowest priority
     */
    public function sortClusterRepositories($cluster, $mode = Cluster::MODE_READ, $limit = false)
    {
        // Sort repositories by priority
        $priorityProperty = $mode.'Priority';
        $priority = array();
        foreach ($cluster->clusterRepository as $key => $clusterRepository) {
            $priority[$key] = $clusterRepository->$priorityProperty;
        }
        array_multisort($priority, SORT_ASC, SORT_NUMERIC, $cluster->clusterRepository);

        // Get lowest priority and unset other repositories
        if ($limit) {
            $firstRepository = reset($cluster->clusterRepository);
            $lowestPriority = $firstRepository->$priorityProperty;
            foreach ($cluster->clusterRepository as $index => $clusterRepository) {
                if ($clusterRepository->$priorityProperty > $lowestPriority) {
                    unset($cluster->clusterRepository[$index]);
                }
            }

            if (is_array($cluster->clusterRepository) && empty($cluster->clusterRepository)) {
                throw \laabs::newException("digitalResource/noClusterRepositoryException", "No repository for %s mode", 404, null, [$mode]);
            }
        }
    }

    /**
     * Create a ressource container on the cluster (after opening it)
     * @param object $cluster
     * @param string $path
     * @param mixed  $metadata
     *
     * @return String[] Array of ressource container on the cluster
     */
    public function openContainers($cluster, $path, $metadata = null)
    {
        if (is_array($cluster->clusterRepository) && empty($cluster->clusterRepository)) {
            throw \laabs::newException("digitalResource/clusterException", "All repositories must be accessible");
        }

        foreach ($cluster->clusterRepository as $clusterRepository) {
            if ($clusterRepository->repository == null || !is_readable($clusterRepository->repository->repositoryUri)) {
                throw \laabs::newException("digitalResource/clusterException", "All repositories must be accessible");
            }

            $realPath = $this->repositoryController->openContainer($clusterRepository->repository, $path, $metadata);

            if (!$realPath) {
                throw \laabs::newException("digitalResource/clusterException", "Container %s couldn't not be opened.", 404, null, [$path]);
            }
        }

        return $realPath;
    }

    /**
     * Store a resource on cluster (after opening it)
     * @param digitalResource/cluster         $cluster
     * @param digitalResource/digitalResource $resource
     */
    public function storeResource($cluster, $resource)
    {
        foreach ($cluster->clusterRepository as $index => $clusterRepository) {
            if ($clusterRepository->repository == null) {
                throw \laabs::newException("digitalResource/clusterException", "All repositories must be accessible");
            }

            $address = $this->repositoryController->storeResource($clusterRepository->repository, $resource);

            if (!$address) {
                throw \laabs::newException("digitalResource/clusterException", "%s not found", 404, null, [$cluster->clusterId]);
            }

            $resource->address[$index] = $address;
        }
    }

    /**
     * Rollback storage transaction
     * @param digitalResource/digitalResource $resource
     */
    public function rollbackStorage($resource)
    {
        if (is_array($resource->address) && !empty($resource->address)) {
            foreach ($resource->address as $address) {
                $this->repositoryController->rollbackStorage($address);
            }
        }
    }

    /**
     * Retrieve a resource contents on cluster (after opening it)
     * @param digitalResource/cluster         $cluster
     * @param digitalResource/digitalResource $resource
     *
     * @return bool The result of the operation
     */
    public function retrieveResource($cluster, $resource)
    {
        foreach ($cluster->clusterRepository as $clusterRepository) {
            if ($clusterRepository->repository == null) {
                continue;
            }
            $address = $this->sdoFactory->read("digitalResource/address", array('resId' => $resource->resId, 'repositoryId' => $clusterRepository->repositoryId));
            if ($address) {
                $handler = null;
                $resource->address[] = $address;
                $address->repository = $clusterRepository->repository;

                try {
                    $handler = $this->repositoryController->retrieveContents($clusterRepository->repository, $address);

                    if (isset($resource->hash) && !$this->checkHash($address, $resource, $handler)) {
                        throw \laabs::newException("digitalResource/clusterException", 'Invalid hash for resource %1$s at address %2$S', 404, null, [$resource->resId, $address->repository->repositoryUri.DIRECTORY_SEPARATOR.$address->path]);
                    }

                    $resource->setHandler($handler);

                    return $handler;
                } catch (\Exception $e) {
                    $address->integrityCheckResult = false;
                    $this->sdoFactory->update($address);
                    // No content retrieved : send error as audit event
                    \laabs::notify(LAABS_BUSINESS_EXCEPTION, $e);
                }
            } else {
                throw \laabs::newException("digitalResource/clusterException", "No address found for ressource %s", 404, null, [$resource->resId]);
                \laabs::notify(LAABS_BUSINESS_EXCEPTION, \laabs::newException("digitalResource/clusterException", "No address found for ressource %s", 404, null, [$resource->resId]));
            }
        }

        // TODO : throw exception if resource not available on repo, based on options ?
        return null;
    }

    /**
     * Verify a resource
     *
     * @param object $cluster  The cluster object where the resource is store
     * @param object $resource The digitalResource object to verify
     *
     * @return boolean The digitalResource verify
     */
    public function verifyResource($cluster, $resource)
    {
        $result = true;

        foreach ($cluster->clusterRepository as $clusterRepository) {
            if ($clusterRepository->repository == null) {
                continue;
            }

            $queryParams['repositoryId'] = $clusterRepository->repositoryId;
            $queryParts['repositoryId'] = "repositoryId = :repositoryId";
            $queryParams['resId'] = $resource->resId;
            $queryParts['resId'] = "resId = :resId";

            $queryString = implode(' AND ', $queryParts);

            $resource->address = $this->sdoFactory->find("digitalResource/address", $queryString, $queryParams);

            foreach ($resource->address as $address) {
                $address->repository = $clusterRepository->repository;
                $handler = $this->repositoryController->retrieveContents($clusterRepository->repository, $address);

                if ($handler) {
                    $result = $result && $this->checkHash($address, $resource, $handler);

                    if (!$result) {
                        throw \laabs::newException("digitalResource/invalidHashException", "Invalid hash on repository '%s'", 409, null, $clusterRepository->repositoryId);
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Check a resource
     *
     * @param object $cluster  The cluster object where the resource is store
     * @param object $resource The digitalResource object to verify
     *
     * @return boolean The digitalResource verify
     */
    public function checkResource($cluster, $resource)
    {

        $result = true;

        foreach ($cluster->clusterRepository as $clusterRepository) {
            if ($clusterRepository->repository == null) {
                continue;
            }

            $queryParams['repositoryId'] = $clusterRepository->repositoryId;
            $queryParts['repositoryId'] = "repositoryId = :repositoryId";
            $queryParams['resId'] = $resource->resId;
            $queryParts['resId'] = "resId = :resId";

            $queryString = implode(' AND ', $queryParts);

            $resource->address = $this->sdoFactory->find("digitalResource/address", $queryString, $queryParams);
            foreach ($resource->address as $address) {
                if (!$this->repositoryController->isResource($clusterRepository->repository, $address)) {
                    $result = false;
                }
            }
        }

        return $result;
    }


    private function checkHash($address, $resource, $handler)
    {
        $hash = \laabs\hash_stream($resource->hashAlgorithm, $handler);

        $address->lastIntegrityCheck = \laabs::newTimestamp();
        $address->integrityCheckResult = false;

        if ($hash == strtolower($resource->hash)) {
            $address->integrityCheckResult = true;
        }
        $this->sdoFactory->update($address);

        return $address->integrityCheckResult;
    }
}
