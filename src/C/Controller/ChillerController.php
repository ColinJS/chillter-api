<?php

namespace C\Controller;

use C\Event;
use C\Model\Chiller;
use C\Provider\EventListenerProvider;
use C\Application;
use Doctrine\DBAL\Connection;
use RandomLib;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use OneSignal\Model\Notification;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @apiDefine ChillerPhotos Chiller photos
 */
class ChillerController extends AbstractController
{
    /**
     * @api {post} /login Login a chiller and obtain a token
     * @apiGroup Chillers
     * @apiPermission authenticated (only self data)
     *
     * @apiExample Example request:
        {
            "email": "noreply@chillter.fr",
            "password": "secretPassword"
        }
     *
     * @apiSuccessExample Example success response:
        {
            "id": 126,
            "token": "L+UrY2ClA+05s4xn2E5ix4z3rt7UBC5GrYv/JqZ+aACrZ17yMfOsmfFKM2EQ5KjZ"
        }
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request)
    {
        $content = \GuzzleHttp\json_decode($request->getContent(), true);
        $email = array_key_exists('email', $content) ? $content['email'] : null;
        $password = array_key_exists('password', $content) ? $content['password'] : null;
        $user = $this->db->fetchAssoc("SELECT `id`, `password` from `chiller` WHERE `email` = ?", [ $email ]);

        if (!$user) {
            throw new NotFoundHttpException("User does not exist.");
        }

        if (!password_verify($password, $user['password'])) {
            throw new AccessDeniedHttpException('Invalid credentials.');
        }

        $bearer = (new RandomLib\Factory)->getMediumStrengthGenerator()->generateString(64);
        $this->db->update('chiller', [ 'bearer' => $bearer ], [ 'id' => $user['id'] ]);

        return new JsonResponse([
            'id' =>  (int)$user['id'],
            'token' => $bearer,
        ]);
    }

    /**
     * @api {post} /chillers/{chillerId}/phone_book Upload a phone book
     * @apiGroup Chillers
     * @apiPermission authenticated (only self data)
     *
     * @apiExample Example request:
        [
            "0667089314",
            "0633093815",
            "2133734253",
            "2133734253",
            "8005553535",
            "9265127278",
            "5133561547",
            "5133561548"
        ]
     *
     * @apiSuccessExample Example success response:
        {
            "added_friends_count": 3,
            "not_found_phone_numbers": [
                "0667089314",
                "0633093815",
                "2133734253",
                "2133734253",
                "9265127278",
                "5133561547",
                "5133561548"
            ]
        }
     *
     * @param $userId
     * @param Request $request
     * @return JsonResponse
     */
    public function uploadPhoneBook($userId, Request $request)
    {
        $phoneNumbers = \GuzzleHttp\json_decode($request->getContent(), true) ? : [];

        $users = $this->db->fetchAll(
            " SELECT `id`, `phone` FROM `chiller` WHERE `id` != ? AND `phone` IN (?)",
            [ $userId, $phoneNumbers ],
            [ \PDO::PARAM_INT, Connection::PARAM_INT_ARRAY ]
        );

        $addedFriends = 0;

        foreach ($users as $user) {
            $friends = $this->getFriends($userId, $user['id']);

            if (!$friends instanceof Chiller\Friends) {
                $addedFriends += $this->db->insert('chiller_friends', array(
                    'first_id' => $userId < $user['id'] ? $userId : $user['id'],
                    'second_id' => $userId > $user['id'] ? $userId : $user['id'],
                    'status' => Chiller\Friends::STATUS_CONFIRMED,
                ));
            } elseif (Chiller\Friends::STATUS_PENDING === $friends->getStatus()) {
                $addedFriends += $this->db->update('chiller_friends', [
                    'status' => Chiller\Friends::STATUS_CONFIRMED,
                ], [
                    'id' => $friends->getId()
                ]);
            }
        }

        $notFoundPhoneNumbers = array_values(array_diff($phoneNumbers, array_map(function ($user) {
            return $user['phone'];
        }, $users)));

        return new JsonResponse([
            'added_friends_count' => $addedFriends,
            'not_found_phone_numbers' => $notFoundPhoneNumbers,
        ]);
    }

