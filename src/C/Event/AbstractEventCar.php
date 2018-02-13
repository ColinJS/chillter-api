<?php

namespace C\Event;

abstract class AbstractEventCar extends AbstractEvent
{
    use EventTrait;

    /**
     * @var int
     */
    protected $driverId;

    /**
     * EventCarAbstract constructor
     *
     * @param $eventId
     * @param $driverId
     */
    public function __construct($eventId, $driverId)
    {
        $this->checkPropertyExistence('eventId');

        $this->eventId = $eventId;
        $this->driverId = $driverId;
    }

    /**
     * @return int
     */
    public function getDriverId()
    {
        return $this->driverId;
    }
}
