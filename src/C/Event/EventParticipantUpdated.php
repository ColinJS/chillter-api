<?php

namespace C\Event;

class EventParticipantUpdated extends AbstractEvent
{
    use EventTrait;
    use ParticipantTrait;

    /**
     * @var int
     */
    protected $participationStatus;

    /**
     * EventCreated constructor.
     * @param $eventId
     * @param $participantId
     * @param $participationStatus
     */
    public function __construct($eventId, $participantId, $participationStatus)
    {
        $this->checkPropertyExistence('eventId');
        $this->checkPropertyExistence('participantId');

        $this->eventId = (int)$eventId;
        $this->participantId = (int)$participantId;
        $this->participationStatus = (int)$participationStatus;
    }

    /**
     * @return int
     */
    public function getParticipationStatus()
    {
        return $this->participationStatus;
    }
}
