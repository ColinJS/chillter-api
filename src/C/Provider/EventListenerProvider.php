<?php

namespace C\Provider;

use Doctrine\Common\Util\Inflector;
use Doctrine\DBAL\Connection;
use OneSignal\Model\Notification;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Api\EventListenerProviderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use C\Event;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Translation\Translator;

class EventListenerProvider implements ServiceProviderInterface, EventListenerProviderInterface
{
    const EVENT_UPDATED                 = 'chillter.event_updated';
    const EVENT_CANCELLED               = 'chillter.event_cancelled';
    const EVENT_PARTICIPATION_CREATED   = 'chillter.event_participation_created';
    const EVENT_PARTICIPATION_UPDATED   = 'chillter.event_participation_updated';
    const EVENT_CAR_CREATED             = 'chillter.event_car_created';
    const EVENT_CAR_REMOVED             = 'chillter.event_car_removed';
    const EVENT_CAR_GET_IN              = 'chillter.event_car_get_in';
    const EVENT_CAR_GET_OUT             = 'chillter.event_car_get_out';
    const EVENT_SPENDING_CREATED        = 'chillter.event_spending_created';
    const EVENT_SPENDING_REMOVED        = 'chillter.event_spending_removed';
    const EVENT_LIST_ELEMENT_ACTION     = 'chillter.event_list_element_action';
    const EVENT_MESSAGE_CREATE          = 'chillter.event_message_created';
    const FRIEND_REQUEST                = 'chillter.friend_request';
    const FRIEND_REQUEST_ACCEPTED       = 'chillter.friend_request_accepted';

    /**
     * @var Connection
     */
    protected $db;

    /**
     * @var \OneSignal\Client;
     */
    protected $onesignal;

    /**
     * @var Translator
     */
    protected $translator;

    /**
     * Available translation codes
     *
     * @var array
     */
    protected $availableTranslations = [];

    public function register(Container $app)
    {
        return $app;
    }

    public function subscribe(Container $app, EventDispatcherInterface $dispatcher)
    {
        foreach ((new \ReflectionClass($this))->getConstants() as $event) {
            $dispatcher->addListener($event, [$this, Inflector::camelize(substr($event, 9))]);
        }

        $this->db = $app['db'];
        $this->onesignal = $app['onesignal'];
        $this->translator = $app['translator'];
        $this->availableTranslations = $app['available_translations'];
    }

    /**
     * Event triggered after successful POST /chillers/{chillerId}/events/{eventId}/cars
     *
     * @param Event\EventCarCreated $event
     */
    public function eventCarCreated(Event\EventCarCreated $event)
    {
        return $this->eventCarModified($event, 'event.car.created');
    }

    /**
     * Event triggered after successful DELETE /chillers/{chillerId}/events/{eventId}/cars/{carId}
     *
     * @param Event\EventCarRemoved $event
     */
    public function eventCarRemoved(Event\EventCarRemoved $event)
    {
        return $this->eventCarModified($event, 'event.car.removed');
    }

    /**
     * Event triggered after successful PUT /chillers/{chillerId}/events/{eventId}/cars/{carId}/get_in
     *
     * @param Event\EventCarGetIn $event
     */
    public function eventCarGetIn(Event\EventCarGetIn $event)
    {
        return $this->eventCarSeat($event, 'event.car.get_in');
    }

    /**
     * Event triggered after successful PUT /chillers/{chillerId}/events/{eventId}/cars/{carId}/get_out
     *
     * @param Event\EventCarGetOut $event
     */
    public function eventCarGetOut(Event\EventCarGetOut $event)
    {
        return $this->eventCarSeat($event, 'event.car.get_out');
    }

    /**
     * Event triggered after successful POST /chillers/{chillerId}/events/{eventId}/expenses
     *
     * @param Event\EventSpendingCreated $event
     */
    public function eventSpendingCreated(Event\EventSpendingCreated $event)
    {
        return $this->eventSpending($event, 'event.spending.created');
    }

    /**
     * Event triggered after successful DELETE /chillers/{chillerId}/events/{eventId}/expenses/{expenseId}
     *
     * @param Event\EventSpendingRemoved $event
     */
    public function eventSpendingRemoved(Event\EventSpendingRemoved $event)
    {
        return $this->eventSpending($event, 'event.spending.removed');
    }