    /**
     * @api {post} /chillers/{chillerId}/update_password Update chiller password
     * @apiGroup Chillers
     * @apiPermission authenticated (only self data)
     *
     * @apiExample Example request:
        {
            "oldPassword": "oldSecretPassword",
            "newPassword": "newSecretPassword"
        }
     *
     * @param $userId
     * @param Request $request
     * @return Response
     */
    public function updatePassword($userId, Request $request)
    {
        $content = \GuzzleHttp\json_decode($request->getContent(), true) ? : [];
        $oldPassword = array_key_exists('oldPassword', $content) ? $content['oldPassword'] : null;
        $newPassword = array_key_exists('newPassword', $content) ? password_hash($content['newPassword'], PASSWORD_BCRYPT) : null;
        $user = $this->db->fetchAssoc("SELECT `id`, `password` from `chiller` WHERE `id` = ?", [ $userId ]);

        if (!($oldPassword && $newPassword)) {
            throw new BadRequestHttpException();
        }

        if (!password_verify($oldPassword, $user['password'])) {
            throw new BadRequestHttpException('Invalid current password.');
        }

        $this->db->update('chiller', [ 'password' => $newPassword ], [ 'id' => $userId ]);

        return new Response('', Response::HTTP_NO_CONTENT);
    }
    
