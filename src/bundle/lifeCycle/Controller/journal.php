<?php

/*
 * Copyright (C) 2015 Maarch
 *
 * This file is part of bundle lifeCycle.
 *
 * Bundle lifeCycle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Bundle lifeCycle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with bundle lifeCycle.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace bundle\lifeCycle\Controller;

/**
 * Class of archives life cycle journal
 *
 * @author Prosper DE LAURE <prosper.delaure@maarch.org>
 */
class journal
{
    protected $separateInstance;
    protected $interval;
    protected $signatureScript;

    // Journal files reading
    protected $currentJournalFile;
    protected $currentJournalId;
    protected $currentEvent;
    protected $journalCursor;
    protected $eventFormats;

    // Mail notification
    protected $mailHost;
    protected $mailUsername;
    protected $mailPassword;
    protected $mailPort;
    protected $mailSender;
    protected $mailReceiver;
    protected $mailSMTPAuth;
    protected $mailSMTPSecure;

    protected $journals;

    /**
     * Constructor
     * @param \dependency\sdo\Factory $sdoFactory       The sdo factory
     * @param string                  $separateInstance Read only instance events
     * @param string                  $interval         The time bewteen 2 journal changes
     * @param string                  $signatureScript  The signature script path
     * @param string                  $mailHost         The mail host
     * @param string                  $mailUsername     The mail user name
     * @param string                  $mailPassword     The mail user password
     * @param string                  $mailPort         The mail port
     * @param string                  $mailSender       The mail sender
     * @param string                  $mailReceiver     The mail receiver
     * @param bool                    $mailSMTPAuth     The mail SMTP auth
     * @param string                  $mailSMTPSecure   The mail SMTP secure
     */
    public function __construct(\dependency\sdo\Factory $sdoFactory, $separateInstance = false, $interval = 86400, $signatureScript = null, $mailHost = null, $mailUsername = null, $mailPassword = null, $mailPort = null, $mailSender = null, $mailReceiver = null, $mailSMTPAuth = null, $mailSMTPSecure = null)
    {
        $this->separateInstance = $separateInstance;
        $this->interval = $interval;
        $this->signatureScript = $signatureScript;
        $this->sdoFactory = $sdoFactory;

        $this->currentJournalFile = null;
        $this->currentJournalId = null;
        $this->currentOffset = 0;

        $this->eventFormats = $this->sdoFactory->index('lifeCycle/eventFormat');
        foreach ($this->eventFormats as $eventFormat) {
            $eventFormat->format = explode(' ', $eventFormat->format);
        }

        $this->mailHost = $mailHost;
        $this->mailUsername = $mailUsername;
        $this->mailPassword = $mailPassword;
        $this->mailPort = $mailPort;
        $this->mailSender = $mailSender;
        $this->mailReceiver = $mailReceiver;
        $this->mailSMTPAuth = $mailSMTPAuth;
        $this->mailSMTPSecure = $mailSMTPSecure;

        $this->journals = [];

    }

    /**
     * Get event type list
     *
     * @return array The eventType list
     */
    public function listEventType()
    {
        return $this->sdoFactory->index('lifeCycle/eventFormat', 'type');
    }

