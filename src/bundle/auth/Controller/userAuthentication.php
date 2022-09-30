<?php
/*
 * Copyright (C) 2015 Maarch
 *
 * This file is part of bundle user.
 *
 * Bundle user is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Bundle user is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with bundle user.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace bundle\auth\Controller;

/**
 * user authentication controller
 *
 * @package Auth
 * @author  Cyril VAZQUEZ <cyril.vazquez@maarch.org>
 */
class userAuthentication
{
    /**
     * The password encryption
     *
     * @var string
     **/
    protected $passwordEncryption;

    /**
     * The security policy of the password
     *
     * @var string
     **/
    protected $securityPolicy;

    /**
     * Constructor
     * @param object $sdoFactory         The user model
     * @param string $passwordEncryption The password encryption
     * @param array  $securityPolicy     The security policy
     */
    public function __construct(\dependency\sdo\Factory $sdoFactory, $passwordEncryption, $securityPolicy)
    {
        $this->sdoFactory = $sdoFactory;
        $this->passwordEncryption = $passwordEncryption;
        $this->securityPolicy = $securityPolicy;
    }

    /**
     * Authenticate a user
     * @param string $userName The user name
     * @param string $password The user password
     *
     * @throws \bundle\auth\Exception\authenticationException
     *
     * @return auth/account The user account object
     */
    public function login($userName, $password)
    {
        // Check userAccount exists and get it
        $userAccount = $this->getUserByName($userName);

        $this->checkEnabled($userAccount);

        // Check password ans status
        $userLogin = $this->checkCredentials($userAccount, $password);

        if (isset($this->securityPolicy['sessionTimeout'])) {
            $tokenDuration = $this->securityPolicy['sessionTimeout'];
        } else {
            $tokenDuration = 86400;
        }

        // Set token
        $this->setToken($userLogin, $tokenDuration);

        // Require password change
        if ($this->securityPolicy['passwordValidity'] && $this->securityPolicy["passwordValidity"] != 0) {
            $diff = ($userLogin->lastLogin->getTimestamp() - $userAccount->passwordLastChange->getTimestamp()) / $tokenDuration;
            if ($diff > $this->securityPolicy['passwordValidity']) {
                throw \laabs::newException('auth/userPasswordChangeRequestException');
            }
        }

        if ($userAccount->passwordChangeRequired == true) {
            \laabs::setToken('TEMP-AUTH', $userLogin, $tokenDuration);
            \laabs::unsetToken('AUTH');
            throw \laabs::newException('auth/userPasswordChangeRequestException');
        }

        return $userAccount;
    }

    /**
     * Log a remote user
     * @param string $userName
     *
     * @return auth/userAccount
     */
    public function logRemoteUser($userName)
    {
        // Check userAccount exists and get it
        $userAccount = $this->getUserByName($userName);

        $this->checkEnabled($userAccount);

        $userLogin = \laabs::newInstance('auth/userLogin');
        $userLogin->accountId = $userAccount->accountId;
        $userLogin->lastIp = $_SERVER["REMOTE_ADDR"];

        if (isset($this->securityPolicy['sessionTimeout'])) {
            $tokenDuration = $this->securityPolicy['sessionTimeout'];
        } else {
            $tokenDuration = 86400;
        }

        // Set token
        $this->setToken($userLogin, $tokenDuration);

        return $userAccount;
    }

    /**
     * Get user from userName
     * @param string $userName
     *
     * @return auth/account
     * @throws auth/authenticationException when username not found
     */
    public function getUserByName(string $userName)
    {
        $exists = $this->sdoFactory->exists('auth/account', array('accountName' => $userName));

        if (!$exists) {
            throw \laabs::newException(
                'auth/authenticationException',
                'Username and / or password invalid and / or User is locked',
                401
            );
        }

        return $this->sdoFactory->read('auth/account', array('accountName' => $userName));
    }

