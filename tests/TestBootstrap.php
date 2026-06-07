<?php

declare(strict_types=1);

use Kommandhub\PaystackSW\Tests\TestBootstrapper;

$loader = (new TestBootstrapper())
    ->setPlatformEmbedded(true)
    ->addCallingPlugin()
    ->setForceInstallPlugins(true)
    ->addActivePlugins(
        'KommandhubFoundationSW',
        'KommandhubPaystackSW',
    )
    ->bootstrap()
    ->getClassLoader();

$loader->addPsr4('Kommandhub\\PaystackSW\\Tests\\', __DIR__);
