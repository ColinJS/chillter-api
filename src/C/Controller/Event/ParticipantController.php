<?php

namespace C\Controller\Event;

use C\Controller\AbstractController;
use C\Event;
use C\Provider\EventListenerProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @apiDefine EventParticipant Event participant
 */
class ParticipantController extends AbstractController
{
    /**
     * @api {post} /chillers/{chillerId}/events/{eventId}/participants/{participantId} Add an event participant
     * @apiPermission authenticated (all event participants)
     * @apiGroup EventParticipant
     *
     * @param $userId
     * @param $eventId
     * @param $participantId
     * @return Response
     */
    public function addGuest($userId, $eventId, $participantId)
    {
        $this->denyAccessUnlessInvitedToEvent($userId, $eventId);

        $this->db->insert('event_participant', [
            'chillerid' => $participantId,
            'eventid' => $eventId,
            'statut' => 3
        ]);

        $this->getEventDispatcher()->dispatch(
            EventListenerProvider::EVENT_PARTICIPATION_CREATED,
            new Event\EventParticipantCreated($eventId, $participantId, $userId)
        );

        return new Response('', Response::HTTP_CREATED, [
            'X-Resource-ID' => $this->db->lastInsertId()
        ]);
    }

    /**
     * @api {delete} /chillers/{chillerId}/events/{eventId}/participants/{participantId} Remove an event participant
     * @apiPermission authenticated (only event creator)
     * @apiGroup EventParticipant
     *
     * @param $userId
     * @param $eventId
     * @param $participantId
     * @return Response
     */
    public function deleteGuest($userId, $eventId, $participantId)
    {
        $this->denyAccessUnlessIsEventCreator($userId, $eventId);

        if (!$this->db->executeUpdate("DELETE FROM `event_participant` WHERE `eventid` = ? AND `chillerid` = ?", [
            $eventId, $participantId
        ])) {
            throw new NotFoundHttpException("User (ID: $participantId) does not participate the event (ID: $eventId).");
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @api {put} /chillers/{chillerId}/events/{eventId}/participate Update event participation
     * @apiGroup EventParticipant
     *
     * @apiExample Example request:
        {
            "status": 2
        }
     *
     * @param $userId
     * @param $eventId
     * @param Request $request
     * @return Response
     */
    public function updateParticipation($userId, $eventId, Request $request)
    {
        $data = \GuzzleHttp\json_decode($request->getContent(), true) ? : [];
        $status = (int)$data["status"];

        if (!($data && array_key_exists('status', $data) && $status >= 0 && $status <= 2)) {
            throw new BadRequestHttpException();
        }

        $this->db->executeUpdate("UPDATE event_participant SET statut = ? WHERE chillerid = ? AND eventid = ?", [
            (int)$data["status"], $userId, $eventId
        ]);

        $this->getEventDispatcher()->dispatch(
            EventListenerProvider::EVENT_PARTICIPATION_UPDATED,
            new Event\EventParticipantUpdated($eventId, $userId, (int)$data["status"])
        );

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
