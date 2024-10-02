<?php

/*
 * This file is a part of the TwitchPHP project.
 *
 * Copyright (c) 2021-Present Valithor Obsidion <valithor@valgorithms.com>
 */

namespace Twitch;

enum ModerationMethod: string {
    case EXACT = 'exact';
    case CYRILLIC = 'cyrillic';
    case STR_STARTS_WITH = 'str_starts_with';
    case STR_ENDS_WITH = 'str_ends_with';
    case STR_CONTAINS = 'str_contains';

    private static function matches(Filter $filter, string $content): bool {
        return match (self::from($filter->method ?? 'str_contains')) {
            self::EXACT => preg_match('/\b' . preg_quote($$filter->word, '/') . '\b/i', $content),
            //self::CYRILLIC => preg_match('/[\p{Cyrillic}]/u', $$filter->word),
            self::STR_STARTS_WITH => str_starts_with($content, $$filter->word),
            self::STR_ENDS_WITH => str_ends_with($content, $$filter->word),
            self::STR_CONTAINS => str_contains($content, $$filter->word),
            default => str_contains($content, $$filter->word),
        };
    }

    public function moderate(string $content): bool
    {
        $content_lower = strtolower($content);
        $seenCategories = [];
        $infractions = array_filter($filter_array, function($badwords) use ($content_lower, &$seenCategories) {
            if ($badwords['category'] && ! isset($seenCategories[$badwords['category']]) && ModerationMethod::matches($content_lower, $badwords)) {
                $seenCategories[$badwords['category']] = true;
                return true;
            }
            return false;
        });
        foreach ($infractions as $filter_array) {
            //$__relayViolation($filter_array);
        }
        return $seenCategories ? true : false;
    }

    public function __invoke(string $username, string $content): array|false
    {
        foreach ($this->filters as $filter)
            if ($this->matches($filter, strtolower($content)))
                $badword_warnings[$filter->category][] = $filter->reason;

        return ! empty($badword_warnings[$username]);
    }

    private function __relayViolation(string $username, array $filter_array, array &$badword_warnings): string|false
    {
        if ($username === $this->nick) {
            $this->logger->info("Ignoring self-violation:" . json_encode($filter_array));
            return false; // Don't ban the bot
        }
        if (isset($this->civ13->verifier))
            if ($guild = $this->discord->guilds->get('id', $this->civ13->civ13_guild_id))
                if ($item = $this->civ13->verifier->get('ss13', $username))
                    if ($member = $guild->members->get('id', $item['discord']))    
                        if ($member->roles->has($this->civ13->role_ids['Admin']))
                            return false; // Don't ban an admin

        $filtered = substr($filter_array['word'], 0, 1) . str_repeat('%', strlen($filter_array['word'])-2) . substr($filter_array['word'], -1, 1);
        if (! $this->__relayWarningCounter($username, $filter_array, $badword_warnings)) return $this->civ13->ban(['ckey' => $ckey, 'duration' => $filter_array['duration'], 'reason' => "Blacklisted phrase ($filtered). Review the rules at {$this->civ13->rules}. Appeal at {$this->civ13->discord_formatted}"]);
        $warning = "You are currently violating a server rule. Further violations will result in an automatic ban that will need to be appealed on our Discord. Review the rules at {$this->civ13->rules}. Reason: {$filter_array['reason']} ({$filter_array['category']} => $filtered)";
        if (isset($this->civ13->channel_ids['staff_bot']) && $channel = $this->discord->getChannel($this->civ13->channel_ids['staff_bot'])) $this->civ13->sendMessage($channel, "`$ckey` is" . substr($warning, 7));
        //$gameserver->DirectMessage($warning, $this->discord->username, $ckey);
        return $warning;
    }
}

class Moderator
{
    private array $filters = [];

    public function addFilter(Filter $filter): void
    {
        $this->filters[] = $filter;
    }

    public function __invoke(string $username, string $content, array &$badword_warnings): bool
    {
        $moderationMethod = new class {
        };

        foreach ($this->filters as $filter) {
            if ($moderationMethod->moderate($content, [$filter]))
                $badword_warnings[$username][] = $filter->reason;
        }

        return !empty($badword_warnings[$username]);
    }
}

class Filter
{
    /**
     * Constructor for the ModerationMethod enum.
     *
     * @param string $word     The word to be moderated.
     * @param string $category The category of the moderation.
     * @param string $method   The case/method used for moderation.
     * @param string $reason   The reason for the moderation.
     * @param int    $warnings The number of warnings issued.
     */
    public function __construct(
        public string $word,
        public string $category,
        public string $method,
        public string $reason,
        public int    $warnings
    ) {}

    public function __toString(): string
    {
        return $this->word;
    }
}

// Example usage

$filters = ['badwordtestmessage', 'Violated server rule.', 'test', 'str_contains', 1];
$filter = new Filter('badwordtestmessage', 'Violated server rule.', 'test', 'str_contains', 1);