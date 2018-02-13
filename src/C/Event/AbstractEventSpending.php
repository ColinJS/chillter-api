<?php

namespace C\Event;

abstract class AbstractEventSpending extends AbstractEvent
{
    use EventTrait;

    use ParticipantTrait;

    /**
     * EventCarAbstract constructor
     *
     * @param $eventId
     * @param $participantId
     */
    public function __construct($eventId, $participantId)
    {
        $this->checkPropertyExistence('eventId');
        $this->checkPropertyExistence('participantId');

        $this->eventId = $eventId;
        $this->participantId = $participantId;
    }
}
