<?php

namespace C\Controller\Chiller;

use C\Application;
use C\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @apiDefine ChillerHome Chiller home
 */
class HomeController extends AbstractController
{
    /**
     * @api {get} /chillers/{chillerId}/home Get a chiller's home
     * @apiGroup ChillerHome
     * @apiPermission authenticated (only self data)
     * @apiDescription Collection is ordered ascending by <code>position</code> field.
     *
     * @apiSuccessExample Example success response:
        [
            {
                "id": 37,
                "position": 1,
                "name": "kebab",
                "logo": "http://chillter.fr/api/images/chills/kebab.svg",
                "type": "chill",
                "chill_id": "20"
            },
            {
                "id": 39,
                "position": 2,
                "name": "My custom chill",
                "logo": null,
                "type": "custom",
                "chill_id": "9"
            },
            {
                "id": 36,
                "position": 3,
                "name": "burger",
                "logo": "http://chillter.fr/api/images/chills/burger.svg",
                "type": "chill",
                "chill_id": "18"
            }
        ]
     *
     * @param $userId
     * @param Request $request
     * @param Application $app
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getHome($userId, Request $request, Application $app)
    {
        $translator = $app['translator.extension'];

        $query = <<<SQL
            SELECT
                h.`id`,
                h.`position`,
                COALESCE(`chills`.`name`, `chills_custom`.`name`) as 'name',
                COALESCE(`chills`.`name`, `chills_custom`.`logo`) as 'logo',
                `chills`.`logo` as 'chill_logo',
                IF(`chills`.`id` IS NOT NULL, 'chill', 'custom') as 'type',
                COALESCE(`chills`.`id`, `chills_custom`.`id`) as 'chill_id'
            FROM `chiller_home` h
            LEFT OUTER JOIN `chills` ON h.`chill_id` = `chills`.`id` AND h.`chill_id` IS NOT NULL
            LEFT OUTER JOIN `chills_custom` ON h.`custom_id` = `chills_custom`.`id` AND h.`custom_id` IS NOT NULL
            WHERE h.`chiller_id` = :chillerId
            ORDER BY h.`position` ASC
SQL;

        $collection = array_map(function ($element) use ($request, $app, $translator) {
            $element['id'] = (int)$element['id'];
            $element['position'] = (int)$element['position'];

            $element['uniqueName'] = $element['name'];
            $element['name'] = $translator->transChill($element['name']);

            if ($element['logo']) {
                if ('chill' === $element['type']) {
                    $element['logo'] = 'assets/images/chills/' . $element['chill_logo'] . '.svg';//$request->getUriForPath('images/chills/' . $element['uniqueName'] . '.svg');
                } elseif('custom' === $element['type']) {
                    $element['logo'] = $request->getUriForPath($app['upload.directory'] . $element['logo']);
                }
            }

            return $element;
        }, $this->db->fetchAll($query, ['chillerId' => $userId]));

        return new JsonResponse($collection);
    }

    /**
     * @api {post} /chillers/{chillerId}/home Update a chiller's home
     * @apiGroup ChillerHome
     * @apiPermission authenticated (only self data)
     *
     * @apiExample Example request (move):
        {
            "move": [
                {
                    "id": 28,
                    "pos": 4
                }
            ]
        }
     *
     * @apiExample Example request (delete):
        {
            "delete": [
                {
                    "id": 28
                }
            ]
        }
     *
     * @apiExample Example request (insert):
        {
            "insert": [
                {
                    "id": 28,
                    "type": "chill"
                },
                {
                    "id": 31,
                    "type": "custom"
                }
            ]
        }
     *
     * @param $userId
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function updateHome($userId, Request $request)
    {
        $payload = \GuzzleHttp\json_decode($request->getContent(), true);

        foreach ($payload as $operationName => $elements) {
            switch ($operationName) {
                case 'insert':
                    foreach ($elements as $element) {
                        switch ($element['type']) {
                            case 'chill':
                                $this->db->insert('chiller_home', [
                                    'chiller_id' => $userId,
                                    'chill_id' => $element['id'],
                                    'position' => 1 + $this->getLatestPosition($userId),
                                ]);
                            break;

                            case 'custom':
                                $this->db->insert('chiller_home', [
                                    'chiller_id' => $userId,
                                    'custom_id' => $element['id'],
                                    'position' => 1 + $this->getLatestPosition($userId)
                                ]);
                            break;

                            default:
                                throw new BadRequestHttpException('Invalid element type: \"' . $element['type'] . '"');
                        }
                    }
                break;

                case 'move':
                    foreach ($elements as $element) {
                        $id = (int)$element['id'];
                        $targetPosition = (int)$element['pos'];

                        $this->db->transactional(function () use ($userId, $id, $targetPosition) {
                            $position = $this->getPosition($id);
                            $latestPosition = $this->getLatestPosition($userId);

                            if ($targetPosition > $latestPosition) {
                                throw new BadRequestHttpException("Given position exceeds the elements count");
                            }

                            if ($targetPosition > $position) {
                                $this->decrementPositions($userId, $position + 1, $targetPosition);
                            } elseif ($targetPosition < $position) {
                                $this->incrementPositions($userId, $targetPosition, $position - 1);
                            } else {
                                throw new BadRequestHttpException("Given position equals the current position");
                            }

                            $this->db->update('chiller_home', ['position' => $targetPosition], ['id' => $id]);
                        });
                    }
                break;

                case 'delete':
                    foreach ($elements as $element) {
                        $id = (int)$element['id'];

                        $this->db->transactional(function () use ($userId, $id) {
                            $position = $this->getPosition($id);
                            $latestPosition = $this->getLatestPosition($userId);

                            if ($position < $latestPosition) {
                                $this->decrementPositions($userId, $position + 1, $latestPosition);
                            }

                            $this->db->delete('chiller_home', ['id' => $id]);
                        });
                    }
                break;

                default:
                    throw new BadRequestHttpException("Invalid operation type: \"$operationName\"");

            }
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param $userId
     * @param $greaterThanOrEqual
     * @param $lessThanOrEqual
     */
    protected function incrementPositions($userId, $greaterThanOrEqual, $lessThanOrEqual)
    {
        echo "incrementing from $greaterThanOrEqual to $lessThanOrEqual";
        $query = <<<SQL
            UPDATE `chiller_home`
            SET `position` = `position` + 1
            WHERE `chiller_id` = ? AND `position` >= ? AND `position` <= ?
SQL;

        $this->db->executeUpdate($query, [$userId, $greaterThanOrEqual, $lessThanOrEqual]);
    }

