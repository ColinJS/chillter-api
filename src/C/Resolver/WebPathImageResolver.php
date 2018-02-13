<?php

namespace C\Resolver;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class WebPathImageResolver implements ImageResolverInterface
{
    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * Upload directory relative to the entry point, e.g. "/images/"
     *
     * @var string
     */
    protected $uploadDirectory;

    /**
     * API entry point URL, e.g. "127.0.0.1:8000/api"
     *
     * @var string
     */
    protected $entryPointUrl;

    public function __construct(RequestStack $requestStack, $uploadDirectory, $entryPointUrl)
    {
        $this->requestStack = $requestStack;
        $this->uploadDirectory = $uploadDirectory;
        $this->entryPointUrl = $entryPointUrl;
    }

    /**
     * Return absolute URL to the file
     *
     * @param $fileName
     * @return null|string
     */
    public function resolve($fileName)
    {
        if (!$fileName) {
            return null;
        }

        if ($this->requestStack->getMasterRequest() instanceof Request) {
            return $this->requestStack->getMasterRequest()->getUriForPath($this->uploadDirectory . $fileName);
        }

        return $this->entryPointUrl . $this->uploadDirectory . $fileName;
    }
}
