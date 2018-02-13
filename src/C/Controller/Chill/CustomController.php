<?php

namespace C\Controller\Chill;

use C\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @apiDefine ChillCustom Custom chill
 */
class CustomController extends AbstractController
{
    /**
     * @api {post} /chillers/{userId}/custom_chills/{chillId}/logo Upload a logo
     * @apiGroup ChillCustom
     * @apiPermission authenticated (only own chill)
     *
     * @apiExample Example request:
        {
            "image": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAA3XAAAN1wFCKJt4AAAAB3RJTUUH4QsJDx8KPce8oQAAAW5JREFUGNMlzF1Lk2EYwPH/de+Ze8acm5RpzWELZOJL6yCh8MCP0GEhehohgn2RoCDIwxCP/BgeiOxANOzFSle+obNINzf3vNz35UG/D/CT4F2B1OIxwYd8nq7kC8kOVsDE2jzc06Dz0Z9vXARvCwhAsDRYIXNry9x9onq2KahD+h+rnlVF2/Wp1Mvjdem8uTMit4eX8fxJwgbaOAQEyRXBS4NzW3q+O+ep9comNzqJn4PWOSQHIJVFxAM/D9HVI1f/NeaRLs3Y3TXUxRC1ka4Mqg7iEJL+/z1TnDEaYzU06LXDlJ+h6QKSH0HDBNqK0NCgsVjjjn6vmvvTaCvAfqsidGNrX9BA0HZMYmgad3K0aoj0q6193jD9FVz9lPjHNtpRtBVh+saxB9836USfBKD56sFDyfRuJ8pP1R3sCDhMcULtz6po8+9Udml/Xa4WSnS/r3E529eDST03vffGEYndv5Ma9no5t/Kn0X5d4gZ1/qt+WXQnQAAAAABJRU5ErkJggg=="
        }
     *
     * @apiSuccessExample Example success response:
        {
            "url": "http://chillter.fr/api/images/chills/custom/59d4a0bb98b18.jpeg"
        }
     *
     * @param $userId
     * @param $chillId
     * @param Request $request
     * @return JsonResponse
     */
    public function postLogo($userId, $chillId, Request $request)
    {
        $this->denyAccessUnlessGrantedToCustomChill($userId, $chillId);

        $content = \GuzzleHttp\json_decode($request->getContent(), true);

        if (!array_key_exists('image', $content)) {
            throw new BadRequestHttpException();
        }

        $fileName = $this->savePhotoFromBase64String($content['image'], 'chills/custom/');

        if ($current = $this->db->fetchColumn("SELECT `logo` FROM `chills_custom` WHERE`id` = ?", [$chillId])) {
            (new Filesystem())->remove($this->getRootDir().$this->getUploadDirectory().$current);
        }

        $this->db->update('chills_custom', ['logo' => 'chills/custom/'.$fileName], ['id' => $chillId]);

        return new JsonResponse([
            "url" => $request->getUriForPath($this->getUploadDirectory().'chills/custom/'.$fileName)
        ], Response::HTTP_CREATED);
    }

    /**
     * @api {post} /chillers/{userId}/custom_chills/{chillId}/banner Upload a banner
     * @apiGroup ChillCustom
     * @apiPermission authenticated (only own chill)
     *
     * @apiExample Example request:
        {
            "image": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAA3XAAAN1wFCKJt4AAAAB3RJTUUH4QsJDx8KPce8oQAAAW5JREFUGNMlzF1Lk2EYwPH/de+Ze8acm5RpzWELZOJL6yCh8MCP0GEhehohgn2RoCDIwxCP/BgeiOxANOzFSle+obNINzf3vNz35UG/D/CT4F2B1OIxwYd8nq7kC8kOVsDE2jzc06Dz0Z9vXARvCwhAsDRYIXNry9x9onq2KahD+h+rnlVF2/Wp1Mvjdem8uTMit4eX8fxJwgbaOAQEyRXBS4NzW3q+O+ep9comNzqJn4PWOSQHIJVFxAM/D9HVI1f/NeaRLs3Y3TXUxRC1ka4Mqg7iEJL+/z1TnDEaYzU06LXDlJ+h6QKSH0HDBNqK0NCgsVjjjn6vmvvTaCvAfqsidGNrX9BA0HZMYmgad3K0aoj0q6193jD9FVz9lPjHNtpRtBVh+saxB9836USfBKD56sFDyfRuJ8pP1R3sCDhMcULtz6po8+9Udml/Xa4WSnS/r3E529eDST03vffGEYndv5Ma9no5t/Kn0X5d4gZ1/qt+WXQnQAAAAABJRU5ErkJggg=="
        }
     *
     * @apiSuccessExample Example success response:
        {
            "url": "http://chillter.fr/api/images/chills/custom/59d4a0bb98b18.jpeg"
        }
     *
     * @param $userId
     * @param $chillId
     * @param Request $request
     * @return JsonResponse
     */
    public function postBanner($userId, $chillId, Request $request)
    {
        $this->denyAccessUnlessGrantedToCustomChill($userId, $chillId);

        $content = \GuzzleHttp\json_decode($request->getContent(), true);

        if (!array_key_exists('image', $content)) {
            throw new BadRequestHttpException();
        }

        $fileName = $this->savePhotoFromBase64String($content['image'], 'chills/custom/');

        if ($current = $this->db->fetchColumn("SELECT `banner` FROM `chills_custom` WHERE`id` = ?", [$chillId])) {
            (new Filesystem())->remove($this->getRootDir().$this->getUploadDirectory().$current);
        }

        $this->db->update('chills_custom', ['banner' => 'chills/custom/'.$fileName], ['id' => $chillId]);

        return new JsonResponse([
            "url" => $request->getUriForPath($this->getUploadDirectory().'chills/custom/'.$fileName)
        ], Response::HTTP_CREATED);
    }
}
