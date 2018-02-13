<?php

namespace C\Event;

trait PassengerTrait
{
    /**
     * @var int
     */
    protected $passengerId;

    /**
     * @return int
     */
    public function getPassengerId()
    {
        return $this->passengerId;
    }
}
