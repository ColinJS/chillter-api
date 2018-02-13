<?php

namespace C\Provider;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Loader\YamlFileLoader;

class RouteProvider implements ServiceProviderInterface
{
    public function register(Container $app)
    {
        $app['routes'] = $app->extend('routes', function (RouteCollection $routes, Container $app) {
            foreach ($app['routing.paths'] as $path) {
                $loader = new YamlFileLoader(new FileLocator($path));
                $routes->addCollection($loader->load('routing.yml'));

                if ($app['debug'] && (new Filesystem())->exists($path . '/routing_dev.yml')) {
                    $routes->addCollection($loader->load('routing_dev.yml'));
                }
            }
            return $routes;
        });
    }
}
