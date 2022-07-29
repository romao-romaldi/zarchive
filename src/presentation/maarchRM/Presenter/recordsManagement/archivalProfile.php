<?php
/*
 * Copyright (C) 2015 Maarch
 *
 * This file is part of bundle recordsManagement.
 *
 * Bundle recordsManagement is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Bundle recordsManagement is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with bundle recordsManagement.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace presentation\maarchRM\Presenter\recordsManagement;

/**
 * Serializer html adminArchivalProfile
 *
 * @package RecordsManagement
 * @author  Alexis Ragot <alexis.ragot@maarch.com>
 */
class archivalProfile
{
    use \presentation\maarchRM\Presenter\exceptions\exceptionTrait;

    public $view;

    protected $json;

    protected $translator;

    /**
     * Constuctor of archival profile html serializer
     * @param \dependency\html\Document                    $view
     * @param \dependency\json\JsonObject                  $json
     * @param \dependency\localisation\TranslatorInterface $translator
     */
    public function __construct(\dependency\html\Document $view, \dependency\json\JsonObject $json, \dependency\localisation\TranslatorInterface $translator)
    {
        $this->view = $view;

        $this->json = $json;
        $this->json->status = true;

        $this->translator = $translator;
        $this->translator->setCatalog('recordsManagement/archivalProfile');
    }

    /**
     * Get archival profiles
     *
     * @return string
     */
    public function index()
    {
        $archivalProfiles = \laabs::callService('recordsManagement/archivalProfile/readIndex');

        $this->view->addContentFile('recordsManagement/archivalProfile/index.html');

        $dataTable = $this->view->getElementsByClass("dataTable")->item(0)->plugin['dataTable'];
        $dataTable->setPaginationType("full_numbers");
        $dataTable->setUnsortableColumns(3);

        $this->view->translate();
        $this->view->setSource("profile", $archivalProfiles);
        $this->view->merge();

        return $this->view->saveHtml();
    }

    /**
     * Get archival profiles list
     *
     * @return string
     */
    public function archivalProfileList($archivalProfiles)
    {
        return json_encode($archivalProfiles, JSON_UNESCAPED_UNICODE);
    }

    /**
     * The view to create or edit a archival profile
     * @param string $archivalProfile The archival profile identifier
     *
     * @return string
     */
    public function edit($archivalProfile = null)
    {
        $this->view->addContentFile('recordsManagement/archivalProfile/edit.html');

        $profilesDirectory = \laabs::configuration('recordsManagement')['profilesDirectory'];
        $profileList = \laabs::callService('recordsManagement/archivalProfile/readIndex');

        foreach ($profileList as $key => $profile) {
            if (!empty($profile->containedProfiles)) {
                foreach ($profile->containedProfiles as $containedProfile) {
                    if ($containedProfile->archivalProfileId == $archivalProfile->archivalProfileId) {
                        unset($profileList[$key]);
                    }
                }
            }
            if ($profile->archivalProfileId == $archivalProfile->archivalProfileId) {
                unset($profileList[$key]);
            } 
        }

        $archivalProfile->containedProfiles = json_encode($archivalProfile->containedProfiles);

        if ($archivalProfile) {
            $this->getProfileType($archivalProfile);

            // Description by class
            $descriptionClasses = $this->getDescriptionClasses();

            // Description by fulltext index fields
            $descriptionFields = \laabs::callService('recordsManagement/descriptionScheme/read_name_Descriptionfields', $archivalProfile->descriptionClass);
            $dateFields = [];
            foreach ($descriptionFields as $descriptionField) {
                if (strtolower($descriptionField->type) == 'date') {
                    $dateFields[] = $descriptionField;
                }
            }

            if (is_file($profilesDirectory.DIRECTORY_SEPARATOR.$archivalProfile->reference.".rng")) {
                $filename = $profilesDirectory.DIRECTORY_SEPARATOR.$archivalProfile->reference.".rng";
                $this->view->setSource("profileFileName", $archivalProfile->reference.".rng");
                $this->view->setSource("profileFileFormat", "Relax NG (Regular Language for XML Next Generation)");
            }

            if (is_file($profilesDirectory.DIRECTORY_SEPARATOR.$archivalProfile->reference.".xsd")) {
                $filename = $profilesDirectory.DIRECTORY_SEPARATOR.$archivalProfile->reference.".xsd";
                $this->view->setSource("profileFileName", $archivalProfile->reference.".xsd");
                $this->view->setSource("profileFileFormat", "XML Schema Definition");
            }

            if (isset($filename)) {
                $this->view->setSource("profileFileLastModified", \laabs::newDatetime(date("Y-m-d H:i:s", filemtime($filename))));
            }

            $this->view->setSource("dateFields", $dateFields);
            $this->view->setSource("descriptionFields", $descriptionFields);
            $this->view->setSource("profileList", json_encode($profileList));
            $this->view->setSource("descriptionClasses", $descriptionClasses);
            $this->view->setSource("archivalProfile", $archivalProfile);
        }

        $this->view->translate();

        //access code selector
        $accessRuleController = \laabs::newController('recordsManagement/accessRule');
        $accessRules = $accessRuleController->index();

        foreach ($accessRules as $accessRule) {
            $completeAccessRule = $accessRuleController->edit($accessRule->code);
            $accessRule->description = $completeAccessRule->description;

            $accessRule->json = json_encode($completeAccessRule);
            if ($accessRule->duration != null) {
                $accessRule->accessRuleDurationUnit = substr($accessRule->duration, -1);
                $accessRule->accessRuleDuration = substr($accessRule->duration, 1, -1);
            }
        }

        $this->view->setSource("accessRules", $accessRules);

        $retentionRuleController = \laabs::newController('recordsManagement/retentionRule');
        $retentionRules = $retentionRuleController->index();

        foreach ($retentionRules as $retentionRule) {
            if ($retentionRule->duration != null) {
                $retentionRule->retentionDurationUnit = substr($retentionRule->duration, -1);
                $retentionRule->retentionDuration = substr($retentionRule->duration, 1, -1);
            }
        }

        $this->view->setSource("retentionRules", $retentionRules);
        $retentionRuleSelector = $this->view->getElementById("code");
        $this->view->merge($retentionRuleSelector);

        $this->view->setSource("profilesDirectory", $profilesDirectory);

        $this->view->merge();

        return $this->view->saveHtml();
    }

