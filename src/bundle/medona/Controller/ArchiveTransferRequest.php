<?php

/*
 * Copyright (C) 2022 Maarch
 *
 * This file is part of bundle medona
 *
 * Bundle medona is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Bundle medona is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with bundle medona.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace bundle\medona\Controller;

/**
 * Class for archive transfer request
 *
 * @package Medona
 * @author  Cyril Vazquez <cyril.vazquez@maarch.org>
 */
class ArchiveTransferRequest extends abstractMessage
{
    /**
     * Receive message with all contents embedded
     * @param mixed  $package The message binary contents OR a filename
     * @param string $schema  The message file schema
     *
     * @return medona/message
     *
     * @todo Remove files from sas when error on reception
     */
    public function receive($package, $schema = null)
    {
        $message = $this->createNewMessage($schema);

        // Spécifique receive
        $this->receivePackage($message, $package);

        if (empty($schema)) {
            $this->detectSchema($message);
        }

        $this->receiveMessage($message);

        return $this->sendAcknowledgement($message);
    }

    protected function createNewMessage($schema = null)
    {
        $messageId = \laabs::newId();
        $message = \laabs::newInstance('medona/message');
        $message->messageId = $messageId;
        $message->type = "ArchiveTransferRequest";
        $message->receptionDate = \laabs::newTimestamp();
        $message->schema = $schema;
        $message->isIncoming = true;

        $messageDir = $this->messageDirectory.DIRECTORY_SEPARATOR.(string) $message->messageId;
        if (!is_dir($messageDir)) {
            mkdir($messageDir, 0777, true);
        }

        return $message;
    }

    protected function receivePackage($message, $package)
    {
        if (is_object($package)) {
            $this->receiveObject($message, $package);
        } else {
            $this->receiveStream($message, $package);
        }
    }

    protected function receiveObject($message, $package)
    {
        $connectorService = \laabs::newService('medona/Connectors/Multiparts');

        $message->path = $connectorService->receive(
            $package,
            $params = [],
            $this->messageDirectory.DIRECTORY_SEPARATOR.(string) $message->messageId
        );
    }

    protected function receiveFiles($message, $data, $attachments, $filename = false, $mediatype = null)
    {
        $messageDir = $this->messageDirectory.DIRECTORY_SEPARATOR.(string) $message->messageId;

        if (!$filename) {
            $filename = (string) $message->messageId;

            if ($mediatype) {
                $filename .= '.'.\laabs\basename($mediatype);
            }
        }

        file_put_contents($messageDir.DIRECTORY_SEPARATOR.$filename, $data);

        $message->path = $messageDir.DIRECTORY_SEPARATOR.$filename;
    }

    protected function receiveStream($message, $package)
    {
        switch (true) {
            case is_string($package)
                && (filter_var(substr($package, 0, 10), FILTER_VALIDATE_URL) || is_file($package)):
                $data = file_get_contents($package);
                break;

            case is_string($package) &&
                preg_match('%^[a-zA-Z0-9\\\\/+]*={0,2}$%', $package):
                $data = base64_decode($package);
                break;

            case is_resource($package):
                $handler = \core\Encoding\Base64::decode($package);
                $data = stream_get_contents($handler);
                break;
        }

        $mediatype = $this->finfo->buffer($data);
        $this->receiveFiles($message, $data, $mediatype);
    }

