<?php
declare(strict_types=1);
namespace Conquer\Game\Premium;

/** Development-only adapter. It requires an HMAC signed server notification and is disabled in production. */
final class PreviewPaymentGateway implements PaymentGateway
{
    public function __construct(private readonly string $secret) {
        if (strlen($secret) < 32) throw new \InvalidArgumentException('Der Vorschau-Zahlungsschlüssel muss mindestens 32 Zeichen lang sein.');
    }
    public function name(): string { return 'preview'; }
    public function isAvailable(): bool { return true; }
    public function createCheckout(array $order): array
    {
        return ['provider_reference'=>$order['id'],'checkout_url'=>null,'mode'=>'preview'];
    }
    public function verifyNotification(string $rawBody, array $headers): array
    {
        $signature = strtolower(trim((string) ($headers['x-conquer-signature'] ?? '')));
        $expected = hash_hmac('sha256', $rawBody, $this->secret);
        if (!preg_match('/^[a-f0-9]{64}$/D', $signature) || !hash_equals($expected, $signature)) {
            throw new \DomainException('Ungültige Signatur des Zahlungsanbieters.', 403);
        }
        try { $data=json_decode($rawBody,true,16,JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new \DomainException('Ungültige Zahlungsnachricht.'); }
        if (!is_array($data)) throw new \DomainException('Ungültige Zahlungsnachricht.');
        foreach (['event_id','provider_reference','currency','status'] as $key) {
            if (!is_string($data[$key]??null) || $data[$key]==='') throw new \DomainException('Unvollständige Zahlungsnachricht.');
        }
        if (!is_int($data['amount_cents']??null) || $data['amount_cents']<0) throw new \DomainException('Ungültiger Zahlungsbetrag.');
        return [
            'event_id'=>$data['event_id'], 'provider_reference'=>$data['provider_reference'],
            'amount_cents'=>$data['amount_cents'], 'currency'=>strtoupper($data['currency']),
            'status'=>$data['status'], 'payload_hash'=>hash('sha256',$rawBody),
        ];
    }
}
