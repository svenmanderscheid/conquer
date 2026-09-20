<?php
declare(strict_types=1);
namespace Conquer\Auth;

use Conquer\Db\Connection;
use Conquer\Game\Locale;
use Conquer\Security\RateLimit;

/** Public interest list, separate from player accounts and alpha access keys. */
final class AlphaWaitlist
{
    public const CONSENT_VERSION = 'alpha-invitation-v1';

    /** Returns a translation key on failure; successful requests use PRG. */
    public static function submit(): string
    {
        $token = $_POST['csrf'] ?? null;
        $expected = $_SESSION['login_csrf'] ?? '';
        if (!is_string($token) || $expected === '' || !hash_equals($expected, $token)) {
            http_response_code(403);
            return 'waitlist.error_csrf';
        }
        try {
            $retry = RateLimit::consume('alpha.waitlist.ip', RateLimit::ip(), 5, 600);
            if ($retry > 0) {
                header('Retry-After: ' . $retry);
                http_response_code(429);
                return 'waitlist.error_rate';
            }
            self::join($_POST, Locale::current());
            $_SESSION['alpha_waitlist_success'] = true;
            header('Location: ' . APP_BASE . '/?zugang=waitlist#zugang', true, 303);
            exit;
        } catch (\InvalidArgumentException $e) {
            http_response_code(422);
            return $e->getMessage();
        } catch (\Throwable $e) {
            // Never log form values or SQL exception messages containing contact data.
            error_log('Alpha waitlist storage unavailable (' . get_class($e) . ').');
            header('Retry-After: 30');
            http_response_code(503);
            return 'waitlist.error_unavailable';
        }
    }

    public static function join(array $input, string $locale): void
    {
        $names = [];
        foreach (['first_name', 'last_name'] as $field) {
            $value = $input[$field] ?? null;
            if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
                throw new \InvalidArgumentException('waitlist.error_name');
            }
            $value = trim($value);
            if ($value === '' || mb_strlen($value) > 80 || preg_match('/[\p{C}]/u', $value)) {
                throw new \InvalidArgumentException('waitlist.error_name');
            }
            $names[] = $value;
        }
        $email = $input['email'] ?? null;
        if (!is_string($email) || strlen($email = strtolower(trim($email))) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('waitlist.error_email');
        }
        if (($input['consent'] ?? null) !== '1') {
            throw new \InvalidArgumentException('waitlist.error_consent');
        }
        // The unique email key makes retries safe. Public submissions cannot change
        // an existing person's name, consent record, language or invitation status.
        Connection::getInstance()->execute(
            'INSERT INTO alpha_waitlist(first_name,last_name,email,locale,consent_version,consent_at,created_at) VALUES(?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE id=id',
            [...$names, $email, Locale::normalize($locale), self::CONSENT_VERSION]
        );
    }
}
