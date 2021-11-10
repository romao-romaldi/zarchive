<?php
/*
 * Copyright (C) 2021 Maarch
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

namespace presentation\maarchRM\UserStory\archiveManagement;

/**
 * Interface for management of description fields
 *
 * @package adminArchive
 * @author Jerome Boucher <alexis.ragot@maarch.org>
 */
interface adminOriginatorInterface
{

    /**
     * Get available originators for an archive
     * 
     * @uses recordsManagement/archive/read_archiveId_Availableoriginators
     */
    public function readRecordsmanagementArchiveAvailableoriginators_archiveId_();

    /**
     * @uses recordsManagement/archives/updateOriginator
     * @return recordsManagement/archive/setOriginator
     */
    public function updateRecordsmanagementArchiveOriginator($archiveIds, $orgId);
}
