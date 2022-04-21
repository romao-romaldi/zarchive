<?php

/*
 * Copyright (C) 2015 Maarch
 *
 * This file is part of bundle auth.
 *
 * Bundle auth is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Bundle auth is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with bundle auth.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace bundle\auth\Controller;

/**
 * userAccount  controller
 *
 * @package Auth
 * @author  Alexandre Morin <alexandre.morin@maarch.org>
 */
class userAccount
{
    use ForgotAccountTrait;

    protected $sdoFactory;
    protected $csv;
    protected $passwordEncryption;
    protected $securityPolicy;
    protected $adminUsers;
    protected $currentAccount;
    protected $accountPrivileges;
    protected $hasSecurityLevel;

    /**
     * Constructor
     * @param \dependency\sdo\Factory $sdoFactory         The dependency Sdo Factory object
     * @param \dependency\csv\Csv     $csv                The dependency csv
     * @param string                  $passwordEncryption The password encryption algorythm
     * @param array                   $securityPolicy     The array of security policy parameters
     */
    public function __construct(\dependency\sdo\Factory $sdoFactory = null, \dependency\csv\Csv $csv = null, $passwordEncryption = 'md5', $securityPolicy = [], $adminUsers = [])
    {
        $this->csv = $csv;
        $this->sdoFactory = $sdoFactory;
        $this->passwordEncryption = $passwordEncryption;
        $this->securityPolicy = $securityPolicy;
        $this->adminUsers = \laabs::configuration('auth')['adminUsers'];
        $this->hasSecurityLevel = isset(\laabs::configuration('auth')['useSecurityLevel']) ? (bool) \laabs::configuration('auth')['useSecurityLevel'] : false;
    }

    /**
     * List all users to display
     *
     * @param integer $limit Max limit of info to return
     * @param string  $query sdo query
     *
     * @return array The array of stdClass with dislpay name and user identifier
     */
    public function index($limit = null, $query = null)
    {
        if ($query) {
            $query .= "AND accountType='user'";
        } else {
            $query .= "accountType='user'";
        }

        $userAccounts = $this->sdoFactory->find('auth/account', $query, null, null, null, $limit);
        $userAccounts = \laabs::castMessageCollection($userAccounts, 'auth/userAccountIndex');

        return $userAccounts;
    }

    /**
     * List all users to display
     * @param string $query
     *
     * @return array The array of stdClass
     */
    public function userList($query = null)
    {
        $organizationController = \laabs::newController('organization/organization');
        $accountId = \laabs::getToken("AUTH")->accountId;

        $queryAssert = [];
        $queryAssert[] = "accountType='user'";

        if ($query) {
            $queryAssert[] = "$query";
        }

        $account = $this->sdoFactory->read("auth/account", array("accountId" => $accountId));

        if (!empty($this->adminUsers) && !in_array($account->accountName, $this->adminUsers)) {
            $queryAssert[] = "accountId!=['".\laabs\implode("','", $this->adminUsers)."']";
        }

        if ($this->hasSecurityLevel) {
            switch ($account->getSecurityLevel()) {
                case $account::SECLEVEL_GENADMIN:
                    $queryAssert[] = "(isAdmin=TRUE AND ownerOrgId!=null)";
                    break;

                case $account::SECLEVEL_FUNCADMIN:
                    $organization = $this->sdoFactory->read('organization/organization', $account->ownerOrgId);
                    $organizations = $organizationController->readDescendantOrg($organization->orgId);
                    $organizations[] = $organization;
                    $organizationsIds = [];
                    foreach ($organizations as $key => $organization) {
                        $organizationsIds[] = (string) $organization->orgId;
                    }

                    $queryAssert[] = "((ownerOrgId= ['" .
                        implode("', '", $organizationsIds) .
                        "']) OR (isAdmin!=TRUE AND ownerOrgId=null))
                        ";
                    break;

                case $account::SECLEVEL_USER:
                    $queryAssert[] = "((isAdmin!=TRUE AND ownerOrgId='". $account->ownerOrgId."')";
                    break;
            }
        }
        $userAccounts = $this->sdoFactory->find('auth/account', \laabs\implode(" AND ", $queryAssert));

        return $this->removeSensibleData($userAccounts);
    }

    /**
     * Remove sensible data from an array of users
     *
     * @param  array $userAccounts Array of user Accounts
     *
     * @return array               Array of userAccounts removed of sensible data
     */
    protected function removeSensibleData($userAccounts)
    {
        foreach ($userAccounts as $key => $user) {
            unset($userAccounts[$key]->password);
            unset($userAccounts[$key]->replacingUserAccountId);
            unset($userAccounts[$key]->salt);
            unset($userAccounts[$key]->tokenDate);
            unset($userAccounts[$key]->lastIp);
        }

        return $userAccounts;
    }

