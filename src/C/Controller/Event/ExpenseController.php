<?php

namespace C\Controller\Event;

use C\Controller\AbstractController;
use C\Application;
use C\Event\EventSpendingCreated;
use C\Event\EventSpendingRemoved;
use C\Provider\EventListenerProvider;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @apiDefine EventExpenses Event expense
 */
class ExpenseController extends AbstractController
{
    /**
     * @api {post} /chillers/{chillerId}/events/{eventId}/expenses Add expenses to the event
     * @apiGroup EventExpenses
     * @apiPermission authenticated (only event invited to)
     *
     * @apiExample Example request:
        {
            "expenses": [
                {
                    "element": "Vodka",
                    "price": 30.50,
                    "inheriters": [144, 126]
                },
                {
                    "element": "Whiskey",
                    "price": 45.25,
                    "inheriters": []
                }
            ]
        }
     *
     * @param $userId
     * @param $eventId
     * @param Application $app
     * @return Response
     */
    public function addExpenses($userId, $eventId, Application $app)
    {
        $this->denyAccessUnlessInvitedToEvent($userId, $eventId);
        $data = $app['chillter.json.req'];

        if (!array_key_exists('expenses', $data)) {
            throw new BadRequestHttpException();
        }

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

        $this->getEventDispatcher()->dispatch(
            EventListenerProvider::EVENT_SPENDING_CREATED,
            new EventSpendingCreated($eventId, $userId)
        );

        return new Response('', Response::HTTP_CREATED);
    }

