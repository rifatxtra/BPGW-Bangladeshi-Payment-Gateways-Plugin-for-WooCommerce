<?php

namespace BPGW\Core;

defined('ABSPATH') || exit;

class GatewayManager
{
    public static function register(array $gateways): array
    {
        $gateways[] = \BPGW\Gateways\BkashGateway::class;
        $gateways[] = \BPGW\Gateways\SSLCommerzGateway::class;

        return $gateways;
    }
}