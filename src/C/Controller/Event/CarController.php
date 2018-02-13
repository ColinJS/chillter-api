<?php

namespace C\Controller\Event;

use C\Controller\AbstractController;
use C\Provider\EventListenerProvider;
use C\Event;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @apiDefine EventCar Event cars
 */
class CarController extends AbstractController
{
    /**
     * @api {get} /chillers/{chillerId}/events/{eventId}/cars Get event cars
     * @apiGroup EventCar
     * @apiPermission authenticated (only event invited to)
     * @apiDescription The first car passenger is a driver.
     * @apiSuccessExample Example success response:
        [
            {
                "id": 124,
                "seats": 5,
                "driver_id": 215,
                "passengers": [
                    {
                        "id": 215,
                        "firstname": "Anne",
                        "picture": "http://chillter.fr/api/images/avatars/590b057244bc1.jpeg"
                    },
                    {
                        "id": 217,
                        "firstname": "Francois",
                        "picture": null
                    }
                ]
            }
        ]
     *
     * @param $userId
     * @param $eventId
     * @return JsonResponse
     */
    public function get($userId, $eventId)
    {
        $this->denyAccessUnlessInvitedToEvent($userId, $eventId);

        $cars = [];

        $query = <<<SQL
            SELECT c.`id`, c.`seats`, c.`chillerid` as 'driver_id'
            FROM car c
            WHERE c.`eventid` = ?
SQL;

        foreach ($this->db->fetchAll($query, [ (int)$eventId ]) as $car) {
            $car = [
                'id' => (int)$car['id'],
                'seats' => (int)$car['seats'],
                'driver_id' => (int)$car['driver_id'],
                'passengers' => [],
            ];

            $query = <<<SQL
                SELECT c.`id`, c.`firstname`, `chiller_photo`.`url` as 'picture'
                FROM `car_passenger` p
                LEFT JOIN `chiller` c ON p.`chillerid` = c.`id`
                LEFT JOIN `chiller_photo` ON c.`id` = `chiller_photo`.`userid` AND `chiller_photo`.`statut` = 1
                WHERE p.`carid` = ?
                ORDER BY p.`driver` DESC
SQL;

            $car['passengers'] = $this->normalizeEntities('chiller', $this->db->fetchAll($query, [$car['id']] ? : []));

            $cars[] = $car;
        }

        return new JsonResponse($cars);
    }


    /**
     * @api {post} /chillers/{chillerId}/events/{eventId}/cars Add a car to the event
     * @apiGroup EventCar
     * @apiPermission authenticated (only event invited to)
     *
     * @apiExample Example request:
        {
            "seats": 5
        }
     *
     * @param $userId
     * @param $eventId
     * @param Request $request
     * @throws \Exception
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function addCar($userId, $eventId, Request $request)
    {
        $this->denyAccessUnlessInvitedToEvent($userId, $eventId);

        $data = \GuzzleHttp\json_decode($request->getContent(), true);

        if (!(
            array_key_exists('seats', $data)
            && is_numeric($data['seats'])
            && (int)$data['seats'] >= 1
        )) {
            throw new BadRequestHttpException();
        }

        $this->db->beginTransaction();

        try {
            $this->db->insert('car', [
                'chillerid' => (int)$userId,
                'eventid' => (int)$eventId,
                'seats' => (int)$data['seats'],
            ]);

            $this->db->insert('car_passenger', [
                'chillerid' => (int)$userId,
                'carid' => $this->db->lastInsertId(),
                'driver' => 1
            ]);

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->getEventDispatcher()->dispatch(
            EventListenerProvider::EVENT_CAR_CREATED,
            new Event\EventCarCreated((int)$eventId, (int)$userId)
        );

        return new Response(null, Response::HTTP_CREATED);
    }

    /**
     * @api {put} /chillers/{chillerId}/events/{eventId}/cars/{carId} Update a car
     * @apiGroup EventCar
     * @apiPermission authenticated (only event invited to)
     *
     * @apiExample Example request:
        {
            "seats": 3
        }
     *
     * @param $userId
     * @param $eventId
     * @param $carId
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function updateCar($userId, $eventId, $carId, Request $request)
    {
        $this->denyAccessUnlessInvitedToEvent($userId, $eventId);

        $data = \GuzzleHttp\json_decode($request->getContent(), true);

        if (!(
            array_key_exists('seats', $data)
            && is_numeric($data['seats'])
            && (int)$data['seats'] >= 1
        )) {
            throw new BadRequestHttpException();
        }

        $this->db->update('car', [ 'seats' => (int)$data['seats' ]], [ 'id' => (int) $carId]);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @api {delete} /chillers/{chillerId}/events/{eventId}/cars/{carId} Delete a car
     * @apiGroup EventCar
     * @apiPermission authenticated (only event invited to)
     *
     * @param $userId
     * @param $eventId
     * @param $carId
     * @return Response
     */
    public function deleteCar($userId, $eventId, $carId)
    {
        $this->denyAccessUnlessInvitedToEvent($userId, $eventId);

        $this->db->transactional(function () use ($carId) {
            $this->db->delete('car_passenger', [
                'carid' => (int)$carId
            ]);

            $this->db->delete('car', [
                'id' => (int)$carId
            ]);
        });

        $this->getEventDispatcher()->dispatch(
            EventListenerProvider::EVENT_CAR_REMOVED,
            new Event\EventCarRemoved($eventId, $userId)
        );

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @api {put} /chillers/{chillerId}/events/{eventId}/cars/{carId}/get_in Get in to the car
     * @apiGroup EventCar
     * @apiPermission authenticated (only event invited to)
     *
     * @param $userId
     * @param $eventId
     * @param $carId
     * @return Response
     */
    public function getIn($userId, $eventId, $carId)
    {
        $this->denyAccessUnlessInvitedToEvent($userId, $eventId);

        try {
            $this->db->insert('car_passenger', [
                'chillerid' => (int)$userId,
                'carid' => $carId,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            throw new BadRequestHttpException("User (ID: $userId) is already in the car (ID: $carId).");
        }

        $this->getEventDispatcher()->dispatch(
            EventListenerProvider::EVENT_CAR_GET_IN,
            new Event\EventCarGetIn((int)$eventId, (int)$carId, (int)$userId)
        );

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @api {put} /chillers/{chillerId}/events/{eventId}/cars/{carId}/get_out Get out of the car
     * @apiGroup EventCar
     * @apiPermission authenticated (only event invited to)
     *
     * @param $userId
     * @param $eventId
     * @param $carId
     * @return Response
     */
    public function getOut($userId, $eventId, $carId)
    {
        $this->denyAccessUnlessInvitedToEvent($userId, $eventId);

        $isDriver = '1' === $this->db->fetchColumn('SELECT 1 FROM `car` WHERE `id` = ? AND `chillerId` = ?', [
            (int)$carId,
            (int)$userId,
        ]);

        if ($isDriver) {
            throw new BadRequestHttpException("User (ID: $userId) cannot get out the car (ID: $carId) because he is a driver.");
        }

        $this->db->delete('car_passenger', [
            'chillerid' => (int)$userId,
            'carid' => $carId,
        ]);

        $this->getEventDispatcher()->dispatch(
            EventListenerProvider::EVENT_CAR_GET_OUT,
            new Event\EventCarGetOut((int)$eventId, (int)$carId, (int)$userId)
        );

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
