<?php
declare(strict_types=1);
namespace Conquer\Game\Premium;

/** Payment providers may create a checkout, but only a verified provider event can grant rewards. */
interface PaymentGateway
{
    public function name(): string;
    public function isAvailable(): bool;

    /** @param array<string,mixed> $order @return array<string,mixed> */
    public function createCheckout(array $order): array;

    /** @param array<string,string> $headers @return array<string,mixed> */
    public function verifyNotification(string $rawBody, array $headers): array;
}
