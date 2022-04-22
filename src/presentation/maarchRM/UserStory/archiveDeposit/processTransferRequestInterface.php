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
 * User story of deposit processing
 * @author Alexandre Morin <alexandre.morin@maarch.org>
 */
interface processTransferRequestInterface
{
    /**
     * Search form
     *
     * @uses medona/archiveTransferRequest/readIncominglist
     * @return medona/message/transferRequestIncomingList
     */
    public function readTransferrequestReceived();

    /**
     * Validate archive transfer
     *
     * @uses medona/archiveTransferRequest/updateRequestvalidate_messageId_
     * @return medona/message/validateArchiveTransferRequest
     */
    public function updateTransferrequestvalidate_messageId_();

    /**
     * Accept archive transfer
     *
     * @uses medona/archiveTransferRequest/updateRequestacceptance_messageId_
     * @return medona/message/acceptArchiveTransferRequest
     */
    public function updateTransferrequestacceptance_messageId_();

    /**
     * Reject archive transfer
     * @param string $messageId
     * @param string $comment
     *
     * @uses medona/archiveTransferRequest/updateRequestrejection
     * @return medona/message/rejectArchiveTransferRequest
     */
    public function updateTransferrequestrejection_messageId_($messageId, $comment = null);
}
