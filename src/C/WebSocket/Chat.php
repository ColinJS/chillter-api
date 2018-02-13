<?php

namespace C\WebSocket;

use C\Event\EventMessageCreated;
use C\Provider\EventListenerProvider;
use C\Resolver\ImageResolverInterface;
use Doctrine\DBAL\Connection;
use Guzzle\Http\Message\Header;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Ratchet\WebSocket\Version\RFC6455\Connection as SocketConnection;

class Chat implements MessageComponentInterface
{
    protected $clients;

    /**
     * @var Connection
     */
    protected $db;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var ImageResolverInterface
     */
    protected $imageResolver;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * Chat constructor
     *
     * @param OutputInterface $output
     * @param Connection $connection
     * @param EventDispatcherInterface $eventDispatcher
     * @param ImageResolverInterface $imageResolver
     */
    public function __construct(
        OutputInterface $output,
        Connection $connection,
        EventDispatcherInterface $eventDispatcher,
        ImageResolverInterface $imageResolver
    ) {
        $this->db = $connection;
        $this->eventDispatcher = $eventDispatcher;
        $this->imageResolver = $imageResolver;
        $this->output = $output;
        $this->clients = [];
    }

    /**
     * @param ConnectionInterface $conn
     */
    public function onOpen(ConnectionInterface $conn)
    {
        /** @var SocketConnection $conn */
        $eventId = (int)$conn->WebSocket->request->getQuery()->get('eventId');
        $this->output->writeln("[{$conn->resourceId}] New connection!");
        $this->output->writeln("[{$conn->resourceId}] Client is requesting chat for event ID: $eventId");

        $token = null;
        $tokenHeader = $conn->WebSocket->request->getHeader('sec-websocket-protocol');

        if (!$tokenHeader instanceof Header) {
            $this->output->writeln("[{$conn->resourceId}] Client did not send header. Closing connection!");
            $conn->close();
        }

        foreach ($tokenHeader as &$value) {
            $value = urldecode($value);

            if (0 === strpos($value, 'Bearer ')) {
                $token = substr($value, strlen('Bearer '));
                break;
            }
        }

        if (!$token) {
            $this->output->writeln("[{$conn->resourceId}] Client did not send token in the header. Closing connection!");
            $conn->close();

            return;
        }

        $userId = (int)$this->db->fetchColumn("SELECT `id` FROM `chiller` WHERE `bearer` = ? ", [ $token ]);

        if (!$userId) {
            $this->output->writeln("[{$conn->resourceId}] Client did not send valid token. Closing connection!");
            $conn->close();

            return;
        }

        $this->output->writeln("[{$conn->resourceId}] Client sent valid token and is authenticated as user (ID: $userId).");

        $isParticipant = '1' === $this->db->fetchColumn("SELECT 1 FROM `event_participant` WHERE `eventid` = ? AND `chillerid` = ? ", [ $eventId, $userId ]);

        if (!$isParticipant) {
            $this->output->writeln("[{$conn->resourceId}] User (ID: $userId) is not a participant of event (ID: $eventId). Closing connection!");
            $conn->close();

            return;
        }

        $this->output->writeln("[{$conn->resourceId}] User (ID: $userId) is a participant of event (ID: $eventId).");

        if (!array_key_exists("event:$eventId", $this->clients)) {
            $this->clients["event:$eventId"] = [];
        }

        $this->clients["event:$eventId"][$userId] = $conn;

        $collection = [];

        $query = <<<SQL
            SELECT
                c.`id` as 'user_id',
                c.`firstname` as 'user_firstname',
                c.`lastname` as 'user_lastname',
                m.`message`,
                m.`creation_date`,
                p.`url`
            FROM `event_message` m
            LEFT JOIN `chiller` c ON c.id = m.`chiller_id`
            LEFT JOIN `chiller_photo` p ON c.`id` = p.`userid` AND p.`statut` = 1
            WHERE m.`event_id` = ?
SQL;

        foreach ($this->db->fetchAll($query, [ $eventId ]) as $message) {
            $collection[] = [
                'user' => [
                    'id' => $message['user_id'],
                    'firstname' => $message['user_firstname'],
                    'lastname' => $message['user_lastname'],
                    'picture' => $message['url'] ? $this->imageResolver->resolve($message['url']) : null,
                ],
                'creation_date' => (new \DateTime($message['creation_date']))->format('c'),
                'content' => $message['message']
            ];
        }

        $conn->send(\GuzzleHttp\json_encode($collection));
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        /** @var SocketConnection $from */
        $eventId = (int)$from->WebSocket->request->getQuery()->get('eventId');
        $userId = $this->getUserId($eventId, $from->resourceId);

        $this->output->writeln("[{$from->resourceId}] User (ID: $userId) wrote a message \"$msg\".");

        $this->db->insert('event_message', [
            'message' => $msg,
            'event_id' => $eventId,
            'chiller_id' => $userId,
        ]);

        $query = <<<SQL
            SELECT c.`firstname`, c.`lastname`, p.`url`
            FROM `chiller` c
            LEFT JOIN `chiller_photo` p ON c.`id` = p.`userid` AND p.`statut` = 1
            WHERE c.`id` = ?
SQL;

        list($firstname, $lastname, $picture) = $this->db->fetchArray($query, [ $userId ]);

        /** @var \Ratchet\WebSocket\Version\RFC6455\Connection $resource */
        foreach ($this->clients["event:$eventId"] as $resource) {
            $resource->send(\GuzzleHttp\json_encode([
                'user' => [
                    'id' => $userId,
                    'firstname' => $firstname,
                    'lastname' => $lastname,
                    'picture' => $picture ? $this->imageResolver->resolve($picture) : null,
                ],
                'creation_date' => (new \DateTime())->format('c'),
                'content' => $msg
            ]));
        }

        $this->eventDispatcher->dispatch(
            EventListenerProvider::EVENT_MESSAGE_CREATE,
            new EventMessageCreated(
                $eventId,
                $this->getEventName($eventId),
                $userId,
                array_keys($this->clients["event:$eventId"])
            )
        );
    }

