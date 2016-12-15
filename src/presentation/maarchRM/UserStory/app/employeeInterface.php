<?php

/*
 * Copyright (C) 2015 Maarch
 *
 * This file is part of userStory app.
 *
 * UserStory app is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * UserStory app is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with bundle financialRecords.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace presentation\maarchRM\UserStory\app;

/**
 * Standard interface for thirdParty class
 */
interface employeeInterface
{
    /**
     * Get the list of available third
     *
     * @uses businessRecords/employee/readQuery_query_
     */
    public function readEmployeeQuerythird_query_();
}
