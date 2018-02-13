<?php

namespace C\Event;

trait EventTrait
{
    /**
     * @var int
     */
    protected $eventId;

    /**
     * Get event ID
     *
     * @return int
     */
    public function getEventId()
    {
        return $this->eventId;
    }
}
