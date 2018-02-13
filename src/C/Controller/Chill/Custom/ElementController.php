<?php

namespace C\Controller\Chill\Custom;

use C\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @apiDefine ChillCustom Custom chill
 */
class ElementController extends AbstractController
{
    /**
     * @api {post} /chillers/{chillerId}/custom_chills/{customChillId}/elements Add an element
     * @apiGroup ChillCustom
     * @apiPermission authenticated (only own custom chill)
     *
     * @apiExample Example request:
        {
            "element": "Lorem ipsum..."
        }
     *
     * @param $userId
     * @param $customChillId
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function create($userId, $customChillId, Request $request)
    {
        $this->denyAccessUnlessGrantedToCustomChill($userId, $customChillId);

        $content = \GuzzleHttp\json_decode($request->getContent(), true);

        if (!array_key_exists('element', $content)) {
            throw new BadRequestHttpException();
        }

        $this->db->insert('chills_custom_element', [
            'chills_custom_id' => $customChillId,
            'name' => $content['element'],
        ]);

        return new JsonResponse(['id' => $this->db->lastInsertId()], Response::HTTP_CREATED);
    }

    /**
     * @api {put} /chillers/{chillerId}/custom_chills/{customChillId}/elements/{elementId} Update an element
     * @apiGroup ChillCustom
     * @apiPermission authenticated (only own custom chill)
     *
     * @apiExample Example request:
        {
            "element": "Lorem ipsum..."
        }
     *
     * @param $userId
     * @param $customChillId
     * @param $elementId
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function update($userId, $customChillId, $elementId, Request $request)
    {
        $this->denyAccessUnlessGrantedToCustomChillElement($userId, $customChillId, $elementId);

        $content = \GuzzleHttp\json_decode($request->getContent(), true);

        if (!array_key_exists('element', $content)) {
            throw new BadRequestHttpException();
        }

        $this->db->update('chills_custom_element', ['name' => $content['element']], ['id' => $elementId]);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @api {delete} /chillers/{chillerId}/custom_chills/{customChillId}/elements/{elementId} Delete an element
     * @apiGroup ChillCustom
     * @apiPermission authenticated (only own custom chill)
     *
     * @param $userId
     * @param $customChillId
     * @param $elementId
     * @return Response
     */
    public function delete($userId, $customChillId, $elementId)
    {
        $this->denyAccessUnlessGrantedToCustomChillElement($userId, $customChillId, $elementId);

        $this->db->delete('chills_custom_element', ['id' => $elementId]);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Checks permission to access to a custom chill element
     *
     * @param $userId
     * @param $customChillId
     * @param $elementId
     */
    protected function denyAccessUnlessGrantedToCustomChillElement($userId, $customChillId, $elementId)
    {
        $sql = <<<SQL
            SELECT `chiller_id`, e.`chills_custom_id`
            FROM `chills_custom_element` e
            LEFT JOIN `chills_custom` c ON e.`chills_custom_id` = c.`id`
            WHERE e.`id` = ?
SQL;

        list($chillerId, $chillsCustomId) = $this->db->fetchArray($sql, [$elementId]);

        if (false === $chillerId) {
            throw new NotFoundHttpException("Element (ID: $elementId) does not exist!");
        }

        if ($chillerId !== $userId) {
            throw new BadRequestHttpException("Element (ID: $elementId) does not belong to user (ID: $userId)!");
        }

        if ($customChillId !== $chillsCustomId) {
            throw new BadRequestHttpException("Element (ID: $elementId) does not belong to custom chill (ID: $customChillId)!");
        }
    }
}
