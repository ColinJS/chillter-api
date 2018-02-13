<?php

namespace C\Provider;

use C\Resolver\ImageResolverInterface;
use C\Resolver\WebPathImageResolver;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class CommonServicesProvider implements ServiceProviderInterface
{
    public function register(Container $app)
    {
        $app[ImageResolverInterface::class] = new WebPathImageResolver(
            $app['request_stack'],
            $app['upload.directory'],
            $app['entry_point_url']
        );
    }
}
