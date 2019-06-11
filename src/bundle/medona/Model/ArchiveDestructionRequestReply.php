<?php
namespace bundle\medona\Model;
/**
 * The archive transfer message
 * 
 * @package Medona
 * @author  Alexandre MORIN (Maarch) <alexandre.morin@maarch.org>
 * 
 * @xmlns medona org:afnor:medona:1.0
 * 
 */
class ArchiveDestructionRequestReply
    extends AbstractBusinessMessage
{
    /**
     * @var string
     * @xpath medona:ReplyCode
     */
    public $replyCode;
    
    /**
     * @var medona/Identifier
     * @xpath medona:MessageRequestIdentifier
     */
    public $messageRequestIdentifier;

    /**
     * @var medona/Identifier
     * @xpath medona:DestructionRequestReplyIdentifier
     */
    public $destructionRequestReplyIdentifier;
    
    /**
     * @var medona/Identifier[]
     * @xpath medona:UnitIdentifier
     */
    public $unitIdentifier;
    
    /**
     * @var medona/Organization
     * @xpath medona:ArchivalAgency
     */
    public $archivalAgency;

    /**
     * @var medona/Organization
     * @xpath medona:OriginatingAgency
     */
    public $originatingAgency;
}