    /**
     * Add an event to the journal
     * @param string $eventType       The type of the event
     * @param string $objectClass     The aimed object class
     * @param string $objectId        The aimed object id
     * @param mixed  $context         The description of the event
     * @param bool   $operationResult The operation result
     *
     * @return lifeCycle/event The new event
     */
    public function logEvent($eventType, $objectClass, $objectId, $context = null, $operationResult = true)
    {
        $event = \laabs::newInstance('lifeCycle/event');

        $event->eventId = \laabs::newId();
        $event->instanceName = \laabs::getInstanceName();

        $event->timestamp = \laabs::newTimestamp();
        $event->eventType = $eventType;
        $event->objectClass = $objectClass;
        $event->objectId = $objectId;
        $event->operationResult = $operationResult;
        $event->description = "";

        if ($accountToken = \laabs::getToken('AUTH')) {
            $event->accountId = $accountToken->accountId;

        } else {
            $event->accountId = '__system__';
        }

        if ($currentOrganization = \laabs::getToken("ORGANIZATION")) {
            $organizationController = \laabs::newController('organization/organization');
            $organization = $organizationController->read($currentOrganization->ownerOrgId);

            $event->orgRegNumber = $organization->registrationNumber;
            $event->orgUnitRegNumber = $currentOrganization->registrationNumber;
        }

        $arrayToMerge[] = (string) $event->eventId;
        $arrayToMerge[] = $event->eventType;
        $arrayToMerge[] = (string) $event->timestamp;
        $arrayToMerge[] = (string) $event->accountId;
        $arrayToMerge[] = $event->objectClass;
        $arrayToMerge[] = (string) $event->objectId;
        $arrayToMerge[] = $event->operationResult;
        $arrayToMerge[] = $event->description; // 8th line

        $eventInfo = array();

        // Event info from position 9 to ...
        if ($context) {
            if (is_object($context)) {
                $context = get_object_vars($context);
            }

            if (!isset($this->eventFormats[$event->eventType])) {
                throw \laabs::newException("lifeCycle/journalException", "Unknown event type: ".$event->eventType);
            }

            $eventFormat = $this->eventFormats[$event->eventType];
            foreach ($eventFormat->format as $item) {
                if (isset($context[$item])) {
                    $eventInfo[] = $context[$item];
                    $arrayToMerge[] = $context[$item];
                } else {
                    $eventInfo[] = "";
                    $arrayToMerge[] = "";
                }
            }
        }

        if (!empty($eventInfo)) {
            $event->eventInfo = json_encode($eventInfo);
        }

        $event->description = vsprintf($eventFormat->message, $arrayToMerge);

        $this->sdoFactory->create($event);

        if (!$operationResult) {
            $this->sendMail($event);
        }

        return $event;
    }

    /**
     * Retrieve an archive event in a lifeCycle journal
     * @param mixed  $date   The journal date
     * @param string $needle The string to search in the journal
     *
     * @return lifeCycle/event[]
     */
    public function matchEvent($date, $needle)
    {
        $events = [];
        $logController = \laabs::newController('recordsManagement/log');

        $journal = $logController->getByDate('lifeCycle', (string) $date);

        if (!$journal) {
            return $events;
        }

        if (!isset($this->journals[(string) $journal->archiveId])) {
            $archiveController = \laabs::newController('recordsManagement/archive');
            $journalDocuments = $archiveController->getDocuments($journal->archiveId);
            $this->journals[(string) $journal->archiveId] = null;

            foreach ($journalDocuments as $document) {
                if ($document->type == "CDO") {
                    $this->journals[(string) $journal->archiveId] = $document->digitalResource->getContents();
                    break;
                }
            }
        }

        $journalFile = $this->journals[(string) $journal->archiveId];
        $offset = 0;

        do {
            $offset = strpos($journalFile, (string) $needle, $offset);

            if ($offset) {
                $journalLength = strlen($journalFile);
                $startOffset = strrpos($journalFile, "\n", -$journalLength + $offset) + 1;
                $endOffset = strpos($journalFile, "\n", $startOffset);
                $eventLine = substr($journalFile, $startOffset, $endOffset - $startOffset);

                $events[] = $this->getEventFromLine($eventLine);
                $offset = $endOffset;
            }
        } while ($offset);

        return $events;
    }

    /**
     * Get the events for a given object id and class
     * @param string $objectId    The identifier of the object
     * @param string $objectClass The class of the object
     * @param mixed  $eventType   An event type or an array of event types to retrieve
     *
     * @return lifeCycle/event[]
     */
    public function getObjectEvents($objectId, $objectClass, $eventType = null)
    {
        $query = "objectId='$objectId' AND objectClass='$objectClass'";

        if ($eventType) {
            if (is_array($eventType)) {
                $query .= " AND eventType=['".\laabs\implode("', '", $eventType)."']";
            } else {
                $query .= " AND eventType='$eventType'";
            }
        }

        $events = $this->sdoFactory->find('lifeCycle/event', $query, null, 'timestamp');

        foreach ($events as $key => $event) {
            $events[$key] = $this->getEventFromJournal($event);
        }

        return $events;
    }

