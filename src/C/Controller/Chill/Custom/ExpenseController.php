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
class ExpenseController extends AbstractController
{
    /**
     * @api {post} /chillers/{chillerId}/custom_chills/{customChillId}/expenses Add an expense
     * @apiGroup ChillCustom
     * @apiPermission authenticated (only own custom chill)
     *
     * @apiExample Example request:
        {
            "name": "Whisky",
            "price": 20.75,
            "inheritors": [217, 222]
        }
     *
     * @param $userId
     * @param $customChillId
     * @param Request $request
     * @throws \Exception
     * @return Response
     */
    public function create($userId, $customChillId, Request $request)
    {
        $this->denyAccessUnlessGrantedToCustomChill($userId, $customChillId);
        $content = \GuzzleHttp\json_decode($request->getContent(), true);

        if (!array_key_exists('name', $content)
            || !array_key_exists('price', $content)
            || !array_key_exists('inheritors', $content)
        ) {
            throw new BadRequestHttpException();
        }

        $this->db->beginTransaction();
        try {
            $this->db->insert('chills_custom_expense', [
                'chills_custom_id' => $customChillId,
                'name' => $content['name'],
                'price' => $content['price'],
            ]);

            $expenseId = $this->db->lastInsertId();

            foreach ($content['inheritors'] as $inheritorId) {
                $this->db->insert('chills_custom_expense_inheritor', [
                    'expense_id' => $expenseId,
                    'chiller_id' => $inheritorId,
                ]);
            }

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();

            throw $e;
        }

        return new JsonResponse(['id' => $expenseId], Response::HTTP_CREATED);
    }

    /**
     * @api {delete} /chillers/{chillerId}/custom_chills/{customChillId}/expenses/{elementId} Delete an expense
     * @apiGroup ChillCustom
     * @apiPermission authenticated (only own custom chill)
     *
     * @param $userId
     * @param $customChillId
     * @param $expenseId
     * @return Response
     */
    public function delete($userId, $customChillId, $expenseId)
    {
        $this->denyAccessUnlessGrantedToCustomChillExpense($userId, $customChillId, $expenseId);

        $this->db->transactional(function() use ($expenseId) {
            $this->db->delete('chills_custom_expense_inheritor', ['expense_id' => $expenseId]);
            $this->db->delete('chills_custom_expense', ['id' => $expenseId]);
        });

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Checks permission to access to a custom chill element
     *
     * @param $userId
     * @param $customChillId
     * @param $expenseId
     */
    protected function denyAccessUnlessGrantedToCustomChillExpense($userId, $customChillId, $expenseId)
    {
        $sql = <<<SQL
            SELECT `chiller_id`, e.`chills_custom_id`
            FROM `chills_custom_expense` e
            LEFT JOIN `chills_custom` c ON e.`chills_custom_id` = c.`id`
            WHERE e.`id` = ?
SQL;

        list($chillerId, $chillsCustomId) = $this->db->fetchArray($sql, [$expenseId]);

        if (false === $chillerId) {
            throw new NotFoundHttpException("Expense (ID: $expenseId) does not exist!");
        }

        if ($chillerId !== $userId) {
            throw new BadRequestHttpException("Expense (ID: $expenseId) does not belong to user (ID: $userId)!");
        }

        if ($customChillId !== $chillsCustomId) {
            throw new BadRequestHttpException("Expense (ID: $expenseId) does not belong to custom chill (ID: $customChillId)!");
        }
    }
}