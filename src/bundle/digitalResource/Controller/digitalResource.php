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
 * Main digital resource controller
 */
class digitalResource
{

    protected $finfo;
    protected $sdoFactory;
    public $hashAlgorithm;

    /**
     * Format controller
     * @var digitalResource/Controller/format
     */
    protected $pronomFormatController;

    /**
     * Cluster controller
     * @var digitalResource/cluster
     */
    protected $clusterController;

    /**
     * Previously loaded digital resource clusters
     * @var array
     */
    protected $clusters;

    /**
     * Currently used ddigital resource cluster
     * @var digitalResource/cluster
     */
    protected $currentCluster;

    /**
     * Constructor
     * @param \dependency\sdo\Factory $sdoFactory    The dependency sdo factory
     * @param string                  $hashAlgorithm The hash algorithm as in php::hash_algos()
     */
    public function __construct(\dependency\sdo\Factory $sdoFactory, $hashAlgorithm = "sha256")
    {
        $this->hashAlgorithm = $hashAlgorithm;

        $this->finfo = new \finfo(FILEINFO_MIME_TYPE);

        $this->sdoFactory = $sdoFactory;

        $this->clusterController = \laabs::newController('digitalResource/cluster');

        $this->pronomFormatController = \laabs::newController('digitalResource/pronomFormat');

        $this->clusters = [];
    }

    /**
     * Search digital resource
     * @param string    $archiveId
     * @param integer   $sizeMin
     * @param integer   $sizeMax
     * @param string    $puid
     * @param string    $mimetype
     * @param string    $hash
     * @param string    $hashAlgorithm
     * @param string    $fileName
     * @param timestamp $startDate
     * @param timestamp $endDate
     */
    public function findDocument(
        $archiveId = null,
        $sizeMin = null,
        $sizeMax = null,
        $puid = null,
        $mimetype = null,
        $hash = null,
        $hashAlgorithm = null,
        $fileName = null,
        $startDate = null,
        $endDate = null
    ) {
        $queryParts = array();
        $queryParams = array();

        if ($archiveId != null) {
            $queryParams['archiveId'] = $archiveId;
            $queryParts['archiveId'] = "archiveId = :archiveId";
        }

        if ($sizeMin) {
            $queryParams['sizeMin'] = $sizeMin;
            $queryParts['size'] = "size >= :sizeMin";
        }
        
        if ($sizeMax) {
            $queryParams['sizeMax'] = $sizeMax;
            $queryParts['size'] = "size <= :sizeMax";
        }

        if ($puid != null) {
            $queryParams['puid'] = $puid;
            $queryParts['puid'] = "puid = :puid";
        }

        if ($mimetype != null) {
            $queryParams['mimetype'] = $mimetype;
            $queryParts['mimetype'] = "mimetype = :mimetype";
        }

        if ($hash != null) {
            $queryParams['hash'] = $hash;
            $queryParts['hash'] = "hash = :hash";
        }

        if ($hashAlgorithm != null) {
            $queryParams['hashAlgorithm'] = $hashAlgorithm;
            $queryParts['hashAlgorithm'] = "hashAlgorithm = :hashAlgorithm";
        }

        if ($fileName != null) {
            if (strpos($fileName,'*')!== false) {
                $queryParams['fileName'] = str_replace('*','%',$fileName);
                $queryParts['fileName'] = "fileName ~ :fileName";
            } else {
                $queryParams['fileName'] = $fileName;
                $queryParts['fileName'] = "fileName = :fileName";
            }
        }
        
        if ($startDate) {
            $queryParams['startDate'] = $startDate;
            $queryParts['created'] = "created >= :startDate";
        }
        
        if ($endDate) {
            $queryParams['endDate'] = $endDate;
            $queryParts['created'] = "created <= :endDate";
        }

        $queryString = implode(' AND ', $queryParts);
        return $resources = $this->sdoFactory->find("digitalResource/digitalResource", $queryString, $queryParams);
    }

