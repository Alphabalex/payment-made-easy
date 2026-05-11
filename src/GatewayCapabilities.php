<?php

namespace NexusPay\PaymentMadeEasy;

use NexusPay\PaymentMadeEasy\Contracts\DisbursementDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\PaymentLinkDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\SubscriptionDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\VirtualAccountDriverInterface;
use ReflectionClass;

/**
 * Runtime capability checks for payment drivers (which optional interfaces each gateway implements).
 */
final class GatewayCapabilities
{
    /**
     * Optional capability interfaces (all drivers implement {@see PaymentDriverInterface}).
     *
     * @var list<class-string>
     */
    public const OPTIONAL_INTERFACES = [
        SubscriptionDriverInterface::class,
        DisbursementDriverInterface::class,
        VirtualAccountDriverInterface::class,
        PaymentLinkDriverInterface::class,
    ];

    /**
     * @param  class-string  $interface  FQCN of a driver contract (e.g. SubscriptionDriverInterface::class)
     */
    public static function driverImplements(string $gateway, string $interface): bool
    {
        $class = GatewayRegistry::driverClass($gateway);
        if ($class === null || !class_exists($class)) {
            return false;
        }

        if (!interface_exists($interface)) {
            return false;
        }

        return (new ReflectionClass($class))->implementsInterface($interface);
    }

    /**
     * @return array<string, bool> Short interface name (e.g. "SubscriptionDriverInterface") => supported
     */
    public static function optionalCapabilities(string $gateway): array
    {
        $out = [];
        foreach (self::OPTIONAL_INTERFACES as $fqcn) {
            $out[class_basename($fqcn)] = self::driverImplements($gateway, $fqcn);
        }

        return $out;
    }

    /**
     * @param  class-string  $interface
     * @return list<string> Gateway slugs whose driver implements the interface
     */
    public static function gatewaysImplementing(string $interface): array
    {
        $slugs = [];
        foreach (array_keys(GatewayRegistry::DRIVER_CLASSES) as $gateway) {
            if (self::driverImplements($gateway, $interface)) {
                $slugs[] = $gateway;
            }
        }

        return $slugs;
    }
}
