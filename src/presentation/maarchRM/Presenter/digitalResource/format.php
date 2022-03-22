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
namespace Presentation\maarchRM\Presenter\digitalResource;

/**
 * digitalResource format html serializer
 *
 * @package DigitalResource
 * @author  Maarch Alexis ragot <alexis.ragot@maarch.org>
 */
class format
{
    use \presentation\maarchRM\Presenter\exceptions\exceptionTrait;

    public $view;
    
    protected $json;
    protected $translator;

    /**
     * Constuctor of registered mail html serializer
     * @param \dependency\html\Document                    $view       The view
     * @param \dependency\json\JsonObject                  $json       The JSON object
     * @param \dependency\localisation\TranslatorInterface $translator The translator object
     */
    public function __construct(
            \dependency\html\Document $view,
            \dependency\json\JsonObject $json,
            \dependency\localisation\TranslatorInterface $translator)
    {
        $this->view = $view;
        
        $this->json = $json;
        $this->json->status = true;
        $this->translator = $translator;
        $this->translator->setCatalog('digitalResource/conversionRule');
    }

    /**
     * Get all formats
     * @param digitalResource/format $formats Array of digitalResource/format objects
     * 
     * @return string
     */
    public function index($formats)
    {
        $this->view->addContentFile("digitalResource/format/index.html");
        $description =  \laabs::newController("digitalResource/pronomFormat")->formatDescription();
        $this->view->setSource("description", $description);
        $this->view->setSource("formats", $formats);
        $dataTable = $this->view->getElementsByClass("dataTable")->item(0)->plugin['dataTable'];
        $dataTable->setPaginationType("full_numbers");

        $this->view->translate();
        $this->view->merge();

        return $this->view->saveHtml();
    }
    
    //JSON
    /**
     * Serializer JSON for find method
     * 
     * @return object JSON object
     */
    public function find($format)
    {
        return json_encode($format);
    }

    /**
     * Serializer JSON for fileFormatInformation method
     * 
     * @return object JSON object
     */
    public function fileFormatInformation($fileInformation)
    {
        $fileInformation->status = true;

        return json_encode($fileInformation);
    }
}