    /**
     * List all users to display
     * @param string $query
     *
     * @return array The array of stdClass with dislpay name and user identifier
     */
    public function search($query = null)
    {
        if ($query) {
            $query .= "AND accountType='user'";
        } else {
            $query .= "accountType='user'";
        }
        $userAccounts = $this->sdoFactory->find('auth/account', $query);

        return $userAccounts;
    }

    /**
     * Prepare an empty user object
     *
     * @return auth/account The user object
     */
    public function newUser()
    {
        return \laabs::newInstance('auth/account');
    }

    /**
     * Record a new user & role members
     * @param auth/account $userAccount The user object
     *
     * @return string The user identifier
     */
    public function add($userAccount)
    {
        $userAccountId = $this->addUserAccount($userAccount);

        if (is_array($userAccount->roles) && !empty($userAccount->roles[0])) {
            foreach ($userAccount->roles as $roleId) {
                $roleMemberController = \laabs::newController('auth/roleMember');
                $roleMemberController->create($roleId, $userAccountId);
            }
        }

        return $userAccountId;
    }

    /**
     * Record a new user account
     * @param auth/account $userAccount The user object
     *
     * @throws \bundle\auth\Exception\userAlreadyExistException
     * @throws \bundle\auth\Exception\invalidUserInformationException
     *
     * @return string The user identifier
     */
    public function addUserAccount($userAccount)
    {
        $this->isAuthorized(['gen_admin', 'func_admin']);

        $accountToken = \laabs::getToken('AUTH');
        $account = $this->sdoFactory->read("auth/account", $accountToken->accountId);

        $organizations = $userAccount->organizations;
        $userAccount = \laabs::cast($userAccount, "auth/account");
        $userAccount->accountId = \laabs::newId();
        $userAccount->accountType = 'user';

        if (!is_null($userAccount->emailAddress) && !filter_var($userAccount->emailAddress, FILTER_VALIDATE_EMAIL)) {
            $validationErrors = ["Email adress isn't valid"];
            throw \laabs::newException("auth/invalidUserInformationException", $validationErrors);
        }

        $organizationController = \laabs::newController('organization/organization');
        if (!is_null($organizations)) {
            $organization = $organizationController->read($organizations[0]);
            $userAccount->ownerOrgId = $organization->ownerOrgId;
        }

        if ($userAccount->ownerOrgId) {
            try {
                $organizationController->read($userAccount->ownerOrgId);
            } catch (\Exception $e) {
                throw new \core\Exception\UnauthorizedException($userAccount->ownerOrgId . " does not exist.");
            }
        }

        if ($this->sdoFactory->exists('auth/account', array('accountName' => $userAccount->accountName))) {
            throw \laabs::newException("auth/userAlreadyExistException", "User already exist");
        }

        if (!\laabs::validate($userAccount, 'auth/account')) {
            $validationErrors = \laabs::getValidationErrors();
            throw \laabs::newException("auth/invalidUserInformationException", $validationErrors);
        }

        $securityLevel = NULL;
        if ($this->hasSecurityLevel) {
            $securityLevel = $account->getSecurityLevel();
        }
        if ($securityLevel == $account::SECLEVEL_GENADMIN) {
            if (!$userAccount->ownerOrgId || !$userAccount->isAdmin) {
                throw new \core\Exception\UnauthorizedException("You are not allowed to do this action");
            }
        } elseif ($securityLevel == $account::SECLEVEL_FUNCADMIN) {
            
            if (!$organizations || $userAccount->isAdmin) {
                throw new \core\Exception\UnauthorizedException("You are not allowed to do this action");
            }
        }

        $encryptedPassword = $userAccount->password;
        if ($this->passwordEncryption != null) {
            $encryptedPassword = hash($this->passwordEncryption, $userAccount->password);
        }

        $userAccount->password = $encryptedPassword;
        $userAccount->passwordChangeRequired = true;
        $userAccount->passwordLastChange = \laabs::newTimestamp();

        $userAccount->badPasswordCount = 0;
        $userAccount->lastLogin = null;
        $userAccount->lastIp = null;

        $this->sdoFactory->create($userAccount, 'auth/account');

        if (!$userAccount->isAdmin) {
            foreach ($organizations as $orgId) {
                $organizationController->addUserPosition($userAccount->accountId, $orgId);
            }
        }

        return $userAccount->accountId;
    }

