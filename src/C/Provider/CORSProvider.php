<?php

namespace C\Provider;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CORSProvider implements ServiceProviderInterface
{
    public function register(Container $app)
    {
        if (!$app instanceof Application) {
            throw new \LogicException('Container must be an instance of Application.');
        }

        $app->before(function (Request $request) {
            if ($request->getMethod() === "OPTIONS") {
                return (new Response('', 200, [
                    'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
                    'Access-Control-Allow-Headers' => 'Content-Type, X-Token, X-Resource-Id',
                ]))->send();
            }
        }, Application::EARLY_EVENT);

        $app->after(function (Request $request, Response $response) {
            $response->headers->set('Access-Control-Allow-Origin', '*');
        });
    }
}
