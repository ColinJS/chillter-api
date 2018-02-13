<?php

namespace C\Event;

use Doctrine\Common\Inflector\Inflector;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Translation\Exception\LogicException;

abstract class AbstractEvent extends Event
{
    /**
     * @return array
     */
    public function toArray()
    {
        $data = [
            'event' => $this->getEventName()
        ];

        foreach (get_object_vars($this) as $key =>  $value) {
            $data[Inflector::tableize($key)] = $value;
        }

        return $data;
    }

    /**
     * @return string
     */
    protected function getEventName()
    {
        $name = explode('\\', get_class($this));

        return 'chillter.' . Inflector::tableize(array_pop($name));
    }

    /**
     * @param $propertyName
     */
    protected function checkPropertyExistence($propertyName)
    {
        if (!property_exists($this, $propertyName)) {
            throw new LogicException("Class " . get_class($this) . " must have property \"$propertyName\".");
        }
    }
}