    /**
     * Get a user object
     * @param id $userAccountId The user unique identifier
     *
     * @return auth/account The user object
     */
    public function get($userAccountId)
    {
        return $this->sdoFactory->read('auth/account', $userAccountId);
    }

    /**
     * Prepare a user object for update
     * @param id $userAccountId The user unique identifier
     *
     * @return auth/account The user object
     */
    public function edit($userAccountId)
    {
        $userAccountModel = $this->sdoFactory->read('auth/account', $userAccountId);

        $roleMembers = $this->sdoFactory->find("auth/roleMember", "userAccountId='$userAccountId'");
        $userAccount = \laabs::castMessage($userAccountModel, 'auth/userAccount');

        $userAccount->securityLevel = null;
        if ($this->hasSecurityLevel) {
            $userAccount->securityLevel = $userAccountModel->getSecurityLevel();
        }

        if (empty($roleMembers)) {
            return $userAccount;
        }

        foreach ($roleMembers as $roleMember) {
            $roleController = \laabs::newController('auth/role');
            $role = $roleController->edit($roleMember->roleId);
            $userRole = \laabs::newMessage('auth/userRole');
            $userRole->roleId = $role->roleId;
            $userRole->roleName = $role->roleName;

            $userAccount->roles[] = $userRole;
        }

        return $userAccount;
    }

    /**
     * Prepare a user object for update profile
     *
     * @return auth/account The user object
     */
    public function editProfile()
    {
        $accountToken = \laabs::getToken('AUTH');

        return $this->edit($accountToken->accountId);
    }

    /**
     * Modify a  user & role members
     * @param string       $userAccountId The user account id
     * @param auth/account $userAccount   The user object
     */
    public function update($userAccountId, $userAccount)
    {
        $this->isAuthorized(['gen_admin', 'func_admin']);

        $accountToken = \laabs::getToken('AUTH');
        $account = $this->sdoFactory->read("auth/account", $accountToken->accountId);

        if ($this->hasSecurityLevel) {
            $this->checkPrivilegesAccess($account, $userAccount);
        }

        $organizationController = \laabs::newController('organization/organization');
        if (!is_null($userAccount->organizations)) {
            $organization = $organizationController->read($userAccount->organizations[0]);
            $userAccount->ownerOrgId = $organization->ownerOrgId;
        }

        if ($userAccount->ownerOrgId) {
            try {
                $organizationController->read($userAccount->ownerOrgId);
            } catch (\Exception $e) {
                throw new \core\Exception\UnauthorizedException($userAccount->ownerOrgId . " does not exist.");
            }
        }

        $userAccount = $this->updateUserInformation($userAccount);

        $oldUserAccount = $this->sdoFactory->read('auth/account', $userAccount->accountId);
        if ($oldUserAccount->ownerOrgId && $oldUserAccount->ownerOrgId != $userAccount->ownerOrgId ||
            !$oldUserAccount->ownerOrgId && $userAccount->ownerOrgId
        ) {
            throw new \core\Exception\UnauthorizedException("The owner org id cannot be modified");
        }

        $this->sdoFactory->deleteChildren("auth/roleMember", $userAccount, "auth/account");

        if (is_array($userAccount->roles) && !empty($userAccount->roles)) {
            foreach ($userAccount->roles as $roleId) {
                if ($roleId == null) {
                    continue;
                }

                $roleMemberController = \laabs::newController('auth/roleMember');
                $roleMemberController->create($roleId, $userAccount->accountId);
            }
        }
    }

    /**
     * Modify userAccount information
     * @param auth/accountInformation $userAccount The user object
     *
     * @throws \bundle\auth\Exception\unknownUserException
     *
     * @return boolean The result of the request
     */
    public function updateUserInformation($userAccount = null)
    {
        if (!$this->sdoFactory->exists('auth/account', array('accountId' => $userAccount->accountId))) {
            throw \laabs::newException("auth/unknownUserException");
        }

        $accountToken = \laabs::getToken('AUTH');
        $currentUserAccount = $this->sdoFactory->read("auth/account", $accountToken->accountId);

        $allowUserModification = true;
        if (isset(\laabs::configuration('auth')['allowUserModification'])) {
            $allowUserModification = (bool) \laabs::configuration('auth')['allowUserModification'];
        }

        if (!$allowUserModification) {
            $user = $this->sdoFactory->read('auth/account', $userAccount->accountId);
            $userAccount->displayName = $user->displayName;
            $userAccount->lastName = $user->lastName;
            $userAccount->firstName = $user->firstName;
            $userAccount->title = $user->title;
            $userAccount->emailAddress = $user->emailAddress;
        }

        
        if ($this->hasSecurityLevel && $currentUserAccount->getSecurityLevel() == \bundle\auth\Model\role::SECLEVEL_FUNCADMIN) {
            $this->checkPrivilegesAccess($currentUserAccount, $userAccount);
        }
        
        if (!$userAccount->isAdmin) {
            if (isset($userAccount->modificationRight)) {
                if ($userAccount->organizations == null) {
                    throw new \core\Exception\BadRequestException('No service chosen', 400);
                }

                $organizationController = \laabs::newController("organization/organization");
                $organizationController->updateUserPosition($userAccount->accountId, $userAccount->organizations);
            }
        }

        $this->sdoFactory->update($userAccount, "auth/account");

        return $userAccount;
    }

