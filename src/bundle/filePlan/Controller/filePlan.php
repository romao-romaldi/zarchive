<?php
/*
 * Copyright (C) 2015 Maarch
 *
 * This file is part of bundle filePlan.
 *
 * Bundle filePlan is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Bundle filePlan is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with bundle filePlan.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace bundle\filePlan\Controller;

/**
 * Controler of the file plan
 *
 * @package FilePlan
 * @author  Prosper DE LAURE (maarch) <prosper.delaure@maarch.org> 
 */
class filePlan
{

    protected $sdoFactory;

    /**
     * Constructor
     * @param object $sdoFactory The model for file plan
     */
    public function __construct(\dependency\sdo\Factory $sdoFactory)
    {
        $this->sdoFactory = $sdoFactory;
    }

    /**
     * Get the file plan's tree
     *
     * @return array The list of file plan folder with their position
     */
    public function getTree()
    {
        // Get user org tree
        $tree = \laabs::callService("organization/userPosition/readGetcurrentorgtree");
        if (!$tree) {
            return;
        }

        $orgRegNumbers = $this->getOrgIdsFromTree($tree);

        $folders = $this->sdoFactory->find('filePlan/folder', "ownerOrgRegNumber=['".\laabs\implode("', '", $orgRegNumbers)."']");

        $folderTree = \laabs::buildTree($folders, 'filePlan/folder');
        $folderTreeByOwner = [];

        foreach ($folderTree as $folder) {
            if (!isset($folderTreeByOwner[$folder->ownerOrgRegNumber])) {
                $folderTreeByOwner[$folder->ownerOrgRegNumber] = [];
            }
            $folderTreeByOwner[$folder->ownerOrgRegNumber][] = $folder;
        }

        $this->mergeFoldersToTree($tree, $folderTreeByOwner);

        return $tree;
    }

    /**
     * Create a folder
     * @param string  $name
     * @param string  $ownerOrgRegNumber
     * @param string  $parentFolderId
     * @param string  $description
     * @param boolean $disabled
     * 
     * @return boolean
     */
    public function add($name, $ownerOrgRegNumber, $parentFolderId=null, $description=null, $disabled=false)
    {
        // Validate :
        // OwnerOrgRegNumber exists
        // ParentFolderId exists if sent
        // Couple parentFolderId + name is unique

        $folder = \laabs::newInstance('filePlan/folder');
        $folder->folderId = \laabs::newId();
        $folder->name = $name;
        $folder->ownerOrgRegNumber = $ownerOrgRegNumber;
        $folder->parentFolderId = $parentFolderId;
        $folder->description = $description;
        $folder->disabled = $disabled;

        $this->sdoFactory->create($folder);

        return true;
    }

    /**
     * Move a folder on a new position
     * @param string $folderId
     * @param string $parentFolderId
     * 
     * @return boolean
     */
    public function move($folderId, $parentFolderId=null)
    {
        $folder = $this->sdoFactory->read('filePlan/folder', $folderId);

        // Check 
        if ($parentFolderId) {
            $parentFolder = $this->sdoFactory->read('filePlan/folder', $parentFolderId);
            if ($parentFolder->ownerOrgRegNumber != $folder->ownerOrgRegNumber) {
                throw \Exception("Can't move to another service !");
            }
        }

        $folder->parentFolderId = $parentFolderId;

        $this->sdoFactory->update($folder);

        return true;
    }

    /**
     * Update a folder
     * @param string  $folderId
     * @param string  $name
     * @param string  $description
     * @param boolean $disabled
     * 
     * @return boolean
     */
    public function update($folderId, $name, $description=null, $disabled=false)
    {
        $folder = $this->sdoFactory->read('filePlan/folder', $folderId);

        // Validate :
        // Couple parentFolderId + new name is unique

        $folder->name = $name;
        $folder->description = $description;
        $folder->disabled = $disabled;

        $this->sdoFactory->update($folder);

        return true;
    }

    /**
     * Delete a folder
     * @param string $folderId
     * 
     * @return boolean
     */
    public function delete($folderId)
    {
        $folder = $this->sdoFactory->read('filePlan/folder', $folderId);

        $positionController = \laabs::newController('filePlan/position');
        $archivesCount = $positionController->count($folder->ownerOrgRegNumber, $folderId);

        if ($archivesCount > 0) {
            return false;
        }

        $this->sdoFactory->delete('filePlan/folder', $folderId);

        return true;
    }

    /**
     * Get organization identifier from tree
     * @param object $tree   The organization tree
     * 
     * @return array the organization identifier list
     */
    protected function getOrgIdsFromTree($tree)
    {
        $orgRegNumbers = [];
        $orgRegNumbers[] = $tree->registrationNumber;

        if (isset($tree->organization)) {
            foreach ($tree->organization as $organization) {
                $orgRegNumbers = array_merge($orgRegNumbers, $this->getOrgIdsFromTree($organization));
            }
        }

        return $orgRegNumbers;

    }

    /**
     * Merge folder tree into organization tree
     * @param object $tree       The organization tree
     * @param object $folderTree The folder tree sorted by parentId
     * 
     * @return object the tree with folders
     */
    protected function mergeFoldersToTree($tree, $folderTree)
    {
        if (isset($folderTree[$tree->registrationNumber])) {
            $tree->folder = $folderTree[$tree->registrationNumber];
        }

        if (isset($tree->organization)) {
            foreach ($tree->organization as $organization) {
                $this->mergeFoldersToTree($organization, $folderTree);
            }
        }
    }   

}
