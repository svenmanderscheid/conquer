<?php
declare(strict_types=1);
namespace Conquer\Auth;

/** Credential transport shared by both local administrator creation commands. */
final class AdminCli
{
    public static function run(array $args, callable $create, $input, $output, $error, string $defaultRole = 'moderator', ?callable $hiddenReader = null): int
    {
        $usage = "Usage: php create_admin.php <username> [superadmin|moderator|support] [--must-change] [--password-stdin]\nPasswords are never accepted as arguments. Use a terminal or explicitly pipe one password line.\n";
        if ($args === ['--help']) { fwrite($output, $usage); return 0; }
        $username = array_shift($args); $role = $defaultRole; $roleSeen = false; $flags = [];
        if (!is_string($username) || $username === '' || str_starts_with($username, '--')) { fwrite($error, $usage); return 1; }
        foreach ($args as $arg) {
            if (in_array($arg, ['superadmin', 'moderator', 'support'], true) && !$roleSeen) { $role = $arg; $roleSeen = true; }
            elseif (in_array($arg, ['--must-change', '--password-stdin'], true) && !isset($flags[$arg])) { $flags[$arg] = true; }
            else { fwrite($error, "Invalid arguments. Password arguments and unknown or repeated options are rejected.\n" . $usage); return 1; }
        }
        try {
            if (isset($flags['--password-stdin'])) {
                if (stream_isatty($input)) throw new \RuntimeException('Password stdin must be a pipe.');
                $password = stream_get_contents($input, 203);
                if (!is_string($password)) throw new \RuntimeException('Password input failed.');
                // Exactly one optional terminator; preserve all password whitespace.
                $password = preg_replace('/\r?\n$/D', '', $password);
            } else {
                if ($hiddenReader === null && !stream_isatty($input)) throw new \RuntimeException('A terminal is required.');
                $read = $hiddenReader ?? static fn(): string => self::hidden($input);
                fwrite($error, 'Password (hidden): ');
                try { $password = $read(); } finally { fwrite($error, "\n"); }
                fwrite($error, 'Repeat password (hidden): ');
                try { $confirmation = $read(); } finally { fwrite($error, "\n"); }
                if (!is_string($password) || !is_string($confirmation) || !hash_equals($password, $confirmation)) throw new \RuntimeException('Passwords do not match.');
                unset($confirmation);
            }
            // Same length range as the mandatory administrator password-change flow.
            if (strlen($password) < 14 || strlen($password) > 200 || strpbrk($password, "\0\r\n") !== false) throw new \RuntimeException('Invalid password input.');
        } catch (\Throwable) {
            fwrite($error, "Password could not be read safely. Use matching passwords of 14–200 UTF-8 bytes, or --password-stdin with exactly one line from a protected input source.\n");
            return 1;
        }
        try {
            $id = $create($username, $password, $role, isset($flags['--must-change']));
            fwrite($output, 'Admin created successfully. ID: ' . (int)$id . "\n");
            return 0;
        } catch (\Throwable) {
            // Never echo SQL, driver or helper exception text with credential data.
            fwrite($error, "Admin creation failed. Check database availability and whether the username already exists.\n");
            return 1;
        } finally { unset($password); }
    }

    private static function hidden($input): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $powershell = rtrim((string)(getenv('SystemRoot') ?: 'C:\\Windows'), '\\/') . '/System32/WindowsPowerShell/v1.0/powershell.exe';
            // Hidden console input; only a private, explicitly UTF-8 pipe carries the result.
            $script = <<<'PS'
$ErrorActionPreference='Stop';try{$s=New-Object System.Text.StringBuilder;while($true){$k=[Console]::ReadKey($true);if($k.Key -eq 'Enter'){break};if($k.Key -eq 'Escape'){exit 1};if($k.Key -eq 'Backspace'){if($s.Length -gt 0){$s.Length--};continue};if($k.KeyChar -ne [char]0){[void]$s.Append($k.KeyChar)}};[Console]::OutputEncoding=New-Object System.Text.UTF8Encoding($false);[Console]::Out.Write($s.ToString());$s.Clear()|Out-Null;exit 0}catch{exit 1}
PS;
            return self::command([$powershell, '-NoLogo', '-NoProfile', '-Command', $script], $input);
        }
        $stty = is_executable('/bin/stty') ? '/bin/stty' : '/usr/bin/stty';
        $mode = trim(self::command([$stty, '-g'], $input));
        if ($mode === '' || !preg_match('/^[a-zA-Z0-9:;=]+$/D', $mode)) throw new \RuntimeException('Terminal unavailable.');
        self::command([$stty, '-echo'], $input);
        $restored = false;
        $restore = static function () use ($stty, $mode, $input, &$restored): void {
            if (!$restored) { self::command([$stty, $mode], $input); $restored = true; }
        };
        register_shutdown_function(static function () use ($restore): void { try { $restore(); } catch (\Throwable) {} });
        $handlers = [];
        if (function_exists('pcntl_signal') && function_exists('pcntl_async_signals')) {
            foreach ([SIGINT, SIGTERM] as $signal) { $handlers[$signal] = pcntl_signal_get_handler($signal); pcntl_signal($signal, static function () use ($restore): void { $restore(); exit(130); }); }
            $async = pcntl_async_signals(true);
        }
        try {
            $line = fgets($input, 203);
            if ($line === false) throw new \RuntimeException('Input ended.');
            return (string)preg_replace('/\r?\n$/D', '', $line);
        } finally {
            $restore();
            foreach ($handlers as $signal => $handler) pcntl_signal($signal, $handler);
            if (isset($async)) pcntl_async_signals($async);
        }
    }

    private static function command(array $command, $input): string
    {
        $process = @proc_open($command, [0 => $input, 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) throw new \RuntimeException('Secure input helper unavailable.');
        $result = stream_get_contents($pipes[1]); fclose($pipes[1]);
        stream_get_contents($pipes[2]); fclose($pipes[2]);
        if (proc_close($process) !== 0 || !is_string($result)) throw new \RuntimeException('Secure input helper failed.');
        return $result;
    }
}