    /**
     * Modify userAccount information
     * @param auth/ownAccountUpdate $userAccount The user object
     *
     * @throws \bundle\auth\Exception\unknownUserException
     *
     * @return boolean The result of the request
     */
    public function updateOwnUserInformation($userAccount)
    {
        $this->sdoFactory->update($userAccount, "auth/account", \laabs::getToken('AUTH')->accountId);

        return $userAccount;
    }

    /**
     * get user account information
     * @param id $userAccountId The user account identifier
     *
     * @return auth/userAccount $userAccount User account information object
     */
    public function getUserAccountInformation($userAccountId)
    {
        $userAccount = $this->sdoFactory->read("auth/userAccount", $userAccountId);

        return $userAccount;
    }

    /**
     * Change a user password
     * @param string $userAccountId The identifier of the user
     * @param string $newPassword   The new password
     * @param string $oldPassword          The old password
     *
     * @return boolean The result of the request
     */
    public function setPassword($userAccountId, $newPassword, $oldPassword)
    {
        $userAccount = $this->sdoFactory->read("auth/account", $userAccountId);

        if (!password_verify($oldPassword, $userAccount->password) && hash($this->passwordEncryption, $oldPassword) != $userAccount->password) {
            throw new \core\Exception\UnauthorizedException("User password error.");
        }

        if ($oldPassword == $newPassword) {
            throw new \core\Exception\ForbiddenException("The password is the same as the precedent.", 403);
        }

        return $this->updatePassword($userAccount, $newPassword);
    }

    protected function updatePassword($userAccount, $newPassword)
    {
        $userAuthenticationController = \laabs::newController("auth/userAuthentication");
        $userAuthenticationController->checkPasswordPolicies($newPassword);

        $encryptedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $userAccount->password = $encryptedPassword;
        $userAccount->passwordLastChange = \laabs::newTimestamp();
        $userAccount->badPasswordCount = 0;
        $userAccount->passwordChangeRequired = false;

        return $this->sdoFactory->update($userAccount);
    }

    /**
     * Required password change
     * @param string $userAccountId The identifier of the user
     *
     * @return boolean The result of the request
     */
    public function requirePasswordChange($userAccountId)
    {
        $userAccount = $this->sdoFactory->read("auth/account", $userAccountId);
        $userAccount->badPassword = 0;
        $userAccount->passwordChangeRequired = true;

        return $this->sdoFactory->update($userAccount);
    }

    /**
     * Lock a user
     * @param string $userAccountId The identifier of the user
     * @param bool   $setLockDate   Set true to set the lock date
     *
     * @return boolean The result of the request
     */
    public function lock($userAccountId, $setLockDate = false)
    {
        $userAccount = $this->sdoFactory->read("auth/account", $userAccountId);
        $userAccount->locked = true;

        if ($setLockDate) {
            $userAccount->lockDate = \laabs::newTimestamp();
        }

        return $this->sdoFactory->update($userAccount);
    }

    /**
     * Unlock a user
     * @param string $userAccountId The identifier of the user
     *
     * @return boolean The result of the request
     */
    public function unlock($userAccountId)
    {
        $userAccount = $this->sdoFactory->read("auth/account", $userAccountId);
        $userAccount->locked = false;

        return $this->sdoFactory->update($userAccount);
    }

