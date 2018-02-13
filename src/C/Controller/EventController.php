<?php

namespace C\Controller;

use C\Event;
use C\Application;
use C\Provider\EventListenerProvider;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EventController extends AbstractController
{
    /**
     * @api {post} /chillers/{chillerId}/events Create an event
     * @apiPermission authenticated
     * @apiGroup Events
     *
     * @apiExample Example request (event creation from public chill):
        {
            "event": {
                "chill": {
                    "type": "chill",
                    "id": 90
                },
                "category": 2,
                "name": "New event",
                "color": "ffb206",
                "place": "Event place",
                "address": "Street number",
                "date": "@1496819787",
                "comment": ""
            },
            "chillers": [45, 81, 114],
            "cars": 4,
            "elements": ["super", "super2", "cool"],
            "expenses": [
                {
                    "element": "sel",
                    "price": "1000",
                    "inheriters": ["1", "45", "52"]
                },
                {
                    "element": "poivre",
                    "price": "200",
                    "inheriters": ["1", "45", "52"]
                }
            ]
        }
     *
     * @apiExample Example request (event creation from custom chill):
        {
            "event": {
                "chill": {
                    "type": "custom",
                    "id": 24,
                    "banner_changed": false,
                    "logo_changed": false
                },
                "category": 2,
                "name": "New event",
                "color": "ffb206",
                "place": "Event place",
                "address": "Street number",
                "date": "@1496819787",
                "comment": ""
            },
            "chillers": [45, 81, 114],
            "cars": 4,
            "elements": ["super", "super2", "cool"],
            "expenses": [
                {
                    "element": "sel",
                    "price": "1000",
                    "inheriters": ["1", "45", "52"]
                },
                {
                    "element": "poivre",
                    "price": "200",
                    "inheriters": ["1", "45", "52"]
                }
            ]
        }
     *
     * @param $userId
     * @param Application $app
     * @throws \Exception
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function createNewEvent($userId, Application $app)
    {
        $data = $app['chillter.json.req'];

        if (!$data) {
            throw new BadRequestHttpException();
        }

        $chill = $data["event"]['chill'];

        if (!in_array($chill['type'], ['chill', 'custom'])) {
            throw new BadRequestHttpException("Invalid chill type: \"".$chill['type']."\"");
        }

        $chillId = 'chill' === $chill['type'] ? (int)$chill['id'] : null;
        $category = (int)$data["event"]["category"];
        $name = $data["event"]["name"];
        $color = $data["event"]["color"];
        $place = $data["event"]["place"];
        $address = $data["event"]["address"];
        $date = date_create($data["event"]["date"]);
        $endingDate = isset($data["event"]["endingDate"]) ? date_create($data["event"]["endingDate"]) : $date;
        $comment = $data["event"]["comment"];

        $this->db->beginTransaction();

        try {
            $app['db']->executeUpdate(
                "
                INSERT INTO event (`chill`, `category`, `name`, `color`, `chillerid` , `place` , `address`, `date`, `ending_date`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                array($chillId, $category, $name, $color, (int)$userId, $place, $address, date_format($date, 'Y-m-d H:i:s'), date_format($endingDate, 'Y-m-d H:i:s'))
            );

            $eventId = $this->db->lastInsertId();

            if (trim($comment)) {
                $this->db->insert('event_message', [
                    'message' => $comment,
                    'event_id' => $eventId,
                    'chiller_id' => $userId,
                ]);
            }

            $this->db->insert('event_participant', [
                'chillerid' => $userId,
                'eventid' => $eventId,
                'statut' => 1,
            ]);

            if (isset($data['chillers'])) {
                foreach ($data['chillers'] as $participant) {
                    $this->db->insert('event_participant', [
                        'chillerid' => $participant,
                        'eventid' => $eventId,
                        'statut' => 3
                    ]);
                }
            }

            if (isset($data['cars'])) {
                $this->db->insert('car', [
                    'chillerid' => $userId,
                    'eventid' => $eventId,
                    'seats' => $data['cars']
                ]);

                $this->db->insert('car_passenger', [
                    'chillerid' => $userId,
                    'carid' => $this->db->lastInsertId(),
                    'driver' => 1
                ]);
            }

            if (isset($data['elements'])) {
                foreach ($data['elements'] as $element) {
                    $app['db']->insert('list', array(
                        'eventid' => (int)$eventId,
                        'created_by' => (int)$userId,
                        'assigned_to' => (int)$userId,
                        'content' => $element
                    ));
                }
            }

            if (isset($data['expenses'])) {
                foreach ($data['expenses'] as $expense) {
                    $this->db->insert('expense', [
                        'eventid' => (int)$eventId,
                        'chillerid' => (int)$userId,
                        'element' => $expense["element"],
                        'price' => $expense["price"]
                    ]);

                    $expenseId = $this->db->lastInsertId();

                    foreach ($expense["inheriters"] as $inheriter) {
                        $this->db->insert('expense_inheritor', [
                            'expenseid' => $expenseId,
                            'chillerid' => (int)$inheriter
                        ]);
                    }
                }
            }

            if ('custom' === $chill['type']) {
                $copyBannerFromCustomChill = !(array_key_exists('banner_changed', $chill) && true === $chill['banner_changed']);
                $copyLogoFromCustomChill = !(array_key_exists('logo_changed', $chill) && true === $chill['logo_changed']);

                if ($copyBannerFromCustomChill || $copyLogoFromCustomChill) {
                    $query = "SELECT `logo`, `banner` FROM `chills_custom` WHERE `id` = ?";
                    list($logo, $banner) = $this->db->fetchArray($query, [$chill['id']]);

                    if ($logo) {
                        $oldPath = $app['root.dir'] . $app['upload.directory'] . $logo;
                        $newPath = "events/logos/" . uniqid() . '.' . pathinfo($logo, PATHINFO_EXTENSION);

                        (new Filesystem())->copy($oldPath, $app['root.dir'] . $app['upload.directory'] . $newPath);

                        $this->db->update('event', ['logo' => $newPath], ['id' => (int)$eventId]);
                    }

                    if ($banner) {
                        $oldPath = $app['root.dir'] . $app['upload.directory'] . $banner;
                        $newPath = "events/banners/" . uniqid() . '.' . pathinfo($banner, PATHINFO_EXTENSION);

                        (new Filesystem())->copy($oldPath, $app['root.dir'] . $app['upload.directory'] . $newPath);

                        $this->db->update('event', ['banner' => $newPath], ['id' => (int)$eventId]);
                    }
                }
            }


            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        if (isset($data['chillers'])) {
            foreach ($data['chillers'] as $participantId) {
                $this->getEventDispatcher()->dispatch(
                    EventListenerProvider::EVENT_PARTICIPATION_CREATED,
                    new Event\EventParticipantCreated($eventId, $participantId, $userId)
                );
            }
        }

        return new JsonResponse(
            ['id' => $eventId],
            Response::HTTP_CREATED,
            ['X-Resource-ID' => $eventId]
        );
    }

    /**
     * @api {get} /chillers/{chillerId}/events/{eventId} Get event details
     * @apiGroup Events
     * @apiPermission authenticated (only self data)
     *
     * @apiSuccessExample Example success response:
        {
            "id": "417",
            "is_cancelled": false,
            "category": {
                "id": "1",
                "name": "culture"
            },
            "chill": {
                "id": "7",
                "name": "exposition",
                "logo": "http://chillter.fr/api/images/chills/exposition.svg"
            },
            "chat_message": "Hello! WHat do you think about this event?",
            "logo": "http://chillter.fr/api/images/events/logos/592fe268e133d.png",
            "banner": null,
            "name": "restaurant",
            "color": "c661b5",
            "chillerid": "144",
            "place": "",
            "address": "92 Boulevard Victor Hugo",
            "date": "2017-05-11T11:16:51+00:00",
            "ending_date": "2017-06-11T11:16:51+00:00",
            "chillers": [
                {
                    "id": "136",
                    "firstname": "Anne",
                    "lastname": "Le Campion",
                    "picture": "http://chillter.fr/api/images/avatars/590b057244bc1.jpeg"
                    "statut": "3"
                },
                {
                    "id": "144",
                    "firstname": "Mélissa",
                    "lastname": "Golcberg",
                    "picture": null,
                    "statut": "1"
                }
            ]
        }
     *
     * @param $userId
     * @param $eventId
     * @param Application $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getDetailEvent($userId, $eventId, Application $app, Request $request)
    {
        $this->denyAccessUnlessInvitedToEvent($userId, $eventId);

        $sql = <<<SQL
            SELECT e.`id`, e.`logo`, e.`name`, e.`color`, e.`banner`, e.`chillerid`, e.`place`, e.`address`, e.`date`,e.`ending_date`, e.`is_cancelled`,
                    c.`id` as "category_id", c.`name` as "category_name",
                    ch.`id` as "chill_id", ch.`name` as "chill_name"
            FROM `event` e
            LEFT JOIN `category` c ON c.`id` = e.`category`
            LEFT JOIN `chills` ch ON ch.`id` = e.`chill`
            WHERE e.id = ?
SQL;

        $result = $app['db']->fetchAssoc($sql, array((int)$eventId));

        if (!$result) {
            throw new NotFoundHttpException();
        }

        $firstMessageQuery = <<<SQL
            SELECT `message`
            FROM `event_message`
            WHERE `event_id` = ?
            ORDER BY `creation_date` ASC
            LIMIT 1
SQL;

        $translator = $app['translator.extension'];

        $eventJSON = [
            "id" => $result["id"],
            "is_cancelled" => '1' === $result['is_cancelled'],
            "category" => [
                "id" => $result["category_id"],
                "name" => $result["category_name"],
            ],
            "chill" => [
                "id" => $result["chill_id"] ? (int)$result["chill_id"] : null,
                "name" => $translator->transChill($result["chill_name"]),
                "logo" => $result["chill_name"] ? "assets/images/chills/" . $result["chill_name"] . ".svg" : null
            ],
            "chat_message" => $this->db->fetchColumn($firstMessageQuery, array((int)$eventId)),
            "logo" => $result["logo"] ? $request->getUriForPath($app['upload.directory'] . $result["logo"]) : null,
            "banner" => $result["banner"] ? $request->getUriForPath($app['upload.directory'] . $result["banner"]) : null,
            "name" => $result["name"],
            "color" => $result["color"],
            "chillerid" => $result["chillerid"],
            "place" => $result["place"],
            "address" => $result["address"],
            "date" => date("c", strtotime($result["date"])),
            "ending_date" => date("c", strtotime($result["ending_date"])),
            "chillers" => [],
        ];

        $chillerSql = <<<SQL
            SELECT DISTINCT c.id as id, c.firstname, c.lastname, p.url as picture, e.statut
            FROM event_participant e
            INNER JOIN chiller c ON e.chillerid = c.id
            LEFT JOIN chiller_photo p ON p.userid = c.id AND p.statut = 1
            WHERE e.eventid = ?
SQL;

        $chillers = $app['db']->fetchAll($chillerSql, array($eventId)) ? : [];

        $eventJSON["chillers"] = $this->normalizeEntities('chiller', $chillers);

        return $app->json($eventJSON);
    }

    /**
     * @api {get} /chillers/{chillerId}/events Get events
     * @apiGroup Events
     * @apiPermission authenticated (only self data)
     *
     * @apiSuccessExample Example success response:
     *
        [
            {
                "date": "2017-06-07T08:41:53+00:00",
                "ending_date": "2017-07-07T08:41:53+00:00",
                "participation_status": 1,
                "info": {
                    "id": 51,
                    "name": "exposition",
                    "color" "ffb206",
                    "chiller": "Valentin",
                    "chillerid": 137,
                    "logo": "http://chillter.fr/api/images/events/logos/592fe268e133d.png",
                    "chill": {
                        "id": 7,
                        "name": "exposition",
                        "logo": "http://chillter.fr/api/images/chills/exposition.svg"
                    }
                }
            }
        ]
     *
     * @param $userId
     * @param Request $request
     * @param Application $app
     * @return JsonResponse
     */
    public function getListEvents($userId, Request $request, Application $app)
    {
        $translator = $app['translator.extension'];
        $chiller = ($request->get('chiller') != null) ? htmlentities($request->get('chiller')) : false;

        if ($chiller === false) {
            $eventSql = <<<SQL
                  SELECT DISTINCT e.id, e.date,e.ending_date, e.name, e.chillerid, e.logo, e.banner, u.firstname, e.is_cancelled, c.`statut` as 'participation_status',
                                  `chills`.`id` as "chill_id", `chills`.`name` as "chill_name" , `chills`.`logo` as "chill_logo" , `chills`.`banner` as "chill_banner" ,
                                  k.id as "category"
                  FROM event e
                  LEFT JOIN `chills` ON `chills`.`id` = e.`chill`
                  LEFT JOIN `category` k ON k.`id` = e.`category`
                  INNER JOIN `event_participant` c ON c.eventid = e.id AND c.chillerid = :currentChiller
                  INNER JOIN chiller u ON u.id = e.chillerid  
                  LEFT JOIN `event_hidden` h ON h.`event_id` = e.`id` AND h.`chiller_id` = :currentChiller
                  WHERE h.`id` IS NULL
                  ORDER BY e.date
SQL;
            $paramList = [
                'currentChiller' => $userId
            ];
        } else {
            $eventSql = <<<SQL
                SELECT DISTINCT e.id, e.date, e.ending_date , e.name, e.chillerid, u.firstname, e.logo, e.banner, c.chillerid as filter, e.is_cancelled, c.`statut` as 'participation_status',
                                `chills`.`id` as "chill_id", `chills`.`name` as "chill_name" , `chills`.`logo` as "chill_logo" , `chills`.`banner` as "chill_banner",
                                k.id as "category"
                FROM event e
                LEFT JOIN `chills` ON `chills`.`id` = e.`chill`
                LEFT JOIN `category` k ON k.`id` = e.`category`
                INNER JOIN `event_participant` c ON c.eventid = e.id AND (c.chillerid = :currentChiller OR c.chillerid = :filterChiller)
                INNER JOIN chiller u ON u.id = e.chillerid
                LEFT JOIN `event_hidden` h ON h.`event_id` = e.`id` AND h.`chiller_id` = :currentChiller
                WHERE h.`id` IS NULL
                ORDER BY e.date
SQL;
            $paramList = [
                'currentChiller' => $userId,
                'filterChiller' => $chiller
            ];
        }

        $dbrps = $app['db']->fetchAll($eventSql, $paramList) ? : [];

        if ($chiller != false) {
            $tmpList = [];
            foreach ($dbrps as $d) {
                $i = 0;
                foreach ($dbrps as $t) {
                    if ($d["filter"] == $userId && $t["filter"] == $chiller && $d["id"] == $t["id"]) {
                        $tmpList[] = $d;
                    }
                    $i++;
                }
            }
            $dbrps = $tmpList;
        }

        $eventJSON = [];

        foreach ($dbrps as $d) {
            $eventJSON[] = array(
                "date" => date("c", strtotime($d["date"])),
                "ending_date" => date("c", strtotime($d["ending_date"])),
                "participation_status" => (int)$d['participation_status'],
                "info" => array(
                    "id" => (int)$d["id"],
                    "is_cancelled" => '1' === $d['is_cancelled'],
                    "name" => html_entity_decode($d["name"]),
                    'color' => $d['color'],
                    "chiller" => html_entity_decode($d["firstname"]),
                    "chillerid" => (int)$d["chillerid"],
                    "logo" => $d["logo"] ? $request->getUriForPath($app['upload.directory'] . $d["logo"]) : null,
                    "banner" => $d["banner"] ? $request->getUriForPath($app['upload.directory'] . $d["banner"]) : null,
                    "chill" => [
                        "id" => (int)$d["chill_id"],
                        "name" => $translator->transChill($d["chill_name"]),
                        "logo" => $d["chill_name"] ? "assets/images/chills/" . $d["chill_name"] . ".svg" : null,
                        "category" => $d["category"]
                    ],
                )
            );
        }

        return $app->json($eventJSON);
    }

    /**
     * @api {put} /chillers/{chillerId}/events/{eventId}/hide Hide an event
     * @apiPermission authenticated (only own event)
     * @apiGroup Events
     *
     * @apiError 400 Event is already hidden for user.
     * @apiError 403 User is not invited to the event.
     * @apiSuccess 204 Event successfully hidden.
     *
     * @param $userId
     * @param $eventId
     * @return Response
     */
    public function hideEvent($userId, $eventId)
    {
        $this->denyAccessUnlessInvitedToEvent($userId, $eventId);

        try {
            $this->db->insert('event_hidden', [
                'chiller_id' => (int)$userId,
                'event_id' => (int)$eventId,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            throw new BadRequestHttpException("Event (ID: $eventId) is already hidden for user (ID: $userId).");
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @api {put} /chillers/{chillerId}/events/{eventId}/cancel Cancel an event
     * @apiPermission authenticated (only event creator)
     * @apiGroup Events
     *
     * @apiError 403 User is not invited to the event.
     * @apiSuccess 204 Event successfully cancelled.
     *
     * @param $userId
     * @param $eventId
     * @return Response
     */
    public function cancelEvent($userId, $eventId)
    {
        $this->denyAccessUnlessIsEventCreator($userId, $eventId);
        $this->db->update('event', [ 'is_cancelled' => 1 ], [ 'id' => (int)$eventId ]);

        $this->getEventDispatcher()->dispatch(EventListenerProvider::EVENT_CANCELLED, new Event\EventCancelled($eventId));

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @api {put} /chillers/{chillerId}/events/{eventId} Update an event
     * @apiPermission authenticated (only own event)
     * @apiGroup Events
     *
     * @apiExample Example request:
        {
            "event": {
                "name": "New event",
                "color": "ffb206",
                "place": "Event place",
                "address": "Street number",
                "date": "@1496819787",
                "ending_date": "@1496819787"
            }
        }
     * @param $userId
     * @param $eventId
     * @param Application $app
     * @return Response
     */
    public function updateEvent($userId, $eventId, Application $app)
    {
        $data = $app['chillter.json.req'];

        if (!$this->isEventCreator($userId, $eventId)) {
            throw new AccessDeniedHttpException("User (ID: $userId) is not a creator of event (ID: $eventId).");
        }

        if (!$data) {
            throw new BadRequestHttpException();
        }

        $sql = <<<SQL
                UPDATE event SET `name` = ?, `color` = ?, `place` = ?, `address` = ?, `date` = ?, `ending_date` = ?
                WHERE `id` = ?
SQL;

        $this->db->executeUpdate($sql, [
            $data["event"]["name"],
            $data["event"]["color"],
            $data["event"]["place"],
            $data["event"]["address"],
            $data["event"]["date"],
            $data["event"]["ending_date"],
            (int) $eventId
        ]);

        $this->getEventDispatcher()->dispatch(EventListenerProvider::EVENT_UPDATED, new Event\EventUpdated($eventId));

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @api {post} /chillers/{userId}/events/{eventId}/logo Upload an event logo
     * @apiGroup Events
     * @apiPermission authenticated (only self data)
     *
     * @apiExample Example request:
        {
            "image": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAA3XAAAN1wFCKJt4AAAAB3RJTUUH4QsJDx8KPce8oQAAAW5JREFUGNMlzF1Lk2EYwPH/de+Ze8acm5RpzWELZOJL6yCh8MCP0GEhehohgn2RoCDIwxCP/BgeiOxANOzFSle+obNINzf3vNz35UG/D/CT4F2B1OIxwYd8nq7kC8kOVsDE2jzc06Dz0Z9vXARvCwhAsDRYIXNry9x9onq2KahD+h+rnlVF2/Wp1Mvjdem8uTMit4eX8fxJwgbaOAQEyRXBS4NzW3q+O+ep9comNzqJn4PWOSQHIJVFxAM/D9HVI1f/NeaRLs3Y3TXUxRC1ka4Mqg7iEJL+/z1TnDEaYzU06LXDlJ+h6QKSH0HDBNqK0NCgsVjjjn6vmvvTaCvAfqsidGNrX9BA0HZMYmgad3K0aoj0q6193jD9FVz9lPjHNtpRtBVh+saxB9836USfBKD56sFDyfRuJ8pP1R3sCDhMcULtz6po8+9Udml/Xa4WSnS/r3E529eDST03vffGEYndv5Ma9no5t/Kn0X5d4gZ1/qt+WXQnQAAAAABJRU5ErkJggg=="
        }
     *
     * @apiSuccessExample Example success response:
        {
            "url": "http://chillter.fr/api/images/events/logos/59d4a0bb98b18.jpeg"
        }
     *
     * @param $userId
     * @param $eventId
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Exception
     */
    public function postLogo($userId, $eventId, Request $request)
    {
        $this->denyAccessUnlessInvitedToEvent($userId, $eventId);

        $content = \GuzzleHttp\json_decode($request->getContent(), true);

        if (!array_key_exists('image', $content)) {
            throw new BadRequestHttpException();
        }

        $fileName = $this->savePhotoFromBase64String($content['image'], 'events/logos/');

        if ($current = $this->db->fetchColumn("SELECT `logo` FROM `event` WHERE`id` = ?", [$eventId])) {
            (new Filesystem())->remove($this->getRootDir().$this->getUploadDirectory().$current);
        }

        $this->db->update('event', ['logo' => 'events/logos/'.$fileName], ['id' => $eventId]);

        return new JsonResponse([
            "url" => $request->getUriForPath($this->getUploadDirectory().'events/logos/'.$fileName)
        ], Response::HTTP_CREATED);
    }

    /**
     * @api {delete} /chillers/{userId}/events/{eventId}/logo Delete an event logo
     * @apiGroup Events
     * @apiPermission authenticated (only self data)
     *
     * @param $userId
     * @param $eventId
     * @param Application $app
     * @return Response
     */
    public function deleteChillPhoto($userId, $eventId, Application $app)
    {
        $this->denyAccessUnlessInvitedToEvent($userId, $eventId);

        if ($currentLogo = $app['db']->fetchColumn("SELECT `logo` FROM `event` WHERE`id` = ?", array((int)$eventId))) {
            $fs = new Filesystem();
            $fs->remove($app['root.dir'] . $app['upload.directory'] . $currentLogo);
            $app['db']->executeQuery("UPDATE `event` SET `logo` = NULL WHERE`id` = ?", array((int)$eventId));

            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        throw new NotFoundHttpException();
    }

    /**
     * @api {post} /chillers/{userId}/events/{eventId}/banner Upload an event banner
     * @apiGroup Events
     * @apiPermission authenticated (only self data)
     *
     * @apiExample Example request:
        {
            "image": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAA3XAAAN1wFCKJt4AAAAB3RJTUUH4QsJDx8KPce8oQAAAW5JREFUGNMlzF1Lk2EYwPH/de+Ze8acm5RpzWELZOJL6yCh8MCP0GEhehohgn2RoCDIwxCP/BgeiOxANOzFSle+obNINzf3vNz35UG/D/CT4F2B1OIxwYd8nq7kC8kOVsDE2jzc06Dz0Z9vXARvCwhAsDRYIXNry9x9onq2KahD+h+rnlVF2/Wp1Mvjdem8uTMit4eX8fxJwgbaOAQEyRXBS4NzW3q+O+ep9comNzqJn4PWOSQHIJVFxAM/D9HVI1f/NeaRLs3Y3TXUxRC1ka4Mqg7iEJL+/z1TnDEaYzU06LXDlJ+h6QKSH0HDBNqK0NCgsVjjjn6vmvvTaCvAfqsidGNrX9BA0HZMYmgad3K0aoj0q6193jD9FVz9lPjHNtpRtBVh+saxB9836USfBKD56sFDyfRuJ8pP1R3sCDhMcULtz6po8+9Udml/Xa4WSnS/r3E529eDST03vffGEYndv5Ma9no5t/Kn0X5d4gZ1/qt+WXQnQAAAAABJRU5ErkJggg=="
        }
     *
     * @apiSuccessExample Example success response:
        {
            "url": "http://chillter.fr/api/images/events/banners/59d4a0bb98b18.jpeg"
        }
     *
     * @param $userId
     * @param $eventId
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Exception
     */
    public function postBanner($userId, $eventId, Request $request)
    {
        $this->denyAccessUnlessInvitedToEvent($userId, $eventId);

        $content = \GuzzleHttp\json_decode($request->getContent(), true);

        if (!array_key_exists('image', $content)) {
            throw new BadRequestHttpException();
        }

        $fileName = $this->savePhotoFromBase64String($content['image'], 'events/banners/');

        if ($current = $this->db->fetchColumn("SELECT `banner` FROM `event` WHERE`id` = ?", [$eventId])) {
            (new Filesystem())->remove($this->getRootDir().$this->getUploadDirectory().$current);
        }

        $this->db->update('event', ['banner' => 'events/banners/'.$fileName], ['id' => $eventId]);

        return new JsonResponse([
            "url" => $request->getUriForPath($this->getUploadDirectory().'events/banners/'.$fileName)
        ], Response::HTTP_CREATED);
    }

    /**
     * @api {delete} /chillers/{userId}/events/{eventId}/banner Delete an event banner
     * @apiGroup Events
     * @apiPermission authenticated (only self data)
     *
     * @param $userId
     * @param $eventId
     * @param Application $app
     * @return Response
     */
    public function deleteBanner($userId, $eventId, Application $app)
    {
        $this->denyAccessUnlessInvitedToEvent($userId, $eventId);

        if ($currentLogo = $app['db']->fetchColumn("SELECT `banner` FROM `event` WHERE`id` = ?", array((int)$eventId))) {
            $fs = new Filesystem();
            $fs->remove($app['root.dir'] . $app['upload.directory'] . $currentLogo);
            $app['db']->executeQuery("UPDATE `event` SET `banner` = NULL WHERE`id` = ?", array((int)$eventId));

            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        throw new NotFoundHttpException();
    }
}
