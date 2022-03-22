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
 * digitalResource content type html serializer
 *
 * @package DigitalResource
 * @author  Maarch Alexis ragot <alexis.ragot@maarch.org>
 */
class contentType
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
    public function __construct(\dependency\html\Document $view, \dependency\json\JsonObject $json, \dependency\localisation\TranslatorInterface $translator)
    {
        $this->view = $view;
        $this->json = $json;
        $this->translator = $translator;
        $this->translator->setCatalog('digitalResource/contentType');
        $this->json->status = true;
    }

    /**
     * Get all content types
     * @param digitalResource/contentType[] $contentTypes Array of digitalResource/contentType objects
     *
     * @return string
     */
    public function index($contentTypes)
    {
        $this->view->addContentFile("digitalResource/contentType/index.html");
        $this->view->setSource("contentTypes", $contentTypes);

        $dataTable = $this->view->getElementsByClass("dataTable")->item(0)->plugin['dataTable'];
        $dataTable->setUnsortableColumns(3);
        $dataTable->setUnsearchableColumns(3);
        $dataTable->setPaginationType("full_numbers");

        $this->view->translate();
        $this->view->merge();

        return $this->view->saveHtml();
    }

    /**
     * Edit or create a content type
     * @param digitalResource/contentType $contentType The digitalResource/contentType object
     *
     * @return string
     */
    public function edit($contentType = null)
    {
        if ($contentType != null) {
            $contentType->puids = (array) $contentType->puids;
            $pronomFormatController = \laabs::newController('digitalResource/pronomFormat');

            foreach ($contentType->puids as $key => $puid) {
                $contentType->puids[$key] = $pronomFormatController->get($puid);
            }
        }

        $this->view->addContentFile("digitalResource/contentType/edit.html");
        $this->view->translate();
        $this->view->setSource("contentType", $contentType);
        $this->view->merge();

        return $this->view->saveHtml();
    }

    //JSON
    /**
     * Serializer JSON for Get method
     * @param digitalResource/contentType $contentType The digitalResource/contentType object
     *
     * @return object JSON object
     */
    public function get($contentType)
    {
        return json_encode($contentType);
    }

    /**
     * Serializer JSON for Create method
     * @param digitalResource/contentType $contentType The digitalResource/contentType object
     *
     * @return object JSON object
     */
    public function create($contentType)
    {
        $this->json->message = "Content type created";
        $this->json->message = $this->translator->getText($this->json->message);

        return $this->json->save();
    }

    /**
     * Serializer JSON for Update method
     * @param digitalResource/contentType $contentType The digitalResource/contentType object
     *
     * @return object JSON object
     */
    public function update($contentType)
    {
        $this->json->message = "Content type updated";
        $this->json->message = $this->translator->getText($this->json->message);

        return $this->json->save();
    }
}