    /**
     * @api {get} /chillers/{chillerId} Get a chiller
     * @apiGroup Chillers
     * @apiPermission authenticated (only self data)
     *
     * @apiSuccessExample Example success response:
        {
            "picture": "http://chillter.fr/api/images/avatars/590b0f453bb8f.jpeg",
            "firstname": "Francois",
            "lastname": "Dupois",
            "sex": "0",
            "phone": "1234567",
            "email": "example@example.com",
            "language": "fr",
            "currency": "euro"
        }
     *
     * @param $userId
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getChillerInfo($userId)
    {
        $sql = <<<SQL
            SELECT p.url AS picture, c.firstname, c.lastname, c.sex, c.phone, c.email, c.language, c.currency
            FROM chiller c
            LEFT JOIN chiller_photo p ON p.userid = c.id AND p.statut = 1
            WHERE c.id = ?
SQL;

        $chiller = $this->db->fetchAssoc($sql, [$userId]);

        if (!$chiller) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse($this->normalizeEntity('chiller', $chiller));
    }

    /**
     * @api {post} /chillers Create a new chiller
     * @apiGroup Chillers
     * @apiPermission anonymous
     *
     * @apiParam {String{..64}} firstname
     * @apiParam {String{..64}} lastname
     * @apiParam {Number=0,1} sex
     * @apiParam {String{..16}} phone
     * @apiParam {String{..128}} email
     * @apiParam {String{..4}} language
     * @apiParam {String{..8}} currency
     * @apiParam {String} password
     * @apiParam {Base64} [image]
     *
     * @apiExample Example request:
        {
            "firstname": "Francois",
            "lastname": "Dupois",
            "sex": 0,
            "phone": "1234567",
            "email": "example@example.com",
            "language": "fr",
            "currency": "euro",
            "pass": "my secret password",
            "image": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAA3XAAAN1wFCKJt4AAAAB3RJTUUH4QsJDx8KPce8oQAAAW5JREFUGNMlzF1Lk2EYwPH/de+Ze8acm5RpzWELZOJL6yCh8MCP0GEhehohgn2RoCDIwxCP/BgeiOxANOzFSle+obNINzf3vNz35UG/D/CT4F2B1OIxwYd8nq7kC8kOVsDE2jzc06Dz0Z9vXARvCwhAsDRYIXNry9x9onq2KahD+h+rnlVF2/Wp1Mvjdem8uTMit4eX8fxJwgbaOAQEyRXBS4NzW3q+O+ep9comNzqJn4PWOSQHIJVFxAM/D9HVI1f/NeaRLs3Y3TXUxRC1ka4Mqg7iEJL+/z1TnDEaYzU06LXDlJ+h6QKSH0HDBNqK0NCgsVjjjn6vmvvTaCvAfqsidGNrX9BA0HZMYmgad3K0aoj0q6193jD9FVz9lPjHNtpRtBVh+saxB9836USfBKD56sFDyfRuJ8pP1R3sCDhMcULtz6po8+9Udml/Xa4WSnS/r3E529eDST03vffGEYndv5Ma9no5t/Kn0X5d4gZ1/qt+WXQnQAAAAABJRU5ErkJggg=="
        }
     *
     * @apiSuccessExample Example success response:
        {
            "id": 134,
            "pass": "luBO4g0Qm2Mt."
        }
     *
     * @param Request $request
     * @param Application $app
     * @throws \Exception
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function postNewChiller(Request $request, Application $app)
    {
        $data = \GuzzleHttp\json_decode($request->getContent(), true);

        if (!$data) {
            throw new BadRequestHttpException();
        }

        try {
            $this->db->insert('chiller', [
                'firstname' => $data["firstname"],
                'lastname' => $data["lastname"],
                'sex' => $data["sex"],
                'phone' => $data["phone"],
                'email' => $data["email"],
                'language' => $data["language"],
                'currency' => $data["currency"],
                'birth' => date_format(date_create($data["birth"]), 'Y-m-d'),
                'password' => password_hash($data["pass"], PASSWORD_BCRYPT),
                'roles' => serialize([]),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            throw new BadRequestHttpException('User with email "' . $data["email"] . '" already exist.');
        }

        $userId = (int)$this->db->lastInsertId();

        if (isset($data['image'])) {
            $subRequest = Request::create(
                $this->generateUrl('chiller_picture_create', ['userId' => $userId], UrlGeneratorInterface::ABSOLUTE_URL),
                'POST',
                [],
                [],
                [],
                $_SERVER,
                \GuzzleHttp\json_encode(["image" => $data['image']])
            );

            $subResponse = $app->handle($subRequest, HttpKernelInterface::SUB_REQUEST, false);
            $subResponseContent = \GuzzleHttp\json_decode($subResponse->getContent(), true);

            if (!isset($subResponseContent['url'])) {
                throw new \Exception("Error occurred during image upload.");
            }
        }

        foreach (range(1, 5) as $chillId) {
            $this->db->insert('chiller_home', [
                'chiller_id' => $userId,
                'position' => $chillId,
                'chill_id' => $chillId
            ]);
        }

        return new JsonResponse([
            "id" => (int)$userId,
            "pass" => $data["pass"]
        ]);
    }

    /**
     * @api {put} /chillers/{chillerId} Update a chiller
     * @apiGroup Chillers
     * @apiPermission authenticated (only self data)
     * @apiDescription The same request data as creating new chiller
     *
     * @param $userId
     * @param Application $app
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function updateChillerInfo($userId, Application $app)
    {
        $data = $app['chillter.json.req'];

        if (!$data) {
            return $app->json(false);
        }

        $userSql = "UPDATE chiller SET";
        $userSql .= isset($data["info"]["lastname"]) ? " lastname = :lastname," : "";
        $userSql .= isset($data["info"]["firstname"]) ? " firstname = :firstname," : "";
        $userSql .= isset($data["info"]["sex"]) ? " sex = :sex," : "";
        $userSql .= isset($data["info"]["phone"]) ? " phone = :phone," : "";
        $userSql .= isset($data["info"]["email"]) ? " email = :email," : "";
        $userSql .= isset($data["info"]["language"]) ? " language = :language," : "";
        $userSql .= isset($data["info"]["currency"]) ? " currency = :currency," : "";

        $userSql = substr($userSql, 0, strlen($userSql) - 1) . " WHERE id = :id";
        $data["info"]["id"] = $userId;
        $dbrps = $app['db']->executeUpdate($userSql, $data["info"]);

        if ($dbrps == 0) {
            return $app->json(false);
        }

        return $app->json(true);
    }

    /**
     * @api {get} /chillers Get all chillers
     * @apiGroup Chillers
     * @apiPermission authenticated
     *
     * @apiParam {String} [name] Search by chiller's first name
     * @apiParam {Number} [id] User ID – allow to exclude chillers that are friends with passed User ID
     *
     * @apiSuccessExample Example success response:
        [
            {
                "id": "108",
                "firstname": "Adrieb",
                "lastname": "Frappat",
                "picture": "http://www.chillter.fr/api/v0.1/images/avatars/58d809243ba91.jpeg"
            },
            {
                "id": "131",
                "firstname": "Antoine",
                "lastname": "Hicham",
                "picture": "http://www.chillter.fr/api/v0.1/images/avatars/58d15685cb89a.jpeg"
            }
        ]
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getChillerList(Request $request)
    {
        $name = $request->get('name') ? $request->get('name') . '%' : null;
        $id = $request->get('id') ? (int)$request->get('id') : null;

        $params = [];
        $query = <<<SQL
            SELECT c.`id`, c.`firstname`, c.`lastname`, p.`url` AS picture
            FROM chiller c
            LEFT JOIN chiller_photo p ON p.userid = c.id AND p.statut = 1\n
SQL;

        if ($id) {
            $query .= <<<SQL
                LEFT JOIN `chiller_friends` f ON (
                    (f.`first_id` = :chillerId AND f.`second_id` = c.`id`)
                    OR (f.`second_id` = :chillerId AND f.`first_id` = c.`id`)
                )\n
SQL;
            $params['chillerId'] = $id;
        }

        if ($id || $name) {
            $query .= "WHERE ";
        }

        if ($id) {
            $query .= "(f.`status` IS NULL OR f.`status` = " . Chiller\Friends::STATUS_REMOVED . ") AND c.`id` != :chillerId\n";
        }

        if ($name) {
            $query .= ($id ? "AND " : "") . "c.`firstname` LIKE :name\n";
            $params['name'] = $name;
        }

        $collection = $this->db->fetchAll($query, $params) ? : [];

        return new JsonResponse($this->normalizeEntities('chiller', $collection));
    }

    /**
     * @api {put} /chillers/{chillerId}/notification_token Update a notification token
     * @apiGroup Chillers
     * @apiPermission authenticated (only self data)
     *
     * @apiExample Example request:
        {
            "notification_token": "d30ec69e-efdc-4dca-a3b6-ba84f00b358a"
        }
     *
     * @param $userId
     * @param Request $request
     * @return Response
     */
    public function putNotificationToken($userId, Request $request)
    {
        $content = \GuzzleHttp\json_decode($request->getContent(), true);

        if (!array_key_exists('notification_token', $content)) {
            throw new BadRequestHttpException();
        }

        $this->db->update('chiller', array(
            'notification_token' => $content['notification_token']
        ), array(
            'id' => $userId
        ));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @api {post} /chillers/{chillerId}/notification_test Test push notification
     * @apiDescription This method allows to test push notification. It sends test push to the current user mobile.
     * @apiGroup Chillers
     * @apiPermission authenticated (only self data)
     *
     * @param $userId
     * @param Request $request
     * @return Response
     */
    public function postNotificationTest($userId, Request $request)
    {
        $notificationToken = $this->db->fetchColumn("SELECT `notification_token` FROM  `chiller` WHERE `id` = ?", array($userId));

        if (!$notificationToken) {
            throw new BadRequestHttpException('User (ID: ' . (int)$userId . ') does not have notification_token.');
        }

        $notification = (new Notification())
            ->addRecipient($notificationToken)
            ->setContents([
                (new Notification\Content())
                ->setHeading("Notification test \xF0\x9F\x99\x88\xF0\x9F\x99\x89\xF0\x9F\x99\x8A")
                ->setContent("Sent " . date("Y-m-d H:i") . "\nby " . $request->getHttpHost())
                ->setLanguageCode('en')
            ])
        ;

        return $this->getOneSignal()->postNotification($notification);
    }

    /**
     * @api {post} /chillers/{chillerId}/photos Upload a chiller photo
     * @apiGroup ChillerPhotos
     * @apiPermission authenticated
     *
     * @apiExample Example request:
        {
            "image": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAA3XAAAN1wFCKJt4AAAAB3RJTUUH4QsJDx8KPce8oQAAAW5JREFUGNMlzF1Lk2EYwPH/de+Ze8acm5RpzWELZOJL6yCh8MCP0GEhehohgn2RoCDIwxCP/BgeiOxANOzFSle+obNINzf3vNz35UG/D/CT4F2B1OIxwYd8nq7kC8kOVsDE2jzc06Dz0Z9vXARvCwhAsDRYIXNry9x9onq2KahD+h+rnlVF2/Wp1Mvjdem8uTMit4eX8fxJwgbaOAQEyRXBS4NzW3q+O+ep9comNzqJn4PWOSQHIJVFxAM/D9HVI1f/NeaRLs3Y3TXUxRC1ka4Mqg7iEJL+/z1TnDEaYzU06LXDlJ+h6QKSH0HDBNqK0NCgsVjjjn6vmvvTaCvAfqsidGNrX9BA0HZMYmgad3K0aoj0q6193jD9FVz9lPjHNtpRtBVh+saxB9836USfBKD56sFDyfRuJ8pP1R3sCDhMcULtz6po8+9Udml/Xa4WSnS/r3E529eDST03vffGEYndv5Ma9no5t/Kn0X5d4gZ1/qt+WXQnQAAAAABJRU5ErkJggg=="
        }
     *
     * @apiSuccessExample Example success response:
        {
            "id": 71,
            "url": "http://chillter.fr/api/images/avatars/596f2050820fd.png"
        }
     *
     * @param $userId
     * @param Request $request
     * @throws \Exception
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function postPicture($userId, Request $request)
    {
        $content = \GuzzleHttp\json_decode($request->getContent(), true);

        if (!array_key_exists('image', $content)) {
            throw new BadRequestHttpException();
        }

        $fileName = $this->savePhotoFromBase64String($content['image'], 'avatars/');

        $this->db->beginTransaction();
        try {
            $this->db->update('chiller_photo', ['statut' => 0], ['userid' => $userId]);
            $this->db->insert('chiller_photo', [
                'userid' => $userId,
                'url' => 'avatars/'.$fileName
            ]);
            $photoId = (int)$this->db->lastInsertId();
            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();

            throw $e;
        }

        return new JsonResponse([
            "id" => $photoId,
            "url" => $request->getUriForPath($this->getUploadDirectory().'avatars/'.$fileName)
        ], Response::HTTP_CREATED);
    }

    /**
     * @api {get} /chillers/{chillerId}/photos Get chiller photos
     * @apiGroup ChillerPhotos
     * @apiPermission authenticated (only self data)
     * @apiDescription Collection is sorted descending by "status" field.
     *
     * @apiSuccessExample Example success response:
        [
            {
                "id": 66,
                "url": "http://chillter.fr/api/images/avatars/596f205da1fe1.png",
                "date": "2017-07-19 09:03:25",
                "status": 1
            },
            {
                "id": 64,
                "url": "http://chillter.fr/api/images/avatars/596f2036857dd.png",
                "date": "2017-07-19 09:02:46",
                "status": 0
            },
            {
                "id": 65,
                "url": "http://chillter.fr/api/images/avatars/596f2050820fd.png",
                "date": "2017-07-19 09:03:12",
                "status": 0
            }
        ]
     *
     * @param $userId
     * @param Application $app
     * @param Request $request
     * @return JsonResponse
     */
    public function getPhotos($userId, Application $app, Request $request)
    {
        $query = <<<SQL
            SELECT `id`, `url`, `date`, `statut` as 'status'
            FROM chiller_photo
            WHERE userid = ?
            ORDER BY `statut` DESC
SQL;

        $photos = $this->db->fetchAll($query, [ $userId ]) ? : [];

        foreach ($photos as &$photo) {
            $photo['id'] = (int)$photo['id'];
            $photo['status'] = (int)$photo['status'];
            $photo['url'] = $request->getUriForPath($app['upload.directory'] . $photo['url']);
        }

        return new JsonResponse($photos);
    }

    /**
     * @api {put} /chillers/{chillerId}/photos/{photoId} Enable chiller photo
     * @apiGroup ChillerPhotos
     * @apiPermission authenticated (only self data)
     *
     * @param $userId
     * @param $photoId
     * @return Response
     */
    public function enablePhoto($userId, $photoId)
    {
        if ('1' !== $this->db->fetchColumn('SELECT 1 FROM `chiller_photo` WHERE `id` = ?', [ (int)$photoId ])) {
            throw new NotFoundHttpException("Photo (ID: $photoId) does not exist.");
        }

        if ('1' !== $this->db->fetchColumn('SELECT 1 FROM `chiller_photo` WHERE `id` = ? AND `userid` = ?', [
                (int)$photoId,
                (int)$userId
            ])
        ) {
            throw new BadRequestHttpException("Photo (ID: $photoId) does not belong to the user (ID: $userId).");
        }

        $query = <<<SQL
          UPDATE `chiller_photo` SET `statut` = 0 WHERE `userid` = :userId;
          UPDATE `chiller_photo` SET `statut` = 1 WHERE `id` = :photoId;
SQL;

        $this->db->executeQuery($query, [
            'userId' => $userId,
            'photoId' => $photoId
        ]);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @api {delete} /chillers/{chillerId}/photos/{photoId} Remove chiller photo
     * @apiGroup ChillerPhotos
     * @apiPermission authenticated (only self data)
     *
     * @param $userId
     * @param $photoId
     * @param Application $app
     * @return Response
     */
    public function deletePhoto($userId, $photoId, Application $app)
    {
        if ('1' !== $this->db->fetchColumn('SELECT 1 FROM `chiller_photo` WHERE `id` = ?', [ (int)$photoId ])) {
            throw new NotFoundHttpException("Photo (ID: $photoId) does not exist.");
        }

        list($url, $status) = $this->db->fetchArray("SELECT `url`, `statut` FROM `chiller_photo` WHERE `id` = ? AND `userid` = ?", [
            (int)$photoId,
            (int)$userId,
        ]);

        if (!$url) {
            throw new BadRequestHttpException("Photo (ID: $photoId) does not belong to the user (ID: $userId).");
        }

        if ('1' === $status) {
            throw new BadRequestHttpException("You are not allowed to delete currently enabled photo.");
        }

        (new Filesystem())->remove($app['root.dir'] . $app['upload.directory'] . $url);

        $this->db->delete('chiller_photo', [
            'id' => (int)$photoId
        ]);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @api {post} /chillers/{chillerId}/friends/{friendId} Send an invitation to become friends
     * @apiGroup Chillers
     * @apiPermission authenticated (only self data)
     *
     * @param $userId
     * @param $friendId
     * @return Response
     */
    public function addFriend($userId, $friendId)
    {
        $this->throwExceptionUnlessUserExists($friendId);

        $friends = $this->getFriends($userId, $friendId);

        if (!$friends instanceof Chiller\Friends) {
            $this->db->insert('chiller_friends', [
                'first_id' => $userId < $friendId ? $userId : $friendId,
                'second_id' => $userId > $friendId ? $userId : $friendId,
                'inviting_id' => $userId,
            ]);

            $this->getEventDispatcher()->dispatch(EventListenerProvider::FRIEND_REQUEST, new Event\FriendRequest($this->db->lastInsertId()));

            return new Response('', Response::HTTP_CREATED);
        }

        if (Chiller\Friends::STATUS_PENDING === $friends->getStatus()) {
            throw new BadRequestHttpException("Users (ID: $userId and $friendId) have pending invitation.");
        }

        if (Chiller\Friends::STATUS_CONFIRMED === $friends->getStatus()) {
            throw new BadRequestHttpException("Users (ID: $userId and $friendId) are already friends.");
        }

        if (Chiller\Friends::STATUS_REMOVED === $friends->getStatus()) {
            $this->db->update('chiller_friends', [
                'status' => 0,
                'inviting_id' => $userId
            ], [
                'first_id' => $userId < $friendId ? $userId : $friendId,
                'second_id' => $userId > $friendId ? $userId : $friendId,
            ]);

            $this->getEventDispatcher()->dispatch(EventListenerProvider::FRIEND_REQUEST, new Event\FriendRequest($friends->getId()));
        }

        return new Response('', Response::HTTP_CREATED);
    }

    /**
     * @api {put} /chillers/{chillerId}/friends/{friendId} Accept an invitation to become friends
     * @apiGroup Chillers
     * @apiPermission authenticated (only self data)
     *
     * @param $userId
     * @param $friendId
     * @return Response
     */
    public function acceptFriend($userId, $friendId)
    {
        $query = <<<SQL
            SELECT `id`
            FROM `chiller_friends`
            WHERE (`first_id` = :chillerId OR `second_id` = :chillerId) AND `inviting_id` = :inviting_id
SQL;

        $id = $this->db->fetchColumn($query, [
            'chillerId' => $userId,
            'inviting_id' => $friendId,
        ]);

        if (!$this->db->update('chiller_friends', [ 'status' => 1 ], [ 'id' => $id ])) {
            throw new NotFoundHttpException();
        }

        $this->getEventDispatcher()->dispatch(
            EventListenerProvider::FRIEND_REQUEST_ACCEPTED,
            new Event\FriendRequest($id)
        );

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @api {delete} /chillers/{chillerId}/friends/{friendId} Delete a friend
     * @apiGroup Chillers
     * @apiPermission authenticated (only self data)
     *
     * @param $userId
     * @param $friendId
     * @return Response
     */
    public function deleteFriend($userId, $friendId)
    {
        $deleteQuery = <<<SQL
            DELETE FROM `blacklist` WHERE (blocker = :userId AND blockee = :friendId) OR (blocker = :friendId AND blockee = :userId);
SQL;

        $this->db->executeUpdate($deleteQuery, [ 'userId' => $userId, 'friendId' => $friendId ]);

        $updateQuery = <<<SQL
            UPDATE `chiller_friends`
            SET `status` = 2
            WHERE (`first_id` = :userId AND `second_id` = :friendId) OR (`first_id` = :friendId AND `second_id` = :userId)
SQL;

        if ($this->db->executeUpdate($updateQuery, [ 'userId' => $userId, 'friendId' => $friendId ])) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        throw new NotFoundHttpException();
    }

    /**
     * @api {get} /chillers/{chillerId}/friends/{friendId} Get a chiller's friend details
     * @apiGroup Chillers
     * @apiPermission authenticated (only self data)
     *
     * @apiSuccessExample Example success response:
        {
            "picture": "http://chillter.fr/api/images/avatars/590b057244bc1.jpeg",
            "firstname": "Anne",
            "lastname": "Le Campion",
            "phone": "0686911009",
            "email": "annelecampion@me.com"
        }
     *
     * @param $userId
     * @param $friendId
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getFriendInfo($userId, $friendId)
    {
        if (!$this->areFriends($userId, $friendId)) {
            throw new BadRequestHttpException("Users (ID: $userId and $friendId) are not friends.");
        }

        $sql = <<<SQL
            SELECT p.url AS picture, c.firstname, c.lastname, c.phone, c.email
            FROM chiller c
            LEFT JOIN chiller_photo p ON p.userid = c.id AND p.statut = 1
            WHERE c.id =?
SQL;

        $chiller = $this->db->fetchAssoc($sql, [$friendId]);

        if (!$chiller) {
            throw new BadRequestHttpException();
        }

        return new JsonResponse($this->normalizeEntity('chiller', $chiller));
    }

    /**
     * @api {get} /chillers/{chillerId}/friends Get a chiller's friends
     * @apiGroup Chillers
     * @apiPermission authenticated (only self data)
     *
     * @apiParam {String} [name] Search by chiller's first name
     * @apiParam {Boolean} [pending] Show only pending friend requests
     *
     * @apiSuccessExample Example success response:
        [
            {
                "id": 6,
                "firstname": "Anne",
                "lastname": "Le Campion",
                "picture": "http://chillter.fr/api/images/avatars/590b057244bc1.jpeg"
            },
            {
                "id": 26,
                "firstname": "Mélissa",
                "lastname": "Golcberg",
                "picture": null
            }
        ]
     *
     * @param $userId
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getFriendsList($userId, Request $request)
    {
        $filter = 'yes' === $request->get("pending") || 'true' === $request->get("pending");
        $name = $request->get('name') ? $request->get('name') . '%' : null;

        $params = [
            'chillerId' => $userId
        ];
        $query = <<<SQL
            SELECT c.`id`, c.`firstname`, c.`lastname`, p.`url` AS picture
            FROM `chiller` c
            LEFT JOIN `chiller_photo` p ON p.userid = c.id AND p.statut = 1
            INNER JOIN `chiller_friends` f ON (
                (f.`first_id` = :chillerId AND f.`second_id` = c.`id`) OR (f.`second_id` = :chillerId AND f.`first_id` = c.`id`)
            )
            WHERE c.`id` != :chillerId\n
SQL;

        if ($filter) {
            $query .= "AND f.`status` = " . Chiller\Friends::STATUS_PENDING . " AND f.`inviting_id` != :chillerId \n";
        } else {
            $query .= "AND f.`status` = " . Chiller\Friends::STATUS_CONFIRMED . "\n";
        }

        if ($name) {
            $query .= "AND c.`firstname` LIKE :name\n";
            $params['name'] = $name;
        }

        $collection = $this->db->fetchAll($query, $params) ? : [];

        return new JsonResponse($this->normalizeEntities('chiller', $collection));
    }

    /**
     * @api {get} /chillers/{chillerId}/friends/invitation_sent Get a chiller's sent invitations
     * @apiGroup Chillers
     * @apiPermission authenticated (only self data)
     *
     * @apiSuccessExample Example success response:
        [
            {
                "id": 6,
                "firstname": "Anne",
                "lastname": "Le Campion",
                "picture": "http://chillter.fr/api/images/avatars/590b057244bc1.jpeg"
            },
            {
                "id": 26,
                "firstname": "Mélissa",
                "lastname": "Golcberg",
                "picture": null
            }
        ]
     *
     * @param $userId
     * @return JsonResponse
     */
    public function getFriendsInvitationSent($userId)
    {
        $query = <<<SQL
            SELECT
                COALESCE(c1.`id`, c2.`id`) as 'id',
                COALESCE(c1.`firstname`, c2.`firstname`) as 'firstname',
                COALESCE(c1.`lastname`, c2.`lastname`) as 'lastname',
                p.`url` AS 'picture'
            FROM `chiller_friends` f
            LEFT JOIN `chiller` c1 ON c1.`id` = f.`first_id` AND f.`first_id` != `inviting_id`
            LEFT JOIN `chiller` c2 ON c2.`id` = f.`second_id` AND f.`second_id` != `inviting_id`
            LEFT JOIN `chiller_photo` p ON p.`id` = COALESCE (c1.`id`, c2.`id`)
            WHERE f.`inviting_id` = ? AND f.`status` = ?
SQL;

        $collection = $this->db->fetchAll($query, [$userId, Chiller\Friends::STATUS_PENDING]) ? : [];

        return new JsonResponse($this->normalizeEntities('chiller', $collection));
    }

    /**
     * @api {get} /chillers/{chillerId}/friends/{friendId}/block Block a friend
     * @apiGroup Chillers
     * @apiPermission authenticated (only self data)
     *
     * @param $userId
     * @param $friendId
     * @return Response
     */
    public function blockFriend($userId, $friendId)
    {
        $this->throwExceptionUnlessUserExists($friendId);

        $checkSql = <<<SQL
          SELECT 1
          FROM `blacklist`
          WHERE `blocker` = :userId AND `blockee` = :friendId
SQL;

        if ('1' === $this->db->fetchColumn($checkSql, [ 'userId' => $userId, 'friendId' => $friendId ])) {
            throw new BadRequestHttpException("User (ID: $friendId) is already blocked by user (ID: $userId).");
        }

        $this->db->executeUpdate("INSERT INTO `blacklist` (`blocker`, `blockee`) VALUES (:userId, :friendId)", [
            'userId' => $userId,
            'friendId' => $friendId
        ]);

        return new Response('', Response::HTTP_CREATED);
    }

    /**
     * @api {delete} /chillers/{chillerId}/friends/{friendId}/unblock Unblock a friend
     * @apiGroup Chillers
     * @apiPermission authenticated (only self data)
     *
     * @param $userId
     * @param $friendId
     * @return Response
     */
    public function unblockFriend($userId, $friendId)
    {
        $this->throwExceptionUnlessUserExists($friendId);

        $checkSql = <<<SQL
          SELECT 1
          FROM `blacklist`
          WHERE `blocker` = :userId AND `blockee` = :friendId
SQL;

        if ('1' !== $this->db->fetchColumn($checkSql, [ 'userId' => $userId, 'friendId' => $friendId ])) {
            throw new BadRequestHttpException("User (ID: $friendId) is not blocked by user (ID: $userId).");
        }

        $this->db->delete('blacklist', [
            'blocker' => $userId,
            'blockee' => $friendId
        ]);

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
