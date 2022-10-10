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
class eventNotifier
{
    protected $sdoFactory;
    protected $notifications;
    protected $notificationController;

    /**
     * Constructs a new event notifier
     */
    public function __construct(\dependency\sdo\Factory $sdoFactory, $notifications = [])
    {
        $this->sdoFactory = $sdoFactory;
        $this->notifications = $notifications;
        $this->notificationController = \laabs::newController('batchProcessing/notification');
    }

    /**
     * Sends a notification or not
     * @param lifeCycle/event $event
     * 
     */
    public function dispatch($event)
    {
        $notifications = $this->selectNotifications($event);

        foreach ($notifications as $notification) {
            $this->sendNotification($notification, $event);
        }
    }

    protected function selectNotifications($event)
    {
        $notifications = [];

        foreach ($this->notifications as $notification) {
            // check if a rule matches the event
            if (!isset($notification['filter']) || empty($notification['filter'])) {
                continue;
            }

            if (!$this->matchFilter($notification['filter'], $event)) {
                continue;
            }

            $notifications[] = $notification;
        }

        return $notifications;
    }

    protected function matchFilter($filter, $event)
    {
        foreach ($filter as $name => $value) {
            if (!$this->matchAssert($event, $name, $value)) {
                return;
            }
        }

        return true;
    }

    protected function matchAssert($event, $name, $value)
    {
        if (is_scalar($value)) {
            return ($event->{$name} == $value);
        }
    }

    protected function sendNotification($notification, $event)
    {
        if (is_array($notification['recipients'])) {
            $recipients = $notification['recipients'];
        } else {
            $recipients = include $notification['recipients'];
        }

        if (empty($recipients)) {
            return;
        }

        $subject = $notification['title'] ?? 'Notification';
        $message = '';
        if (isset($notification['message'])) {
            if (is_string($notification['message'])) {
                $message = $notification['message'];
            } else {
                $template = \laabs::newService('dependency/html/Document');
                $template->setSource('event', $event);
                $template->setSource('recipients', $recipients);

                $message = $this->composeMessage($notification['message'], $template, $event);
            }
        }

        $this->notificationController->create($subject, $message, $recipients);
    }

    protected function composeMessage($message, $template, $event)
    {
        $htmlTemplate = file_get_contents($message['template']);

        $template->loadHTML($htmlTemplate);

        if (isset($message['sources'])) {
            $sources = include $message['sources'];
            foreach ($sources as $name => $source) {
                $template->setSource($name, $source);
            }
        }

        $template->merge();

        $message = $template->saveHTML();

        return $message;
    }
}