    /**
     * Enable a user
     * @param string $userAccountId The identifier of the user
     *
     * @return boolean The result of the request
     */
    public function enable($userAccountId)
    {
        $this->isAuthorized(['gen_admin', 'func_admin']);

        $userAccount = $this->sdoFactory->read("auth/account", $userAccountId);

        $accountToken = \laabs::getToken('AUTH');
        $account = $this->sdoFactory->read("auth/account", $accountToken->accountId);

        if ($this->hasSecurityLevel) {
            $this->checkPrivilegesAccess($account, $userAccount);
        }

        $userAccount->enabled = true;
        $userAccount->replacingUserAccountId = null;

        return $this->sdoFactory->update($userAccount);
    }

    /**
     * Disable a user
     * @param string $userAccountId          The identifier of the user
     *
     * @return boolean The result of the request
     */
    public function disable($userAccountId)
    {
        $this->isAuthorized(['gen_admin', 'func_admin']);

        $userAccount = $this->sdoFactory->read("auth/account", $userAccountId);
        $accountToken = \laabs::getToken('AUTH');
        $account = $this->sdoFactory->read("auth/account", $accountToken->accountId);

        if ($this->hasSecurityLevel) {
            $this->checkPrivilegesAccess($account, $userAccount);
        }

        $userAccount->enabled = false;

        return $this->sdoFactory->update($userAccount);
    }

    /**
     * Get list of user story
     * @param string $userAccountId The identifier of the user account
     *
     * @return array The user story names
     */
    public function getPrivilege($userAccountId)
    {
        $userAccountId = (string) $userAccountId;
        if (!isset($this->accountPrivileges[$userAccountId])) {
            $roleMemberController = \laabs::newController("auth/roleMember");
            $roles = $roleMemberController->readByUseraccount($userAccountId);

            $userStories = \laabs::configuration('auth')['publicUserStory'];

            foreach ($roles as $role) {
                $privileges = $this->sdoFactory->find("auth/privilege", "roleId='$role->roleId'");

                foreach ($privileges as $privilege) {
                    $userStories[] = $privilege->userStory;
                }
            }

            $this->accountPrivileges[$userAccountId] = array_unique($userStories);
        }

        return $this->accountPrivileges[$userAccountId];
    }