    /**
     * Get file information about one file
     * @param string $filename The file name
     *
     * @return digitalResource/digitalResource The digitalResource
     */
    public function createFromFile($filename, $withDateTime = true)
    {
        if (!file_exists($filename)) {
            return false;
        }

        $resource = $this->newResource();

        $UTF8filename = $filename;
        if (!preg_match('//u', $filename)) {
            $UTF8filename = utf8_encode($filename);
        }

        // Basic path information
        $pathinfo = pathinfo($UTF8filename);

        $resource->fileName = str_replace(["<", ">", ":", '"', "/", "\\", "|", "?", "*"], "-", $pathinfo['filename']);

        if ($withDateTime) {
            $resource->fileName .= "_".(string) \laabs::newDate(\laabs::newDatetime(null, "UTC"), "Y-m-d_H-i-s");
        }

        if (isset($pathinfo['extension'])) {
            $resource->fileName .= "." . $pathinfo['extension'];
            $resource->fileExtension = $pathinfo['extension'];
        }

        $resource->size = filesize($filename);
        $resource->mimetype = $this->finfo->file($filename, \FILEINFO_MIME_TYPE);

        $resource->setFilename($filename);

        return $resource;
    }

    /**
     * Get information about resource contents
     * @param string $contents The contents to store
     * @param string $filename The original filename
     *
     * @return digitalResource/digitalResource The digitalResource
     */
    public function createFromContents($contents, $filename = false)
    {
        $resource = $this->newResource();

        // Basic path information
        $resource->size = strlen($contents);
        $resource->mimetype = $this->finfo->buffer($contents, \FILEINFO_MIME_TYPE);

        if ($filename) {
            $pathinfo = pathinfo($filename);
            $resource->fileName = $pathinfo['basename'];
            if (isset($pathinfo['extension'])) {
                $resource->fileExtension = $pathinfo['extension'];
            }
        }

        $tmpfile = \laabs\tempnam();
        file_put_contents($tmpfile, $contents);
        $handler = fopen($tmpfile, 'r+');

        $resource->sethandler($handler, $isTemp = true);

        return $resource;
    }

    /**
     * Get information about resource stream contents
     * @param string $stream   The data stream to store
     * @param string $filename The original filename
     *
     * @return digitalResource/digitalResource The digitalResource
     */
    public function createFromStream($stream, $filename = null)
    {
        $resource = $this->newResource();

        $resource->setHandler($stream);

        $this->getHash($resource);
        $this->getMimetype($resource);
        $this->getSize($resource);

        if ($filename) {
            $pathinfo = pathinfo($filename);
            $resource->fileName = $pathinfo['basename'];
            if (isset($pathinfo['extension'])) {
                $resource->fileExtension = $pathinfo['extension'];
            }
        }

        return $resource;
    }

    protected function newResource()
    {
        $resource = \laabs::newInstance("digitalResource/digitalResource");

        $resource->resId = \laabs::newId();

        $resource->created = \laabs::newTimestamp();

        return $resource;
    }


    /**
     * Get the hash for a given resource
     * @param digitalResource/digitalResource $resource      The resource
     * @param string                          $hashAlgorithm The hash algorithm to use
     */
    public function getHash($resource, $hashAlgorithm = false)
    {
        if (!$hashAlgorithm) {
            $hashAlgorithm = $this->hashAlgorithm;
        }

        $resource->hashAlgorithm = $hashAlgorithm;
        $resource->hash = \laabs\hash_stream($hashAlgorithm, $resource->gethandler());
    }

    /**
     * Get the mimetype for a given resource
     * @param digitalResource/digitalResource $resource The resource
     */
    public function getMimetype($resource)
    {
        $handler = $resource->gethandler();
        $metadata = stream_get_meta_data($handler);
        if ($metadata['wrapper_type'] == 'plainfile') {
            $resource->mimetype = $this->finfo->file($metadata['uri'], \FILEINFO_MIME_TYPE);
        }
    }

    /**
     * Get the size for a given resource
     * @param digitalResource/digitalResource $resource The resource
     */
    public function getSize($resource)
    {
        $handler = $resource->gethandler();
        $metadata = stream_get_meta_data($handler);
        if ($metadata['wrapper_type'] == 'plainfile') {
            $resource->size = filesize($metadata['uri']);
        } else {
            $fstats = fstat($handler);
            if (isset($fstats['size'])) {
                $resource->size = $fstats['size'];
            }
        }
    }

