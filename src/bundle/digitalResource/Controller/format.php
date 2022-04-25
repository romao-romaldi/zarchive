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
 * Class for digitalResource format ref
 */
class format
{
    protected $sdoFactory;
    protected $format;


    /**
     * Constructor
     */
    public function __construct(\dependency\sdo\Factory $sdoFactory)
    {
        $this->sdoFactory = $sdoFactory;
    }

    /**
     * Get all the formats
     *
     * @return digitalResource/format[] Array of formats
     */
    public function list()
    {
        return $this->sdoFactory->index('digitalResource/format', array("puid", "name", "version", "mimetypes", "extensions", "status"));
    }

    /**
     * Get the format by puid
     * @param string $puid The puid
     *
     * @return digitalResource/format The format object 
     */
    public function getByPuid($puid) {
        return $this->sdoFactory->read('digitalResource/format',$puid);
        
    }

    /**
     * Create a new format
     * @param digitalResource/format $format The format object
     *
     * @return bool The result of the operation
     */
    public function create($format) {
        if ($this->sdoFactory->exists('digitalResource/format', $format->puid)) {
            throw \laabs::newException("digitalResource/formatException", "The format with the puid %s already exists.", 404, null, [$format->puid]);
        }
        return $this->sdoFactory->create($format, 'digitalResource/format');
 
    }

    /**
     * Update a format
     * @param string                 $puid   The format puid
     * @param digitalResource/format $format The format to update
     *
     * @return bool The result of the operation
     */
    public function update($puid, $format)
    {
        $format->puid = $puid;
        return $this->sdoFactory->update($format, 'digitalResource/format');
    }

    /**
     * Delete the format
     * @param string $puid The puid of the format
     *
     * @return bool The result of the operation
     */
    public function delete($puid) {
        $format = $this->sdoFactory->read('digitalResource/format', $puid);
        return $this->sdoFactory->delete($format);
    }

}
