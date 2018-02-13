<?php

namespace C\Event;

class EventListElement extends AbstractEvent
{
    use EventTrait;
    use ParticipantTrait;

    const ELEMENT_CREATE = 'element_create';
    const ELEMENT_REMOVE = 'element_remove';
    const ELEMENT_TAKEN = 'element_take';
    const ELEMENT_LEAVE = 'element_leave';

    /**
     * @var string
     */
    protected $action;

    /**
     * EventListElement constructor
     *
     * @param $eventId int
     * @param $participantId int
     * @param $action string
     */
    public function __construct($eventId, $participantId, $action)
    {
        $this->checkPropertyExistence('eventId');
        $this->checkPropertyExistence('participantId');

        $this->eventId = (int)$eventId;
        $this->participantId = (int)$participantId;

        if (!in_array($action, (new \ReflectionClass($this))->getConstants())) {
            throw new \LogicException('Invalid "action" argument.');
        }

        $this->action = $action;
    }

    /**
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }
}
