<?php

namespace C\Controller\Chill\Custom;

use C\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @apiDefine ChillCustom Custom chill
 */
class CarController extends AbstractController
{
    /**
     * @api {post} /chillers/{chillerId}/custom_chills/{customChillId}/cars Add a car
     * @apiGroup ChillCustom
     * @apiPermission authenticated (only own custom chill)
     *
     * @apiExample Example request:
        {
            "seats": 5
        }
     *
     * @param $userId
     * @param $customChillId
     * @param Request $request
     * @return Response
     */
    public function create($userId, $customChillId, Request $request)
    {
        $this->denyAccessUnlessGrantedToCustomChill($userId, $customChillId);

        if  ($this->db->fetchColumn("SELECT `car_seats` FROM `chills_custom` WHERE `id` = ?", [$customChillId])) {
            throw new BadRequestHttpException("Car for custom chill (ID: $customChillId) already exists.");
        }

        $this->db->update('chills_custom', ['car_seats' => $this->getSeats($request)], ['id' => $customChillId]);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @api {put} /chillers/{chillerId}/custom_chills/{customChillId}/cars Update a car
     * @apiGroup ChillCustom
     * @apiPermission authenticated (only own custom chill)
     *
     * @apiExample Example request:
        {
            "seats": 5
        }
     *
     * @param $userId
     * @param $customChillId
     * @param Request $request
     * @return Response
     */
    public function update($userId, $customChillId, Request $request)
    {
        $this->denyAccessUnlessGrantedToCustomChill($userId, $customChillId);
        $this->throwNotFoundExceptionUnlessCarExists($customChillId);

        $this->db->update('chills_custom', ['car_seats' => $this->getSeats($request)], ['id' => $customChillId]);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @api {delete} /chillers/{chillerId}/custom_chills/{customChillId}/cars Delete a car
     * @apiGroup ChillCustom
     * @apiPermission authenticated (only own custom chill)
     *
     * @param $userId
     * @param $customChillId
     * @return Response
     */
    public function delete($userId, $customChillId)
    {
        $this->denyAccessUnlessGrantedToCustomChill($userId, $customChillId);
        $this->throwNotFoundExceptionUnlessCarExists($customChillId);

        $this->db->update('chills_custom', ['car_seats' => null], ['id' => $customChillId]);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Get cat seats from request
     *
     * @param Request $request
     * @return int
     */
    protected function getSeats(Request $request)
    {
        $payload = \GuzzleHttp\json_decode($request->getContent(), true);

        if (!array_key_exists('seats', $payload)
            || (array_key_exists('seats', $payload) && $payload['seats'] < 1)
        ) {
            throw new BadRequestHttpException();
        }

        return (int)$payload['seats'];
    }

    /**
     * Throw exception unless car does not exist in the custom chill
     *
     * @param $customChillId
     */
    protected function throwNotFoundExceptionUnlessCarExists($customChillId)
    {
        if (null === $this->db->fetchColumn("SELECT `car_seats` FROM `chills_custom` WHERE `id` = ?", [$customChillId])) {
            throw new NotFoundHttpException("Car for custom chill (ID: $customChillId) does not exist.");
        }
    }
}
