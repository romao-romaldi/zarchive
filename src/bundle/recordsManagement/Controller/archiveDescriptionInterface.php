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
 * Interface for archive description class control
 *
 * @package RecordsManagement
 * @author  Cyril VAZQUEZ (Maarch) <cyril.vazquez@maarch.org>
 */
interface archiveDescriptionInterface
{
    /**
     * Create the description object
     * @param object $description
     * @param id     $archiveId
     */
    public function create($description, $archiveId);

    /**
     * Retrieve the description object
     * @param id $archiveId
     */
    public function read($archiveId);

    /**
     * Update the description object
     * @param object $description
     * @param id     $archiveId
     */
    public function update($description, $archiveId);

    /**
     * Delete the description object
     * @param id   $archiveId
     * @param bool $deleteDescription
     */
    public function delete($archiveId, $deleteDescription = true);
} // END class