    /**
     * Get an events by id from journal file
     * @param mixed $eventId The event or the identifier of the event
     *
     * @return string
     */
    public function getEventFromJournal($eventId)
    {
        if (is_scalar($eventId) || get_class($eventId) == 'core\Type\Id') {
            $event = $this->sdoFactory->read('lifeCycle/event', $eventId);
        } else {
            $event = $eventId;
        }

        $logController = \laabs::newController('recordsManagement/log');

        // Read journal file
        $journalReference = $logController->getByDate('lifeCycle', $event->timestamp);

        if ($journalReference) {
            $this->openJournal($journalReference->archiveId);

            // Get the eventId position on the journal file
            $this->currentOffset = strpos($this->currentJournalFile, (string) $event->eventId);

            // Place cursor to the begin of line
            if ($this->currentOffset) {
                $endOffset = strpos($this->currentJournalFile, "\n", $this->currentOffset);
                $eventLine = substr($this->currentJournalFile, $this->currentOffset, $endOffset - $this->currentOffset);
                $this->currentOffset = $this->currentOffset + strlen($eventLine) + 1;

                // Get the event
                $event = $this->getEventFromLine($eventLine);
            } else {
                return $event;

                //throw \laabs::newException("lifeCycle/journalException", "Event can't be found.");
            }
        } else {
             $this->decodeEventFormat($event);
        }

        $this->currentEvent = $event;

        return $event;
    }

    /**
     * Search a journal event
     * @param string    $eventType      The type of the event
     * @param string    $objectClass    The object class
     * @param string    $objectId       The identifier of the object
     * @param timestamp $minDate        The minimum date of the event
     * @param timestamp $maxDate        The maximum date of the event
     * @param sring     $sortBy         The event sorting request
     * @param int       $numberOfResult The number of result
     *
     * @return array The result of the request
     */
    public function searchEvent($eventType = false, $objectClass = false, $objectId = false, $minDate = false, $maxDate = false, $sortBy = null, $numberOfResult = 300)
    {
        $query = array();
        $queryParams = array();

        if ($this->separateInstance) {
            $queryParams['instanceName'] = \laabs::getInstanceName();
            $query['instanceName'] = "instanceName = :instanceName";
        }

        if ($eventType) {
            $queryParams['eventType'] = $eventType;
            $query['eventType'] = "eventType = :eventType";
        }

        if ($objectClass) {
            $queryParams['objectClass'] = $objectClass;
            $query['objectClass'] = "objectClass = :objectClass";
        }

        if ($objectId) {
            $queryParams['objectId'] = $objectId;
            $query['objectId'] = "objectId = :objectId";
        }

        if ($minDate) {
            $queryParams['minDate'] = $minDate;
            $query['minDate'] = "timestamp >= :minDate";
        }

        if ($maxDate) {
            $queryParams['maxDate'] = $maxDate->add(new \DateInterval('PT23H59M59S'));
            $query['maxDate'] = "timestamp <= :maxDate";
        }

        $queryString = implode(' AND ', $query);

        $events = $this->sdoFactory->find('lifeCycle/event', $queryString, $queryParams, $sortBy, null, $numberOfResult);

        $users = \laabs::callService('auth/userAccount/readIndex');
        foreach ($users as $i => $user) {
            $users[(string) $user->accountId] = $user;
            unset($users[$i]);
        }

        $services = \laabs::callService('auth/serviceAccount/readIndex');
        foreach ($services as $i => $service) {
            $services[(string) $service->accountId] = $service;
            unset($services[$i]);
        }

         foreach ($events as $i => $event) {
            if (isset($event->accountId) && isset($users[(string) $event->accountId])) {
                $event->accountName = $users[(string) $event->accountId]->accountName;
            } elseif (isset($event->accountId) && isset($services[(string) $event->accountId])) {
                $event->accountName = $services[(string) $event->accountId]->accountName;
            } else {
                $event->accountName = "__system__";
            }
        }

        return $events;
    }

