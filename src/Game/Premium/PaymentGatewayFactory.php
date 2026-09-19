<?php
declare(strict_types=1);
namespace Conquer\Game\Premium;

use Conquer\Bootstrap;

final class PaymentGatewayFactory
{
    public static function configured(): PaymentGateway
    {
        $config=Bootstrap::getConfig();$payment=$config['premium_payments']??[];
        if (($config['env']??'production')!=='production'
            && ($payment['provider']??null)==='preview'
            && ($payment['preview']['enabled']??false)===true
            && is_string($payment['preview']['secret']??null)) {
            return new PreviewPaymentGateway($payment['preview']['secret']);
        }
        return new UnavailablePaymentGateway();
    }

    public static function notification(string $provider): PaymentGateway
    {
        $gateway=self::configured();
        if (!$gateway->isAvailable() || !hash_equals($gateway->name(),$provider)) {
            throw new \DomainException('Dieser Zahlungsanbieter ist nicht eingerichtet.',403);
        }
        return $gateway;
    }
}
