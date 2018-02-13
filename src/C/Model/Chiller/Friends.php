<?php

namespace C\Model\Chiller;

use C\Model\Chiller;
use C\Traits\EntityTrait;

class Friends
{
    const STATUS_PENDING = 0;
    const STATUS_CONFIRMED = 1;
    const STATUS_REMOVED = 2;

    use EntityTrait;

    /**
     * @var Chiller
     */
    protected $first;

    /**
     * @var Chiller
     */
    protected $second;

    /**
     * @var Chiller|null
     */
    protected $inviting;

    /**
     * @var int|null
     */
    protected $status;

    /**
     * @return Chiller
     */
    public function getFirst()
    {
        return $this->first;
    }

    /**
     * @param Chiller $first
     * @return Friends
     */
    public function setFirst($first)
    {
        $this->first = $first;
        return $this;
    }

    /**
     * @return Chiller
     */
    public function getSecond()
    {
        return $this->second;
    }

    /**
     * @param Chiller $second
     * @return Friends
     */
    public function setSecond($second)
    {
        $this->second = $second;
        return $this;
    }

    /**
     * @return Chiller|null
     */
    public function getInviting()
    {
        return $this->inviting;
    }

    /**
     * @param Chiller|null $inviting
     * @return Friends
     */
    public function setInviting($inviting)
    {
        $this->inviting = $inviting;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param int|null $status
     * @return Friends
     */
    public function setStatus($status)
    {
        $this->status = (int)$status;

        return $this;
    }
}