    /**
     * Load a journal
     * @param string $journalReference The id of the journal or the journal object
     *
     * @return boolean The result of the operation
     */
    public function openJournal($journalReference)
    {
        $logController = \laabs::newController('recordsManagement/log');

        if (is_scalar($journalReference) || get_class($journalReference) == 'core\Type\Id') {
            $journalReference = $logController->read($journalReference);
        }

        if (isset($journalReference->toDate)) {
            $archiveController = \laabs::newController('recordsManagement/archive');
            $journalDocuments = $archiveController->getDocuments($journalReference->archiveId);
            $journalFile = null;

            foreach ($journalDocuments as $document) {
                if ($document->type == "CDO") {
                    $journalFile = $document->digitalResource->getContents();
                    $this->journalCursor = 0;
                    break;
                }
            }

            if ($journalFile == null) {
                throw \laabs::newException("lifeCycle/journalException", "The journal file can't be opened");
            } else {
                $this->checkIntegrity($journalReference);
                $this->currentJournalFile = $journalFile;
                $this->currentJournalId = $journalReference->archiveId;
            }
        }

        $this->currentOffset = 0;

        return true;
    }

    /**
     * Get the next event or get the next event which contain a given item
     * @param string $eventType The event type
     * @param bool   $chain     Chain to the next journal
     *
     * @return string
     */
    public function getNextEvent($eventType = null, $chain = true)
    {
        $nextEvent = null;

        if (!$this->currentJournalFile) {
            $queryString = [];
            if ($eventType) {
                $queryString['eventType'] = "eventType='$eventType'";
            }
            $timestamp = $this->currentEvent->timestamp;

            if ($this->separateInstance) {
                $queryString['instanceName'] = "instanceName = '".\laabs::getInstanceName()."'";
            }

            $queryString['timestamp'] = "timestamp>'$timestamp'";

            $nextEvent = $this->sdoFactory->find("lifeCycle/event", implode(' and ', $queryString), null, "<timestamp", 0, 1);

            if (count($nextEvent)) {
                $nextEvent = $nextEvent[0];
                $nextEvent = $this->decodeEventFormat($nextEvent);
            } else {
                $logController = \laabs::newController('recordsManagement/log');
                $journal = $logController->getFirstJournal('lifeCycle');
                if ($journal) {
                    $this->openJournal($journal->archiveId);
                    $this->getNextEvent($eventType, $chain);

                } else {

                    $nextEvent = false;
                }

            }

        } else {
            // Place the cursor to the first event if it not positioned yet
            if ($this->currentOffset == 0) {
                $this->currentOffset = strpos($this->currentJournalFile, "\n") + 1;
            }

            // Search the event
            if ($eventType) {
                $offset = strpos($this->currentJournalFile, $eventType, $this->currentOffset);
            } else {
                $offset = $this->currentOffset;
            }

            // Read the event
            if ($offset != false) {
                $journalLength = strlen($this->currentJournalFile);

                $startOffset = strrpos($this->currentJournalFile, "\n", -$journalLength + $offset) + 1;
                $endOffset = strpos($this->currentJournalFile, "\n", $startOffset);
                $eventLine = substr($this->currentJournalFile, $startOffset, $endOffset - $startOffset);

                $this->currentOffset = $startOffset + strlen($eventLine) + 1;

                $nextEvent = $this->getEventFromLine($eventLine);
            }

            // Search on the next journal
            if ($chain && $nextEvent == null) {                
                if ($this->openNextJournal()) {
                    $nextEvent = $this->getNextEvent($eventType);
                }
            }
        }

        if ($nextEvent) {
            $this->currentEvent = $nextEvent;
        }

        return $nextEvent;
    }

