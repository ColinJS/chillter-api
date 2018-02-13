<?php

namespace C\Controller\Event;

use C\Event;
use C\Provider\EventListenerProvider;
use C\Controller\AbstractController;
use C\Application;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @apiDefine EventElement Event element
 */
class ElementController extends AbstractController
{
    /**
     * @api {post} /chillers/{chillerId}/events/{eventId}/elements Add an element to the event
     * @apiGroup EventElement
     * @apiPermission authenticated (only event invited to)
     *
     * @apiExample Example request:
        {
            "element": "Lorem ipsum..."
        }
     *
     * @param $userId
     * @param $eventId
     * @param Application $app
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function addElements($userId, $eventId, Application $app)
    {
        $this->denyAccessUnlessInvitedToEvent($userId, $eventId);

        $data = $app['chillter.json.req'];

        if (!(is_array($data)
            && array_key_exists('element', $data))
        ) {
            return new Response(null, 400);
        }

        $result = $app['db']->insert('list', array(
            'eventid' => (int)$eventId,
            'created_by' => (int)$userId,
            'assigned_to' => (int)$userId,
            'content' => $data['element']
        ));

        if (0 === $result) {
            return new Response(null, 500);
        }

        $this->getEventDispatcher()->dispatch(
            EventListenerProvider::EVENT_LIST_ELEMENT_ACTION,
            new Event\EventListElement($eventId, $userId, Event\EventListElement::ELEMENT_CREATE)
        );

        return new Response(null, Response::HTTP_CREATED, [
            'X-Resource-Id' => $this->db->lastInsertId()
        ]);
    }

    /**
     * @api {get} /chillers/{chillerId}/events/{eventId}/elements Get event elements
     * @apiGroup EventElement
     * @apiPermission authenticated (only event invited to)
     *
     * @apiSuccessExample Example success response:
        [
            {
                "id": "62",
                "content": "Lorem ipsum...",
                "created_by": {
                    "id": "144",
                    "firstname": "Hugo"
                },
                "assigned_to": {
                    "id": null,
                    "firstname": null
                }
            },
            {
                "id": "63",
                "content": "Lorem ipsum...",
                "created_by": {
                    "id": "144",
                    "firstname": "Hugo"
                },
                "assigned_to": {
                    "id": "126",
                    "firstname": "Arnold"
                }
            }
        ]
     *
     * @param $userId
     * @param $eventId
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getElements($userId, $eventId)
    {
        $this->denyAccessUnlessInvitedToEvent($userId, $eventId);

        $sql = <<<SQL
            SELECT l.id, l.content,
                created_by.id as 'created_by_id', created_by.firstname as 'created_by_firstname', created_by_photo.url as 'created_by_logo',
                assigned_to.id as 'assigned_to_id', assigned_to.firstname as 'assigned_to_firstname', assigned_to_photo.url as 'assigned_to_logo'
            FROM list l
            LEFT JOIN chiller created_by ON l.created_by = created_by.id
            LEFT JOIN `chiller_photo` created_by_photo ON created_by.id = created_by_photo.userid AND created_by_photo.statut = 1
            LEFT JOIN chiller assigned_to ON l.assigned_to = assigned_to.id
            LEFT JOIN `chiller_photo` assigned_to_photo ON assigned_to.id = assigned_to_photo.userid AND assigned_to_photo.statut = 1
            WHERE l.eventid = ? ORDER BY l.content
SQL;

        $collection = [];
        $creator_logo = null;
        $assigned_logo = null;

        foreach ($this->db->fetchAll($sql, array($eventId)) ? : [] as $element) {

            //$creator_logo = $element['created_by_logo'] ? $request->getUriForPath($this->getUploadDirectory().$element['created_by_logo']) : null;
            //$assigned_logo = $element['assigned_to_logo'] ? $request->getUriForPath($this->getUploadDirectory().$element['assigned_to_logo']) : null;

            $collection[] = [
                'id' => $element['id'],
                'content' => $element['content'],
                'created_by' => [
                    'id' => $element['created_by_id'],
                    'firstname' => $element['created_by_firstname'],
                    'logo' => $element['created_by_logo'] ? "http://www.chillter.fr/api/images/".$element['created_by_logo'] : null,
                ],
                'assigned_to' => [
                    'id' => $element['assigned_to_id'],
                    'firstname' => $element['assigned_to_firstname'],
                    'logo' => $element['assigned_to_logo'] ? "http://www.chillter.fr/api/images/".$element['assigned_to_logo'] : null,
                ]
            ];
        }

        return new JsonResponse($collection);
    }

    /**
     * @api {put} /chillers/{chillerId}/events/{eventId}/elements/{elementId}/take Take an event element
     * @apiGroup EventElement
     * @apiPermission authenticated (only event invited to)
     *
     * @param $userId
     * @param $eventId
     * @param $elementId
     * @return \Symfony\Component\HttpFoundation\JsonResponse|Response
     */
    public function takeElement($userId, $eventId, $elementId)
    {
        $this->denyAccessUnlessInvitedToEvent($userId, $eventId);

        if (!$this->db->executeUpdate("UPDATE list SET assigned_to = ? WHERE id = ?", array($userId, $elementId))) {
            throw new NotFoundHttpException();
        }

        $this->getEventDispatcher()->dispatch(
            EventListenerProvider::EVENT_LIST_ELEMENT_ACTION,
            new Event\EventListElement($eventId, $userId, Event\EventListElement::ELEMENT_TAKEN)
        );

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @api {put} /chillers/{chillerId}/events/{eventId}/elements/{elementId}/leave Leave an event element
     * @apiGroup EventElement
     * @apiPermission authenticated (only event invited to)
     *
     * @param $userId
     * @param $eventId
     * @param $elementId
     * @return \Symfony\Component\HttpFoundation\JsonResponse|Response
     */
    public function leaveElement($userId, $eventId, $elementId)
    {
        $this->denyAccessUnlessInvitedToEvent($userId, $eventId);

        if (!$this->db->executeUpdate("UPDATE list SET assigned_to = NULL WHERE id = ?", array($elementId))) {
            throw new NotFoundHttpException();
        }

        $this->getEventDispatcher()->dispatch(
            EventListenerProvider::EVENT_LIST_ELEMENT_ACTION,
            new Event\EventListElement($eventId, $userId, Event\EventListElement::ELEMENT_LEAVE)
        );

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @api {delete} /chillers/{chillerId}/events/{eventId}/elements/{elementId} Delete an event element
     * @apiGroup EventElement
     * @apiPermission authenticated (only event invited to)
     *
     * @param $userId int
     * @param $eventId int
     * @param $elementId int
     * @return \Symfony\Component\HttpFoundation\JsonResponse|Response
     */
    public function deleteElement($userId, $eventId, $elementId)
    {
        $this->denyAccessUnlessInvitedToEvent($userId, $eventId);

        if (!$this->db->delete('list', array('id' => $elementId))) {
            throw new NotFoundHttpException();
        }

        $this->getEventDispatcher()->dispatch(
            EventListenerProvider::EVENT_LIST_ELEMENT_ACTION,
            new Event\EventListElement($eventId, $userId, Event\EventListElement::ELEMENT_REMOVE)
        );

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