    public function onClose(ConnectionInterface $conn)
    {
        /** @var SocketConnection $conn */
        foreach ($this->clients as $eventKey => $eventChat) {
            foreach ($eventChat as $userId => $connection) {
                if ($connection->resourceId === $conn->resourceId) {
                    unset($this->clients[$eventKey][$userId]);
                }
            }
        }

        $this->output->writeln("[{$conn->resourceId}] Closed connection!");
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $conn->close();

        throw $e;
    }

    /**
     * @param $eventId int
     * @param $resourceId
     * @return int
     */
    protected function getUserId($eventId, $resourceId)
    {
        /**
         * @var int $userId
         * @var SocketConnection $resource
         */
        foreach ($this->clients["event:$eventId"] as $userId => $resource) {
            if ($resource->resourceId === $resourceId) {
                return (int)$userId;
            }
        }

        throw new \LogicException('This point should not be reached.');
    }

    /**
     * Get event name` by its ID
     *
     * @param $eventId
     * @return string
     */
    protected function getEventName($eventId)
    {
        return $this->db->fetchColumn("SELECT `name` FROM `event` WHERE `id` = ?", array($eventId));
    }

    /**
     * @param $eventId
     * @param array $excludedIds
     * @return array
     */
    protected function getEventParticipantsNotificationTokens($eventId, $excludedIds)
    {
        $sql = <<<SQL
            SELECT `chiller`.`notification_token`
            FROM `event`
            LEFT JOIN `event_participant` ON `event`.`id` = `event_participant`.`eventid`
            LEFT JOIN `chiller` ON `event_participant`.`chillerid` = `chiller`.`id`
            WHERE `chiller`.`notification_token` IS NOT NULL
                  AND `chiller`.`id` NOT IN (?)
                  AND `event`.`id` = ?
SQL;
        return $this->db->executeQuery(
            $sql,
            [
            $excludedIds,
            $eventId,
        ],
            [
                Connection::PARAM_INT_ARRAY,
                \PDO::PARAM_INT,
            ]
        )->fetchAll(\PDO::FETCH_COLUMN);
    }
}
