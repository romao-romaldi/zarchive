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
 * Interface for management of archival profile
 *
 * @package RecordsMangement
 * @author  Maarch Prosper DE LAURE <prosper.delaure@maarch.org>
 */
interface archivalProfileInterface
{
    /**
     * List the profiles
     * @param integer $limit Maximal number of results to dispay
     * @param string  $query The query filter
     *
     * @action recordsManagement/archivalProfile/index The list of profile
     *
     */
    public function readIndex($limit = null, $query = null);

    /**
     *  New profile
     *
     * @action recordsManagement/archivalProfile/newProfile
     *
     */
    public function readNew();

    /**
     * Create a csv file
     *
     * @param  integer $limit Max number of results to display
     *
     * @action recordsManagement/archivalProfile/exportCsv
     *
     */
    public function readExport($limit = null);

    /**
     * @param resource  $data     Data base64 encoded or not
     * @param boolean   $isReset  Reset tables or not
     *
     * @action recordsManagement/archivalProfile/import
     *
     * @return boolean        Import with reset of table data or not
     */
    public function createImport($data, $isReset);

    /**
     * Edit a archival profile
     * @param bool $withRelatedProfiles Bring back the children profiles
     *
     * @action recordsManagement/archivalProfile/read The profile object
     */
    public function read_archivalProfileId_($withRelatedProfiles = true);

    /**
     * Edit a archival profile
     *
     * @action recordsManagement/archivalProfile/getByReference
     */
    public function readByreference_reference_();

    /**
     * Read a profile description
     *
     * @action recordsManagement/archivalProfile/getByReference The profile object
     */
    public function readProfiledescription_archivalProfileReference_();

    /**
     * create a archival profile
     * @param recordsManagement/archivalProfile $archivalProfile The archival profile object
     *
     * @action recordsManagement/archivalProfile/create
     *
     */
    public function create($archivalProfile);

    /**
     * update a archival profile
     * @param recordsManagement/archivalProfile $archivalProfile The archival profile object
     *
     * @action recordsManagement/archivalProfile/update
     *
     */
    public function update($archivalProfile);

    /**
     * delete an archival profile
     *
     * @action recordsManagement/archivalProfile/delete
     *
     */
    public function delete_archivalProfileId_();

    /**
     * upload an archival profile
     * @param recordsManagement/archivalProfile $archivalProfile The profile object
     * @param base64 $content The profile binary file
     * @param string $format          The profile file format
     *
     * @action recordsManagement/archivalProfile/uploadArchivalProfile
     */
    public function createArchivalprofileUpload_profileReference_($archivalProfile, $content, $format = "rng");

    /**
     * Download the profile file
     *
     * @action recordsManagement/archivalProfile/exportFile
     */
    public function readArchivalprofileExport_profileReference_();

    /**
     * Get used archival profiles
     *
     * @action recordsManagement/archivalProfile/getUsedArchivalProfileIds
     */
    public function readUsedArchivalProfileIds();

}
