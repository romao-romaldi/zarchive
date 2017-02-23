<?php
/*
 * Copyright (C) 2015 Maarch
 *
 * This file is part of MaarchRM.
 *
 * MaarchRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * MaarchRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with MaarchRM.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace presentation\maarchRM\UserStory\adminArchive;

/**
 * Interface for management of file plan
 *
 * @author Prosper DE LAURE <prosper.delaure@maarch.org>
 */
interface filePlanInterface
{
    /**
     * Add a new folder
     * @param object $folder The new folder
     *
     * @uses filePlan/filePlan/create
     * @return filePlan/filePlan/create
     */
    public function createFileplanFolder($folder);

    /**
     * Update a folder
     * @param object $folder The new folder
     *
     * @uses filePlan/filePlan/update
     * @return filePlan/filePlan/update
     */
    public function updateFileplanFolder($folder);

}