    /**
     * @param $userId
     * @param $greaterThanOrEqual
     * @param $lessThanOrEqual
     */
    protected function decrementPositions($userId, $greaterThanOrEqual, $lessThanOrEqual)
    {
        echo "decrementing from $greaterThanOrEqual to $lessThanOrEqual";

        $query = <<<SQL
            UPDATE `chiller_home`
            SET `position` = `position` - 1
            WHERE `chiller_id` = ? AND `position` >= ? AND `position` <= ?
SQL;

        $this->db->executeUpdate($query, [$userId, $greaterThanOrEqual, $lessThanOrEqual]);
    }

    /**
     * Get home element position by ID
     *
     * @param $id
     * @return int
     */
    protected function getPosition($id)
    {
        $position = $this->db->fetchColumn('SELECT `position` from `chiller_home` where `id` = ?', [$id]);

        if (false === $position) {
            throw new NotFoundHttpException("Element (ID: $id) does not exist.");
        }

        return (int)$position;
    }

    /**
     * Get the latest home element position for the user
     *
     * @param $userId
     * @return int
     */
    protected function getLatestPosition($userId)
    {
        return (int)$this->db->fetchColumn('SELECT MAX(`position`) FROM `chiller_home` WHERE `chiller_id` = ?', [
            $userId
        ]);
    }
}
