<?php

namespace C\Event;

trait ParticipantTrait
{
    /**
     * @var int
     */
    protected $participantId;

    /**
     * @return int
     */
    public function getParticipantId()
    {
        return $this->participantId;
    }
}