    protected function receiveMessage($message)
    {
        try {
            if (empty($message->path)) {
                $this->sendError("202", "Name of zip and his content files doesn't match");
                throw \laabs::newException('medona/invalidMessageException', "Invalid message", 400);
            }

            $namespace = \laabs::configuration("medona")["packageSchemas"][$message->schema]["phpNamespace"];
            $ArchiveTransferRequestController = \laabs::newController("$namespace/ArchiveTransferRequest");
            $ArchiveTransferRequestController->receive($message);

            if ($ArchiveTransferRequestController->replyCode) {
                $this->replyCode = $ArchiveTransferRequestController->replyCode;

                $this->errors = array_merge($this->errors, $ArchiveTransferRequestController->errors);

                throw \laabs::newException('medona/invalidMessageException', "Invalid message", 400);
            }

            try {
                $message->senderOrg = $this->orgController->getOrgByRegNumber($message->senderOrgRegNumber);
                $message->senderOrgName = $message->senderOrg->orgName;
            } catch (\Exception $e) {
                $this->sendError("202", "Le service versant identifié par '".$message->senderOrgRegNumber."' est inconnu du système.");

                throw \laabs::newException('medona/invalidMessageException', "Invalid message", 400);
            }

            try {
                $message->recipientOrg = $this->orgController->getOrgByRegNumber($message->recipientOrgRegNumber);
                $message->recipientOrgName = $message->recipientOrg->orgName;
            } catch (\Exception $e) {
                $this->sendError("201", "Le service d'archive identifié par '".$message->recipientOrgRegNumber."' est inconnu du système.");

                throw \laabs::newException('medona/invalidMessageException', "Invalid message", 400);
            }

            $message->status = "received";
            $this->create($message);
        } catch (\Exception $e) {
            $event = $this->lifeCycleJournalController->logEvent(
                'medona/reception',
                'medona/message',
                $message->messageId,
                $message,
                false
            );

            $this->sendError("000", $e->getMessage());

            // Remove files from sas
            $messageURI = $this->messageDirectory.DIRECTORY_SEPARATOR.$message->messageId;
            if (is_dir($messageURI)) {
                \laabs\rmdir($messageURI, true);
            }

            if ($e->getCode() != 0) {
                $exception = \laabs::newException('medona/invalidMessageException', "Invalid message", $e->getCode());
            } else {
                $exception = \laabs::newException('medona/invalidMessageException', "Invalid message", 400);
            }

            if (isset($e->errors) && is_array($e->errors)) {
                $exception->errors = array_merge($this->errors, $e->errors);
            } else {
                $exception->errors = $this->errors;
            }

            throw $exception;
        }
    }

    /**
     * sendAcknowledgement message
     *
     * @param  medona/message $message
     *
     * @return medona/message $acknowledgement
     */
    protected function sendAcknowledgement($message)
    {
        $event = $this->lifeCycleJournalController->logEvent(
            'medona/reception',
            'medona/message',
            $message->messageId,
            $message,
            true
        );

        $acknowledgementController = \laabs::newController('medona/Acknowledgement');
        $acknowledgement = $acknowledgementController->send($message);
        $acknowledgement->receivedMessageId = $message->messageId;

        return $acknowledgement;
    }

    protected function detectSchema($message)
    {
        $mediatype = $this->finfo->file($message->path);
        if ($mediatype == 'application/xml' || $mediatype === 'text/xml') {
            $xml = new \DOMDocument();
            $xml->load($message->path);
            $messageNamespace = $xml->documentElement->namespaceURI;
            foreach ($this->packageSchemas as $name => $info) {
                if (isset($info->xmlNamespace) && $info->xmlNamespace == $messageNamespace) {
                    $schema = $name;
                    break;
                }
            }

            if (empty($schema)) {
                $schema = \laabs::resolveXmlNamespace($messageNamespace);
            }

            if (empty($schema)) {
                throw \laabs::newException('medona/invalidMessageException', "Unknown message schema'.$messageNamespace", 400);
            }

            $message->schema = $schema;
        } else {
            $message->schema = 'recordsManagement';
        }
    }

    /**
     * Validate the messages
     *
     * @return medona/message $message
     */
    public function validateBatch()
    {
        $results = [];

        $messageIds = $this->sdoFactory->index("medona/message", ["messageId"], "(status='received' OR status='modified') AND type='ArchiveTransferRequestReply' AND active=true");

        // Avoid paralleling processes
        foreach ($messageIds as $messageId) {
            $message = $this->sdoFactory->read('medona/message', (string) $messageId);

            if (!in_array($message->status, ['received', 'modified'])) {
                continue;
            }

            $this->changeStatus($message->messageId, "processing");

            $this->loadData($message);

            try {
                $results[(string) $message->messageId] = $this->validate($message);
            } catch (\Exception $e) {
                $results[(string) $message->messageId] = $e;
            }
        }

        return $results;
    }

