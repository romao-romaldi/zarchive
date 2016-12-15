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
namespace bundle\digitalResource\Message;

/**
 * Class message that represents a digital resource format
 *
 * @package Digitalresource
 * @author Alexis Ragot <alexis.ragot@maarch.org>
 */
class format
{
    /**
     * The UK National Archives PRONOM format identifier
     *
     * @var string
     */
    public $puid;

    /**
     * The format name
     *
     * @var string
     */
    public $name;

    /**
     * The version
     *
     * @var string
     */
    public $version;

    /**
     * The mime type(s)
     *
     * @var array
     */
    public $mimetypes;

    /**
     * The extension(s)
     *
     * @var array
     */
    public $extensions;

    /**
     * The mediatype
     *
     * @var string
     * @enumeration [message, text, audio, video, image, application, multipart, model]
     */
    public $mediatype;
}
