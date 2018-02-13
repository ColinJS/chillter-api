<?php

namespace C\Controller;

use C\Application;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ChillController extends AbstractController
{
    /**
     * @api {post} /chillers/{chillerId}/custom_chills Create a custom chill
     * @apiGroup Chills
     *
     * @apiParam {String} name Event name.
     * @apiParam {String} place Event place.
     * @apiParam {String} address Event address.
     * @apiParam {String} comment Description / comment about the event.
     * @apiParam {Boolean} [take_logo] Determines if creator's logo should be used as chill logo.
     *
     * @apiExample Example request:
        {
            "name": "My custom chill",
            "place": "SensioLabs",
            "address": "92-98 Boulevard Victor Hugo, 92110 Clichy",
            "comment": "Let's meet here to...",
            "take_logo": true
        }
     *
     *
     * @param $userId
     * @param Application $app
     * @return Response
     */
    public function postCustomChill($userId, Application $app)
    {
        $payload = [
            'name' => null,
            'place' => null,
            'address' => null,
            'comment' => null
        ];

        $payload = array_merge($payload, $app['chillter.json.req']);

        if (is_null($payload['name'])
            || is_null($payload['place'])
            || is_null($payload['address'])
            || is_null($payload['comment'])
        ) {
            return $app->json(null, 400);
        }

        $app['db']->insert('chills_custom', [
            'chiller_id' => $userId,
            'name' => $payload['name'],
            'place' => $payload['place'],
            'address' => $payload['address'],
            'comment' => $payload['comment'],
        ]);

        $chillId = $this->db->lastInsertId();

        if (array_key_exists('take_logo', $payload)
            && true === $payload['take_logo']
            && $logo = $this->db->fetchColumn('SELECT `url` FROM `chiller_photo` WHERE `userid` = ? AND `statut` = 1 LIMIT 1', [$userId])
        ) {
            $oldPath = $app['root.dir'] . $app['upload.directory'] . $logo;
            $newPath = "chills/custom/".uniqid().'.'.pathinfo($logo, PATHINFO_EXTENSION);

            (new Filesystem())->copy($oldPath, $app['root.dir'] . $app['upload.directory'] . $newPath);

            $this->db->update('chills_custom', ['logo' => $newPath], ['id' => (int)$chillId]);
        }

        return new JsonResponse(
            ['id' => $chillId],
            Response::HTTP_CREATED,
            ['X-Resource-Id' => $this->db->lastInsertId()]
        );
    }

    /**
     * @api {put} /chillers/{chillerId}/custom_chills/{chillId} Update a custom chill
     * @apiPermission authenticated (only own event)
     * @apiGroup Chills
     *
     * @apiExample Example request:
        {
            "chill": {
                "name": "New event",
                "place": "Event place",
                "address": "Street number",
                "comment": "Let's meet here",
                "take_logo": true
            }
        }
     * @param $userId
     * @param $chillId
     * @param Application $app
     * @return Response
     */
    public function updateCustomChill($userId, $chillId, Application $app)
    {
        $this->denyAccessUnlessGrantedToCustomChill($userId, $chillId);

        $payload = [
            'name' => null,
            'place' => null,
            'address' => null,
            'comment' => null
        ];

        $payload = array_merge($payload, $app['chillter.json.req']);
        
        foreach($payload as $data){
            if (null === $data) {
            throw new BadRequestHttpException();
            }
        }

        $sql = <<<SQL
                UPDATE chills_custom SET `name` = ?, `place` = ?, `address` = ?, `comment` = ?
                WHERE `id` = ?
SQL;

        $this->db->executeUpdate($sql, [
            $payload["name"],
            $payload["place"],
            $payload["address"],
            $payload["comment"],
            (int) $chillId
        ]);

        return new Response('', Response::HTTP_NO_CONTENT);
    } 

    /**
    * @api {delete} /chillers/{chillerId}/custom_chills/{chillId} Delete custom a custom chill
    * @apiGroup Chills
    * @apiPermission authenticated (only own chill)
    *
    * @param $userId
    * @param $chillId
    * @param $request
    * @param $app
    * @return Response
    **/   
    public function deleteCustomChill($userId, $chillId, Request $request, Application $app){
        
        $this->denyAccessUnlessGrantedToCustomChill($userId, $chillId);

        $app['db']->delete('chiller_home',[
            'custom_id' => (int)$chillId
        ]);

        if ($current = $this->db->fetchColumn("SELECT `banner` FROM `chills_custom` WHERE`id` = ?", [$chillId])) {
            (new Filesystem())->remove($this->getRootDir().$this->getUploadDirectory().$current);
        }

        if ($current = $this->db->fetchColumn("SELECT `logo` FROM `chills_custom` WHERE`id` = ?", [$chillId])) {
            (new Filesystem())->remove($this->getRootDir().$this->getUploadDirectory().$current);
        }

        $app['db']->delete('chills_custom',[
                'id' => (int)$chillId
            ]);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @api {get} /chillers/{chillerId}/custom_chills Get custom chills
     * @apiGroup Chills
     *
     * @apiSuccessExample Example success response:
        [
            {
                "id": "4",
                "name": "My custom chill",
                "logo": "http://chillter.fr/api/images/chills/custom/59d4a0bb98b18.jpeg",
                "banner": "http://chillter.fr/api/images/chills/custom/59d648ff457d2.jpeg",
                "place": "SensioLabs",
                "address": "92-98 Boulevard Victor Hugo, 92110 Clichy",
                "comment": "Let's meet here to..."
            }
        ]
     *
     * @param $userId
     * @param $request
     * @param $app
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getCustomChills($userId, Request $request, Application $app)
    {
        $sql = <<<SQL
            SELECT `id`, `name`, `logo`, `banner`, `place`, `address`, `comment`
            FROM `chills_custom`
            WHERE chiller_id = ?
            ORDER BY `name` ASC
SQL;

        $customChills = $this->db->fetchAll($sql, [$userId]);

        foreach ($customChills as &$customChill) {
            if ($customChill['logo']) {
                $customChill['logo'] = $request->getUriForPath($app['upload.directory'] . $customChill['logo']);
            }

            if ($customChill['banner']) {
                $customChill['banner'] = $request->getUriForPath($app['upload.directory'] . $customChill['banner']);
            }
        }

        return new JsonResponse($customChills);
    }

    /**
     * @api {get} /chillers/{chillerId}/custom_chills/{chillId} Get a custom chill
     * @apiGroup Chills
     * @apiPermission authenticated (only own chill)
     *
     * @apiSuccessExample Example success response:
        {
            "id": 90,
            "name": "My custom chill",
            "logo": "http://chillter.fr/api/images/chills/custom/59d4a0bb98b18.jpeg",
            "banner": "http://chillter.fr/api/images/chills/custom/12d4a0bb98b1d.jpeg",
            "car_seats": 3,
            "place": "SensioLabs",
            "address": "92-98 Boulevard Victor Hugo, 92110 Clichy",
            "comment": "Let's meet here to...",
            "expenses": [
                {
                    "id": 8,
                    "name": "Whisky",
                    "price": 20.75,
                    "inheritors": []
                },
                {
                    "id": 23,
                    "name": "Whisky",
                    "price": 20.75,
                    "inheritors": [
                        {
                            "id": 108,
                            "firstname": "Adrieb",
                            "lastname": "Frappat",
                            "picture": "http://www.chillter.fr/api/v0.1/images/avatars/58d809243ba91.jpeg"
                        }
                    ]
                }
            ],
            "elements": [
                {
                    "id": 203,
                    "name": "Lorem ipsum..."
                }
            ],
            "participants": [
                {
                    "id": 108,
                    "firstname": "Adrieb",
                    "lastname": "Frappat",
                    "picture": "http://www.chillter.fr/api/v0.1/images/avatars/58d809243ba91.jpeg"
                }
            ]
        }
     *
     * @param $userId
     * @param $chillId
     * @param $request
     * @param $app
     * @return JsonResponse
     */
    public function getCustomChill($userId, $chillId, Request $request, Application $app)
    {
        $this->denyAccessUnlessGrantedToCustomChill($userId, $chillId);

        $sql = <<<SQL
            SELECT `id`, `name`, `logo`, `banner`, `car_seats`, `place`, `address`, `comment`
            FROM `chills_custom`
            WHERE `id` = ?
SQL;

        $customChill = $this->db->fetchAssoc($sql, [$chillId]);

        $customChill['id'] = (int)$customChill['id'];
        $customChill['car_seats'] = $customChill['car_seats'] ? (int)$customChill['car_seats'] : null;

        if ($customChill['logo']) {
            $customChill['logo'] = $request->getUriForPath($app['upload.directory'] . $customChill['logo']);
        }

        if ($customChill['banner']) {
            $customChill['banner'] = $request->getUriForPath($app['upload.directory'] . $customChill['banner']);
        }

        $customChill['expenses'] = $this->db->fetchAll("SELECT `id`, `name`, `price` FROM `chills_custom_expense` WHERE `chills_custom_id` = ?", [
            $chillId
        ]) ? : [];

        foreach ($customChill['expenses'] as &$expense) {
            $inheritorsSql = <<<SQL
                SELECT c.`id`, c.`firstname`, c.`lastname`, photo.`url` as 'picture'
                FROM `chills_custom_expense_inheritor` i
                LEFT JOIN `chiller` c ON i.`chiller_id` = c.`id`
                LEFT JOIN `chiller_photo` photo ON photo.`userid` = c.`id` AND photo.`statut` = 1
                WHERE i.`expense_id` = ?
SQL;

            $inheritors = $this->db->fetchAll($inheritorsSql, [$expense['id']]) ? : [];

            $expense['inheritors'] = $this->normalizeEntities('chiller', $inheritors);
            $expense['id'] = (int)$expense['id'];
            $expense['price'] = (float)$expense['price'];
        }

        $elements = $this->db->fetchAll("SELECT `id`, `name` FROM `chills_custom_element` WHERE `chills_custom_id` = ?", [
            $chillId
        ]) ? : [];

        $customChill['elements'] = array_map(function($element) {
            return [
                'id' => (int)$element['id'],
                'name' => $element['name'],
            ];
        }, $elements);

        $participantsSql = <<<SQL
            SELECT c.`id`, c.`firstname`, c.`lastname`, photo.`url` as 'picture'
            FROM `chills_custom_participant` p
            LEFT JOIN `chiller` c ON p.`chiller_id` = c.`id`
            LEFT JOIN `chiller_photo` photo ON photo.`userid` = c.`id` AND photo.`statut` = 1
            WHERE `chills_custom_id` = ?
SQL;

        $participants = $this->db->fetchAll($participantsSql, [$chillId]) ? : [];
        $customChill['participants'] = $this->normalizeEntities('chiller', $participants);

        return new JsonResponse($customChill);
    }

    /**
     * @api {get} /chills/{chillId} Get a chill
     * @apiGroup Chills
     * @apiPermission anonymous
     *
     * @apiSuccessExample Example success response:
        {
            "id": "1",
            "logo": "cinema",
            "name": "cinema",
            "banner": "",
            "category": "1",
            "color": "fd7479"
        }
     *
     * @param $chillId
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getChill($chillId)
    {
        $sql = <<<SQL
          SELECT c.id, c.name,c.logo, c.banner, c.category, cat.color
          FROM chills c
          LEFT JOIN category cat ON c.category = cat.id
          WHERE c.id = ?
SQL;
        $data = $this->db->fetchAssoc($sql, array($chillId));

        /** @var string $name */
        $data['name'] = $this->app['translator.extension']->transChill($data['name']);

        return new JsonResponse($data);
    }

    /**
     * @api {get} /chills Get all chills
     * @apiGroup Chills
     * @apiPermission anonymous
     * @apiDescription All Chills are grouped by categories. Fetches all public chills (not private).
     *
     * @apiSuccessExample Example success response:
        [
            {
                "name": "culture",
                "chills": [
                    {
                        "info": {
                            "id": "1",
                            "name": "cinema",
                            "logo": "cinema",
                            "color": "fd7479"
                        },
                        "link": {
                            "chill": "1"
                        }
                    },
                    {
                        "info": {
                            "id": "6",
                            "name": "concert",
                            "logo": "concert",
                            "color": "fd7479"
                        },
                        "link": {
                            "chill": "6"
                        }
                    }
                ]
            },
            {
                "name": "rassemblement",
                "chills": [
                    {
                        "info": {
                            "id": "2",
                            "name": "verre",
                            "logo": "verre",
                            "color": "ffb206"
                        },
                        "link": {
                            "chill": "2"
                        }
                    }
                ]
            }
        ]

     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getAllChills()
    {
        $sql = <<<SQL
            SELECT c.id, c.name,c.logo, t.color, t.name as cat_name, t.id as cat_id
            FROM chills c
            INNER JOIN category t ON c.category = t.id
SQL;
        $collection = [];
        $translator = $this->app['translator.extension'];

        foreach ($this->db->fetchAll($sql) as $chill) {
            $categoryIndex = (int)$chill['cat_id'] - 1;

            if (!array_key_exists($categoryIndex, $collection)) {
                $collection[$categoryIndex] = [
                    "name" => $translator->trans('category.'.$chill["cat_name"]),
                    "color" => $chill["color"],
                    "chills" => []
                ];
            }

            $name = $translator->transChill($chill["name"]);

            $collection[$categoryIndex]['chills'][] = [
                "info" => array(
                    "id" => $chill["id"],
                    "name" => $name,
                    "logo" => $chill["logo"],
                    "color" => $chill["color"]
                ),
                "link" => array(
                    "chill" => $chill["id"]
                )
            ];
        }

        return new JsonResponse($collection);
    }
}