    /**
     * Validate message against schema and rules
     * @param mixed  $messageId         The message identifier
     * @param object $archivalAgreement The archival agreement
     *
     * @return boolean
     */
    public function validate($messageId, $archivalAgreement = null)
    {
        $this->errors = array();
        $this->replyCode = null;

        $message = $this->sdoFactory->read('medona/message', $messageId);
        $this->loadData($message);

        if (in_array($message->status, ["error", "validationError"])) {
            $this->sendError('104', "The message is in error status");
            $exception = \laabs::newException('medona/invalidMessageException', "Invalid message", 400);
            $exception->errors = $this->errors;

            throw $exception;
        }

        try {
            $this->validateMessageHeaders($message);
            $namespace = \laabs::configuration("medona")["packageSchemas"][$message->schema]["phpNamespace"];
            $ArchiveTransferRequestController = \laabs::newController("$namespace/ArchiveTransferRequest");
            $res = $ArchiveTransferRequestController->validate($message, $this->currentArchivalAgreement);

            if ($ArchiveTransferRequestController->replyCode) {
                $this->replyCode = $ArchiveTransferRequestController->replyCode;
            }

            $this->errors = array_merge($this->errors, $ArchiveTransferRequestController->errors);
            $this->infos = array_merge($this->infos, $ArchiveTransferRequestController->infos);

        } catch (\Exception $exception) {
            $this->errors[] = new \core\Error($exception->getMessage());
            $this->sendValidationError($message);

            $exception = \laabs::newException('medona/invalidMessageException', "Invalid message", 400);
            $exception->errors = $this->errors;

            throw $exception;
        }

        // Non blocking errors
        if (count($this->errors) > 0) {
            if ($res = -1) {
                $this->sendValidationError($message, false, 'toBeModified');
            } else {
                $this->sendValidationError($message, false, 'validationError');
            }

            $exception = \laabs::newException('medona/invalidMessageException', "Invalid message", 400);
            $exception->errors = $this->errors;

            throw $exception;
        } else {
            $message->status = "valid";

            $eventInfo = get_object_vars($message);
            foreach ((array) $this->infos as $info) {
                $eventInfo['code'] = "OK";
                $eventInfo['info'] = $info;

                $event = $this->lifeCycleJournalController->logEvent(
                    'medona/validation',
                    'medona/message',
                    $message->messageId,
                    $eventInfo,
                    true
                );
            }

            $this->update($message);
        }

        if ($this->currentArchivalAgreement && $this->currentArchivalAgreement->autoTransferAcceptance) {
            $this->accept((string) $message->messageId);
        }

        return true;
    }

    /**
     * Validate message header info
     */
    protected function validateMessageHeaders($message)
    {
        // Check sender (depositor) roles
        $this->validateDepositor($message);

        // Check recipient (archiver) roles
        $this->validateArchiver($message);

        if (isset($message->archivalAgreementReference)) {
            $this->validateArchivalAgreement($message);
        }
    }

    protected function sendValidationError($message, $sendReply = true, $status = null)
    {
        if ($status) {
            $message->status = $status;
        } else {
            $message->status = "invalid";
        }

        $eventInfo = get_object_vars($message);
        foreach ((array) $this->errors as $error) {
            $eventInfo['code'] = $error->getCode();
            $eventInfo['info'] = $error->getMessage();

            $event = $this->lifeCycleJournalController->logEvent(
                'medona/validation',
                'medona/message',
                $message->messageId,
                $eventInfo,
                false
            );
        }

        if (isset($message->comment)) {
            $message->comment = json_encode($message->comment);
        }

        $this->update($message);

        if ($sendReply) {
            $archiveTransferRequestReplyController = \laabs::newController('medona/ArchiveTransferRequestReply');
            $archiveTransferRequestReplyController->send($message, $this->replyCode);
        }
    }

    protected function validateDepositor($message)
    {
        try {
            $senderOrg = $this->orgController->getOrgByRegNumber($message->senderOrgRegNumber);
        } catch (\Exception $e) {
            $this->sendError("202", "Le service versant identifié par '".$message->senderOrgRegNumber."' est inconnu du système.");

            throw \laabs::newException('medona/invalidMessageException', "Invalid message", 400);
        }

        $senderRoles = (array) $senderOrg->orgRoleCodes;
        if (!in_array("depositor", $senderRoles)) {
            $this->sendError("202", "Le service versant identifié par '".$message->senderOrgRegNumber."' ne possède pas le rôle d'acteur adéquat dans le système.");
        }

        return true;
    }

