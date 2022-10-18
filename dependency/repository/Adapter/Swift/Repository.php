<?php
/*
 * Copyright (C) 2022 Maarch
 *
 * This file is part of dependency repository.
 *
 * Dependency repository is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Dependency repository is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with dependency repository.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace dependency\repository\Adapter\Swift;

use OpenStack\OpenStack;
use GuzzleHttp;

/**
 * Class for repository
 *
 * @package Dependency\Repository
 * @author  Maarch Cyril Vazquez <cyril.vazquez@maarch.org>
 */
class Repository implements \dependency\repository\RepositoryInterface
{
    /* Properties */
    protected $endpoint;

    /**
     * The options
     * @var array
     */
    protected $options = [
        'containerPathSteps' => 2
    ];

    /**
     * The client
     * @var OpenStack\ObjectStora\Service
     */
    protected $objectStorageService;

    /* Methods */
    /**
     * Constructor
     * @param string $name    The endpoint name
     * @param array  $options The repository options
     *
     * @return void
     */
    public function __construct($name, array $options = null)
    {
        // Set host name
        $this->endpoint = $name;

        if (is_array($options)) {
            $this->options = array_merge($this->options, $options);
        }

        if (!isset($this->options['sdk']) || !is_file($this->options['sdk'].'/autoload.php')) {
            throw new \Exception("Unable to find SDK autoloader");
        }

        require $this->options['sdk'].'/autoload.php';

        // Authentification informations
        // $authUrl = 'https://auth.cloud.ovh.net/v3'; Point d'accès au service Identity définit dans la partie la partie "Accès et sécurité" puis dans l'onglet "Accès API".
        // $username = utilisateur
        // $password = mot de passe 
        // $tenant = OS_TENANT_NAME du fichier RC OpenStack
        // $projectId = PROJECT_ID du fichier RC OpenStack
        // $projectName = PROJECT_NAME du fichier RC OpenStack
        // $region = Region du service endpoint

        // Get region
        if (!isset($this->options['region'])) {
            throw new \Exception("No region provided");
        }

        // Get auth service
        if (!isset($this->options['authUrl'])) {
            throw new \Exception("No authentication URL provided");
        }

        // Get authentication
        if (!isset($this->options['username']) || !isset($this->options['password'])) {
            throw new \Exception("No credential provided");
        }
     
        // Constructor initialization
        $client = new OpenStack(array(
          'authUrl'=> $this->options['authUrl'],
          'region' => $this->options['region'],
          'user' => [
            'name'=> $this->options['username'],
            'password'=> $this->options['password'],
            'domain'   => [
              'name' => 'Default'
            ]
          ],
          'scope'   => [
            'project' => [
                'id' => $this->options['projectId']
            ]
          ]
        ));

        // Swift informations
        $serviceName = 'swift';
        $catalogType = 'object-store';

        // Send a request to the Object Store service (Swift)
        $this->objectStorageService = $client->objectStoreV1([
          'catalogName' => $serviceName, 
          'catalogType' => $catalogType
        ]);
    }

    // CONTAINER
    /**
     * Delete a container
     * @param string $name     The name of container
     * @param mixed  $metadata The object or array of metadata
     *
     * @return mixed The address/uri/identifier of created container on repository
     */
    public function createContainer($name, $metadata = null)
    {
        $path = $this->resolvePath($name);

        // Parse container name
        $parser = $this->parsePath($path);

        // Call Swift API
        $result = $this->objectStorageService->createcontainer([
            'name' => $parser->container
        ]);

        // Call Swift API
        // Store object for metadata ?
        //

        return $path;
    }

    /**
     * Update a container metadata
     * @param string $name     The name of container
     * @param mixed  $metadata The object or array of metadata
     *
     * @return bool
     */
    public function updateContainer($name, $metadata)
    {
    }

    /**
     * Read a container metadata
     * @param string $name The name of container
     *
     * @return mixed The object or array of metadata if available
     */
    public function readContainer($name)
    {
    }

