<?php
/*
 * Copyright (C) 2015 Maarch
 *
 * This file is part of bundle documentManagement.
 *
 * Bundle documentManagement is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Bundle documentManagement is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with bundle documentManagement.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace bundle\documentManagement\Message;

/**
 * Class message that represents a document
 *
 * @package DocumentManagement
 * @author  Alexis Ragot <alexis.ragot@maarch.org>
 */
class document
{
    /**
     * The type of document
     *
     * @var string
     */
    public $type;

    /**
     * The digital resource object
     *
     * @var digitalResource/digitalResource
     */
    public $digitalResource;

    /**
     * The title of document
     *
     * @var string
     */
    public $title;

    /**
     * The description of document
     *
     * @var string
     */
    public $description;

    /**
     * The creator of document
     *
     * @var string
     */
    public $creator;

    /**
     * The publisher of document
     *
     * @var string
     */
    public $publisher;

    /**
     * The contributor(s) of document
     *
     * @var string
     */
    public $contributor;

    /**
     * The category of document
     *
     * @var string
     */
    public $category;

    /**
     * Language of the document
     *
     * @var string
     */
    public $language;

    /**
     * Purpose of the document
     *
     * @var string
     */
    public $purpose;

    /**
     * Date of creation of the document
     *
     * @var timestamp
     */
    public $creation;

    /**
     * Date of issue of the document
     *
     * @var timestamp
     */
    public $issue;

    /**
     * Date of receipt of the document
     *
     * @var timestamp
     */
    public $receipt;

    /**
     * Date of response of the document
     *
     * @var timestamp
     */
    public $response;

    /**
     * Date of submission of the document
     *
     * @var timestamp
     */
    public $submission;

    /**
     * Date when document is available
     *
     * @var timestamp
     */
    public $available;

    /**
     * Date when document was validated
     *
     * @var timestamp
     */
    public $valid;

    /**
     * The depositor document identifier
     *
     * @var string
     */
    public $depositorDocId;

    /**
     * The originator document identifier
     *
     * @var string
     */
    public $originatorDocId;

    /**
     * The document description
     *
     */
    public $descriptionObject;
}