    /**
     * Check user password and hability to login
     * @param object $userAccount
     *
     * @return object
     */
    protected function checkCredentials($userAccount, $password)
    {
        $currentDate = \laabs::newTimestamp();

        // Create user login object
        $userLogin = \laabs::newInstance('auth/userLogin');
        $userLogin->accountId = $userAccount->accountId;
        $userLogin->lastIp = $_SERVER["REMOTE_ADDR"];

        // Check password
        if (
            !password_verify($password, $userAccount->password)
            && hash($this->passwordEncryption, $password) != $userAccount->password
        ) {
            // Update bad password count
            $userLogin->badPasswordCount = $userAccount->badPasswordCount + 1;
            $this->sdoFactory->update($userLogin, 'auth/account');

            // If count exceeds max attempts, lock user
            if (
                $this->securityPolicy['loginAttempts']
                && $userLogin->badPasswordCount > $this->securityPolicy['loginAttempts'] - 1
            ) {
                $userAccountController = \laabs::newController('auth/userAccount');
                $userAccountController->lock($userLogin->accountId, true);

                $eventController = \laabs::newController('audit/event');
                $eventController->add(
                    "auth/userAccount/updateLock_userAccountId_",
                    array("accountId" => $userLogin->accountId),
                    null,
                    true,
                    true
                );
            }

            throw \laabs::newException(
                'auth/authenticationException',
                'Username and / or password invalid and / or User is locked',
                401
            );
        }

        if (password_needs_rehash($userAccount->password, PASSWORD_DEFAULT)) {
            $userLogin->password = password_hash($password, PASSWORD_DEFAULT);
        }

        // Check locked
        if ($userAccount->locked == true) {
            if (
                !isset($this->securityPolicy['lockDelay']) // No delay while locked
                || $this->securityPolicy['lockDelay'] == 0 // Unlimited delay
                || !isset($userAccount->lockDate)          // Delay but no date for lock so unlimited
                || ($currentDate->getTimestamp() - $userAccount->lockDate->getTimestamp()) < ($this->securityPolicy['lockDelay']) // Date + delay upper than current date
            ) {
                throw \laabs::newException(
                    'auth/authenticationException',
                    'Username and / or password invalid and / or User is locked',
                    401
                );
            }
        }

        // Login success, update user account values
        $userLogin->badPasswordCount = 0;
        $userLogin->locked = false;
        $userLogin->lockDate = null;
        $userLogin->tokenDate = null;
        $userLogin->lastLogin = $currentDate;

        $this->sdoFactory->update($userLogin, 'auth/account');

        return $userLogin;
    }

    /**
     * Sets the auth token
     * @param object $userLogin
     * @param int    $tokenDuration
     */
    public function setToken($userLogin, $tokenDuration)
    {
        $accountToken = new \StdClass();
        $accountToken->accountId = $userLogin->accountId;
        $userToken = \laabs::setToken('AUTH', $accountToken, $tokenDuration);
    }

    /**
     * Get form to edit user information
     * @param string $userName    The user's name
     * @param string $oldPassword The user's old password
     * @param string $newPassword The user's new password
     * @param string $requestPath The requested path
     *
     * @return mixed The requested path if the account exists, false if it doesn't
     */
    public function definePassword($userName, $oldPassword, $newPassword, $requestPath)
    {
        $tempToken = \laabs::getToken('TEMP-AUTH');
        if (!$this->sdoFactory->exists('auth/account', array('accountName' => $userName))) {
            return false;
        }
        $userAccount = $this->sdoFactory->read('auth/account', array('accountName' => $userName));

        if (!is_null($tempToken) && $tempToken->accountId == $userAccount->accountId) {
            $this->checkPasswordPolicies($newPassword);

            if (password_verify($newPassword, $userAccount->password)) {
                throw new \core\Exception\ForbiddenException("The password is the same as the precedent.", 403);
            }

            $userAccount->password = password_hash($newPassword, PASSWORD_DEFAULT);
            $userAccount->passwordLastChange = \laabs::newTimestamp();
            $userAccount->passwordChangeRequired = false;
            $this->sdoFactory->update($userAccount, 'auth/account');

            $this->login($userAccount->accountName, $newPassword, $requestPath);
            \laabs::unsetToken('TEMP-AUTH');

            return $requestPath;
        }

        return false;
    }

    /**
     * Validate the new password
     * @param string $newPassword The user's new password
     */
    public function checkPasswordPolicies($newPassword)
    {
        if (
            $this->securityPolicy['passwordMinLength']
            && strlen($newPassword) < $this->securityPolicy['passwordMinLength']
        ) {
            throw new \core\Exception\ForbiddenException("The password is too short.", 403);
        }

        if ($this->securityPolicy['passwordRequiresSpecialChars'] && ctype_alnum($newPassword)) {
            throw new \core\Exception\ForbiddenException("The password must contain special characters.", 403);
        }

        if ($this->securityPolicy['passwordRequiresDigits'] && !preg_match('~[0-9]~', $newPassword)) {
            throw new \core\Exception\ForbiddenException("The password must contain digits.", 403);
        }

        if (
            $this->securityPolicy['passwordRequiresMixedCase']
            && (!preg_match('~[A-Z]~', $newPassword) || !preg_match('~[a-z]~', $newPassword))
        ) {
            throw new \core\Exception\ForbiddenException("The password must contain upper and lower case.", 403);
        }
    }

    /**
     * Check if user is enabled
     * @param object $userAccount
     *
     * @return object
     */
    protected function checkEnabled($userAccount)
    {
        if ($userAccount->enabled != true) {
            $e = \laabs::newException(
                'auth/authenticationException',
                'User %1$s is disabled',
                403,
                null,
                array($userName)
            );
            throw $e;
        }
    }

    /**
     * Log out a user
     */
    public function logout()
    {
        $userAccount = $this->sdoFactory->read('auth/account', \laabs::getToken('AUTH'));
        $authentication = json_decode($userAccount->authentication);
        $authentication->sessionId = null;
        $userAccount->authentication = json_encode($authentication);

        $this->sdoFactory->update($userAccount, "auth/account");

        \laabs::unsetToken("AUTH");
        \laabs::unsetToken("ORGANIZATION");
    }
}
