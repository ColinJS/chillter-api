<?php

$app = require __DIR__ . '/src/app.php';

if (!$app instanceof Silex\Application) {
    throw new LogicException("app.php must be an instance of Silex\Application!");
}

$app->run();
