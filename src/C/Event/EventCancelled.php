<?php

namespace C\Event;

class EventCancelled extends AbstractEvent
{
    use EventTrait;

    /**
     * EventCancelled constructor.
     * @param $eventId
     */
    public function __construct($eventId)
    {
        $this->checkPropertyExistence('eventId');

        $this->eventId = (int)$eventId;
    }
}