    /**
     * Get event object
     * @param id   $archiveId          The archive identifier
     * @param date $searchingStartDate The searching start date
     *
     * @return lifeCycle/event
     */
    public function getEvents($archiveId, $searchingStartDate = null)
    {
        // Open the journal to start with
        $query = "type='lifeCycle'";
        $events = [];

        $logController = \laabs::newController('recordsManagement/log');
        $journal = $logController->getByDate('lifeCycle', $searchingStartDate);
        if (!count($journal)) {
            $events = $this->sdoFactory->find('lifeCycle/event', "objectClass='recordsManagement/archive' AND  objectId='$archiveId'", null, ">timestamp");
            foreach ($events as $key => $event) {
                $events[$key] = $this->decodeEventFormat($event);
            }

            return $events;
        }

        $journal = end($journal);
        $this->openJournal($journal->archiveId);


        // Searching for related events not in the journal yet
        $events = $this->sdoFactory->find('lifeCycle/event', "objectClass='recordsManagement/archive' AND  objectId='$archiveId' AND timestamp>'$journal->toDate'", null, ">timestamp");
        foreach ($events as $key => $event) {
            $events[$key] = $this->decodeEventFormat($event);
        }

        // Searching for related events
        $offset = 0;
        $nextEvent = 0;
        $lastJournal = false;

        while ($lastJournal == false) {
            if ($nextEvent != null) {
                $offset = strpos($this->currentJournalFile, "\n", $offset);
            }

            if ($this->currentJournalId) {
                $offset = strpos($this->currentJournalFile, (string) $archiveId, $offset);
            }

            // Read the event in the file
            if ($offset != false) {
                $journalLength = strlen($this->currentJournalFile);

                $startOffset = strrpos($this->currentJournalFile, "\n", -$journalLength + $offset) + 1;
                $endOffset = strpos($this->currentJournalFile, "\n", $startOffset);
                $eventLine = substr($this->currentJournalFile, $startOffset, $endOffset - $startOffset);

                $this->currentOffset = $startOffset + strlen($eventLine) + 1;
                $nextEvent = $this->getEventFromLine($eventLine);
                $events[] = $nextEvent;
            } else {
                if (!$this->openNextJournal()) {
                    $lastJournal = true;
                } else {
                    $offset = 0;
                }
            }
        }

        return $events;
    }

    /**
     * Get the previous event or get the previous event whitch contain a givven item
     * @param string $eventItem The event item to search
     * @param bool   $chain     Chain to the previous journal
     *
     * @return string
     */
    public function getPreviousEvent($eventItem = null, $chain = true)
    {
        $event = null;

        // Open a journal if there is not any
        if (!$this->currentJournalFile) {
            return false;
        }

        // Place the cursor to the first event if it not positioned yet
        if ($this->currentOffset == 0) {
            if ($this->openPreviousJournal()) {
                return $event = $this->getPreviousEvent($eventItem);
            } else {
                return null;
            }
        }

        $journalLength = strlen($this->currentJournalFile);

        // Search the event
        if ($eventItem) {
            $offset = strrpos($this->currentJournalFile, $eventItem, $this->currentOffset - $journalLength);
        } else {
            $offset = $this->currentOffset - 2;
        }

        // Read the event
        if ($offset != false) {
            $startOffset = strrpos($this->currentJournalFile, "\n", $offset - $journalLength) + 1;
            $endOffset = strpos($this->currentJournalFile, "\n", $startOffset);
            $eventLine = substr($this->currentJournalFile, $startOffset, $endOffset - $startOffset);

            $this->currentOffset = $startOffset - 1;

            $this->getEventFromLine($eventLine);
        }

        // Search on the next journal
        if ($chain && $event == null) {
            if ($this->openPreviousJournal()) {
                $event = $this->getPreviousEvent($eventItem);
            }
        }

        return $event;
    }


    /**
     * Get the last usable journal
     *
     * @return lifeCycle/journal The journal object
     */
    public function getLastJournal()
    {
        $logController = \laabs::newController('recordsManagement/log');
        $journals = $logController->query("type='lifeCycle'", ">fromDate", 1);

        if (empty($journals)) {
            return null;
        }

        $journal = end($journals);

        return $journal;
    }

    /**
     * Load the previous journal
     *
     * @return string The opened journalId
     */
    public function openPreviousJournal()
    {
        $journalId = null;

        if ($this->currentJournalFile) {
            $this->currentOffset = strpos($this->currentJournalFile, "\n");
            $eventLine = substr($this->currentJournalFile, 0, -2);

            $journalArray = str_getcsv($eventLine);

            if (count($journalArray) < 7) {
                if (count($journalArray)) {
                    $journalId = $journalArray[3];
                    $hashAlgorithm = $journalArray[4];
                    $hash = $journalArray[5];
                    $this->openJournal($journalId);

                    $currentHash = hash($hashAlgorithm, $currentJournalFile);
                    if ($currentHash != $hash) {
                        throw \laabs::newException("lifeCycle/journalException", "Journal hash is incorrect.");
                    }

                    $this->currentOffset = strrpos($this->currentJournalFile, "\n", -2);
                }

            } else {
                if (count($journalArray)) {
                    $journalId = $journalArray[8];
                    $hashAlgorithm = $journalArray[9];
                    $hash = $journalArray[10];
                    $this->openJournal($journalId);

                    $currentHash = hash($hashAlgorithm, $currentJournalFile);
                    if ($currentHash != $hash) {
                        throw \laabs::newException("lifeCycle/journalException", "Journal hash is incorrect.");
                    }

                    $this->currentOffset = strrpos($this->currentJournalFile, "\n", -2);
                }
            }

        }

        return $journalId;
    }

