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

namespace bundle\recordsManagement;

/**
 * Interface for management of archive
 *
 * @package Recordsmanagement
 */
interface archiveInterface
{

    /**
     * Create an archive
     *
     * @param recordsManagement/archive $archive      The archive object
     * @param bool                      $zipContainer The archive is a zip container
     *
     * @action recordsManagement/archive/receive
     */
    public function create($archive, $zipContainer = false);

    /**
     * Receive an archive
     *
     * @param string $batchDirectory      The path of the folder that contains archives
     * @param string $descriptionFilePath The path of the description file
     *
     * @action recordsManagement/archive/receiveArchiveBatch
     */
    public function createArchivebatch($batchDirectory, $descriptionFilePath);


    /*
        RETRIEVE ARCHIVE
    */
    /**
     * Retrieve an archive document by its id
     *
     * @action recordsManagement/archive/getDocument
     */
    public function readDocument_docId_();

    /**
     * Retrieve an archive resource by its id
     *
     * @action recordsManagement/archive/consultation
     */
    public function readConsultation_archiveId_Digitalresource_resId_();

    /**
     * Retrieve an archive by its id
     *
     * @action recordsManagement/archive/read
     */
    public function read_archiveId_();

    /**
     * Retrieve an archive content document (CDO)
     *
     * @action recordsManagement/archive/consultation
     */
    public function readContents_archiveId__resId_();

    /**
     * Check if archive exists
     *
     * @action recordsManagement/archive/exists
     */
    public function read_archiveId_Exists();

    /*
        MODIFY ARCHIVE
    */
    /**
     * Read the retention rule of archive
     *
     * @action recordsManagement/archive/editArchiveRetentionRule
     */
    public function read_archiveId_Retentionrule();

    /**
     * Read the access rule of archive
     *
     * @action recordsManagement/archive/editArchiveAccessRule
     */
    public function readAccessrule_archiveId_();

    /**
     * Update the archive index
     *
     * @action recordsManagement/fulltext/updateArchiveIndex
     */
    public function updateIndex($index);

     /**
     * Convert and store a resource
     *
     * @action recordsManagement/archive/convertAndStore
     */
    public function updateInteractiveconversion_resId_();
}
