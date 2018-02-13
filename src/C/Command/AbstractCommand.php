<?php

namespace C\Command;

use Doctrine\Common\Util\Inflector;
use Knp\Command\Command;

abstract class AbstractCommand extends Command
{
    /**
     * Commands namespace
     */
    const PREFIX = 'chillter';

    /**
     * Set generated command name
     */
    protected function configure()
    {
        $this->setName($this->generateCommandName());

        parent::configure();
    }

    /**
     * Generate command name from extending class name
     *
     * @return string
     */
    protected function generateCommandName()
    {
        $commandName = self::PREFIX;
        $className = get_class($this);
        $start = strpos($className, 'Command') + strlen('Command') + 1;
        $className = substr($className, $start);
        $className = substr($className, 0, strlen($className) - strlen('Command'));

        foreach (explode('\\', $className) as $part) {
            $commandName .= ':' . Inflector::tableize($part);
        }

        return $commandName;
    }

    /**
     * @return \Doctrine\DBAL\Connection
     */
    protected function getConnection()
    {
        return $this->getSilexApplication()['db'];
    }
}
