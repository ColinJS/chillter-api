<?php

namespace Backend\Controller;

use C\Application;
use Symfony\Component\HttpFoundation\RequestStack;

abstract class AbstractController
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $db;

    /**
     * @var \Twig_Environment
     */
    protected $twig;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var string
     */
    protected $uploadDirectory;

    /**
     * AbstractController constructor
     *
     * @param Application $application
     */
    public function __construct(Application $application)
    {
        $this->db = $application['db'];
        $this->twig = $application['twig'];
        $this->requestStack = $application['request_stack'];
        $this->uploadDirectory = $application['upload.directory'];
    }

    /**
     * Get normalized URI to the image
     *
     * @param $pathName
     * @return null|string
     */
    public function getUriForPicture($pathName)
    {
        if (!$pathName) {
            return null;
        }

        return $this->requestStack->getCurrentRequest()->getUriForPath($this->uploadDirectory . $pathName);
    }
}