    protected function validateArchiver($message)
    {
        try {
            $recipientOrg = $this->orgController->getOrgByRegNumber($message->recipientOrgRegNumber);
        } catch (\Exception $e) {
            $this->sendError("201", "Le service d'archive identifié par '".$message->recipientOrgRegNumber."' est inconnu du système.");

            throw \laabs::newException('medona/invalidMessageException', "Invalid message", 400);
        }

        $recipientRoles = (array) $recipientOrg->orgRoleCodes;
        if (!in_array("archiver", $recipientRoles) && !in_array("owner", $recipientRoles)) {
            $this->sendError("202", "Le service d'archives identifié par '".$message->recipientOrgRegNumber."' ne possède pas le rôle d'acteur adéquat dans le système.");
        }

        return true;
    }

    protected function validateArchivalAgreement($message)
    {

        try {
            $this->useArchivalAgreement($message->archivalAgreementReference);

        } catch (\Exception $e) {
            $this->sendError("300", "L'accord de versement '$message->archivalAgreementReference' n'a pas pu être lu.");

            throw \laabs::newException('medona/invalidMessageException', "Invalid message", 400);
        }

        // Check actor orgnizations
        if ($this->currentArchivalAgreement->depositorOrgRegNumber != $message->senderOrgRegNumber) {
            $this->sendError("303", "Le service versant n'est pas conforme à celui indiqué dans l'accord de versement.");
        }

        if ($this->currentArchivalAgreement->archiverOrgRegNumber != $message->recipientOrgRegNumber) {
            $this->sendError("304", "Le service d'archives n'est pas conforme à celui indiqué dans l'accord de versement.");
        }

        if ($message->schema == 'medona') {
            if (empty($this->currentArchivalAgreement->originatorOrgIds)) {
                $this->sendError("300", "Il n'y a pas de service producteur.");
            } else {
                $archivalAgreementOriginators = [];

                foreach ((array) $this->currentArchivalAgreement->originatorOrgIds as $orgId) {
                    $organization = $this->orgController->read($orgId);
                    $archivalAgreementOriginators[] = $organization->registrationNumber;
                }

                $originators = (array) $message->object->dataObjectPackage->managementMetadata->accessRule->originatorOrgRegNumber;

                if (is_array($message->object->dataObjectPackage->descriptiveMetadata->archive)) {
                    foreach ($message->object->dataObjectPackage->descriptiveMetadata->archive as $archive) {
                        if ($archive->originatorOrgRegNumber) {
                            $originators[] = $archive->originatorOrgRegNumber;
                        }
                    }
                }

                foreach ($originators as $originator) {
                    if (!in_array($originator, $archivalAgreementOriginators)) {
                        $this->sendError("302", "Le service producteur identifié par $originator ne correspond pas à l'accord de versement.");
                    }
                }
            }
        }

        $today = \laabs::newDate();
        // Check dates and activity
        if (isset($this->currentArchivalAgreement->beginDate)) {
            if ($this->currentArchivalAgreement->beginDate->diff($today)->invert) {
                $this->sendError("312", "L'accord de versement n'est pas encore en cours de validité.");
            }
        }

        if (isset($this->currentArchivalAgreement->endDate)) {
            if (!$this->currentArchivalAgreement->endDate->diff($today)->invert) {
                $this->sendError("312", "L'accord de versement n'est plus en cours de validité.");
            }
        }

        if ($this->currentArchivalAgreement->enabled != true) {
            $this->sendError("300", "L'accord de versement n'est pas actif.");
        }

        if ($this->currentArchivalAgreement->maxSizeTransfer > 0 && $message->size > ($this->currentArchivalAgreement->maxSizeTransfer*1048576)) {
            $this->sendError("301", "La taille maximale par tranfert de l'accord de versement est dépassée.");
        }

        if ($this->currentArchivalAgreement->maxSizeDay > 0) {
            $res = $this->sdoFactory->summarise("medona/message", "archivalAgreementReference", "size", ["receptionDate > :today and archivalAgreementReference='".$this->currentArchivalAgreement->reference."'", ["today" => $today]]);
            if (count($res) && ($res[$this->currentArchivalAgreement->reference]+$message->size) > ($this->currentArchivalAgreement->maxSizeDay*1048576)) {
                $this->sendError("301", "La taille maximale par jour de l'accord de versement est dépassée.");
            }
        }

        if ($this->currentArchivalAgreement->maxSizeWeek > 0) {
            $aWeekAgo = $today->shift(new \core\Type\Duration("-P7D"));
            $res = $this->sdoFactory->summarise("medona/message", "archivalAgreementReference", "size", ["receptionDate > :aWeekAgo and archivalAgreementReference='".$this->currentArchivalAgreement->reference."'", ["aWeekAgo" => $aWeekAgo]]);
            if (count($res) && ($res[$this->currentArchivalAgreement->reference]+$message->size) > ($this->currentArchivalAgreement->maxSizeWeek*1048576)) {
                $this->sendError("301", "La taille maximale par semaine de l'accord de versement est dépassée.");
            }
        }

        if ($this->currentArchivalAgreement->maxSizeMonth > 0) {
            $aMonthAgo = $today->shift(new \core\Type\Duration("-P1M"));
            $res = $this->sdoFactory->summarise("medona/message", "archivalAgreementReference", "size", ["receptionDate > :aMonthAgo and archivalAgreementReference='".$this->currentArchivalAgreement->reference."'", ["aMonthAgo" => $aMonthAgo]]);
            if (count($res) && ($res[$this->currentArchivalAgreement->reference]+$message->size) > ($this->currentArchivalAgreement->maxSizeMonth*1048576)) {
                $this->sendError("301", "La taille maximale par mois de l'accord de versement est dépassée.");
            }
        }

        if ($this->currentArchivalAgreement->maxSizeYear > 0) {
            $aYearAgo = $today->shift(new \core\Type\Duration("-P1Y"));
            $res = $this->sdoFactory->summarise("medona/message", "archivalAgreementReference", "size", ["receptionDate > :aYearAgo and archivalAgreementReference='".$this->currentArchivalAgreement->reference."'", ["aYearAgo" => $aYearAgo]]);
            if (count($res) && ($res[$this->currentArchivalAgreement->reference]+$message->size) > ($this->currentArchivalAgreement->maxSizeYear*1048576)) {
                $this->sendError("301", "La taille maximale par année de l'accord de versement est dépassée.");
            }
        }

        if (isset($this->currentArchivalAgreement->beginDate) && $this->currentArchivalAgreement->maxSizeAgreement > 0) {
            $archivalAgreementBeginDate = $this->currentArchivalAgreement->beginDate;
            $res = $this->sdoFactory->summarise("medona/message", "archivalAgreementReference", "size", ["receptionDate > :archivalAgreementBeginDate and archivalAgreementReference='".$this->currentArchivalAgreement->reference."'", ["archivalAgreementBeginDate" => $archivalAgreementBeginDate]]);
            if (count($res) && ($res[$this->currentArchivalAgreement->reference]+$message->size) > ($this->currentArchivalAgreement->maxSizeAgreement*1048576)) {
                $this->sendError("301", "La taille maximale de l'accord de versement est dépassée.");
            }
        }

        return true;
    }

