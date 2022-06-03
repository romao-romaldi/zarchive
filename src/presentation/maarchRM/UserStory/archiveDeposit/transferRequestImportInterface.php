<?php
/*
 * Copyright (C) 2015 Maarch
 *
 * This file is part of maarchRM.
 *
 * maarchRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * maarchRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with bundle digitalResource.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace presentation\maarchRM\UserStory\archiveDeposit;

/**
 * User story for message deposit
 * @author Cyril Vazquez <cyril.vazquez@maarch.org>
 */
interface transferRequestImportInterface
{
    /**
     * Receive a new message
     *
     * @uses medona/ArchiveTransferRequest/create
     * @return medona/message/receive
     */
    public function createTransferrequest($messageFile, $schema);

    /**
     * Get the message import view
     *
     * @return medona/message/messageRequestimport
     */
    public function readTransferrequest();

    /**
     * Validate archive transfer request by depositor from import screen
     *
     * @uses medona/archiveTransferRequest/updateRequestvalidate_messageId_
     * @return medona/message/validateArchiveTransferRequest
     */
    public function updateTransferrequestvalidate_messageId_();
}
