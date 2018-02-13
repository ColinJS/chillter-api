<?php

namespace C\Controller;

use Symfony\Component\HttpFoundation\Response;

class DebugController extends AbstractController
{
    public function index()
    {
        return new Response('For more details see <a href="/doc">the API documentation</a>.');
    }
}
