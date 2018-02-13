<?php

namespace C\Controller;

use RandomLib;
use Symfony\Component\HttpFoundation;
use Symfony\Component\HttpKernel\Exception;

/**
 * Don't look at those strange (not restful) responses in this class... This is Publish-IT request :-)
 *
 * @apiDefine ResetPassword Reset password
 */
class ResetPasswordController extends AbstractController
{
    protected $tokenChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    const ERROR_EMAIL_DOES_NOT_EXIST = 1;
    const ERROR_TOKEN_EXPIRED = 2;
    const ERROR_TOKEN_INVALID = 3;

    /**
     * @api {post} /reset_password/obtain_token Obtain a token
     * @apiGroup ResetPassword
     * @apiPermission anonymous
     * @apiDescription Token will be valid for the next <strong>24 hours</strong>.
     *
     * @apiExample Example request:
        {
            "email": "example@example.com"
        }
     *
     * @param HttpFoundation\Request $request
     * @return HttpFoundation\Response
     */
    public function obtainToken(HttpFoundation\Request $request)
    {
        $data = \GuzzleHttp\json_decode($request->getContent(), true);

        if (!array_key_exists('email', $data)) {
            throw new Exception\BadRequestHttpException();
        }

        $id = (int)$this->db->fetchColumn("SELECT `id` FROM `chiller` WHERE `email` = ?", [$data['email']]);

        if (!$id) {
            return new HttpFoundation\JsonResponse([
                "success" => false,
                "code" => self::ERROR_EMAIL_DOES_NOT_EXIST,
                "message" => 'User with email "'.$data['email'].'" does not exist.'
            ], HttpFoundation\Response::HTTP_BAD_REQUEST);
        }

        $token = (new RandomLib\Factory)->getMediumStrengthGenerator()->generateString(6, $this->tokenChars);

        $this->db->update('chiller', [
            'password_change_token' => password_hash($token, PASSWORD_BCRYPT),
            'password_change_requested_at' => (new \DateTime())->format('c')
        ], [
            'id' => $id
        ]);

        $message = (new \Swift_Message())
            ->setFrom([$this->getSenderEmail() => $this->getSenderName()])
            ->setTo($data['email'])
            ->setSubject('Reset password token')
            ->setBody($token)
            ->setSender($this->getSenderEmail(), $this->getSenderName())
        ;

        
        $this->getMailer()->send($message);

        return new HttpFoundation\JsonResponse(["success" => true],HttpFoundation\Response::HTTP_OK);
    }

    /**
     * @api {post} /reset_password/verify Verify a token
     * @apiGroup ResetPassword
     * @apiPermission anonymous
     *
     * @apiExample Example request:
        {
            "email": "example@example.com",
            "token": "7JC7ZA"
        }
     *
     * @param HttpFoundation\Request $request
     * @return HttpFoundation\Response
     */
    public function verify(HttpFoundation\Request $request)
    {
        $data = \GuzzleHttp\json_decode($request->getContent(), true);

        if (!array_key_exists('email', $data)
            || !array_key_exists('token', $data)
        ) {
            throw new Exception\BadRequestHttpException();
        }

        $result = $this->checkToken($data['email'], $data['token']);

        if ($result instanceof HttpFoundation\Response) {
            return $result;
        }

        return new HttpFoundation\JsonResponse(["success" => true],HttpFoundation\Response::HTTP_OK);
    }

    /**
     * @api {post} /reset_password/set Set a new password
     * @apiGroup ResetPassword
     * @apiPermission anonymous
     *
     * @apiExample Example request:
        {
            "email": "example@example.com",
            "token": "7JC7ZA",
            "password": "new password"
        }
     *
     * @param HttpFoundation\Request $request
     * @return HttpFoundation\Response
     */
    public function set(HttpFoundation\Request $request)
    {
        $data = \GuzzleHttp\json_decode($request->getContent(), true);

        if (!array_key_exists('email', $data)
            || !array_key_exists('token', $data)
            || !array_key_exists('password', $data)
        ) {
            throw new Exception\BadRequestHttpException();
        }

        $result = $this->checkToken($data['email'], $data['token']);

        if ($result instanceof HttpFoundation\Response) {
            return $result;
        }

        $newPassword = password_hash($data['password'], PASSWORD_BCRYPT);

        $this->db->update('chiller', [
            'password' => $newPassword,
            'password_change_token' => null,
            'password_change_requested_at' => null
        ], [
            'email' => $data['email']
        ]);

        return new HttpFoundation\JsonResponse(["success" => true],HttpFoundation\Response::HTTP_OK);
    }

    /**
     * Check if token is valid for the given email address.
     *
     * @param $email
     * @param $token
     * @return null|HttpFoundation\Response
     */
    protected function checkToken($email, $token)
    {
        $query = <<<SQL
            SELECT `id`, `password_change_token`, `password_change_requested_at` > (NOW() - INTERVAL 24 HOUR) as 'is_valid'
            FROM `chiller`
            WHERE `email` = ?
SQL;

        list($id, $hash, $isValid) = $this->db->fetchArray($query, [$email]);

        if (null === $id) {
            return new HttpFoundation\JsonResponse([
                "success" => false,
                "code" => self::ERROR_EMAIL_DOES_NOT_EXIST,
                "message" => 'User with email "'.$email.'" does not exist.'
            ], HttpFoundation\Response::HTTP_BAD_REQUEST);
        }

        if ('0' === $isValid) {
            return new HttpFoundation\JsonResponse([
                "success" => false,
                "code" => self::ERROR_TOKEN_EXPIRED,
                "message" => "Expired token."
            ], HttpFoundation\Response::HTTP_BAD_REQUEST);
        }

        if (!password_verify($token, $hash)) {
            return new HttpFoundation\JsonResponse([
                "success" => false,
                "code" => self::ERROR_TOKEN_INVALID,
                "message" => "Invalid token."
            ], HttpFoundation\Response::HTTP_BAD_REQUEST);
        }

        return null;
    }
}