    /**
     * Get received archive tranfer message
     *
     * @return array Array of medona/message object
     */
    public function listReception()
    {
        $registrationNumber = $this->getCurrentRegistrationNumber();

        $queryParts = [];
        $queryParts[] = "recipientOrgRegNumber=$registrationNumber";
        $queryParts[] = "type='ArchiveTransferRequest'";
        $queryParts[] = "active=true";
        $queryParts[] = "isIncoming=true";
        $queryParts[] = "status != 'accepted'
        AND status != 'error'
        AND status != 'invalid'
        AND status !='draft'
        AND status !='template'
        AND status !='rejected'
        AND status !='acknowledge'" ;

        $maxResults = \laabs::configuration('presentation.maarchRM')['maxResults'];
        return $this->sdoFactory->find(
            'medona/message',
            implode(' and ', $queryParts),
            null,
            ">receptionDate",
            false,
            $maxResults
        );
    }

    /**
     * Get sending archive tranfer message
     *
     * @return array Array of medona/message object
     */
    public function listSending()
    {
        $registrationNumber = $this->getCurrentRegistrationNumber();
        $accountToken = \laabs::getToken("AUTH");

        $queryParts = [];
        $queryParts[] = "(accountId= '$accountToken->accountId' OR senderOrgRegNumber=$registrationNumber)";
        $queryParts[] = "type='ArchiveTransferRequest'";
        $queryParts[] = "active=true";
        $queryParts[] = "isIncoming=true";
        $queryParts[] = "status=['sent', 'valid']";

        $maxResults = \laabs::configuration('presentation.maarchRM')['maxResults'];
        return $this->sdoFactory->find(
            'medona/message',
            implode(' and ', $queryParts),
            null,
            false,
            false,
            $maxResults
        );
    }

