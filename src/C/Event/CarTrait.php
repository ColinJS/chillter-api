<?php

namespace C\Event;

trait CarTrait
{
    /**
     * @var int
     */
    protected $carId;

    /**
     * @return int
     */
    public function getCarId()
    {
        return $this->carId;
    }
}