    /**
     * Event triggered after successful:
     *  - POST      /chillers/{chillerId}/events/{eventId}/expenses
     *  - DELETE    /chillers/{chillerId}/events/{eventId}/expenses/{expenseId}
     *
     * @param Event\AbstractEventSpending $event
     * @param $contentIdentifier
     */
    protected function eventSpending(Event\AbstractEventSpending $event, $contentIdentifier)
    {
        $query = <<<SQL
            SELECT c.`notification_token`
            FROM `event_participant` p
            LEFT JOIN `chiller` c ON c.`id` = p.`chillerid`
            WHERE p.`eventid` = ? AND p.`chillerid` != ? AND c.`notification_token` IS NOT NULL
SQL;

        $tokens =  $this->db->executeQuery($query, [$event->getEventId(), $event->getParticipantId()])
            ->fetchAll(\PDO::FETCH_COLUMN);

        if (!$tokens) {
            return;
        }

        $notification = (new Notification())
            ->setRecipients($tokens)
            ->setContents($this->getNotificationContentCollection(
                $contentIdentifier,
                [ '%name%' => $this->getChillerName($event->getParticipantId()) ],
                $this->getEventName($event->getEventId())
            ))
            ->setEvent($event)
        ;

        $this->onesignal->postNotification($notification);
    }

    /**
     * Event triggered after successful:
     *  - PUT       /chillers/{chillerId}/events/{eventId}/cars/{carId}/get_in
     *  - PUT       /chillers/{chillerId}/events/{eventId}/cars/{carId}/get_out
     *
     * @param Event\AbstractEventCarSeat $event
     * @param $contentIdentifier
     */
    protected function eventCarSeat(Event\AbstractEventCarSeat $event, $contentIdentifier)
    {
        $notificationToken = $this->getChillerNotificationToken($this->getField('car', 'chillerid', $event->getCarId()));

        if (!$notificationToken) {
            return;
        }

        $notification = (new Notification())
            ->addRecipient($notificationToken)
            ->setContents($this->getNotificationContentCollection(
                $contentIdentifier,
                [ '%name%' => $this->getChillerName($event->getPassengerId()) ],
                $this->getEventName($event->getEventId())
            ))
            ->setEvent($event)
        ;

        $this->onesignal->postNotification($notification);
    }

    /**
     * Event triggered after successful:
     *  - POST      /chillers/{chillerId}/events/{eventId}/cars
     *  - DELETE    /chillers/{chillerId}/events/{eventId}/cars/{carId}
     *
     * @param Event\AbstractEventCar $event
     * @param $contentIdentifier
     */
    protected function eventCarModified(Event\AbstractEventCar $event, $contentIdentifier)
    {
        $result = $this->getEventParticipantsNotificationTokens($event->getEventId(), array($event->getDriverId()));

        if (!$result) {
            return;
        }

        $notification = (new Notification())
            ->setRecipients($result)
            ->setContents($this->getNotificationContentCollection(
                $contentIdentifier,
                [ '%name%' => $this->getChillerName($event->getDriverId()) ],
                $this->getEventName($event->getEventId())
            ))
            ->setEvent($event)
        ;

        $this->onesignal->postNotification($notification);
    }

    /**
     * Event triggered after successful:
     *  - POST   /chillers/{chillerId}/events/{eventId}/elements
     *  - DELETE /chillers/{chillerId}/events/{eventId}/elements/{elementId}
     *  - PUT    /chillers/{chillerId}/events/{eventId}/elements/{elementId}/take
     *  - PUT    /chillers/{chillerId}/events/{eventId}/elements/{elementId}/leave
     *
     * @param Event\EventListElement $event
     */
    public function eventListElementAction(Event\EventListElement $event)
    {
        $participants =  $this->getEventParticipantsNotificationTokens($event->getEventId(), [ $event->getParticipantId() ]);

        if (!$participants) {
            return;
        }

        switch ($event->getAction()) {
            case Event\EventListElement::ELEMENT_CREATE:
                $translationIdentifier = 'event.list.created';
            break;

            case Event\EventListElement::ELEMENT_REMOVE:
                $translationIdentifier = 'event.list.removed';
            break;

            case Event\EventListElement::ELEMENT_LEAVE:
                $translationIdentifier = 'event.list.leaved';
            break;

            case Event\EventListElement::ELEMENT_TAKEN:
                $translationIdentifier = 'event.list.selected';
            break;

            default:
                throw new \LogicException('This code should not be reached.');
        }

        $notification = (new Notification())
            ->setRecipients($participants)
            ->setContents($this->getNotificationContentCollection(
                $translationIdentifier,
                [ '%name%' => $this->getChillerName($event->getParticipantId()) ],
                $this->getEventName($event->getEventId())
            ))
            ->setEvent($event)
        ;

        $this->onesignal->postNotification($notification);
    }