    /**
     * Get transfer history
     *
     * @param string $reference         Reference
     * @param string $archiver          Archiver
     * @param string $originator        Originator
     * @param string $depositor         Depositor
     * @param string $archivalAgreement Archival agreement
     * @param date   $fromDate          From date
     * @param date   $toDate            To date
     * @param string $status            Status
     *
     * @return array Array of medona/message object
     */
    public function history(
        $reference = null,
        $archiver = null,
        $originator = null,
        $depositor = null,
        $archivalAgreement = null,
        $fromDate = null,
        $toDate = null,
        $status = null
    ) {
        return $this->search(
            "ArchiveTransferRequest",
            $reference,
            $archiver,
            $originator,
            $depositor,
            $archivalAgreement,
            $fromDate,
            $toDate,
            $status,
            true
        );
    }

    /**
     * Accept a message
     * @param string $messageId The message identifier
     * @param string $comment   1 comment
     */
    public function accept($messageId, $comment = null)
    {
        $this->changeStatus($messageId, "accepted", $comment);

        $message = $this->sdoFactory->read('medona/message', $messageId);
        if (isset($message->archivalAgreementReference)) {
            $archivalAgreement = $this->sdoFactory->read(
                'medona/archivalAgreement',
                array("reference" => $message->archivalAgreementReference)
            );
        }

        $message = $this->sdoFactory->read('medona/message', array('messageId' => $messageId));

        $archiveTransferRequestReplyController = \laabs::newController('medona/ArchiveTransferRequestReply');
        $archiveTransferRequestReplyController->send($message, [], '000');

        $this->lifeCycleJournalController->logEvent(
            'medona/acceptance',
            'medona/message',
            $message->messageId,
            $message,
            true
        );
    }

    /**
     * Reject a message
     * @param string $messageId The message identifier
     * @param string $comment   a comment
     */
    public function reject($messageId, $comment = null)
    {
        $this->changeStatus($messageId, "rejected", $comment);
        $archiveTransferRequestReplyController = \laabs::newController('medona/ArchiveTransferRequestReply');
        $archiveTransferRequestReplyController->send($messageId, null, "rejected", $comment);

        $message = $this->sdoFactory->read('medona/message', array('messageId' => $messageId));

        $this->lifeCycleJournalController->logEvent(
            'medona/rejection',
            'medona/message',
            $message->messageId,
            $message,
            true
        );
    }


    /**
     * Count archive tranfer message
     *
     * @return array Number of received and sent messages
     */
    public function count()
    {
        $registrationNumber = $this->getCurrentRegistrationNumber();

        $res = array();
        $queryParts = array();

        $queryParts["type"] = "type='ArchiveTransferRequest'";
        $queryParts["registrationNumber"] = "recipientOrgRegNumber=$registrationNumber";
        $queryParts["active"] = "active=true";
        $res['received'] = $this->sdoFactory->count('medona/message', implode(' and ', $queryParts));

        $queryParts["registrationNumber"] = "senderOrgRegNumber=$registrationNumber";

        $res['sent'] = $this->sdoFactory->count('medona/message', implode(' and ', $queryParts));

        return $res;
    }
}
