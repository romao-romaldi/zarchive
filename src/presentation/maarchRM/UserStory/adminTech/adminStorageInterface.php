<?php
/*
 * Copyright (C) 2015 Maarch
 *
 * This file is part of maarchRM.
 *
 * maarchRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * maarchRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with bundle digitalResource.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace presentation\maarchRM\UserStory\adminTech;

/**
 * User story admin storage
 * @author Alexis Ragot <alexis.ragot@maarch.org>
 */
interface adminStorageInterface
{
    /**
     * Edit a cluster
     *
     * @return digitalResource/cluster/edit
     */
    public function readCluster();

    /**
     * Modify a cluster
     *
     * @uses digitalResource/cluster/read_clusterId_
     * @return digitalResource/cluster/edit
     */
    public function readCluster_clusterId_();

    /**
     * Read list of cluster
     *
     * @uses digitalResource/cluster/readList
     * @return digitalResource/cluster/index
     */
    public function readClusters();

    /**
     * Create a new cluster
     * @param digitalResource/cluster $cluster The cluster object to update
     *
     * @uses digitalResource/cluster/create
     * @return digitalResource/cluster/create
     */
    public function createCluster($cluster);

    /**
     * Update an existing cluster
     * @param digitalResource/cluster $cluster The cluster object to update
     *
     * @uses digitalResource/cluster/update
     * @return digitalResource/cluster/update
     */
    public function updateCluster_clusterId_($cluster);

    /**
     * read list of repositories
     *
     * @uses  digitalResource/repository/readList
     * @return digitalResource/repository/index
     */
    public function readRepositories();

    /**
     * Edit a repository
     *
     * @uses digitalResource/repository/read_repositoryId_
     * @return digitalResource/repository/edit
     */
    public function readRepository_repositoryId_();

    /**
     * Prepare an empty role object
     *
     * @return digitalResource/repository/edit
     */
    public function readRepository();

    /**
     * Create a new repository
     * @param digitalResource/repository $repository The repository object to record
     *
     * @uses digitalResource/repository/create
     * @return digitalResource/repository/create
     */
    public function createRepository($repository);

    /**
     * Update an existing repository
     * @param digitalResource/repository $repository The repository object to update
     *
     * @uses digitalResource/repository/update
     * @return  digitalResource/repository/update
     */
    public function updateRepository_repositoryId_($repository);

    /**
     *  Get addresses which fail the integrity test
     *
     * @uses digitalResource/repository/readFlawedAddresses
     * @return  digitalResource/repository/flawedaddresses
     */
    public function readFlawedaddresses();

    /**
     * Validate an address integrity
     * @param string $resId         The resource identifier
     * @param string $repositoryId  The repository identifier
     *
     * @uses digitalResource/repository/updateCheckaddressintegrity
     * @return  digitalResource/repository/checkAddressIntegrity
     */
    public function updateCheckaddressintegrity($resId, $repositoryId);
}