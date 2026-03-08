<?php

namespace Hostinger\AiAssistant\Providers;

use Hostinger\AiAssistant\Container;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class ContainerProvider implements ProviderInterface {
    public function register( Container $container ): void {
        $container->set( Container::class, fn() => $container );
    }
}
