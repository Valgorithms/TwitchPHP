<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2025-present Valithor Obsidion <valithor@valgorithms.com>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE file.
 */

namespace Twitch\Auth;

/**
 * A {@see TokenStoreInterface} backed by a `.env` file.
 *
 * Rewrites only the keys it owns, in place, leaving comments, ordering and
 * unrelated entries untouched. Writes go through a temporary file and an
 * atomic rename so a crash mid-write cannot leave a half-written file where
 * the credentials used to be.
 *
 * Keys are matched without regard to case: `TWITCH_ACCESS_TOKEN`, as this
 * library's own examples spell it, is the same key as `twitch_access_token`.
 * An existing line keeps its spelling; a missing one is added in whichever
 * case the file's other `TWITCH_` keys use. Matching exactly used to append a
 * lowercase copy beside an uppercase line, and a caller reading the uppercase
 * one then started every run with the token from before the last rotation.
 *
 * @author Valithor Obsidion <valithor@valgorithms.com>
 */
final class EnvFileTokenStore implements TokenStoreInterface
{
    /** Token payload key → `.env` key. */
    private const KEYS = [
        'access_token'  => 'twitch_access_token',
        'refresh_token' => 'twitch_refresh_token',
        'expires_in'    => 'twitch_expires_in',
        'token_type'    => 'twitch_token_type',
        'scope'         => 'twitch_scope',
    ];

    public function __construct(private readonly string $path)
    {
    }

    public function load(): array
    {
        $parsed = $this->parse();

        $out = [];
        foreach (['access_token', 'refresh_token'] as $key) {
            $value = $parsed[self::KEYS[$key]] ?? '';
            if ($value !== '') {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    public function save(array $token): void
    {
        $updates = [];

        foreach (self::KEYS as $tokenKey => $envKey) {
            if (! array_key_exists($tokenKey, $token) || $token[$tokenKey] === null) {
                continue;
            }

            $value = $token[$tokenKey];
            $updates[$envKey] = $tokenKey === 'scope' && is_array($value)
                ? json_encode(array_values($value))
                : (string) $value;
        }

        if ($updates !== []) {
            $this->write($updates);
        }
    }

    /**
     * Every `KEY=value` pair in the file.
     *
     * @return array<string, string>
     */
    private function parse(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $out = [];
        foreach (file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || $trimmed[0] === '#' || ! str_contains($trimmed, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $trimmed, 2);
            $out[strtolower(trim($key))] = trim($value, " \t\"'");
        }

        return $out;
    }

    /**
     * Replaces the given keys in place, appending any that are not present.
     *
     * @param array<string, string> $updates
     */
    private function write(array $updates): void
    {
        $lines = is_file($this->path) ? (file($this->path, FILE_IGNORE_NEW_LINES) ?: []) : [];
        $seen = [];
        $uppercase = false;

        foreach ($lines as $i => $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || $trimmed[0] === '#' || ! str_contains($trimmed, '=')) {
                continue;
            }

            $key = trim(explode('=', $trimmed, 2)[0]);
            $lower = strtolower($key);

            // The file's own convention, for any key that has to be added.
            if (str_starts_with($key, 'TWITCH_')) {
                $uppercase = true;
            }

            if (isset($updates[$lower])) {
                $lines[$i] = $key . '=' . $updates[$lower];
                $seen[$lower] = true;
            }
        }

        foreach ($updates as $key => $value) {
            if (! isset($seen[$key])) {
                $lines[] = ($uppercase ? strtoupper($key) : $key) . '=' . $value;
            }
        }

        $contents = implode("\n", $lines) . "\n";

        $temp = @tempnam(dirname($this->path), '.env');
        if ($temp === false) {
            throw new \RuntimeException("Unable to create a temporary file alongside {$this->path}");
        }

        if (@file_put_contents($temp, $contents) === false || ! @rename($temp, $this->path)) {
            @unlink($temp);

            throw new \RuntimeException("Unable to write {$this->path}");
        }

        @chmod($this->path, 0o600);
    }
}