    /**
     * Use a digital resource cluster for storage
     * @param string $clusterId
     * @param string $mode
     * @param bool   $limit
     *
     * @return digitalResource/cluster The cluster
     */
    public function useCluster($clusterId, $mode, $limit)
    {
        if (!isset($this->clusters[$mode][(string) $clusterId])) {
            $this->currentCluster = $this->clusterController->openCluster($clusterId, $mode, $limit);
            $this->clusters[$mode][(string) $clusterId] = $this->currentCluster;
        } else {
            $this->currentCluster = $this->clusters[$mode][(string) $clusterId];
        }

        return $this->currentCluster;
    }

    /**
     * Create a ressource container on the cluster (after opening it)
     * @param string $clusterId
     * @param string $path
     * @param mixed  $metadata
     *
     * @return string Ressource container on the cluster
     */
    public function openContainers($clusterId, $path, $metadata=null)
    {
        $cluster = $this->useCluster($clusterId, Cluster::MODE_WRITE, true);

        return $this->clusterController->openContainers($cluster, $path, $metadata);
    }


    /**
     * Store a given resource
     * @param digitalResource/digitalResource $resource The ressource object
     *
     * @return digitalResource/digitalResource digitalResource object
     */
    public function store($resource)
    {
        // Store resource + metadata
        $this->storeDigitalResource($resource);

        return $resource;
    }

    /**
     * Store a collection of resources sharing the same cluster and repositories from a list of resources
     * @param array  $resources An array of resources to store
     * @param string $clusterId The cluster to apply for store procedure
     *
     * @return mixed Collection of resources
     */
    public function storeCollection($resources, $clusterId)
    {
        // Get the storage objects
        $this->useCluster($clusterId, Cluster::MODE_WRITE, true);

        foreach ($resources as $resource) {
            $this->storeDigitalResource($resource);
            $resources[] = $resource;
        }

        return $resources;
    }

    /**
     * Store a new resource in current cluster
     * @param object $resource The resource
     */
    public function storeDigitalResource($resource)
    {
        if (empty($resource->size)) {
            $this->getSize($resource);
        }

        if (empty($resource->mimetype)) {
            $this->getMimetype($resource);
        }

        if (empty($resource->hash)) {
            $this->getHash($resource);
        }

        $resource->clusterId = $this->currentCluster->clusterId;
        $resource->created = \laabs::newTimestamp();

        $transactionControl = !$this->sdoFactory->inTransaction();

        if ($transactionControl) {
            $this->sdoFactory->beginTransaction();
        }

        try {
            $this->sdoFactory->create($resource, 'digitalResource/digitalResource');

            $this->clusterController->storeResource($this->currentCluster, $resource);

            if ($resource->relatedResource) {
                foreach ($resource->relatedResource as $relatedResource) {
                    if (empty($relatedResource->relatedResId)) {
                        $relatedResource->relatedResId = $resource->resId;
                    }
                    if (empty($relatedResource->archiveId)) {
                        $relatedResource->archiveId = $resource->archiveId;
                    }

                    $this->storeDigitalResource($relatedResource);
                }
            }
        } catch (\Exception $exception) {
            $this->clusterController->rollbackStorage($resource);

            if ($transactionControl) {
                $this->sdoFactory->rollback();
            }

            throw \laabs::newException("digitalResource/clusterException", 'Resource %1$s not created: %2$s', 404, $exception, [$resource->resId, $exception->getMessage()]);
        }

        // All repositories returned an uri, save all
        if ($transactionControl) {
            $this->sdoFactory->commit();
        }
    }

    /**
     * Get resources of archive
     * @param id $archiveId The archive identifier
     *
     * @return array Array of digitalResource object
     */
    public function getResourcesByArchiveId($archiveId)
    {
        $resources = $this->sdoFactory->find("digitalResource/digitalResource", "archiveId='$archiveId' AND relatedResId=null");

        foreach ($resources as $key => $resource) {
            $resources[$key] = $this->info($resource->resId);
        }

        return $resources;
    }

    /**
     * Get related resources
     * @param string $resId            The identifier of the converted or the original resource
     * @param string $relationshipType The relationship type with the resource identified by the second parameter
     *
     * @return digitalResource/digialResource[] Array of digitalResource/digialResource object
     */
    public function getRelatedResources($resId, $relationshipType = null)
    {
        // $relationshipType = isConversionOf OR isSignatureOf
        $whereClause = "relatedResId='$resId'";

        if ($relationshipType) {
            $whereClause .= " AND relationshipType='$relationshipType'";
        }

        $resources = $this->sdoFactory->find("digitalResource/digitalResource", $whereClause);

        return $resources;
    }

