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

namespace bundle\digitalResource\Controller;

/**
 * Class for digitalResource PRONOM format ref
 */
class pronomFormat
{
    protected $droidSignatureFile;
    protected $droidContainerSignatureFile;

    protected $droid;
    protected $jhove;

    protected $domDocument;
    protected $domXPath;
    protected $format;


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->droidSignatureFile = \laabs::configuration('dependency.fileSystem')['signatureFile'];
        $this->droidContainerSignatureFile = \laabs::configuration('dependency.fileSystem')['containerSignatureFile'];
    }

    /**
     * Get all the formats
     * @return digitalResource/format[] Array of digitalResource/format object
     */
    public function index()
    {
        if (!$this->domDocument) {
            $this->getReference();
        }

        $formats = array();
        $formatElements = $this->domXPath->query("/psf:FFSignatureFile/psf:FileFormatCollection/psf:FileFormat");

        foreach ($formatElements as $formatElement) {
            $formats[] = $this->parseFormatElement($formatElement);
        }

        return $formats;
    }

    /**
     * Get a format by id
     * @param string $puid The puid of format to retrieve
     *
     * @return digitalResource/format Format object
     */
    public function get($puid)
    {
        if (!$this->domDocument) {
            $this->getReference();
        }

        $formatElements = $this->domXPath->query("/psf:FFSignatureFile/psf:FileFormatCollection/psf:FileFormat[@PUID='$puid']");

        if ($formatElements->length == 1) {
            return $this->parseFormatElement($formatElements->item(0));
        }
    }

    /**
     * Get all formats matching mimetype
     * @param string $mimetype The mimetype of formats to retrieve
     *
     * @return digitalResource/format[] Array of digitalResource/format object
     */
    public function mimetype($mimetype)
    {
        if (!$this->domDocument) {
            $this->getReference();
        }

        $formats = array();
        $formatElements = $this->domXPath->query("/psf:FFSignatureFile/psf:FileFormatCollection/psf:FileFormat[contains(@MIMEType, '$mimetype')]");
        foreach ($formatElements as $formatElement) {
            $formats[] = $this->parseFormatElement($formatElement);
        }

        return $formats;
    }

    /**
     * Get all formats matching extension
     * @param string $extension The extension of formats to retrieve
     *
     * @return digitalResource/format[] Array of digitalResource/format object
     */
    public function extension($extension)
    {
        if (!$this->domDocument) {
            $this->getReference();
        }
        $formats = array();
        $formatElements = $this->domXPath->query("/psf:FFSignatureFile/psf:FileFormatCollection/psf:FileFormat[./psf:Extension/text() = '$extension']");

        foreach ($formatElements as $formatElement) {
            $formats[] = $this->parseFormatElement($formatElement);
        }

        return $formats;
    }

    /**
     * Get the list of type
     * @param string $query
     *
     * @return digitalResource/format[] The list of type found
     */
    public function find($query = false)
    {
        if (!$this->domDocument) {
            $this->getReference();
        }

        $formats = array();

        $queryTokens = \laabs\explode(" ", $query);
        $queryTokens = array_unique($queryTokens);

        $queryPredicats = array();
        foreach ($queryTokens as $queryToken) {
            $queryPredicats[] = "contains(lower-case(@Name), lower-case('$queryToken'))";
            $queryPredicats[] = "contains(lower-case(@MIMEType), lower-case('$queryToken'))";
            $queryPredicats[] = "./Extension/text() = '$queryToken'";
            $queryPredicats[] = "contains(lower-case(@PUID), lower-case('$queryToken'))";
        }
        $queryString = "/psf:FFSignatureFile/psf:FileFormatCollection/psf:FileFormat[".implode(" or ", $queryPredicats)."]";

        $formatElements = $this->domXPath->query($queryString);

        foreach ($formatElements as $formatElement) {
            $formats[] = $this->parseFormatElement($formatElement);
        }

        return $formats;
    }

    /**
     * Get the signature file info
     * @return string The signature file info
     */
    public function formatDescription()
    {
        if (!$this->domDocument) {
            $this->getReference();
        }

        $description = array();
        $elements = $this->domDocument->getElementsByTagName("FFSignatureFile");
        foreach ($elements as $element) {
            $description[0] = $element->getAttribute("Version");
            $description[1] = explode("T", $element->getAttribute("DateCreated"))[0];
        }

        return $description;
    }

    /**
     * Get all information about the content file (puid, md5/sha256/sha512 hash)
     *
     * @return digitalResource/fileInformation The file information object
     */
    public function getFileInformation($contents, $extension)
    {
        if (is_resource($contents)) {
            $contents = base64_decode(stream_get_contents($contents));
        } elseif (filter_var($contents, FILTER_VALIDATE_URL)) {
            $contents = stream_get_contents($contents);
        } elseif (preg_match('%^[a-zA-Z0-9\\\\/+]*={0,2}$%', $contents)) {
            $contents = base64_decode($contents);
        } elseif (is_file($contents)) {
            $contents = file_get_contents($contents);
        }

        $filename = tempnam(sys_get_temp_dir(), 'digitalResource.format.');
        file_put_contents($filename, $contents);

        $format = $this->identifyFormat($filename);

        if (!$format) {
            throw \laabs::newException("digitalResource/formatIdentificationException", "The format identification failed.");
        }

        $finfo = new \finfo();
        $mimetype = $finfo->buffer($contents, \FILEINFO_MIME_TYPE);

        $valid = $this->validateFormat($filename);

        unlink($filename);

        $fileInformation = \laabs::newMessage("digitalResource/fileInformation");

        $fileInformation->hashMD5 = hash("MD5", $contents);
        $fileInformation->hashSHA256 = hash("SHA256", $contents);
        $fileInformation->hashSHA512 = hash("SHA512", $contents);
        $fileInformation->mimetype = $mimetype;
        $fileInformation->puid = $format->puid;
        $fileInformation->valid = $valid;

        return $fileInformation;
    }

    /**
     * Get format from file
     * @param string $filename
     *
     * @return format The format from file
     */
    public function identifyFormat($filename)
    {
        if (!isset($this->droid)) {
            $this->droid = \laabs::newService('dependency/fileSystem/plugins/fid', $this->droidSignatureFile, $this->droidContainerSignatureFile);
        }
        $format = $this->droid->match($filename);

        return $format;
    }

    /**
     * Get format from file
     * @param string $filename
     *
     * @return format The format from file
     */
    public function validateFormat($filename)
    {
        if (!isset($this->jhove)) {
            $this->jhove = \laabs::newService('dependency/fileSystem/plugins/jhove');
        }
        $valid = $this->jhove->validate($filename);

        if ($valid) {
            return true;
        } else {
            return $this->jhove->getErrors();
        }
    }

    /**
     * Parse a format element from Xml into a digitalResource/format object
     * @param \DOMElement $formatElement
     *
     * @return digitalResource/format format object
     */
    protected function parseFormatElement($formatElement)
    {
        $format = \laabs::newInstance("digitalResource/format");
        $format->puid = $formatElement->getAttribute('PUID');
        $format->name = $formatElement->getAttribute('Name');

        if ($formatElement->hasAttribute('Version')) {
            $format->version = $formatElement->getAttribute('Version');
        }

        if ($formatElement->hasAttribute('MIMEType')) {
            $format->mimetypes = \laabs\explode(', ', $formatElement->getAttribute('MIMEType'));
        }

        $mediatypes = array('application', 'message', 'audio', 'video', 'text', 'multipart', 'model', 'image');
        foreach ((array) $format->mimetypes as $mimetype) {
            if (strpos($mimetype, "/") && (in_array($mediatype = strtok($mimetype, "/"), $mediatypes))) {
                $format->mediatype = $mediatype;
                break;
            }
        }

        foreach ($formatElement->getElementsByTagName('Extension') as $extensionElement) {
            $format->extensions[] = $extensionElement->nodeValue;
        }

        return $format;
    }

    protected function getReference()
    {
        $this->domDocument = new \DOMDocument();
        $this->domDocument->load($this->droidSignatureFile);
        $this->domXPath = \laabs::newService("dependency/xml/XPath", $this->domDocument);
        $this->domXPath->registerNamespace('psf', 'http://www.nationalarchives.gov.uk/pronom/SignatureFile');
    }
}
