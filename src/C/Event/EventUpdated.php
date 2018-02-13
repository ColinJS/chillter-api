<?php

namespace C\Event;

class EventUpdated extends AbstractEvent
{
    use EventTrait;

    /**
     * EventCreated constructor.
     * @param $eventId
     */
    public function __construct($eventId)
    {
        $this->checkPropertyExistence('eventId');

        $this->eventId = (int)$eventId;
    }
}