    /**
     * Retrieve a resource
     * @param string $resId The resource identifier
     *
     * @return digitalResource/digitalResource digitalResource object
     */
    public function retrieve($resId)
    {
        $resource = $this->sdoFactory->read("digitalResource/digitalResource", $resId);

        if (!$resource) {
            throw \laabs::newException("digitalResource/resourceNotFoundException");
        }

        if (isset($resource->puid)) {
            $resource->format = $this->pronomFormatController->get($resource->puid);
        }

        $cluster = $this->useCluster($resource->clusterId, Cluster::MODE_READ, false);
        $resource->cluster = $cluster;
        $handler = $this->clusterController->retrieveResource($cluster, $resource);

        if (!$handler) {
            throw \laabs::newException("digitalResource/clusterException", "Resource %s could not be retrieved.", 404, null, [$resource->resId]);
        }

        $relatedResources = $this->getRelatedResources($resource->resId);
        foreach ($relatedResources as $relatedResource) {
            $resource->relatedResource[] = $this->retrieve($relatedResource->resId);
        }

        return $resource;
    }

    public function checkHash($handler, $hash, $hashAlgorithm)
    {
        if (!in_array($hashAlgorithm, ['sha3-224', 'sha3-256', 'sha3-384', 'sha3-512'])) {
            $hashAlgorithm = str_replace('-', '', $hashAlgorithm);
        }
        $hash_calculated = \laabs\hash_stream($hashAlgorithm, $handler);

        if ($hash_calculated !== strtolower($hash)) {
            throw \laabs::newException("digitalResource/invalidHashException", "Invalid hash.");
        }
    }

    /**
     * Get the contents of a given resource
     * @param string $resId
     *
     * @return string The contents of a given resource
     */
    public function contents($resId)
    {
        $resource = $this->sdoFactory->read("digitalResource/digitalResource", $resId);
        if (!$resource) {
            throw \laabs::newException("digitalResource/resourceNotFoundException");
        }

        $cluster = $this->useCluster($resource->clusterId, Cluster::MODE_READ, false);

        foreach ($cluster->clusterRepository as $clusterRepository) {
            $repositoryService = $clusterRepository->repository->getService();

            $address = $this->sdoFactory->read("digitalResource/address", array('resId' => $resId, 'repositoryId' => $clusterRepository->repositoryId));

            if ($address) {
                $handler = null;
                if (!$handler) {
                    try {
                        $handler = $repositoryService->readObject($address->path);

                        $this->checkHash($handler, $resource->hash, $resource->hashAlgorithm);
                    } catch (\Exception $e) {
                        // TODO : throw exception if resource not available on repo, based on options ?
                    }
                }
            } else {
                // TODO : throw exception if resource not available on repo, based on options ?
            }
        }

        if (!$handler) {
            throw \laabs::newException("digitalResource/clusterException", "Resource unavailable");
        }

        return $handler;
    }

    /**
     * Retrieve a resource metadata
     * @param string $resId
     *
     * @return mixed Resource metadata
     */
    public function metadata($resId)
    {
        $resource = $this->sdoFactory->read("digitalResource/digitalResource", $resId);
        if (!$resource) {
            throw \laabs::newException("digitalResource/clusterException", "Resource not found");
        }

        $cluster = $this->useCluster($resource->clusterId, Cluster::MODE_READ, false);

        if (!$cluster->storeMetadata) {
            return;
        }

        foreach ($cluster->clusterRepository as $clusterRepository) {
            $repositoryService = $clusterRepository->repository->getService();

            $address = $this->sdoFactory->read("digitalResource/address", array('resId' => $resId, 'repositoryId' => $clusterRepository->repositoryId));

            if ($address) {
                if ($contents = $repositoryService->readObject($address->path, 2)) {
                    return $contents;
                }
            }
        }
    }

