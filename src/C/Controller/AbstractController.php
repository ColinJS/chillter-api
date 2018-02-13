<?php

namespace C\Controller;

use C\Model\Chiller;
use C\Model\Chiller\Friends;
use Doctrine\DBAL\Connection;
use Pimple\Container;
use Silex\Application;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

abstract class AbstractController
{
    /**
     * Database connections
     *
     * @var Connection
     */
    protected $db;

    /**
     * @var Application
     */
    protected $app;

    /**
     * AbstractController constructor
     *
     * @param Container $application
     */
    public function __construct(Container $application)
    {
        $this->app = $application;
        $this->db = $application['db'];
    }

    /**
     * Return application root dir, for example: '/var/www/project/src/..'
     *
     * @return string
     */
    protected function getRootDir()
    {
        return $this->app['root.dir'];
    }

    /**
     * Return upload directory, for example: '/images/'
     *
     * @return string
     */
    protected function getUploadDirectory()
    {
        return $this->app['upload.directory'];
    }

    /**
     * @return \OneSignal\Client
     */
    public function getOneSignal()
    {
        return $this->app['onesignal'];
    }

    /**
     * @return \Symfony\Component\EventDispatcher\EventDispatcher
     */
    public function getEventDispatcher()
    {
        return $this->app['dispatcher'];
    }

    /**
     * @return \Swift_Mailer
     */
    public function getMailer()
    {
        return $this->app['mailer'];
    }

    /**
     * @return string
     */
    public function getSenderName()
    {
        return $this->app['sender_name'];
    }

    /**
     * @return string
     */
    public function getSenderEmail()
    {
        return $this->app['sender_email'];
    }

    /**
     * @return Request
     */
    public function getMasterRequest()
    {
        return $this->app['request_stack']->getMasterRequest();
    }

    /**
     * @param $name
     * @param array $parameters
     * @param int $referenceType
     * @return string
     */
    public function generateUrl($name, $parameters = array(), $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH)
    {
        return $this->app['url_generator']->generate($name, $parameters, $referenceType);
    }

    /**
     * Throw an exception if user does not exist
     *
     * @param $userId
     */
    public function throwExceptionUnlessUserExists($userId)
    {
        if (!$this->userExists($userId)) {
            throw new Exception\NotFoundHttpException("User (ID: $userId) does not exist.");
        }
    }

    /**
     * Check if user exists
     *
     * @param $userId
     * @return bool
     */
    public function userExists($userId)
    {
        return '1' === $this->db->fetchColumn('SELECT 1 FROM `chiller` WHERE `id` = ?', array($userId));
    }

    /**
     * Throw AccessDeniedHttpException unless user is invited to the event
     *
     * @param $userId int
     * @param $eventId int
     */
    protected function denyAccessUnlessInvitedToEvent($userId, $eventId)
    {
        if (!$this->invitedToEvent($userId, $eventId)) {
            throw new Exception\AccessDeniedHttpException(
                "The chiller (ID: {$userId}) is not invited to the event (ID: $eventId)."
            );
        }
    }

    /**
     * Checks permission to access to a custom chill
     *
     * @param $userId
     * @param $customChillId
     */
    protected function denyAccessUnlessGrantedToCustomChill($userId, $customChillId)
    {
        $customChillUserId = $this->db->fetchColumn("SELECT `chiller_id` FROM `chills_custom` WHERE `id` = ?", [$customChillId]);

        if (false === $customChillUserId) {
            throw new Exception\NotFoundHttpException("Custom chill (ID: $customChillId) does not exist.");
        }

        if ($userId !== $customChillUserId) {
            throw new Exception\BadRequestHttpException("Custom chill (ID: $customChillId) does not belong to user (ID: $userId)!");
        }
    }

    /**
     * Check if user is invited to the event
     *
     * @param $userId int
     * @param $eventId int
     * @return bool
     */
    protected function invitedToEvent($userId, $eventId)
    {
        $sql = "SELECT 1 FROM event_participant WHERE eventid = ? AND chillerid = ?";

        return 1 === (int)$this->db->fetchColumn($sql, array($eventId, $userId));
    }

    /**
     * Throw AccessDeniedHttpException unless user is not an event creator
     *
     * @param $userId int
     * @param $eventId int
     */
    protected function denyAccessUnlessIsEventCreator($userId, $eventId)
    {
        if (!$this->isEventCreator($userId, $eventId)) {
            throw new Exception\AccessDeniedHttpException(
                "The chiller (ID: {$userId}) is not an event creator (ID: $eventId)."
            );
        }
    }