    /**
     * Delete a container
     * @param string $name The name of container
     *
     * @return bool
     */
    public function deleteContainer($name)
    {
    }

    // OBJECTS

    /**
     * Create an object
     * @param string $data The resource contents
     * @param string $path The path
     *
     * @return string The real path
     */
    public function createObject($data, $path)
    {
        $path = $this->resolvePath($path);

        // Parse container name
        $parser = $this->parsePath($path);

        // Open container
        $container = $this->objectStorageService->getContainer($parser->container);

        // Call Swift API
        $stream = new GuzzleHttp\Psr7\Stream($data);

        $result = $container->createObject([
          'name' => $parser->path,
          'stream' => $stream
        ]); 
        
        return $path;
    }

    /**
     * Get a resource in repository
     * @param mixed   $path The path/uri/identifier of stored resource on repository
     * @param integer $mode A bitmask of what to read 0=nothing - only touch | 1=data | 2=metadata | 3 data+metadata
     *
     * @return mixed The contents of resource
     */
    public function readObject($path, $mode = 1)
    {
        $path = $this->resolvePath($path);

        $parser = $this->parsePath($path);

        // Open container
        $container = $this->objectStorageService->getContainer($parser->container);

        switch ($mode) {
            case 0:
                return $container->objectExists($parser->path);

            case 1:

                // Call Swift API
                $storageObject = $container->getObject($parser->path);
                $stream = $storageObject->download();
                $handler = $stream->detach();

                return $handler;

            case 2:
                // TODO parse result to get metadata as expected
                return $result;

            case 3:
                // TODO parse result to get data and metadata in returned array

                return $result;

            default:
                throw new \Exception("This mode '$mode' isn't avalaible");
        }
    }

     /**
     * Update a resource
     * @param string $path     The URI of the resource
     * @param string $data     The content
     * @param object $metadata The new metadata to update or insert
     *
     * @return bool
     */
    public function updateObject($path, $data = null, $metadata = null)
    {
        $path = $this->resolvePath($path);

        $parser = $this->parsePath($path);

        return false;
    }

     /**
     * Delete a resource
     * @param string $path The URI of the resource
     *
     * @return bool
     */
    public function deleteObject($path)
    {
        $path = $this->resolvePath($path);

        $parser = $this->parsePath($path);

        // Open container
        $container = $this->objectStorageService->getContainer($parser->container);

        // Call Swift API
        $storageObject = $container->getObject($parser->path);

        $storageObject->delete();

        return true;
    }

    /*
     * Non public methods
     */
    /**
     * Parse the path to get container and key
     * @param string $path
     * 
     * @return array
     */
    protected function parsePath($path)
    {
        $steps = explode('/', $path);

        $parser = (object) [
            'container' => implode('.', array_slice($steps, 0, $this->options['containerPathSteps'])),
            'path' => implode('/', array_slice($steps, ($this->options['containerPathSteps'])))
        ];

        return $parser;
    }

    protected function resolvePath($name)
    {
        if (preg_match_all("/\<[^\>]+\>/", $name, $variables)) {
            foreach ($variables[0] as $variable) {
                $token = substr($variable, 1, -1);
                switch (true) {
                    case $token == 'app':
                        $name = str_replace($variable, \laabs::getApp(), $name);
                        break;

                    case $token == 'instance':
                        if ($instanceName = \laabs::getInstanceName()) {
                            $name = str_replace($variable, \laabs::getInstanceName(), $name);
                        } else {
                            $name = "instance";
                        }
                        break;

                    case substr($token, 0, 5) == 'date(':
                        $format = substr($token, 5, -1);
                        $name = str_replace($variable, date($format), $name);
                        break;
                }
            }
        }

        // TO DO : sanitize names regarding Swift DSN
        $illegal = array_merge(array_map('chr', range(0, 31)), array("<", ">", ":", '"', "|", "?", "*", ".."));
        $name = str_replace($illegal, "_", $name);

        return $name;
    }
}
