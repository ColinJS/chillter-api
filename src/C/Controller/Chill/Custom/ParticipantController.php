<?php

namespace C\Controller\Chill\Custom;

use C\Controller\AbstractController;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @apiDefine ChillCustom Custom chill
 */
class ParticipantController extends AbstractController
{
    /**
     * @api {post} /chillers/{chillerId}/custom_chills/{customChillId}/participants/{participantId} Add a participant
     * @apiGroup ChillCustom
     * @apiPermission authenticated (only own custom chill)
     *
     * @param $userId
     * @param $customChillId
     * @param $participantId
     * @return Response
     */
    public function create($userId, $customChillId, $participantId)
    {
        $this->denyAccessUnlessGrantedToCustomChill($userId, $customChillId);

        try {
            $this->db->insert('chills_custom_participant', [
                'chills_custom_id' => $customChillId,
                'chiller_id' => $participantId,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            throw new BadRequestHttpException("User (ID: $participantId) already participates the custom chill (ID: $customChillId).");
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @api {delete} /chillers/{chillerId}/custom_chills/{customChillId}/participants/{participantId} Delete a participant
     * @apiGroup ChillCustom
     * @apiPermission authenticated (only own custom chill)
     *
     * @param $userId
     * @param $customChillId
     * @param $participantId
     * @return Response
     */
    public function delete($userId, $customChillId, $participantId)
    {
        $this->denyAccessUnlessGrantedToCustomChill($userId, $customChillId);

        $affected = $this->db->delete('chills_custom_participant', [
            'chills_custom_id' => $customChillId,
            'chiller_id' => $userId,
        ]);

        if (0 === $affected) {
            throw new BadRequestHttpException("User (ID: $participantId) does not participate the custom chill (ID: $customChillId).");
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}