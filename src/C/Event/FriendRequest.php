<?php

namespace C\Event;

class FriendRequest extends AbstractEvent
{
    /**
     * @var int
     */
    protected $friendRelationId;

    /**
     * FriendRequest constructor
     *
     * @param $friendRelationId
     */
    public function __construct($friendRelationId)
    {
        $this->friendRelationId = (int)$friendRelationId;
    }

    /**
     * @return int
     */
    public function getFriendRelationId()
    {
        return $this->friendRelationId;
    }
}
