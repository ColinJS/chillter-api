<?php

namespace C\Event;

abstract class AbstractEventCarSeat extends AbstractEvent
{
    use PassengerTrait;

    use EventTrait;

    use CarTrait;

    /**
     * EventCarGetIn constructor.
     *
     * @param $eventId
     * @param $carId
     * @param $passengerId
     */
    public function __construct($eventId, $carId, $passengerId)
    {
        $this->checkPropertyExistence('passengerId');
        $this->checkPropertyExistence('eventId');
        $this->checkPropertyExistence('carId');

        $this->passengerId = $passengerId;
        $this->eventId = $eventId;
        $this->carId = $carId;
    }
}
