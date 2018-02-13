<?php

date_default_timezone_set('UTC');

require_once __DIR__ . '/../vendor/autoload.php';

$app = new \C\Application();

foreach (include __DIR__ . '/../config.php' as $groupName => $config) {
    $app[$groupName] = $config;
}

$app['console.name'] = 'Chillter API';
$app['console.version'] = '1.0';
$app['console.project_directory'] = __DIR__ . '/..';
$app['root.dir'] = __DIR__ . '/..';
$app['twig.path'] = __DIR__ . '/Backend/Resources/views';
$app['routing.paths'] = [
    __DIR__ . '/C/Resources/config',
    __DIR__ . '/Backend/Resources/config',
];
$app['security.access_rules'] = array(
    array('^/backend', 'ROLE_ADMIN'),
);
$app['security.default_encoder'] = function ($app) {
    return $app['security.encoder.bcrypt'];
};
$app['security.firewalls'] = array(
    'login' => array(
        'pattern' => '^/backend/login$',
    ),
    'admin' => array(
        'pattern' => '^/backend',
        'form' => array(
            'login_path' => '/backend/login',
            'check_path' => '/backend/login_check',
            'default_target_path' => '/backend',
        ),
        'logout' => array(
            'logout_path' => '/backend/logout',
            'target_url' => '/backend/login',
            'invalidate_session' => true,
        ),
        'users' => function () use ($app) {
            return new Core\Security\UserProvider($app['db']);
        },
    ),
);
$app['request'] = $app->factory(function ($app) {
    return $app['request_stack']->getCurrentRequest();
});

$app->register(new Silex\Provider\SwiftmailerServiceProvider(), [
    'swiftmailer.options' => $app['swiftmailer.options']
]);


$app->registerProviders([
    new Silex\Provider\ServiceControllerServiceProvider(),
    new Silex\Provider\TranslationServiceProvider(),
    new Silex\Provider\SecurityServiceProvider(),
    new Silex\Provider\DoctrineServiceProvider(),
    new Silex\Provider\SessionServiceProvider(),
    new Silex\Provider\LocaleServiceProvider(),
    new Knp\Provider\ConsoleServiceProvider(),
    new Sorien\Provider\PimpleDumpProvider(),
    new C\Provider\CommonServicesProvider(),
    new C\Provider\EventListenerProvider(),
    new C\Provider\TranslationProvider(),
    new C\Provider\ControllerProvider(),
    new C\Provider\SecurityProvider(),
    new C\Provider\CommandProvider(),
    new C\Provider\RouteProvider(),
    new C\Provider\CORSProvider(),
    new OneSignal\ServiceProvider(),
]);

$app->boot();

$app->register(new Silex\Provider\TwigServiceProvider());

$app['twig.loader.filesystem']->addPath(__DIR__ . '/Backend/Resources/views');
$app['backend.backend_controller'] = new \Backend\Controller\BackendController($app);
$app['backend.security_controller'] = new \Backend\Controller\SecurityController($app);

return $app;