    /**
     * Load the next journal
     *
     * @return string The opened jounalId
     */
    public function openNextJournal()
    {
        $journalId = null;

        if ($this->currentJournalId) {
            $this->currentOffset = strpos($this->currentJournalFile, "\n");
            // Last event
            //$eventLine = substr($this->currentJournalFile, 0, -2);
            $eventLines = \laabs\explode("\n", $this->currentJournalFile);
            $journalArray = str_getcsv(end($eventLines));
            $journalTimestamp = $journalArray[2];

            $logController = \laabs::newController('recordsManagement/log');

            $nexJournal = $logController->getNextJournal($this->currentJournalId);

            if (isset($nextJournal)) {
                $this->openJournal($nexJournal->archiveId);
                $journalId = $nexJournal->archiveId;
            }
        }

        return $journalId;
    }

    /**
     * Get the current journal
     * @param string  $journalId The journal identifier
     * @param integer $offset    The reading offset
     * @param integer $limit     The maximum number of event to load
     *
     * @return lifeCycle/event[]
     */
    public function readJournal($journalId, $offset = 0, $limit = 300)
    {
        $this->openJournal($journalId);

        $events = array();

        while ($limit > 0 && $event = $this->getNextEvent(null, false)) {
            $events[] = $event;
            $limit--;
        }

        return $events;
    }

    /**
     * Check integrity
     * @param string $archiveId
     *
     * @return bool
     */
    public function checkIntegrity($archiveId)
    {
        $logController = \laabs::newController('recordsManagement/log');
        $archiveController = \laabs::newController('recordsManagement/archive');

        // Read journal
        if (is_scalar($archiveId) || get_class($archiveId) == 'core\Type\Id') {
            $journal = $logController->read($archiveId);

        } else {
            $journal = $archiveId;
            $archiveId = (string) $journal->archiveId;
        }

        if ($journal->type != 'lifeCycle') {
            return true;
        }

        $journalDocument = $archiveController->getDocuments($journal->archiveId)[0];

        $nextJournal = $logController->getNextJournal($journal);

        // Journal is the last... simply check its hash against min rotate
        if ($nextJournal == null) {
            $now = \laabs::newTimestamp();

            $diff = $journal->toDate->diff($now);

            // In the future ????
            if ($diff->invert) {
                throw \laabs::newException('recordsManagement/journalException', "Invalid journal date: latest date is in the future.");
            }

            return true;
        }

        $nextJournalDocument = $archiveController->getArchiveDocument($nextJournal->archiveId);
        $nextJournalContents = $nextJournalDocument->digitalResource->getContents();

        $chainEvent = explode(',', strtok($nextJournalContents, "\n"));

        // For older version compatibility
        if (count($chainEvent) < 7) {
            if (empty($chainEvent[3]) || empty($chainEvent[4]) || empty($chainEvent[5])) {
                throw \laabs::newException('recordsManagement/journalException', "Invalid journal: Next journal chaining event is incomplete.");
            }

            $chainedJournalId = $chainEvent[3];
            if ($chainedJournalId != $archiveId) {
                throw \laabs::newException('recordsManagement/journalException', "Invalid journal: Next journal is missing or chaining event has an invalid journal identifier.");
            }

            $chainedJournalHashAlgo = $chainEvent[4];
            $chainedJournalHash = $chainEvent[5];

            $calcJournalHash = hash($chainedJournalHashAlgo, $journalDocument->digitalResource->getContents());

            if ($calcJournalHash != $chainedJournalHash) {
                throw \laabs::newException('recordsManagement/journalException', "Invalid journal: Chaining event has a different hash.");
            }

        } else {
            if (empty($chainEvent[8]) || empty($chainEvent[9]) || empty($chainEvent[10])) {
                throw \laabs::newException('recordsManagement/journalException', "Invalid journal: Next journal chaining event is incomplete.");
            }

            $chainedJournalId = $chainEvent[8];
            if ($chainedJournalId != $archiveId) {
                throw \laabs::newException('recordsManagement/journalException', "Invalid journal: Next journal is missing or chaining event has an invalid journal identifier.");
            }

            $chainedJournalHashAlgo = $chainEvent[9];
            $chainedJournalHash = $chainEvent[10];

            $calcJournalHash = hash($chainedJournalHashAlgo, $journalDocument->digitalResource->getContents());

            if ($calcJournalHash != $chainedJournalHash) {
                throw \laabs::newException('recordsManagement/journalException', "Invalid journal: Chaining event has a different hash.");
            }
        }

        return $chainEvent;
    }

