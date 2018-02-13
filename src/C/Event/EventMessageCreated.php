<?php

namespace C\Event;

class EventMessageCreated extends AbstractEvent
{
    use EventTrait;
    use ParticipantTrait;

    /**
     * @var array
     */
    protected $excludedUserIds = array();

    /**
     * @var string
     */
    protected $eventName;

    /**
     * EventMessageCreated constructor.
     * @param $eventId
     * @param $eventName
     * @param $participantId
     * @param array $excludedUserIds
     */
    public function __construct($eventId, $eventName, $participantId, array $excludedUserIds)
    {
        $this->checkPropertyExistence('eventId');

        $this->eventId = (int)$eventId;
        $this->eventName = $eventName;
        $this->participantId = (int)$participantId;
        $this->excludedUserIds = $excludedUserIds;
    }

    /**
     * @return array
     */
    public function getExcludedUserIds()
    {
        return $this->excludedUserIds;
    }
}
