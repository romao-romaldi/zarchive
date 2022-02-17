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
 * API admin repository digital resource
 */
interface repositoryInterface
{
    /**
     * List repositories for administration
     *
     * @action digitalResource/repository/index
     */
    public function readList();

    /**
     * Edit a repository
     *
     * @action digitalResource/repository/edit
     */
    public function read_repositoryId_();

    /**
     * Create a new repository
     * @param digitalResource/repository $repository The repository object
     *
     * @action digitalResource/repository/create
     */
    public function create($repository);

    /**
     * Update an existing repository
     * @param digitalResource/repository $repository The repository object
     *
     * @action digitalResource/repository/update
     */
    public function update($repository);

    /**
     * Check the integrity of all resources in a repository
     * @param string  $repositoryReference The reference of the repository to check
     * @param bool    $init                Start an new integrity check or continue from last check
     * @param integer $addressLimit        The maximum address to check
     * @param integer $maxError            The maximum number of error before the end of the process
     *
     * @action digitalResource/repository/checkRepositoryIntegrity
     */
    public function updateCheckintegrity($repositoryReference, $init = true, $addressLimit = 1000, $maxError = 5);


    /**
     *  Get addresses which fail the integrity test
     *
     * @action digitalResource/repository/getFlawedAddresses
     */
    public function readFlawedaddresses();

    /**
     *  Validate an address integrity
     * @param string $resId        The resource identifier
     * @param string $repositoryId The repository identifier
     *
     * @action digitalResource/repository/validateAddressIntegrity
     */
    public function updateCheckaddressintegrity($resId, $repositoryId);

}