    protected function getDescriptionClasses()
    {
        $descriptionSchemes = \laabs::callService('recordsManagement/descriptionScheme/readIndex');
        $descriptionClasses = [];
        foreach ($descriptionSchemes as $name => $descriptionScheme) {
            $descriptionClasses[] = $descriptionClass = new \stdClass();
            $descriptionClass->label = $descriptionScheme->label;
            $descriptionClass->name = $name;

            $properties = \laabs::callService('recordsManagement/descriptionScheme/read_name_Descriptionfields', $name);
            $dateProperties = [];
            foreach ($properties as $name => $descriptionField) {
                // Internal fields are not shown, it should only be manages by business rules
                if (isset($descriptionField->internal)) {
                    unset($properties[$name]);
                }
                if ($descriptionField->type == 'date') {
                    array_push($dateProperties, $descriptionField);
                }
            }
            // sort alphabetically properties of description classes
            usort($properties, function ($a, $b) {
                return $a->label <=> $b->label;
            });

            $descriptionClass->properties = json_encode($properties);
            $descriptionClass->dateProperties = json_encode($dateProperties);
        }

        return $descriptionClasses;
    }

    /**
     * Get The profile type from the configuration
     * @param recordsManagement/archivalProfile $archivalProfile The archival profile
     */
    protected function getProfileType($archivalProfile)
    {
        $conf = \laabs::configuration('recordsManagement');

        if (isset($conf['archivalProfileType'])) {
            $archivalProfile->type = $conf['archivalProfileType'];
        } else {
            $archivalProfile->type = 1;
        }

        if ($archivalProfile->type != 2) {
            $profileFileTab = $this->view->createDocumentFragment();
            $profileFileTab->appendHtmlFile("recordsManagement/archivalProfile/profileUploadTab.html");

            $this->view->getElementById('archivalProfileNavControl')->appendChild($profileFileTab);

            $profileFileForm = $this->view->createDocumentFragment();
            $profileFileForm->appendHtmlFile("recordsManagement/archivalProfile/profileUpload.html");

            $this->view->getElementById('archivalProfileNavTabs')->appendChild($profileFileForm);
        }
    }

    /**
     * Get form of teh description class
     * @param object $descriptionObject The description class object parsed with the profile descriptions
     *
     * @return string the view with the description class form
     */
    public function descriptionForm($descriptionObject)
    {
        $serializer = \laabs::newSerializer($descriptionObject->descrpitionClass, "html");
        $this->view->addContent($serializer->form($descriptionObject));

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

        $this->json->message = "Archival profile created";
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
        $this->json->message = "Archival profile updated";
        $this->json->message = $this->translator->getText($this->json->message);

        return $this->json->save();
    }

    /**
     * Serializer JSON for delete method
     * @param string $result
     *
     * @return object JSON object with a status and message parameters
     */
    public function delete($result)
    {
        if ($result == true) {
            $this->json->message = "Archival profile deleted";
            $this->json->message = $this->translator->getText($this->json->message);
        } else {
            $this->json->message = "The profile can't be deleted";
            $this->json->status = false;
            $this->json->message = $this->translator->getText($this->json->message);
        }

        return $this->json->save();
    }

    /**
     * Serializer JSON for queryGroups method
     * @param array $groups An array of groups matching the user query
     *
     * @return string
     **/
    public function queryGroups($groups)
    {
        return json_encode($groups);
    }

    /**
     * Serializer JSON for getByReference method
     * @param recordsManagement/archivalProfile $archivalProfile Archival profile object
     *
     * @return string
     **/
    public function getByReference($archivalProfile)
    {
        $this->json->archivalProfile = $archivalProfile;

        return $this->json->save();
    }

    /**
     * Get archival profiles
     * @param string $barcode The data of codes
     *
     * @return string
     */
    public function barcode($barcode)
    {
        \laabs::setResponseType('application/pdf');
        $response = \laabs::kernel()->response;
        $response->setHeader("Content-Disposition", "inline;");

        return $barcode;
    }

    /**
     * Serializer JSON for uploadArchivalProfile method
     *
     * @return string
     **/
    public function uploadArchivalProfile()
    {
        $this->json->message = "Archival profile uploaded";
        $this->json->message = $this->translator->getText($this->json->message);

        return $this->json->save();
    }

    /**
     * Export method
     * @param resource $file The file
     *
     * @return resource
     */
    public function export($file)
    {
        \laabs::setResponseType("text/xml");
        $response = \laabs::kernel()->response;
        $response->setHeader('Content-Disposition', 'attachment; filename="'.func_get_args()[1] . '.rng"');

        return $file;
    }
}
