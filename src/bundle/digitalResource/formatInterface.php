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
namespace bundle\digitalResource;

/**
 * API admin formats of digital resource
 */
interface formatInterface
{
    /**
     * List formats
     *
     * @action digitalResource/format/list
     */
    public function readList();

    /**
     * Get format by puid
     * @param string $puid The puid of the format
     *
     * @action digitalResource/format/getByPuid
     */
    public function readGetbypuid($puid);

    /**
     * Create a new format
     * @param digitalResource/format $format The format object
     *
     * @action digitalResource/format/create
     */
    public function create($format);

    /**
     * Update a format
     * @param string                 $puid   The format puid
     * @param digitalResource/format $format The format object
     *
     * @action digitalResource/format/update
     */
    public function update($puid, $format);
    
    /**
     * Delete the format
     * @param string $puid The puid of the format
     *
     * @action digitalResource/format/delete
     */
    public function delete($puid);

}