    /**
     * Event triggered after successful PUT /chillers/{chillerId}/friends/{friendId}
     *
     * @param Event\FriendRequest $friendRequest
     */
    public function friendRequestAccepted(Event\FriendRequest $friendRequest)
    {
        $sql = <<<SQL
            SELECT `first_id`, `second_id`, `inviting_id`
            FROM `chiller_friends`
            WHERE `id` = ?
SQL;

        list($firstId, $secondId, $invitingId) = $this->db->fetchArray($sql, [ $friendRequest->getFriendRelationId() ]);

        if (!$invitingId) {
            return;
        }

        $notificationToken = $this->getChillerNotificationToken($invitingId);

        if (!$notificationToken) {
            return;
        }

        $notification = (new Notification())
            ->addRecipient($notificationToken)
            ->setContents($this->getNotificationContentCollection('friends.accept', [
                '%name%' => $this->getChillerName($invitingId === $firstId ? $secondId : $firstId)
            ]))
            ->setEvent($friendRequest)
        ;

        $this->onesignal->postNotification($notification);
    }

    /**
     * Event triggered after successful POST /chillers/{chillerId}/friends/{friendId}
     *
     * @param Event\FriendRequest $friendRequest
     */
    public function friendRequest(Event\FriendRequest $friendRequest)
    {
        $invitedQuery = <<<SQL
            SELECT
                CASE
                    WHEN `first_id` = `inviting_id`
                    THEN `second_id`
                    WHEN `second_id` = `inviting_id`
                    THEN `first_id`
                    ELSE null
                END
            FROM `chiller_friends`
            WHERE `id` = ?
SQL;

        $invitedUserId = $this->db->fetchColumn($invitedQuery, [ $friendRequest->getFriendRelationId() ]);

        if (!$invitedUserId) {
            return;
        }

        $notificationToken = $this->db->fetchColumn("SELECT `notification_token` FROM `chiller` WHERE `id` = ?", [
            $invitedUserId
        ]);

        if (!$notificationToken) {
            return;
        }

        $notification = (new Notification())
            ->addRecipient($notificationToken)
            ->setContents($this->getNotificationContentCollection('friends.request'))
            ->setEvent($friendRequest)
        ;

        $this->onesignal->postNotification($notification);
    }

    /**
     * Event triggered after successful:
     *  - POST /chillers/{userId}/events
     *  - POST /chillers/{userId}/events/{eventId}/participants/{participantId}
     *
     * @param Event\EventParticipantCreated $event
     */
    public function eventParticipationCreated(Event\EventParticipantCreated $event)
    {
        $notificationToken = $this->getChillerNotificationToken($event->getParticipantId());
        $invitingName = $this->getChillerName($event->getInvitingChillerId());
        $eventName = $this->getEventName($event->getEventId());

        if (!$notificationToken) {
            return;
        }

        $notification = (new Notification())
            ->addRecipient($notificationToken)
            ->setContents($this->getNotificationContentCollection(
                'event.invited_by',
                [ '%name%' => $invitingName ],
                $eventName
            ))
            ->setEvent($event)
        ;

        $this->onesignal->postNotification($notification);
    }

    /**
     * Event triggered after successful:
     *  - PUT /chillers/{chillerId}/events/{eventId}/participate
     *
     * @param Event\EventParticipantUpdated $event
     */
    public function eventParticipationUpdated(Event\EventParticipantUpdated $event)
    {
        $participants = $this->getEventParticipantsNotificationTokens($event->getEventId(), [ $event->getParticipantId() ]);

        if (!$participants) {
            return;
        }

        $translationIdentifier = null;

        switch ($event->getParticipationStatus()) {
            case 0:
                $translationIdentifier = 'event.does_not_participate';
            break;

            case 1:
                $translationIdentifier = 'event.participate';
            break;

            case 2:
                $translationIdentifier = 'event.maybe_participate';
            break;

            default:
                return;
        }

        $notification = (new Notification())
            ->setRecipients($participants)
            ->setContents($this->getNotificationContentCollection(
                $translationIdentifier,
                [ '%name%' => $this->getChillerName($event->getParticipantId()) ],
                $this->getEventName($event->getEventId())
            ))
            ->setEvent($event)
        ;

        $this->onesignal->postNotification($notification);
    }

    public function eventMessageCreated(Event\EventMessageCreated $event)
    {
        $participants =  $this->getEventParticipantsNotificationTokens($event->getEventId(), $event->getExcludedUserIds());

        if (!$participants) {
            return;
        }

        $notification = (new Notification())
            ->setRecipients($participants)
            ->setContents($this->getNotificationContentCollection(
                'event.message.created',
                [ '%name%' => $this->getChillerName($event->getParticipantId()) ],
                $this->getEventName($event->getEventId())
            ))
            ->setEvent($event)
        ;

        $this->onesignal->postNotification($notification);
    }

