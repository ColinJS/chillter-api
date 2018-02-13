<?php

namespace OneSignal;

use OneSignal;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class ServiceProvider implements ServiceProviderInterface
{
    public function register(Container $app)
    {
        $configuration = new OneSignal\Configuration([
            'key' => $app['onesignal.options']['key'],
            'application_id' => $app['onesignal.options']['application_id']
        ]);

        $app['onesignal'] = new OneSignal\Client($configuration, new OneSignal\Logger\SQLLogger($app['db']));
    }
}
