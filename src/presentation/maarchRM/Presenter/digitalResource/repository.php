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
 * Serializer html adminRepository
 *
 * @package DigitalResource
 * @author  Alexis Ragot <alexis.ragot@maarch.com>
 */
class repository
{
    use \presentation\maarchRM\Presenter\exceptions\exceptionTrait;

    public $view;

    protected $json;

    protected $translator;

    /**
     * Constuctor of repository html serializer
     * @param \dependency\html\Document                    $view       The view
     * @param \dependency\json\JsonObject                  $json       The JSON object
     * @param \dependency\localisation\TranslatorInterface $translator The translator object
     */
    public function __construct(
        \dependency\html\Document $view,
        \dependency\json\JsonObject $json,
        \dependency\localisation\TranslatorInterface $translator
    ) {
        $this->view = $view;

        $this->json = $json;
        $this->json->status = true;

        $this->translator = $translator;
        $this->translator->setCatalog('digitalResource/repository');
    }

    /**
     * Get repositories
     * @param array $repositories Array of repositories
     *
     * @return string
     */
    public function index(array $repositories)
    {
        //$this->view->addHeaders();
       //$this->view->useLayout();
        $this->view->addContentFile('digitalResource/repository/index.html');

        $dataTable = $this->view->getElementsByClass("dataTable")->item(0)->plugin['dataTable'];

        $dataTable->setPaginationType("full_numbers");
        $dataTable->setUnsortableColumns(3);
        $dataTable->setUnsortableColumns(4);

        $this->view->setSource("repositories", $repositories);
        $this->view->merge();

        $this->view->translate();

        return $this->view->saveHtml();
    }

    /**
     * The view to create or edit a repository
     * @param object $repository The repository object
     *
     * @return string
     */
    public function edit($repository = null)
    {
        $this->view->addContentFile('digitalResource/repository/edit.html');

        $repositoryTypes = \laabs::dependency("repository")->getAdapters('Repository');
        $repositoryTypeSelector = $this->view->getElementById("repositoryType");
        $this->view->setSource("repositoryTypes", $repositoryTypes);
        $this->view->merge($repositoryTypeSelector);

        $this->view->setSource("repository", $repository);
        $this->view->merge();

        $this->view->translate();

        return $this->view->saveHtml();
    }

    /**
     * The view to create or edit a repository
     * @param array $addresses The address list
     *
     * @return string
     */
    public function flawedAddresses($addresses)
    {
        $this->view->addContentFile('digitalResource/repository/flawedAddressList.html');

        foreach ($addresses as $address) {
            $address->json = json_encode($address);
        }

        $dataTable = $this->view->getElementsByClass("dataTable")->item(0)->plugin['dataTable'];
        $dataTable->setPaginationType("full_numbers");
        $dataTable->setUnsortableColumns(3);
        $dataTable->setSorting(array(array(1, 'desc')));

        $this->view->setSource("addresses", $addresses);
        $this->view->merge();

        $this->view->translate();
        return $this->view->saveHtml();
    }

    //JSON
    /**
     * Serializer JSON for create method
     *
     * @return object JSON object with a status and message parameters
     */
    public function create()
    {
        $this->json->message = "Repository created";
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
        $this->json->message = "Repository updated";
        $this->json->message = $this->translator->getText($this->json->message);

        return $this->json->save();
    }

    /**
     * Serializer JSON for checkRepositoryIntegrity method
     *
     * @return object JSON object with a status and message parameters
     */
    public function checkRepositoryIntegrity($integrityResult)
    {
        foreach ($integrityResult as $key => $value) {
            $key = $this->translator->getText($key);
        }

        $this->json->message = $integrityResult;

        return $this->json->save();
    }

    /**
     * Serializer JSON for checkAddressIntegrity method
     *
     * @return object JSON object with a status and message parameters
     */
    public function checkAddressIntegrity($integrityResult)
    {
        if ($integrityResult) {
            $this->json->message = $this->translator->getText('No error found on the address.');
        } else {
            $this->json->status = false;
            $this->json->message = $this->translator->getText('The address is still flawed.');
        } 


        return $this->json->save();
    }

    /**
     * Exception
     * @param digitalResource/Exception/repositoryException $repositoryException
     *
     * @return string
     */
    public function repositoryException($repositoryException)
    {
        $this->json->message = $this->translator->getText($repositoryException->getMessage());
        $this->json->status = false;

        return $this->json->save();
    }
}