    /**
     * Check the user account has a privilege
     * @param string $userStory The user story name
     *
     * @return boolean True if the user has the privilege, false if he doesn't
     */
    public function hasPrivilege($userStory)
    {
        if (!\laabs::presentation()->hasUserStory($userStory)) {
            return false;
        }

        $accountToken = \laabs::getToken('AUTH');

        if (!$accountToken) {
            $userPrivileges = \laabs::configuration('auth')['publicUserStory'];
        } else {
            $userPrivileges = $this->getPrivilege($accountToken->accountId);
        }

        if (empty($userPrivileges)) {
            return false;
        }

        foreach ($userPrivileges as $userPrivilege) {
            if (fnmatch($userPrivilege, $userStory)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get user positions
     * @param string $accountId The user identifier
     *
     * @return organization/userPositionTree[] The list of user position
     */
    public function readUserPositions($accountId)
    {
        $users = $this->sdoFactory->find("organization/userPosition", "userAccountId = '$accountId'");
        $users = \laabs::castMessageCollection($users, 'organization/userPositionTree');

        foreach ($users as $user) {
            $user->displayName = $this->sdoFactory->read("organization/organization", $user->orgId)->displayName;
        }

        return $users;
    }

    /**
     * Search user account
     * @param string $query The query
     * @param string $securityLevel The security level
     *
     * @return array The list of found users
     */
    public function queryUserAccounts($query = "", $securityLevel = null)
    {
        $queryTokens = \laabs\explode(" ", $query);
        $queryTokens = array_unique($queryTokens);

        $userAccountQueryProperties = array("displayName");
        $userAccountQueryPredicats = array();
        foreach ($userAccountQueryProperties as $userAccountQueryProperty) {
            foreach ($queryTokens as $queryToken) {
                $userAccountQueryPredicats[] = $userAccountQueryProperty."="."'*".$queryToken."*'";
            }
        }
        $userAccountQueryString = implode(" OR ", $userAccountQueryPredicats);

        $organizationController = \laabs::newController('organization/organization');
        $accountId = \laabs::getToken("AUTH")->accountId;

        $queryAssert = [];
        $queryAssert[] = "accountType='user'";

        if ($query) {
            $queryAssert[] = "$userAccountQueryString";
        }

        $account = $this->sdoFactory->read("auth/account", array("accountId" => $accountId));
        $queryAssert[] = "accountId!=['".$accountId."']";

        if (!empty($this->adminUsers) && !in_array($account->accountName, $this->adminUsers)) {
            $queryAssert[] = "accountId!=['".\laabs\implode("','", $this->adminUsers)."']";
        }

        if ($securityLevel) {
            switch ($securityLevel) {
                case \bundle\auth\Model\role::SECLEVEL_GENADMIN:
                    $queryAssert[] = "(isAdmin=TRUE AND ownerOrgId=null)";
                    break;
                case \bundle\auth\Model\role::SECLEVEL_FUNCADMIN:
                    $queryAssert[] = "(isAdmin=TRUE AND ownerOrgId!=null)";
                    break;
                case \bundle\auth\Model\role::SECLEVEL_USER:
                    $organization = $this->sdoFactory->read('organization/organization', $account->ownerOrgId);
                    $organizations = $organizationController->readDescendantOrg($organization->orgId);
                    $organizations[] = $organization;
                    $organizationsIds = [];
                    foreach ($organizations as $key => $organization) {
                        $organizationsIds[] = (string) $organization->orgId;
                    }

                    $queryAssert[] = "((isAdmin!=TRUE
                AND ownerOrgId=['" .
                        implode("', '", $organizationsIds) .
                        "'])";
            }
            $userAccounts = $this->sdoFactory->find('auth/account', \laabs\implode(" AND ", $queryAssert));
        } else {
            $userAccounts = $this->userList($queryAssert[1]);
        }


        return $userAccounts;
    }

    public function isAuthorized($securitiesLevel)
    {
        if (!$this->hasSecurityLevel) {
            return true;
        }

        if (!is_array($securitiesLevel)) {
            $securitiesLevel = [$securitiesLevel];
        }

        $accountToken = \laabs::getToken('AUTH');
        $account = $this->sdoFactory->read("auth/account", $accountToken->accountId);

        if (!$account->isAdmin && !$account->ownerOrgId) {
            return true;
        }

        foreach ($securitiesLevel as $securityLevel) {
            switch ($securityLevel) {
                case $account::SECLEVEL_GENADMIN:
                    if ($account->isAdmin && !$account->ownerOrgId) {
                        return true;
                    }
                    break;
                case $account::SECLEVEL_FUNCADMIN:
                    if ($account->isAdmin && $account->ownerOrgId) {
                        return true;
                    }
                    break;
                case $account::SECLEVEL_USER:
                    if (!$account->isAdmin && $account->ownerOrgId) {
                        return true;
                    }
                    break;
            }
        }

        throw new \core\Exception\UnauthorizedException("You are not allowed to do this action");
    }

    public function exportCsv($limit = null)
    {
        $userAccounts = $this->sdoFactory->find('auth/account', "accountType='user'", null, null, null, $limit);
        $userPositionController = \laabs::newController('organization/userPosition');
        $roleMemberController = \laabs::newController('auth/roleMember');
        $organizationController = \laabs::newController('organization/organization');
        foreach ($userAccounts as $key => $userAccount) {
            $ownerOrgId = $userAccount->ownerOrgId;
            $accountId = $userAccount->accountId;
            $userAccount = \laabs::castMessage($userAccount, 'auth/userAccountImportExport');

            if ($ownerOrgId) {
                $organization = $organizationController->read($ownerOrgId);
                $userAccount->ownerOrgRegNumber = $organization->registrationNumber;
            }

            $roleMembers = $roleMemberController->readByUserAccount($accountId);
            if (!empty($roleMembers)) {
                foreach ($roleMembers as $roleMember) {
                    $userAccount->roles .= $roleMember->roleId;

                    if (end($roleMembers) !== $roleMember) {
                        $userAccount->roles .= ";";
                    }
                }
            }

            $positions = $userPositionController->listPositions($accountId);
            if (!empty($positions)) {
                foreach ($positions as $position) {
                    $organization = $organizationController->read($position->orgId);
                    $userAccount->organizations .= $organization->registrationNumber;

                    if (end($positions) !== $position) {
                        $userAccount->organizations .= ";";
                    }
                }
            }

            $userAccounts[$key] = $userAccount;
        }

        $handler = fopen('php://temp', 'w+');
        $this->csv->writeStream($handler, (array) $userAccounts, 'auth/userAccountImportExport', true);
        return $handler;
    }

    /**
     * Import User account function and create or update them
     *
     * @param resource   $data      Array of userAccountImportExport Message
     * @param boolean    $isReset   Reset tables or not
     *
     * @return boolean              Success of operation or not
     */
    public function import($data, $isReset = false)
    {

        $organizationController = \laabs::newController('organization/organization');
        $roleController = \laabs::newController('auth/role');

        $users = $this->csv->readStream($data, 'auth/userAccountImportExport', $messageType = true);

        $transactionControl = !$this->sdoFactory->inTransaction();

        if ($transactionControl) {
            $this->sdoFactory->beginTransaction();
        }

        if ($isReset) {
            try {
                $this->checkForSuperAdmin($users);
                $this->deleteAllUsers();
            } catch (\Exception $e) {
                if ($transactionControl) {
                    $this->sdoFactory->rollback();
                }
                throw $e;
            }
        }

        foreach ($users as $key => $user) {
            if ($isReset) {
                $userAccount = $this->newUser();
                $userAccount->accountId = \laabs::newId();
            } else {
                $userAccount = $this->search('accountName="' . $user->accountName . '" ')[0];
            }

            if (is_null($user->password) || empty($user->password)) {
                throw new \core\Exception\BadRequestException("Password cannot be null");
            }

            if (!$user->isAdmin
                && (
                    is_null($user->organizations)
                    || empty($user->organizations)
                )
            ) {
                throw new \core\Exception\BadRequestException("User account must be attached to at least one service");
            }

            $userAccount->accountName = $user->accountName;
            $userAccount->displayName = $user->displayName;
            $userAccount->emailAddress = $user->emailAddress;
            $userAccount->lastName = $user->lastName;
            $userAccount->firstName = $user->firstName;
            $userAccount->title = $user->title;
            $userAccount->password = $user->password;
            $userAccount->passwordChangeRequired = true;
            $userAccount->locked = $user->locked;
            $userAccount->enabled = $user->enabled;
            $userAccount->isAdmin = $user->isAdmin;
            $userAccount->accountType = 'user';

            if (!is_null($user->ownerOrgRegNumber) && !empty($user->ownerOrgRegNumber)) {
                $userOwnerOrg = $organizationController->getOrgByRegNumber($user->ownerOrgRegNumber);
                if (!is_null($userOwnerOrg) && !empty($userOwnerOrg)) {
                    $userAccount->ownerOrgId = (string) $userOwnerOrg->orgId;
                }
            }

            try {
                if ($isReset) {
                    $this->sdoFactory->create($userAccount, 'auth/account');
                } else {
                    $this->sdoFactory->update($userAccount, 'auth/account');
                }

                if (!is_null($user->organizations) && !empty($user->organizations)) {
                    $user->organizations = explode(';', $user->organizations);
                    $this->importUserPositions((array) $user->organizations, (string) $userAccount->accountId);
                }

                $this->importUserRoles((array) explode(';', $user->roles), (string) $userAccount->accountId);
            } catch (\Exception $e) {
                if ($transactionControl) {
                    $this->sdoFactory->rollback();
                }
                throw $e;
            }
        }

        if ($transactionControl) {
            $this->sdoFactory->commit();
        }

        return true;
    }

    /**
     * Verify if there is at least one superadmin when resetting users
     *
     * @param  array $users Array of user/userAccountImportExport message
     *
     * @return boolean
     */
    private function checkForSuperAdmin($users)
    {
        $hasSuperAdmin = false;
        foreach ($users as $key => $user) {
            if ($user->isAdmin
                && (
                    !isset($user->ownerOrgRegNumber)
                    || empty($user->ownerOrgRegNumber)
                    || is_null($user->ownerOrgRegNumber)
                   )
                ) {
                $hasSuperAdmin = true;
            }
        }

        if (!$hasSuperAdmin) {
            throw new \Exception("Csv must have at least one superadmin");
        }
    }

    private function deleteAllUsers()
    {
        $users = $this->index();

        foreach ($users as $key => $user) {
            $this->importDeleteUser((string) $user->accountId);
        }
    }

    /**
     * delete existing user
     *
     * @param auth/account $userAccount The user object unique identifier
     *
     * @return
     */
    public function importDeleteUser($userAccountId)
    {
        // Delete user positions
        $userPositionController = \laabs::newController('organization/userPosition');
        $organizationSdoFactory = \laabs::dependency('sdo', 'organization')->getService('Factory')->newInstance();
        $currentUserServices = $userPositionController->listPositions((string) $userAccountId);
        if (!empty($currentUserServices)) {
            foreach ($currentUserServices as $key => $userPosition) {
                $organizationSdoFactory->delete($userPosition, 'organization/userPosition');
            }
        }

        // Delete user roles members
        $roleMemberController = \laabs::newController('auth/roleMember');
        $roleMemberSdoFactory = \laabs::dependency('sdo', 'auth')->getService('Factory')->newInstance();
        $roleMembers = $roleMemberController->readByUserAccount((string) $userAccountId);
        //delete role
        if (!is_null($roleMembers) || !empty($roleMembers)) {
            foreach ($roleMembers as $key => $roleMember) {
                $roleMemberSdoFactory->delete($roleMember, 'auth/roleMember');
            }
        }

        $this->sdoFactory->delete($this->get($userAccountId));
    }

    /**
     * Import array of organizations
     *
     * @param array  $organizations Array of orgRegNumber
     * @param string $userAccountId Unique user identifier
     *
     * @return [type]                [description]
     */
    private function importUserPositions($organizations, $userAccountId)
    {
        $organizationController = \laabs::newController('organization/organization');
        $userPositionController = \laabs::newController('organization/userPosition');
        $organizationSdoFactory = \laabs::dependency('sdo', 'organization')->getService('Factory')->newInstance();

        $currentUserServices = $userPositionController->listPositions($userAccountId);

        if (!empty($currentUserServices)) {
            foreach ($currentUserServices as $key => $userPosition) {
                $organizationSdoFactory->delete($userPosition, 'organization/userPosition');
            }
        }

        foreach ($organizations as $key => $orgRegNumber) {
            // $organization = $organizationController->getOrgByRegNumber($orgRegNumber);
            $organization = $organizationSdoFactory->read("organization/organization", ['registrationNumber' => $orgRegNumber]);

            if (is_null($organization) || empty($organization)) {
                throw new \core\Exception\BadRequestException("Organization %s does not exists", 400, null, [$organization]);
            }

            $userPosition = \laabs::newInstance('organization/userPosition');
            $userPosition->userAccountId = $userAccountId;
            $userPosition->orgId = (string) $organization->orgId;
            $userPosition->default = false;
            if ($key == 0) {
                $userPosition->default = true;
            }

            $organizationSdoFactory->create($userPosition, 'organization/userPosition');
        }
    }

    /**
     * Import array of user roles
     *
     * @param array  $roles         Array of roles Id
     * @param string $userAccountId Unique user identifier
     *
     * @return [type]        [description]
     */
    private function importUserRoles($roles, $userAccountId)
    {
        $roleMemberController = \laabs::newController('auth/roleMember');
        $roleController = \laabs::newController('auth/role');
        $roleMemberSdoFactory = \laabs::dependency('sdo', 'auth')->getService('Factory')->newInstance();

        if (!empty($roles)) {
            foreach ($roles as $key => $roleId) {
                if (!$roleController->edit($roleId)) {
                    throw new \core\Exception\BadRequestException("Role does not exists");
                }

                $roleMembers = $roleMemberController->readByUserAccount($userAccountId);
                //delete role
                if (!is_null($roleMembers) || !empty($roleMembers)) {
                    foreach ($roleMembers as $key => $roleMember) {
                        if ($roleMember->roleId == $roleId) {
                            $roleMemberSdoFactory->delete($roleMember, 'auth/roleMember');
                            unset($roleMembers[$key]);
                        }
                    }
                }
                // create role
                $roleMember = \laabs::newInstance("auth/roleMember");
                $roleMember->userAccountId = $userAccountId;
                $roleMember->roleId = $roleId;
                $roleMemberSdoFactory->create($roleMember, 'auth/roleMember');
            }
        }
    }

    /**
     * If security level is activated in configuration, check if user has clearance
     *
     * @param auth/account $ownAccount  account realising request
     * @param auth/account $targetUserAccount account to exert action to
     *
     * @return
     */
    protected function checkPrivilegesAccess($ownAccount, $targetUserAccount)
    {
        $securityLevel = $ownAccount->getSecurityLevel();
        if ($securityLevel == $ownAccount::SECLEVEL_GENADMIN) {
            if (!$targetUserAccount->ownerOrgId || !$targetUserAccount->isAdmin) {
                throw new \core\Exception\UnauthorizedException("You are not allowed to do this action");
            }
        } elseif ($securityLevel == $ownAccount::SECLEVEL_FUNCADMIN) {
            if (!$targetUserAccount->organizations || $targetUserAccount->isAdmin) {
                throw new \core\Exception\UnauthorizedException("You are not allowed to do this action");
            }
            
            if (array_search($targetUserAccount->accountName, array_column($this->userList(), 'accountName')) === false) {
                throw new \core\Exception\UnauthorizedException("You are not allowed to modify this user account");
            }
            if ($securityLevel == $ownAccount::SECLEVEL_USER) {
                if ($ownAccount != $targetServiceAccount) {
                    throw new \core\Exception\UnauthorizedException("You are not allowed to do this action");
                }
            }
        }
    }
}
