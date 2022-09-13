<?php
/*
 * Copyright (C) 2017 Maarch
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
 * API digital resource
 * @author Alexis Ragot <alexis.ragot@maarch.org>
 */
interface digitalResourceInterface {

    /**
     * Check if a resource is convertible
     * @param digitalResource/digitalResource $digitalResource The digital resource object
     *
     * @action digitalResource/digitalResource/isConvertible
     */
    public function updateIsconvertible($digitalResource);

    /**
     * Check if resources are complete
     * @param integer $samplingFrequency check frequency in days
     * @param integer $timeout time limit for completeness in seconds
     *
     * @action digitalResource/digitalResource/completenessSampling
     */
    public function readCompleteness($samplingFrequency, $timeout);
   
    /**
     * Search digital resource
     * @param string    $archiveId
     * @param integer   $sizeMin
     * @param integer   $sizeMax
     * @param string    $puid
     * @param string    $mimetype
     * @param string    $hash
     * @param string    $hashAlgorithm
     * @param string    $fileName
     * @param timestamp $startDate
     * @param timestamp $endDate
     *
     * @action digitalResource/digitalResource/findDocument
     */
    public function readFinddocuments(
        $archiveId = null,
        $sizeMin = null,
        $sizeMax = null,
        $puid = null,
        $mimetype = null,
        $hash = null,
        $hashAlgorithm = null,
        $fileName = null,
        $startDate = null,
        $endDate = null
    );

}
