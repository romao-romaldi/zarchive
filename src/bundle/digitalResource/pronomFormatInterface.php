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
 * API admin formats of digital resource
 */
interface pronomFormatInterface
{
    /**
     * List repositories for administration
     *
     * @action digitalResource/pronomFormat/index
     */
    public function readList();

    /**
     * Get the list of type
     *
     * @action digitalResource/pronomFormat/find
     */
    public function readFind_query_();

    /**
     * Get the format by puid
     * @param string $puid The puid
     *
     * @action digitalResource/pronomFormat/get
     */
    public function readGet($puid);

    /**
     * Get the list of type
     *
     * @action digitalResource/pronomFormat/getFileInformation
     */
    public function createFileformatinformation($contents, $extension);
}
