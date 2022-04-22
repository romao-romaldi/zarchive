<?php
/*
 * Copyright (C) 2015 Maarch
 *
 * This file is part of bundle medona.
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
 * along with bundle medona.  If not, see <http://www.gnu.org/licenses/>
 */
namespace presentation\maarchRM\Presenter\medona;

/**
 * trait html for transfer request
 *
 * @package medona
 * @author  Cyril Vazquez <cyril.vazquez@maarch.com>
 */
trait archiveTransferRequestTrait
{
    /**
     * Get the message import form
     *
     * @return string
     */
    public function messageRequestImport()
    {
        $packageSchemas = [];
        if (isset(\laabs::configuration('medona')['packageSchemas'])) {
            $packageSchemas = \laabs::configuration('medona')['packageSchemas'];
        }

        $this->view->addContentFile("medona/archiveTransferRequest/messageImport.html");

        $this->view->setSource("packageSchemas", $packageSchemas);
        $this->view->merge();
        $this->view->translate();

        return $this->view->saveHtml();
    }

    /**
     * Show incoming transfer message list
     * @param array $messages Array of message object
     *
     * @return string The view
     */
    public function transferRequestIncomingList($messages)
    {
        $this->view->addContentFile('medona/archiveTransferRequest/transferRequestIncomingList.html');

        $this->prepareMesageList($messages);

        $this->view->setSource("sender", true);
        $this->view->merge();

        return $this->view->saveHtml();
    }

    /**
     * Show outgoing transfer message list
     * @param array $messages Array of message object
     *
     * @return string The view
     */
    public function transferRequestOutgoingList($messages)
    {
        $this->view->addContentFile('medona/archiveTransferRequest/transferRequestOutgoingList.html');

        $this->prepareMesageList($messages);

        $this->view->setSource("recipient", true);
        $this->view->merge();

        return $this->view->saveHtml();
    }

    /**
     * Show incoming transfer message list
     * @param array $messages Array of message object
     *
     * @return string The view
     */
    public function transferRequestHistory($messages)
    {
        $this->view->addContentFile('medona/archiveTransferRequest/transferRequestHistory.html');
        $this->prepareMesageList($messages, true);
        $this->initHistoryForm();

        $statuses = [
            'template' => $this->translator->getText('template'),
            'draft' => $this->translator->getText('draft'),
            'sent' => $this->translator->getText('sent'),
            'received' => $this->translator->getText('received'),
            'valid' => $this->translator->getText('valid'),
            'accepted' => $this->translator->getText('accepted'),
            'processing' => $this->translator->getText('processing'),
            'processed' => $this->translator->getText('processed'),
            'toBeModified' => $this->translator->getText('toBeModified'),
            'modified' => $this->translator->getText('modified'),
            'rejected' => $this->translator->getText('rejected'),
            'invalid' => $this->translator->getText('invalid'),
            'error' => $this->translator->getText('error')
        ];

        $this->view->setSource('statuses', $statuses);
        $this->view->merge();

        return $this->view->saveHtml();
    }

    /**
     * Serializer JSON for acceptArchiveTransfer method
     *
     * @return type
     */
    public function acceptArchiveTransferRequest()
    {
        $this->json->message = $this->translator->getText("Message accepted");

        return $this->json->save();
    }

    /**
     * Serializer JSON for rejectArchiveTransfer method
     *
     * @return type
     */
    public function rejectArchiveTransferRequest()
    {
        $this->json->message = $this->translator->getText("Message rejected");

        return $this->json->save();
    }

    /**
     * Serializer JSON for validateArchiveTransfer method
     *
     * @return type
     */
    public function validateArchiveTransferRequest()
    {
        $this->json->message = $this->translator->getText("Message validated");

        return $this->json->save();
    }
}
