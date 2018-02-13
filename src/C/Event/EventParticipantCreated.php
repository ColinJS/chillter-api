<?php

namespace C\Event;

class EventParticipantCreated extends AbstractEvent
{
    use EventTrait;
    use ParticipantTrait;

    /**
     * @var int
     */
    protected $invitingChillerId;

    /**
     * EventCreated constructor.
     * @param $eventId
     * @param $participantId
     * @param $invitingChillerId
     */
    public function __construct($eventId, $participantId, $invitingChillerId)
    {
        $this->checkPropertyExistence('eventId');
        $this->checkPropertyExistence('participantId');

        $this->eventId = (int)$eventId;
        $this->participantId = (int)$participantId;
        $this->invitingChillerId = (int)$invitingChillerId;
    }

    /**
     * Get inviting chiller ID
     *
     * @return int
     */
    public function getInvitingChillerId()
    {
        return $this->invitingChillerId;
    }
}
