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
 * Serializer html conversion rule
 *
 * @package DigitalResource
 * @author  Alexis Ragot <alexis.ragot@maarch.com>
 */
class conversionRule
{
    use \presentation\maarchRM\Presenter\exceptions\exceptionTrait;

    public $view;

    protected $json;

    protected $translator;

    /**
     * Constuctor of cluster html serializer
     * @param \dependency\html\Document                    $view       The view
     * @param \dependency\json\JsonObject                  $json       The JSOn object
     * @param \dependency\localisation\TranslatorInterface $translator The translator
     */
    public function __construct(\dependency\html\Document $view, \dependency\json\JsonObject $json, \dependency\localisation\TranslatorInterface $translator)
    {
        $this->view = $view;

        $this->json = $json;
        $this->json->status = true;

        $this->translator = $translator;
        $this->translator->setCatalog('digitalResource/conversionRule');
    }

    /**
     * Get conversion rules
     * @param array $conversionRules Array of digitalResource/conversionRule objects
     *
     * @return string
     */
    public function index(array $conversionRules)
    {
        $conversionServices = \laabs::configuration("dependency.fileSystem")["conversionServices"];
        $this->view->addContentFile('digitalResource/conversionRule/index.html');

        foreach ( $conversionRules as $conversionRule ) {
            $conversionRule->puidName = \laabs::callService('digitalResource/pronomFormat/readGet', $conversionRule->puid)->name;
            $conversionRule->targetPuidName = \laabs::callService('digitalResource/pronomFormat/readGet', $conversionRule->targetPuid)->name;

            foreach ($conversionServices as $conversionService) {
                if ($conversionService['serviceName'] == $conversionRule->conversionService) {
                    $conversionRule->conversionServiceName = $conversionService['softwareName'];
                }
            }
        }

        $dataTable = $this->view->getElementsByClass("dataTable")->item(0)->plugin['dataTable'];
        $dataTable->setPaginationType("full_numbers");
        $dataTable->setUnsortableColumns(3);

        $this->view->translate();

        $this->view->setSource("conversionRules", $conversionRules);
        $this->view->merge();

        return $this->view->saveHtml();
    }

    /**
     * Edit a conversion rule
     *
     * @return string
     */
    public function edit()
    {
        $conversionServices = \laabs::configuration("dependency.fileSystem")["conversionServices"];
        $inputFormats = [];

        for ($i = 0, $count = count($conversionServices); $i < $count; $i++) {
            $formats = [];
            foreach ($conversionServices[$i]["inputFormats"] as $inputformat) {
                $formats['input'][] = \laabs::callService('digitalResource/pronomFormat/readGet', $inputformat);
            }
            $inputFormats = array_merge($formats['input'], $inputFormats);
            $conversionServices[$i]["inputFormats"] = $inputFormats;

            $outputFormats = array_keys($conversionServices[$i]["outputFormats"]);
            foreach ($outputFormats as $outputFormat) {
                $formats['output'][$outputFormat] = \laabs::callService('digitalResource/pronomFormat/readGet', $outputFormat);
            }

            $conversionServices[$i]["outputFormats"] = $formats['output'];
        }

        $conversionServicesEncoded = json_encode($conversionServices);
        $this->view->addContentFile('digitalResource/conversionRule/edit.html');

        $this->view->translate();

        $this->view->setSource("inputFormats", $inputFormats);
        $this->view->setSource("conversionServicesEncoded", $conversionServicesEncoded);
        $this->view->merge();

        return $this->view->saveHtml();
    }

    // JSON
    /**
     * Serializer JSON for create method
     *
     * @return object JSON object with a status and message parameters
     */
    public function create()
    {
        $this->json->message = "Conversion rule created";
        $this->json->message = $this->translator->getText($this->json->message);

        return $this->json->save();
    }

    /**
     * Serializer JSON for update method
     *
     * @return object JSON object with a status and message parameters
     */
    public function update()
    {
        $this->json->message = "Conversion rule updated";
        $this->json->message = $this->translator->getText($this->json->message);

        return $this->json->save();
    }

    /**
     * Serializer JSON for update method
     *
     * @return object JSON object with a status and message parameters
     */
    public function delete()
    {
        $this->json->message = "Conversion rule deleted";
        $this->json->message = $this->translator->getText($this->json->message);

        return $this->json->save();
    }
}
