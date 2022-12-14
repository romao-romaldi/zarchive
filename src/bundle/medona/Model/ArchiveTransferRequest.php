<?php
namespace bundle\medona\Model;
/**
 * The archive transfer request message
 * 
 * @package Medona
 * @author  Alexandre MORIN (Maarch) <alexandre.morin@maarch.org>
 * 
 * @xmlns medona org:afnor:medona:1.0
 * 
 */
class ArchiveTransferRequest
    extends AbstractBusinessMessage
{

    /**
     * @var medona/Identifier[]
     * @xpath medona:RelatedTransferReference
     */
    public $relatedTransferReference;

    /**
     * @var date
     * @xpath medona:TransfertDate
     */
    public $transfertDate;
    
    /**
     * @var medona/Organization
     * @xpath medona:ArchivalAgency
     */
    public $archivalAgency;

    /**
     * @var medona/Organization
     * @xpath medona:TransferringAgency
     */
    public $transferringAgency;
}