    /**
     * Chain the last journal
     *
     * @return string The chained journal file name
     */
    public function chainJournal()
    {
        $tmpdir = \laabs::getTmpDir();
        $timestampFileName = null;
        $logController = \laabs::newController('recordsManagement/log');

        $newJournal = \laabs::newInstance('recordsManagement/log');
        $newJournal->archiveId = \laabs::newId();
        $newJournal->type = "lifeCycle";
        $newJournal->toDate = \laabs::newTimestamp();

        $previousJournal = $logController->getLastJournal('lifeCycle');

        if ($previousJournal) {
            $newJournal->fromDate = $previousJournal->toDate;
            $newJournal->previousJournalId = $previousJournal->archiveId;

            $queryString = "timestamp > '$newJournal->fromDate' AND timestamp <= '$newJournal->toDate'";

            if ($this->separateInstance) {
                $queryString .= "AND instanceName = '".\laabs::getInstanceName()."'";
            }

            $events = $this->sdoFactory->find('lifeCycle/event', $queryString, null, "<timestamp");

        } else {
            // No previous journal, select all events
            $events = $this->sdoFactory->find('lifeCycle/event', "timestamp <= '$newJournal->toDate'", null, "<timestamp");
            if (count($events) > 0) {
                $newJournal->fromDate = reset($events)->timestamp;
            } else {
                $newJournal->fromDate = \laabs::newTimestamp('1970-01-01');
            }
        }

        $journalFilename = $tmpdir.DIRECTORY_SEPARATOR.(string) $newJournal->archiveId.".csv";
        $journalFile = fopen($journalFilename, "w");
        fprintf($journalFile, chr(0xEF).chr(0xBB).chr(0xBF));

        // Journal format information
        $format = [];
        $format['journalChainingEvent'] = ['journalId', 'eventType', 'journalStartingTimestamp', 'journalClosureTimestamp', '', '', '', '', 'previousJournalId', 'hashAlgorithm', 'previousJournalHash'];
        $format['events'] = ['journalId', 'eventType', 'timestamp', 'orgRegNumber', 'orgUnitRegNumber', 'accountId', 'objectClass', 'objectId', 'operationResult', 'description', 'eventInfo'];
        $format['eventInfo'] = $this->eventFormats;
        $format = json_encode($format);

        // First event : chain with previous journal
        $eventLine = array();
        $eventLine[0] = (string) $newJournal->archiveId;
        $eventLine[1] = "lifeCycle/chainJournal";

        $eventLine[2] = (string) $newJournal->fromDate;
        $eventLine[3] = (string) $newJournal->toDate;
        $eventLine[4] = $eventLine[5] = $eventLine[6] = $eventLine[7] = "";

        // Write previous journal informations
        if ($previousJournal) {
            $eventLine[8] = (string) $previousJournal->archiveId;

            $digitalResourceController = \laabs::newController('digitalResource/digitalResource');
            $journalResource = $digitalResourceController->getResources($previousJournal->archiveId)[0];

            $eventLine[9] = (string) $journalResource->hashAlgorithm;
            $eventLine[10] = (string) $journalResource->hash;
        }

        fputcsv($journalFile, $eventLine);

        // Write events
        foreach ($events as $event) {
            $eventLine = array();

            $eventLine[] = (string) $event->eventId;
            $eventLine[] = (string) $event->eventType;
            $eventLine[] = (string) $event->timestamp;
            $eventLine[] = (string) $event->accountId;
            $eventLine[] = (string) $event->objectClass;
            $eventLine[] = (string) $event->objectId;
            $eventLine[] = $event->operationResult ? '1' : '0';
            $eventLine[] = (string) $event->description;

            $event->eventInfo = json_decode($event->eventInfo);
            if (is_array($event->eventInfo)) {
                $eventLine = array_merge($eventLine, $event->eventInfo);
            }

            fputcsv($journalFile, $eventLine);
        }

        fclose($journalFile);

        // create timestamp file
        if ($this->signatureScript) {
            include $this->signatureScript;
            try {
                $timestampFileName = getTimestamp($journalFilename);
            } catch (\Exception $e) {
                throw $e;
            }
        }

        return $logController->archiveJournal($journalFilename, $newJournal, $timestampFileName);
    }

