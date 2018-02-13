<?php

namespace OneSignal\Logger;

use Doctrine\DBAL\Connection;
use Psr\Log\AbstractLogger;

class SQLLogger extends AbstractLogger
{
    /**
     * @var Connection
     */
    protected $db;

    /**
     * SQLLogger constructor
     *
     * @param Connection $db
     */
    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * @param string $level
     * @param string $message
     * @param array $context
     */
    public function log($level, $message, array $context = array())
    {
        $this->db->insert('onesignal', array_merge($context, [
            'datetime' => (new \DateTime())->format('c')
        ]));
    }
}