    /**
     * Get all information about one digital resource
     * @param string $resId The identifier of digital resource
     *
     * @return digitalResource/digitalResource digitalResource object whith all information
     */
    public function info($resId)
    {
        $resource = $this->sdoFactory->read("digitalResource/digitalResource", $resId);

        if (!$resource) {
            throw \laabs::newException("digitalResource/clusterException", "Resource not found");
        }

        if (isset($resource->puid)) {
            $resource->format = $this->pronomFormatController->get($resource->puid);
        }

        $cluster = $this->useCluster($resource->clusterId, Cluster::MODE_READ, false);

        foreach ($cluster->clusterRepository as $clusterRepository) {
            $repositoryId = $clusterRepository->repositoryId;
            $repository = $this->sdoFactory->read("digitalResource/repository", $repositoryId);
            $address = $this->sdoFactory->read("digitalResource/address", array('resId' => $resId, 'repositoryId' => $repositoryId));
            $address->path = str_replace('\\', '/', $address->path);
            $address->repository = $repository;
            $resource->address[] = $address;
        }

        $relatedResources = $this->getRelatedResources($resource->resId);
        foreach ($relatedResources as $relatedResource) {
            $resource->relatedResource[] = $this->info($relatedResource->resId);
        }

        return $resource;
    }

    /**
     * Delete entire resource and all addresses
     * @param string $resId The resource identifier
     *
     * @return bool The result of the operation
     */
    public function delete($resId)
    {
        $resource = $this->sdoFactory->read("digitalResource/digitalResource", $resId);
        if (!$resource) {
            throw \laabs::newException("digitalResource/clusterException", "Resource not found");
        }

        $relatedResources = $this->getRelatedResources($resource->resId);
        foreach ($relatedResources as $relatedResource) {
            $resource->relatedResource[] = $this->delete($relatedResource->resId);
        }

        $cluster = $this->useCluster($resource->clusterId, Cluster::MODE_DELETE, false);

        foreach ($cluster->clusterRepository as $clusterRepository) {
            $repositoryId = $clusterRepository->repositoryId;
            $address = $this->sdoFactory->read("digitalResource/address", array('resId' => $resId, 'repositoryId' => $repositoryId));
            $address->repository = $clusterRepository->repository;
            $resource->address[] = $address;
        }

        $transactionControl = !$this->sdoFactory->inTransaction();

        if ($transactionControl) {
            $this->sdoFactory->beginTransaction();
        }

        try {
            foreach ($resource->address as $address) {
                $this->sdoFactory->read("digitalResource/repository", $address->repositoryId);

                $repositoryService = $address->repository->getService();

                $repositoryService->deleteObject($address->path);

                $this->sdoFactory->delete($address);
            }

            $this->sdoFactory->delete($resource);
        } catch (\Exception $exception) {
            if ($transactionControl) {
                $this->sdoFactory->rollback();
            }
            throw \laabs::newException("digitalResource/clusterException", 'Resource not deleted at address %1$s : %2$s',  404, $exception, [$address->path, $exception->getMessage()]);
        }
        if ($transactionControl) {
            $this->sdoFactory->commit();
        }

        return true;
    }

    /**
     * Delete resource contents only
     * @param object $resource
     */
    public function rollbackStorage($resource)
    {
        $this->clusterController->rollbackStorage($resource);
    }

    /**
     * Verify integrity of resource
     * @param digitalResource/digitalResource $resource The digital resource
     * @param string                          $mode     The mode
     *
     * @return digitalResource/digitalResource The digitalResource object verify
     *
     * @throws digitalResource/resourceNotFoundException
     */
    public function verifyResource($resource, $mode = Cluster::MODE_READ)
    {
        if (!$resource) {
            throw \laabs::newException("digitalResource/resourceNotFoundException");
        }

        $cluster = $this->useCluster($resource->clusterId, $mode, false);

        return $this->clusterController->verifyResource($cluster, $resource);
    }

    /**
     * Verify completeness of resource
     * @param digitalResource/digitalResource $resource The digital resource
     * @param string                          $mode     The mode
     *
     * @return boolean The digitalResource verify
     *
     * @throws digitalResource/resourceNotFoundException
     */
    public function isStored($resource, $mode = Cluster::MODE_READ)
    {
        if (!$resource) {
            throw \laabs::newException("digitalResource/resourceNotFoundException");
        }

        if ($this->currentCluster && ($this->currentCluster->clusterId == $resource->clusterId)) {
            $cluster = $this->currentCluster;
        } else {
            $cluster = $this->useCluster($resource->clusterId, $mode, false);
        }

        return $this->clusterController->checkResource($cluster, $resource);
    }

