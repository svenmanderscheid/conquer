<?php
declare(strict_types=1);
namespace Conquer\Game\Premium;

final class UnavailablePaymentGateway implements PaymentGateway
{
    public function name(): string { return 'unavailable'; }
    public function isAvailable(): bool { return false; }
    public function createCheckout(array $order): array
    {
        throw new \DomainException('Echtgeldkäufe sind noch nicht eingerichtet.');
    }
    public function verifyNotification(string $rawBody, array $headers): array
    {
        throw new \DomainException('Dieser Zahlungsanbieter ist nicht eingerichtet.');
    }
}
