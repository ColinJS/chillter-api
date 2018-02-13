<?php

namespace Backend\Controller;

use Symfony\Component\HttpFoundation\Request;

class BackendController extends AbstractController
{
    public function defaultAction()
    {
        $usersTotalCount = $this->db->fetchColumn("SELECT COUNT(`id`) FROM `chiller`");
        $eventsTotalCount = $this->db->fetchColumn("SELECT COUNT(`id`) FROM `event`");
        $messagesTotalCount = $this->db->fetchColumn("SELECT COUNT(`id`) FROM `event_message`");

        return $this->twig->render('dashboard.html.twig', [
            'users_total_count' => $usersTotalCount,
            'events_total_count' => $eventsTotalCount,
            'messages_total_count' => $messagesTotalCount,
        ]);
    }

    public function userListAction(Request $request)
    {
        $limit = $request->query->has('limit') ? (int)$request->query->get('limit') : 25;

        $query = <<<SQL
            SELECT c.`id`, c.`firstname`, c.`lastname`, c.`email`, c.`roles`, p.`url` as 'picture'
            FROM chiller c
            LEFT JOIN chiller_photo p ON p.userid = c.id AND p.statut = 1
            LIMIT ?
SQL;

        $users = $this->db->fetchAll($query, [$limit], [\PDO::PARAM_INT]);

        foreach ($users as &$user) {
            $user['picture'] = $this->getUriForPicture($user['picture']);
            $user['roles'] = unserialize($user['roles']);
        }

        $totalCount = $this->db->fetchColumn("SELECT COUNT(`id`) FROM `chiller`");

        return $this->twig->render('user_list.html.twig', [
            'users' => $users,
            'totalCount' => $totalCount
        ]);
    }

    public function userDetailsAction($userId)
    {
        $query = <<<SQL
            SELECT c.`firstname`, c.`lastname`
            FROM chiller c
            WHERE c.id = :chillerId
SQL;
        $userDetails = $this->db->fetchAll($query, [
            'chillerId' => $userId
        ]);

        $query2 = <<<SQL
            SELECT c.`id`, c.`place`, c.`place`, c.`address`, c.`date`, p.`name` as categoryName, q.`name` as chillName
            FROM event c
            LEFT JOIN category p ON p.id = c.category
            LEFT JOIN chills q ON q.id = c.chill
            WHERE c.chillerid = :chillerId
SQL;
        $eventsDetails = $this->db->fetchAll($query2, [
            'chillerId' => $userId
        ]);

        $query3 = <<<SQL
            SELECT c.`eventid`, p.`place` as eventPlace, p.`address` as eventAddress, p.`date` as eventDate, q.`name` as categoryName, r.`name` as chillName
            FROM event_participant c
            LEFT JOIN event p ON p.id = c.eventid
            LEFT JOIN category q ON q.id = p.category
            LEFT JOIN chills r ON r.id = p.chill
            WHERE c.chillerid = :chillerId AND c.statut = 1
SQL;
        $eventsParticipesDetails = $this->db->fetchAll($query3, [
            'chillerId' => $userId
        ]);

        $query4 = <<<SQL
            SELECT c.`id` as 'friendId', c.`firstname`, c.`lastname`, c.`email`,  c.`roles`, p.`url` AS picture, f.*
            FROM `chiller` c
            LEFT JOIN `chiller_photo` p ON p.userid = c.id AND p.statut = 1
            INNER JOIN `chiller_friends` f ON (
                (f.`first_id` = :chillerId AND f.`second_id` = c.`id`) OR (f.`second_id` = :chillerId AND f.`first_id` = c.`id`)
            )
            WHERE c.`id` != :chillerId
SQL;
        $userFriends = $this->db->fetchAll($query4, [
            'chillerId' => $userId
        ]);

        foreach ($userFriends as &$user) {
            $user['picture'] = $this->getUriForPicture($user['picture']);
            $user['roles'] = unserialize($user['roles']);
        }

        return $this->twig->render('user_details.html.twig', [
            'userDetails' => $userDetails[0],
            'eventsDetails' => $eventsDetails,
            'eventsParticipesDetails' => $eventsParticipesDetails,
            'userFriends' => $userFriends
        ]);
    }

    public function chillListAction(Request $request)
    {
        $page = $request->query->has('page') ? (int)$request->query->get('page') : 1;
        $limit = $request->query->has('limit') ? (int)$request->query->get('limit') : 15;

        $query = <<<SQL
            SELECT c.`id`, c.`name`, cat.`name` as 'category_name', COUNT(e.`id`) as 'events_count'
            FROM chills c
            LEFT JOIN `category` cat ON cat.`id` = c.`category`
            LEFT JOIN `event` e ON e.`chill` = c.`id`
            GROUP BY c.`id`
            LIMIT ?,?
SQL;

        return $this->twig->render('chill_list.html.twig', [
            'chills' => $this->db->fetchAll($query, [($page - 1) * $limit, $limit], [\PDO::PARAM_INT, \PDO::PARAM_INT]),
            'page' => $page,
            'limit' => $limit,
            'totalCount' => $this->db->fetchColumn("SELECT COUNT(`id`) FROM `chills`")
        ]);
    }
}