    /**
     * Launch a completeness test for a sample of resources
     * @param integer $samplingFrequency check frequency in days
     * @param integer $timeout time limit for completeness check in seconds
     *
     */
    public function completenessSampling($samplingFrequency, $timeout)
    {
        if (!is_null($samplingFrequency)) {
            $samplingFrequency = intval($samplingFrequency);
        }
        
        if (!is_null($timeout)) {
            $timeout = intval($timeout);
        }

        $lifeCycleJournalController = \laabs::newController("lifeCycle/journal");
    
        $sortBy = "<created,<resId";
        $resCount = $this->sdoFactory->count("digitalResource/digitalResource");
        $resourcesToCheck = (int) ceil($resCount/$samplingFrequency);
        $checkedResources = 0;

        $lastCheckedResId = null;


        // last checked resource
        $search = $lifeCycleJournalController->searchEvent("recordsManagement/completenessCheck");
        if (!empty($search)) {
            $lastCheckedResId = json_decode($search[0]->eventInfo)[0];
        }

        // check resources
        $queryParts = array();
        $queryParams = array();

        if ($lastCheckedResId) {
            $queryParams['lastCheckedResId'] = $lastCheckedResId;
            $queryParts['resId'] = "resId > :lastCheckedResId";
        }

        $queryString = implode(' AND ', $queryParts);

        $resources = $this->sdoFactory->find("digitalResource/digitalResource", $queryString, $queryParams, $sortBy, 0, $resourcesToCheck);

        $success = true;
        $nbFailed = 0;
        $eventInfo = [];

        $endTime = microtime(true) + $timeout;

        foreach ($resources as $resource) {
            if (!is_null($timeout) && ($endTime - microtime(true)) <= 0) {
                $logMessage = ["message" => "Time limit reached"];
                \laabs::notify(\bundle\audit\AUDIT_ENTRY_OUTPUT, $logMessage);
                break;
            }
            $checkedResources++;
            $completenessResult = $this->isStored($resource);
            $eventInfo['lastCheckedResId'] = $resource->resId;
            $eventInfo['lastCheckedResCreated'] = $resource->created;

            if (!$completenessResult) {
                $nbFailed++;
                $success = false;
            }

        }
        $eventInfo['resourcesToCheck'] = $resourcesToCheck;
        $eventInfo['checkedResources'] = $checkedResources;
        $eventInfo['failed'] = $nbFailed;

        $event = $lifeCycleJournalController->logEvent('recordsManagement/completenessCheck', 'recordsManagement/archive', 'system', $eventInfo, $success);

        $logMessage = ["message" => "%s resource(s) to check", "variables"=> $resourcesToCheck];
        \laabs::notify(\bundle\audit\AUDIT_ENTRY_OUTPUT, $logMessage);

        $logMessage = ["message" => "%s checked resource(s)", "variables"=> $checkedResources];
        \laabs::notify(\bundle\audit\AUDIT_ENTRY_OUTPUT, $logMessage);

        $logMessage = ["message" => "%s failed resource(s)", "variables"=> $nbFailed];
        \laabs::notify(\bundle\audit\AUDIT_ENTRY_OUTPUT, $logMessage);

        $logMessage = ["message" => "Last checked resource identifier : %s", "variables"=> $eventInfo['lastCheckedResId']];
        \laabs::notify(\bundle\audit\AUDIT_ENTRY_OUTPUT, $logMessage);

        $logMessage = ["message" => "Creation date of the last checked resource : %s", "variables"=> $eventInfo['lastCheckedResCreated']];
        \laabs::notify(\bundle\audit\AUDIT_ENTRY_OUTPUT, $logMessage);
    }

