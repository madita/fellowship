<?php

namespace App\Services\Irc;

class IrcProtocolParser
{
    /**
     * Parse a raw IRC protocol line into structured data.
     *
     * IRC format: [:prefix] <command> [params] [:trailing]
     */
    public static function parse(string $line): array
    {
        $line = trim($line, "\r\n");

        $prefix = null;
        $command = null;
        $params = [];
        $trailing = null;

        // Extract prefix
        if (str_starts_with($line, ':')) {
            $spacePos = strpos($line, ' ');
            $prefix = substr($line, 1, $spacePos - 1);
            $line = substr($line, $spacePos + 1);
        }

        // Extract trailing (after " :")
        $trailingPos = strpos($line, ' :');
        if ($trailingPos !== false) {
            $trailing = substr($line, $trailingPos + 2);
            $line = substr($line, 0, $trailingPos);
        }

        // Split remaining into command and params
        $parts = explode(' ', $line);
        $command = strtoupper(array_shift($parts));
        $params = array_filter($parts, fn ($p) => $p !== '');

        if ($trailing !== null) {
            $params[] = $trailing;
        }

        // Parse prefix into nick!user@host
        $nick = null;
        $user = null;
        $host = null;
        if ($prefix && str_contains($prefix, '!')) {
            preg_match('/^([^!]+)!([^@]+)@(.+)$/', $prefix, $matches);
            if ($matches) {
                $nick = $matches[1];
                $user = $matches[2];
                $host = $matches[3];
            }
        }

        return [
            'raw' => $line,
            'prefix' => $prefix,
            'nick' => $nick,
            'user' => $user,
            'host' => $host,
            'command' => $command,
            'params' => $params,
        ];
    }

    /**
     * Build a raw IRC command string.
     */
    public static function build(string $command, array $params = [], ?string $trailing = null): string
    {
        $line = $command;

        if (!empty($params)) {
            $line .= ' ' . implode(' ', $params);
        }

        if ($trailing !== null) {
            $line .= ' :' . $trailing;
        }

        return $line . "\r\n";
    }

    /**
     * Get nick from a prefix string like "nick!user@host".
     */
    public static function getNickFromPrefix(string $prefix): string
    {
        $bangPos = strpos($prefix, '!');
        return $bangPos !== false ? substr($prefix, 0, $bangPos) : $prefix;
    }

    /**
     * Check if a command is a numeric reply.
     */
    public static function isNumeric(string $command): bool
    {
        return is_numeric($command) && strlen($command) === 3;
    }
}
