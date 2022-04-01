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
 * User story admin format
 * @author Alexis Ragot <alexis.ragot@maarch.org>
 */
interface adminFormatInterface
{
    /**
     * Read list of formats
     *
     * @uses digitalResource/format/readList
     * @return digitalResource/format/index
     */
    public function readDigitalresourceFormats();

    /**
     * Read list of PRONOM formats
     *
     * @uses digitalResource/pronomFormat/readList
     */
    public function readPronomformats();

    /**
     * Create a new format
     *
     * @uses digitalResource/format/create
     * @return digitalResource/format/create
     */
    public function createFormat($format);

    /**
     * Edit a format
     *
     * @uses digitalResource/format/readGetbypuid
     * @return digitalResource/format/edit
     */
    public function readFormat($puid);

    /**
     * Update a format
     *
     * @return digitalResource/format/update
     * @uses digitalResource/format/update
     */
    public function updateFormat($puid, $format);

    /**
     * Delete a format
     *
     * @uses digitalResource/format/delete
     * @return digitalResource/format/delete
     */
    public function deleteFormat($puid);

    /**
     * Get file format information
     *
     * @uses digitalResource/pronomFormat/createFileformatinformation
     * @return digitalResource/format/FileFormatInformation
     */
    public function createDigitalresourceFormatFileformatinformation();

    /**
     * Read list of conversion rules
     *
     * @uses digitalResource/conversionRule/readList
     * @return digitalResource/conversionRule/index
     */
    public function readDigitalresourceConversionrules();

    /**
     * Prepare an empty conversion rules object
     *
     * @return digitalResource/conversionRule/edit
     */
    public function readDigitalresourceConversionruleNew();

    /**
     * Create a new conversion rule
     * @param digitalResource/conversionRule $conversionRule The conversion rule object to record
     *
     * @uses digitalResource/conversionRule/create
     * @return digitalResource/conversionRule/create
     */
    public function createDigitalresourceConversionrule($conversionRule);

    /**
     * Update an existing conversion rule
     *
     * @uses digitalResource/conversionRule/delete_conversionRuleId_
     * @return  digitalResource/conversionRule/delete
     */
    public function deleteDigitalresourceConversionrule_conversionRuleId_();

    /**
     * Update an existing conversion rule
     * @param digitalResource/conversionRule $conversionRule The conversion rule object to update
     *
     * @uses digitalResource/conversionRule/update
     * @return  digitalResource/conversionRule/update
     */
    public function updateDigitalresourceConversionrule($conversionRule);

    /* ***************************************
     * Content types management (families)
     * ************************************* */
    /**
     * Index of content types
     *
     * @uses digitalResource/contentType/read
     * @return digitalResource/contentType/index
     */
    public function readDigitalresourceContenttypes();

    /**
     * New content type
     *
     * @return digitalResource/contentType/edit
     */
    public function readDigitalresourceContenttypeNew();

    /**
     * Edit content type
     *
     * @uses digitalResource/contentType/read_name_
     * @return digitalResource/contentType/edit
     */
    public function readDigitalresourceContenttypeEdit_name_();

    /**
     * Create content type
     *
     * @uses digitalResource/contentType/create
     * @return digitalResource/contentType/create
     */
    public function createDigitalresourceContenttype();

    /**
     * Update content type
     *
     * @uses digitalResource/contentType/update_name_
     * @return digitalResource/contentType/update
     */
    public function updateDigitalresourceContenttype($contentType);
}