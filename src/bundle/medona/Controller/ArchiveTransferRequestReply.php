<?php

/*
 * Copyright (C) 2015 Maarch
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
 * Trait for archive transfer request reply
 *
 * @package Medona
 * @author  Cyril Vazquez <cyril.vazquez@maarch.org>
 */
class ArchiveTransferRequestReply extends abstractMessage
{

    /**
     * Send a new transfer request reply
     * @param string $transferRequestMessage The request message identifier
     * @param string $replyCode       The reply code
     * @param string $comment        The comment
     *
     * @return The reply message generated
     */
    public function send($transferRequestMessage, $archives = null, $replyCode = "OK", $comment = null)
    {
        if (is_scalar($transferRequestMessage)) {
            $messageId = $transferRequestMessage;
            $transferRequestMessage = $this->sdoFactory->read('medona/message', $messageId);
        }

        $message = \laabs::newInstance('medona/message');
        $message->messageId = \laabs::newId();
        $message->type = "ArchiveTransferRequestReply";
        $message->schema = $transferRequestMessage->schema;
        $message->status = "sent";
        $message->date = \laabs::newDatetime(null, "UTC");
        $message->receptionDate = $message->date;
        $message->replyCode = $replyCode;

        $message->reference = $transferRequestMessage->reference.'_Reply_'.date("Y-m-d_H-i-s");

        $lifeCycleEvents = $this->lifeCycleJournalController->getObjectEvents($transferRequestMessage->messageId, 'medona/message');
        foreach ($lifeCycleEvents as $lifeCycleEvent) {
            $message->comment[] = $lifeCycleEvent->description;
        }

        if ($comment) {
            $message->comment[] = $comment;
        }

        $message->requestReference = $transferRequestMessage->reference;
        $message->operationDate = \laabs::newDatetime(null, "UTC");

        $message->senderOrgRegNumber = $transferRequestMessage->recipientOrgRegNumber;
        $message->recipientOrgRegNumber = $transferRequestMessage->senderOrgRegNumber;
        try {
            $this->readOrgs($message); // read org names, addresses, communications, contacts
        } catch (\Exception $e) {
            $recipientOrg = \laabs::getToken("ORGANIZATION");

            $message->recipientOrgRegNumber = $recipientOrg->registrationNumber;
            $message->recipientOrg = $recipientOrg;

            $message->senderOrgRegNumber = $recipientOrg->registrationNumber;
            $message->senderOrg = $recipientOrg;
        }

        if (!is_null($archives)) {
            foreach ($archives as $archive) {
                $unitIdentifier = \laabs::newInstance("medona/unitIdentifier");
                $unitIdentifier->messageId = $message->messageId;
                $unitIdentifier->objectId = (string) $archive->archiveId;
                $unitIdentifier->objectClass = "recordsManagement/archive";

                $message->unitIdentifier[] = $unitIdentifier;
            }
        }

        $message->archive = $archives;

        try {
            $namespace = \laabs::configuration("medona")["packageSchemas"][$message->schema]["phpNamespace"];
            $archiveTransferReplyController = \laabs::newController("$namespace/ArchiveTransferRequestReply");
            $archiveTransferReplyController->send($message);
            $operationResult = true;
        } catch (\Exception $e) {
            $message->status = "error";
            $operationResult = false;

            throw $e;
        }

        $event = $this->lifeCycleJournalController->logEvent(
            'medona/sending',
            'medona/message',
            $message->messageId,
            $message,
            $operationResult
        );

        $this->create($message);

        return $message;
    }
}
