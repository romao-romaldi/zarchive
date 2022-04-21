<?php
/*
 * Copyright (C) 2022 Maarch
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
 * along with bundle medona.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace bundle\medona;

/**
 * Archive transfer request interface
 *
 * @package Medona
 * @author  Cyril Vazquez <cyril.vazquez@maarch.org>
 */
interface archiveTransferrequestInterface
    extends messageInterface
{
    /**
     * Get ingoing transfer messages
     *
     * @action medona/ArchiveTransferRequest/listReception
     */
    public function readIncominglist();

     /**
     * Get outgoing transfer messages
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
     * @action medona/ArchiveTransferRequest/history
     */
    public function readHistory($reference = null, $archiver = null, $originator = null, $depositor = null, $archivalAgreement = null, $fromDate = null, $toDate = null, $status = null);

    /**
     * Count transfer request messages
     *
     * @action medona/ArchiveTransferRequest/count
     */
    public function readCount();

    /**
     * Receive message with all contents embedded
     * @param mixed  $messageFile The message binary contents or a filename
     * @param string $schema      The schema of the message file
     * @param string $filename    The message file name
     *
     * @action medona/ArchiveTransfer/receive
     */
    public function create($messageFile, $schema = null, $filename = null);

    /**
     * Receive message with all contents embedded
     *
     * @param mixed  $package  Message binary contents or a filename
     * @param string $connector    Connector to use
     * @param array  $params       Parameters to adapt message
     *
     * @action medona/ArchiveTransfer/receiveSource
     */
    public function createSource($package, $connector, $params = []);

    /**
     * Validate messages against schema and rules
     *
     * @action medona/ArchiveTransferRequest/validateBatch
     */
    public function updateValidateBatch();

    /**
     * Validate message
     *
     * @action medona/ArchiveTransferRequest/validate
     */
    public function updateValidate_messageId_();

    /**
     * Validate archive transfer
     *
     * @action medona/ArchiveTransferRequest/validate
     */
    public function updateRequestvalidate_messageId_();

    /**
     * Accept archive transfer
     *
     * @action medona/ArchiveTransferRequest/accept
     */
    public function updateRequestacceptance_messageId_();

    /**
     * Reject archive transfer
     * @param string $messageId The message identifier
     * @param string $comment   A comment
     *
     * @action medona/ArchiveTransferRequest/reject
     */
    public function updateRequestrejection($messageId, $comment = null);
}