    /**
     * Convert resource to another format
     * @param object $digitalResource
     *
     * @return object The resource converted
     */
    public function convert($digitalResource)
    {
        $convert = $this->isConvertible($digitalResource);

        if ($convert == false) {
            return false;
        }

        $configuration =  \laabs::configuration('dependency.fileSystem');
        $conversionRule = $this->sdoFactory->read("digitalResource/conversionRule", array('puid' => $digitalResource->puid));
        $conversionServices = $configuration['conversionServices'];

        $outputFormats = null;
        $convertService = null;
        foreach ($conversionServices as $service) {
            if ($service["serviceName"] == $conversionRule->conversionService) {
                $outputFormats = $service["outputFormats"];
                $convertService = $service;
            }
        }

        $handler = $digitalResource->getHandler();

        $tempdir = str_replace("/", DIRECTORY_SEPARATOR, \laabs\tempdir());

        if (isset($digitalResource->fileName)) {
            $srcfile = $tempdir.DIRECTORY_SEPARATOR.$digitalResource->fileName;
        } else {
            $srcfile = $tempdir.DIRECTORY_SEPARATOR.$digitalResource->resId;
        }

        $tgtfp = fopen($srcfile, 'w');
        stream_copy_to_stream($handler, $tgtfp);
        rewind($handler);
        fclose($tgtfp);

        $conversionRule = $this->sdoFactory->read("digitalResource/conversionRule", array('puid' => $digitalResource->puid));

        $converter = \laabs::newService($conversionRule->conversionService);

        $tgtfile = $converter->convert($srcfile, $outputFormats[$conversionRule->targetPuid]);

        unlink($srcfile);

        if (!file_exists($tgtfile)) {
            return false;
        }

        $convertedResource = $this->createFromFile($tgtfile);
        $convertedResource->archiveId = $digitalResource->archiveId;
        $convertedResource->puid = $conversionRule->targetPuid;
        $convertedResource->softwareName = $convertService["softwareName"];
        $convertedResource->softwareVersion = $convertService["softwareVersion"];
        $this->getHash($convertedResource);

        $convertedResource->relationshipType = "isConversionOf";

        // Get previous
        while ($digitalResource->relatedResId != "" && $digitalResource->relationshipType == "isConversionOf") {
            $digitalResource = $this->sdoFactory->read("digitalResource/digitalResource", $digitalResource->relatedResId);
        }
        $convertedResource->relatedResId = $digitalResource->resId;

        return $convertedResource;
    }

    /**
     * Check if the resource can be convertible
     * @param $digitalResource/digitalResource $digitalResource The resource object
     *
     * @return mixed The convertion rule or false if it's no possible
     */
    public function isConvertible($digitalResource)
    {
        if (!$this->sdoFactory->exists("digitalResource/conversionRule", array('puid' => $digitalResource->puid))) {
            return false;
        }

        $configuration =  \laabs::configuration('dependency.fileSystem');

        if (!isset($configuration['conversionServices'])) {
            return false;
        }

        $conversionServices = $configuration['conversionServices'];

        if (!is_array($conversionServices)) {
            return false;
        }

        $conversionRule = $this->sdoFactory->read("digitalResource/conversionRule", array('puid' => $digitalResource->puid));

        $outputFormats = null;
        $convertService = null;
        foreach ($conversionServices as $service) {
            if ($service["serviceName"] == $conversionRule->conversionService) {
                $outputFormats = $service["outputFormats"];
                $convertService = $service;
            }
        }

        if (!in_array($digitalResource->puid, $convertService["inputFormats"])) {
            return false;
        }

        if (!$outputFormats) {
            return false;
        }

        $conversionRule = $this->sdoFactory->read("digitalResource/conversionRule", array('puid' => $digitalResource->puid));

        $converter = \laabs::newService($conversionRule->conversionService);

        if (!($converter instanceof \dependency\fileSystem\conversionInterface)) {
            return false;
        }

        return $converter;
    }

    /**
     * Get the text of the resources of an archive
     * @param string $archiveId The resources archive identifier
     *
     * @return string The text of the resources
     */
    public function getFullTextByArchiveId($archiveId)
    {
        $fullText = "";
        $digitalResources = $this->sdoFactory->find("digitalResource/digitalResource", "archiveId='$archiveId' AND relatedResId=null");

        if (count($digitalResources)) {
            $fullTextService = \laabs::newService('dependency/fileSystem/plugins/Tika');

            foreach ($digitalResources as $digitalResource) {
                $contents = $this->contents($digitalResource->resId);
                $tempdir = str_replace("/", DIRECTORY_SEPARATOR, \laabs\tempdir());

                $srcfile = $tempdir.DIRECTORY_SEPARATOR.$digitalResource->resId;

                file_put_contents($srcfile, $contents);

                try {
                    $fullText .= $fullTextService->getText($srcfile);
                } catch (\Exception $exception) {}
            }
        }

        return $fullText;
    }
}
