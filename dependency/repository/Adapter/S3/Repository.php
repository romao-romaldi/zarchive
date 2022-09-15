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
namespace dependency\repository\Adapter\S3;

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
     * The number of path steps used for bucket name, others for object path
     * @var integer
     */
    protected $bucketPathSteps;

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
            foreach ($options as $name => $value) {
                if (property_exists($this, $name)) {
                    $this->{$name} = $value;
                }
            }
        }

        // TODO : Check if endpoint can be reached

        // TODO Get authentication
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
        $path = $this->getName($name);

        // Parse bucket name
        $pathParser = $this->parsePath($path);

        $bucket = $pathParser['bucket'];
        var_dump('Create bucket at '.$bucket);

        // Call S3 API
        // Create bucket
        //

        // Call S3 API
        // Store object of metadata
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
        $url = $this->getObjectUrl($path);
        var_dump('Store object at url '.$url);

        // Call S3 API
        // Store object with metadata
        //

        // Return internal storage path
        $path = $this->getName($path);

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
        $url = $this->getObjectUrl($path);

        switch ($mode) {
            case 0:
                var_dump('Check objet at url '.$url);

                // Call S3 API
                // Check object exists
                //

                $result = true;

                return $result;

            case 1:
                var_dump('Read objet data at url '.$url);

                // Call S3 API
                // Read object data only
                //

                $data = 'data';

                return $data;

            case 2:
                var_dump('Read objet metadata at url '.$url);

                // Call S3 API
                // Read object metadata only
                //

                $meta = (object) [];

                return $meta;

            case 3:
                var_dump('Read objet at url '.$url);

                // Call S3 API
                // Read object data and metadata
                //

                $object = ['data', (object) []];

                return $object;

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
        $url = $this->getObjectUrl($path);

        var_dump('Can not update objet metadata at url '.$url);

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
        $url = $this->getObjectUrl($path);
        var_dump('Delete object at url '.$url);

        // Call S3 API
        // Delete object
        //

        return true;
    }

    /*
     * Non public methods
     */
    /**
     * Parse the path to get bucket and key
     * @param string $path
     * 
     * @return array
     */
    protected function parsePath($path)
    {
        $steps = explode('/', $path);

        $parser = [
            'bucket' => implode('.', array_slice($steps, 0, $this->bucketPathSteps)),
            'key' => implode('/', array_slice($steps, ($this->bucketPathSteps-1)))
        ];

        return $parser;
    }

    protected function getObjectUrl($path)
    {
        $pathParser = $this->parsePath($path);

        $bucket = $this->getName($pathParser['bucket']);
        $key = $this->getName($pathParser['key']);

        $url = $bucket.'.'.$this->endpoint.'/'.$key;

        return $url;
    }

    protected function getName($name)
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

        // TO DO : sanitize names regarding S3 DSN
        $illegal = array_merge(array_map('chr', range(0, 31)), array("<", ">", ":", '"', "|", "?", "*", ".."));
        $name = str_replace($illegal, "_", $name);

        return $name;
    }

    protected function checkFile($path)
    {
        $filename = $this->root . DIRECTORY_SEPARATOR . $path;

        if (!file_exists($filename)) {
            return false;
        }

        return true;
    }
}
