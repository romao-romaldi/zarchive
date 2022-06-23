<?php

/*
 * Copyright (C) 2015 Maarch
 *
 * This file is part of bundle auth.
 *
 * Bundle auth is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Bundle auth is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with bundle auth.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace bundle\auth\Message;

/**
 * userAccount message
 *
 * @package Auth
 * @author  Prosper DE LAURE <prosper.delaure@maarch.org>
 */
class userAccountUpdate
{
    /**
     * The user account identifier
     *
     * @var id
     * @notempty
     */
    public $accountId;

    /**
     * @var string
     * @notempty
     */
    public $accountName;

    /**
     * @var string
     * @notempty
     */
    public $emailAddress;

    /**
     * @var id[]
     */
    public $roles;

    /**
     * @var id[]
     */
    public $organizations;

    /**
     * The user fisrt name (given name)
     *
     * @var string
     */
    public $firstName;

    /**
     * The user last name (family name, surname)
     *
     * @var string
     */
    public $lastName;

    /**
     * The displayed name
     *
     * @var string
     * @notempty
     */
    public $displayName;

    /**
     * The person title (civility)
     *
     * @var string
     */
    public $title;


    /**
     * The permisions to modify other profiles
     *
     * @var string
     */
    public $modificationRight;

    /**
     * @var string
     */
    public $ownerOrgId;

    /**
     * @var bool
     */
    public $isAdmin;

}