    /**
     * @api {PUT} /chillers/{chillerId}/events/{eventId}/expenses/{expenseId} Update an event expense
     * @apiGroup EventExpenses
     * @apiPermission authenticated (only event invited to)
     *
     * @apiExample Example request:
        {
            "element": "Vodka",
            "price": 30.50
        }
     *
     * @param $userId
     * @param $eventId
     * @param $expenseId
     * @param Application $app
     * @return Response
     */
    public function updateExpense($userId, $eventId, $expenseId, Application $app)
    {
        $this->denyAccessUnlessInvitedToEvent($userId, $eventId);
        $data = $app['chillter.json.req'];

        if (!(array_key_exists('element', $data)
            && array_key_exists('price', $data))
        ) {
            throw new BadRequestHttpException();
        }

        $this->db->executeUpdate("UPDATE `expense` SET element = ?, price = ? WHERE `id` = ?", [
            $data['element'],
            $data['price'],
            (int) $expenseId
        ]);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @api {DELETE} /chillers/{chillerId}/events/{eventId}/expenses/{expenseId} Delete an event expense
     * @apiGroup EventExpenses
     * @apiPermission authenticated (only event invited to)
     *
     * @param $userId
     * @param $eventId
     * @param $expenseId
     * @return Response
     */
    public function deleteExpense($userId, $eventId, $expenseId)
    {
        $this->denyAccessUnlessInvitedToEvent($userId, $eventId);

        $this->db->executeUpdate("DELETE FROM `expense_inheritor` WHERE `expenseid` = ?", [ (int)$expenseId ]);

        if (!$this->db->executeUpdate("DELETE FROM `expense` WHERE `id` = ?", [ (int)$expenseId ])) {
            throw new NotFoundHttpException();
        }

        $this->getEventDispatcher()->dispatch(
            EventListenerProvider::EVENT_SPENDING_REMOVED,
            new EventSpendingRemoved($eventId, $userId)
        );

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @api {get} /chillers/{chillerId}/events/{eventId}/expenses Get an event expenses
     * @apiGroup EventExpenses
     * @apiPermission authenticated (only event invited to)
     *
     * @apiSuccessExample Example success response:
        [
            {
                "id": "12",
                "price": "30.50",
                "element": "Vodka",
                "payer": {
                    "id": "144",
                    "firstname": "Michal"
                },
                "inheriters": [
                    {
                        "id": "126",
                        "firstname": "Coralie"
                    },
                    {
                        "id": "144",
                        "firstname": "Michal"
                    }
                ]
            }
        ]
     *
     * @param $userId
     * @param $eventId
     * @param Application $app
     * @return JsonResponse
     */
    public function getExpenses($userId, $eventId, Application $app)
    {
        $this->denyAccessUnlessInvitedToEvent($userId, $eventId);

        $sql = <<<SQL
            SELECT e.id, e.price, e.element, e.chillerid as payerid, c.id as chillerid, c.firstname, c_photo.url
            FROM expense e 
            INNER JOIN expense_inheritor i ON e.id = i.expenseid 
            INNER JOIN chiller c ON c.id = i.chillerid OR c.id = e.chillerid
            LEFT JOIN `chiller_photo` c_photo ON c.`id` = c_photo.`userid` AND c_photo.statut = 1
            WHERE e.eventid = ?
SQL;

        $dbrps = $app['db']->fetchAll($sql, [ $eventId ]) ? : [];

        $collection = [];
        $logo = null;

        foreach($dbrps as $d) {

            $usedId = false;

            for ($i = 0; $i <= count($collection) - 1; $i++) {
                if ($d["id"] == $collection[$i]["id"]) {
                    $usedId = $i;
                }
            }

            if ($usedId === false) {
                if ($d["payerid"] == $d["chillerid"]) {
                    $collection[] = [
                        "id" => $d["id"],
                        "price" => $d["price"],
                        "element" => html_entity_decode($d["element"]),
                        "payer" => [
                            "id" => $d["chillerid"],
                            "firstname" => html_entity_decode($d["firstname"]),
                            "logo" => $d["url"] ? "http://www.chillter.fr/api/images/".$d["url"] : null,
                        ],
                        "inheriters" => false
                    ];
                } else {
                    $collection[] = [
                        "id" => $d["id"],
                        "price" => $d["price"],
                        "element" => html_entity_decode($d["element"]),
                        "payer" => false,
                        "inheriters" => [[
                            "id" => $d["chillerid"],
                            "firstname" => html_entity_decode($d["firstname"]),
                            "logo" => $d["url"] ? "http://www.chillter.fr/api/images/".$d["url"] : null,
                        ]]
                    ];
                }
            } else {
                if ($d["payerid"] == $d["chillerid"] && !$collection[$usedId]["payer"]) {
                    $collection[$usedId]["payer"] = [
                        "id" => $d["chillerid"],
                        "firstname" => html_entity_decode($d["firstname"]),
                        "logo" => $d["url"] ? "http://www.chillter.fr/api/images/".$d["url"] : null,
                    ];
                } else {
                    $collection[$usedId]["inheriters"][] = [
                        "id" => $d["chillerid"],
                        "firstname" => html_entity_decode($d["firstname"]),
                        "logo" => $d["url"] ? "http://www.chillter.fr/api/images/".$d["url"] : null,
                    ];
                }
            }
        }

        return $app->json($collection);
    }

    /**
     * @api {POST} /chillers/{chillerId}/events/{eventId}/expenses/{expenseId}/inheritors Add inheritors to the expense
     * @apiGroup EventExpenses
     * @apiPermission authenticated (only event invited to)
     *
     * @apiExample Example request:
        [
            32,
            126
        ]
     *
     * @param $userId
     * @param $eventId
     * @param $expenseId
     * @param Application $app
     * @return Response
     */
    public function addInheritors($userId, $eventId, $expenseId, Application $app)
    {
        $this->denyAccessUnlessInvitedToEvent($userId, $eventId);
        $chillerIds = $app['chillter.json.req'];

        if (!is_array($chillerIds)) {
            throw new BadRequestHttpException();
        }

        foreach ($chillerIds as $chillerId) {
            try {
                $this->db->insert('expense_inheritor', [
                    'expenseid' => (int)$expenseId,
                    'chillerid' => (int)$chillerId
                ]);
            } catch (UniqueConstraintViolationException $e) {
                throw new BadRequestHttpException("User (ID: $chillerId) is already an inheritor of the expense (ID: $expenseId).");
            }
        }

        return new Response('', Response::HTTP_CREATED);
    }

    /**
     * @api {DELETE} /chillers/{chillerId}/events/{eventId}/expenses/{expenseId}/inheritors/{inheritor} Delete an expense inheritor
     * @apiGroup EventExpenses
     * @apiPermission authenticated (only event invited to)
     *
     * @param $userId
     * @param $eventId
     * @param $expenseId
     * @param $inheritorId
     * @return Response
     */
    public function deleteInheritor($userId, $eventId, $expenseId, $inheritorId)
    {
        $this->denyAccessUnlessInvitedToEvent($userId, $eventId);

        if (!$this->db->delete('expense_inheritor', [
                'expenseid' => (int)$expenseId,
                'chillerid' => (int)$inheritorId
            ])
        ) {
            throw new NotFoundHttpException();
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