    /**
     * Get an event from a csv line
     * @param string $eventLine The scv line from the journal
     *
     * @return lifeCycle/event The event
     */
    private function getEventFromLine($eventLine)
    {
        $eventArray = str_getcsv($eventLine);

        if (count($eventArray) < 7) {
            return null;
        }

        $event = \laabs::newInstance('lifeCycle/event');

        $event->eventId = \laabs::newId($eventArray[0]);
        $event->eventType = $eventArray[1];
        $event->timestamp = \laabs::newTimestamp($eventArray[2]);
        $event->accountId = $eventArray[3];
        $event->objectClass = $eventArray[4];
        $event->objectId = $eventArray[5];
        $event->operationResult = $eventArray[6] === '0' ? false : true;
        $event->description = $eventArray[7];

        try {
            $i = 8;
            if (!isset($this->eventFormats[$event->eventType])) {
                throw \laabs::newException("lifeCycle/journalException", "Unknown event type.");
            }
            $eventFormat = $this->eventFormats[$event->eventType]->format;

            foreach ($eventFormat as $item) {
                if (isset($eventArray[$i])) {
                    $event->{$item} = $eventArray[$i];
                } else {
                    $event->{$value} = null;
                }
                $i++;
            }
        } catch (\Exception $e) {
        }

        return $event;
    }

    /**
     * Decode events format object from an event
     * @param lifeCycle/event $event The event to decode
     */
    protected function decodeEventFormat($event) {
        if (isset($event->eventInfo)) {
            if (!isset($this->eventFormats[$event->eventType])) {
                throw \laabs::newException("lifeCycle/journalException", "Unknown event type.");
            }

            $eventFormat = $this->eventFormats[$event->eventType]->format;
            $i = 0;

            $event->eventInfo = json_decode($event->eventInfo);
            foreach ($eventFormat as $item) {
                if (isset($event->eventInfo[$i])) {
                    $event->{$item} = $event->eventInfo[$i];
                } else {
                    $event->{$item} = null;
                }
                $i++;
            }
        }
        unset($event->eventInfo);

        return $event;
    }

    /**
     * Send email
     * @param lifeCycle/event $event The event
     */
    private function sendMail($event)
    {
        if (!$this->mailHost || !$this->mailPassword || !$this->mailPort || !$this->mailUsername || !$this->mailReceiver || !$this->mailSender || !$this->mailSMTPAuth || !$this->mailSMTPSecure) {
            return null;
        }

        require_once 'bundle/lifeCycle/PHPMailer-master/PHPMailerAutoload.php';

        $mail = new \PHPMailer();
        $mail->isSMTP();
        $mail->Host = $this->mailHost;
        $mail->SMTPAuth = $this->mailSMTPAuth;
        $mail->Username = $this->mailUsername;
        $mail->Password = $this->mailPassword;
        $mail->SMTPSecure = $this->mailSMTPSecure;
        $mail->Port = $this->mailPort;

        $mail->setFrom($this->mailSender);
        $mail->addAddress($this->mailReceiver);

        $mail->Subject = 'Life cycle error';
        $mail->Body = "Error on event '<b>$event->eventId</b>' of type '<b>$event->eventType</b>'<br/>";
        $mail->Body .= "The object '<b>$event->objectId</b>' of class '<b>$event->objectClass</b>'<br/>";
        $mail->Body .= "Description : $event->description";

        $mail->AltBody = "Error on event '$event->eventId' of type '$event->eventType'\n";
        $mail->AltBody .= "The object '$event->objectId' of class '$event->objectClass'\n";
        $mail->AltBody .= "Description : $event->description";

        $mail->send();
    }
}