    /**
     * Check if user is an event creator
     *
     * @param $userId int
     * @param $eventId int
     * @return bool
     */
    protected function isEventCreator($userId, $eventId)
    {
        $sql = "SELECT 1 FROM event WHERE id = ? AND chillerid = ?";

        return 1 === (int)$this->db->fetchColumn($sql, array($eventId, $userId));
    }

    /**
     * @param $firstId
     * @param $secondId
     * @return bool
     */
    protected function areFriends($firstId, $secondId)
    {
        $status = $this->getFriendsStatus($firstId, $secondId);

        return Friends::STATUS_REMOVED !== $status && null !== $status;
    }

    /**
     * @param $firstId
     * @param $secondId
     * @return int|null
     */
    protected function getFriendsStatus($firstId, $secondId)
    {
        $status = $this->db->fetchColumn("
            SELECT `status`
            FROM `chiller_friends`
            WHERE `first_id` = :firstId AND `second_id` = :secondId 
        ", [
            'firstId' => (int)($firstId < $secondId ? $firstId : $secondId),
            'secondId' => (int)($firstId > $secondId ? $firstId : $secondId),
        ]);

        return false === $status ? null : (int)$status;
    }

    protected function getFriends($firstId, $secondId)
    {
        $result = $this->db->fetchAssoc("
            SELECT `id`, `status`, `inviting_id`
            FROM `chiller_friends`
            WHERE `first_id` = :firstId AND `second_id` = :secondId 
        ", [
            'firstId' => (int)($firstId < $secondId ? $firstId : $secondId),
            'secondId' => (int)($firstId > $secondId ? $firstId : $secondId),
        ]);

        if (false === $result) {
            return null;
        }

        return (new Friends())
            ->setId($result['id'])
            ->setFirst((new Chiller())->setId($firstId < $secondId ? $firstId : $secondId))
            ->setSecond((new Chiller())->setId($firstId > $secondId ? $firstId : $secondId))
            ->setInviting($result['inviting_id'] ? (new Chiller())->setId($result['inviting_id']) : null)
            ->setStatus(false === $result['status'] ? null : $result['status'])
        ;
    }

    /**
     * Save image with unique file name under the provided path prefix and returns file name
     *
     * @param $base64String
     * @param $pathPrefix
     * @return string
     * @throws \Exception
     */
    protected function savePhotoFromBase64String($base64String, $pathPrefix)
    {
        list(, $data) = explode(',', $base64String);

        $image = imagecreatefromstring(base64_decode($data));

        if (!is_resource($image)) {
            throw new Exception\BadRequestHttpException("Decoded base64 image is not a valid image.");
        }

        (new Filesystem())->mkdir($this->getRootDir().$this->getUploadDirectory().$pathPrefix);

        $fileName = $this->getUniqueImageName($pathPrefix, 'jpeg');
        $targetFile = $this->getRootDir().$this->getUploadDirectory().$pathPrefix.$fileName;

        if (!imagejpeg($image, $targetFile)) {
            throw new \Exception("Saving image to \"$targetFile\" failed.");
        }

        return $fileName;
    }

    /**
     * Get unique image file name, for example: '5a0469dc51314.jpeg'
     *
     * @param $pathPrefix
     * @param $extension
     * @return string
     */
    protected function getUniqueImageName($pathPrefix, $extension)
    {
        do {
            $fileName = uniqid();
        } while(file_exists($this->getRootDir().$this->getUploadDirectory().$pathPrefix.$fileName.'.'.$extension));

        return $fileName.'.'.$extension;
    }

    /**
     * @param $entityId
     * @param $entity
     * @return mixed
     */
    protected function normalizeEntity($entityId, $entity)
    {
        switch ($entityId)
        {
            case 'chiller':
                if (isset($entity['id'])) {
                    $entity['id'] = (int)$entity['id'];
                };

                if (isset($entity['picture'])) {
                    $entity['picture'] = $this->getMasterRequest()->getUriForPath($this->getUploadDirectory().$entity['picture']);
                }

                return $entity;

            default:
                throw new \LogicException('Unknown entity identifier');
        }
    }

    /**
     * @param $entityId string
     * @param $entities array
     * @return array
     */
    protected function normalizeEntities($entityId, $entities)
    {
        foreach ($entities as &$entity) {
            $entity = $this->normalizeEntity($entityId, $entity);
        }

        return $entities;
    }
}