    /**
     * Event triggered after successful PUT /chillers/{userId}/events/{eventId}
     *
     * @param Event\EventUpdated $event
     */
    public function eventUpdated(Event\EventUpdated $event)
    {
        if (!$participants = $this->getEventParticipantsNotificationTokens(
            $event->getEventId(),
            [$this->getEventCreatorId($event->getEventId())]
        )) {
            return;
        }

        $eventName = $this->getEventName($event->getEventId());

        $notification = (new Notification())
            ->setRecipients($participants)
            ->setContents($this->getNotificationContentCollection(
                'event.updated',
                [ '%name%' => $eventName ],
                $eventName
            ))
            ->setEvent($event)
        ;

        $this->onesignal->postNotification($notification);
    }

    /**
     * Event triggered after successful PUT /chillers/{chillerId}/events/{eventId}/cancel
     *
     * @param Event\EventCancelled $event
     */
    public function eventCancelled(Event\EventCancelled $event)
    {
        if (!$participants = $this->getEventParticipantsNotificationTokens(
            $event->getEventId(),
            [$this->getEventCreatorId($event->getEventId())]
        )) {
            return;
        }

        $eventName = $this->getEventName($event->getEventId());

        $notification = (new Notification())
            ->setRecipients($participants)
            ->setContents($this->getNotificationContentCollection(
                'event.cancelled',
                [ '%name%' => $eventName ],
                $eventName
            ))
            ->setEvent($event)
        ;

        $this->onesignal->postNotification($notification);
    }

    /**
     * @param int $contentId
     * @param array $contentParameters
     * @param int $headingId
     * @param array $headingParameters
     * @return Notification\Content[]
     */
    protected function getNotificationContentCollection($contentId, $contentParameters = [], $headingId = null, $headingParameters = [])
    {
        $collection = [];

        foreach ($this->availableTranslations as $locale) {
            $content = (new Notification\Content())
                ->setLanguageCode($locale)
                ->setContent($this->translator->trans($contentId, $contentParameters, null, $locale))
            ;

            if ($headingId) {
                $content->setHeading(strtoupper($this->translator->trans($headingId, $headingParameters, null, $locale)));
            }

            $collection[] = $content;
        }

        return $collection;
    }

    /**
     * Get `event.` name` by its ID
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
     * @return array
     */
    protected function getEventNameAndCreatorName($eventId)
    {
        $sql = <<<SQL
            SELECT CONCAT(`chiller`.`firstname`, ' ', `chiller`.`lastname`), `event`.`name`
            FROM `event`
            LEFT JOIN `chiller` ON `event`.`chillerid` = `chiller`.`id`
            WHERE `event`.`id` = ?
SQL;
        return $this->db->fetchArray($sql, array($eventId));
    }

    protected function getEventNameAndCarDriver($carId)
    {
        $sql = <<<SQL
            SELECT `event`.`name`, `chiller`.`id`, CONCAT(`chiller`.`firstname`, ' ', `chiller`.`lastname`)
            FROM `car`
            LEFT JOIN `event` ON `car`.`eventid` = `event`.`id`
            LEFT JOIN `chiller` ON `car`.`chillerid` = `chiller`.`id`
            WHERE `car`.`id` = ?
SQL;
        return $this->db->fetchArray($sql, array($carId));
    }

    /**
     * @param $eventId
     * @param $skipChillers
     * @return array
     */
    protected function getEventParticipantsNotificationTokens($eventId, array $skipChillers = [])
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
                $skipChillers,
                $eventId,
            ],
            [
                Connection::PARAM_INT_ARRAY,
                \PDO::PARAM_INT,
            ]
        )->fetchAll(\PDO::FETCH_COLUMN);
    }

    protected function getChillerNotificationToken($chillerId)
    {
        return $this->db->fetchColumn("SELECT `notification_token` FROM `chiller` WHERE `id` = ?", array($chillerId));
    }

    protected function getChillerName($chillerId)
    {
        return $this->db->fetchColumn("SELECT CONCAT(`firstname`, ' ', `lastname`) FROM `chiller` WHERE `id` = ?", array($chillerId));
    }

    protected function getEventCreatorId($eventId)
    {
        $id = $this->getEventField('chillerid', $eventId);

        if (!$id) {
            throw new NotFoundHttpException("Event (ID: $eventId) does not exist.");
        }

        return (int)$id;
    }

    protected function getChillerField($fieldName, $id)
    {
        return $this->getField('chiller', $fieldName, $id);
    }

    protected function getEventField($fieldName, $id)
    {
        return $this->getField('event', $fieldName, $id);
    }

    protected function getField($tableName, $fieldName, $id)
    {
        return $this->db->fetchColumn("SELECT `$fieldName` FROM `$tableName` WHERE `id` = ?", [ $id ]);
    }
}
