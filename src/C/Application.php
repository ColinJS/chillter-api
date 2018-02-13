<?php

namespace C;

use Pimple\ServiceProviderInterface;
use Silex\Application as SilexApplication;

class Application extends SilexApplication
{
    /**
     * Register array of service providers at once
     *
     * @param array ServiceProviderInterface[] $providers
     */
    public function registerProviders(array $providers = array())
    {
        foreach ($providers as $provider) {
            $this->register($provider);
        }
    }
}
