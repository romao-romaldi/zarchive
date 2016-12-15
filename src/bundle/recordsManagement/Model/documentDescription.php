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

namespace bundle\recordsManagement\Model;

/**
 * Class model that represents a document profile description field
 *
 * @package RecordsManagement
 * @author  Prosper DE LAURE (Maarch) <prosper.delaure@maarch.org>
 *
 * @pkey [documentProfileId, fieldName]
 * @fkey [documentProfileId] recordsManagement/documentProfile [documentProfileId]
 */
class documentDescription extends objectDescription
{
    /**
     * The identifier of the document profile
     *
     * @var id
     */
    public $documentProfileId;

    /**
     * The position
     *
     * @var int
     */
    public $position;
}
