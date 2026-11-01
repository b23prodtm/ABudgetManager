<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Rule engine: case/accent-insensitive substring match with `*` and `?` wildcards.
 * When several rules match, the one with the highest position wins.
 */
class Categorizer
{
    /**
     * @var array<int, array{id: int|null, keyword: string, category_id: int, position: int, regex: string}>
     */
    private array $rules = [];

    /**
     * @param iterable<array{id?: int|null, keyword: string, category_id: int, position: int}> $rules
     */
    public function __construct(iterable $rules)
    {
        foreach ($rules as $rule) {
            $regex = self::toRegex($rule['keyword']);
            if ($regex === null) {
                continue;
            }
            $this->rules[] = [
                'id' => $rule['id'] ?? null,
                'keyword' => $rule['keyword'],
                'category_id' => (int)$rule['category_id'],
                'position' => (int)$rule['position'],
                'regex' => $regex,
            ];
        }
        usort($this->rules, fn(array $a, array $b) => $a['position'] <=> $b['position']);
    }

    public static function normalize(string $text): string
    {
        $text = trim($text);
        $stripped = class_exists(\Transliterator::class)
            ? \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC')?->transliterate($text)
            : null;
        $text = mb_strtoupper($stripped ?: $text, 'UTF-8');

        return trim((string)preg_replace('/\s+/u', ' ', $text));
    }

    private static function toRegex(string $keyword): ?string
    {
        $keyword = self::normalize($keyword);
        if ($keyword === '') {
            return null;
        }
        $parts = preg_split('/([*?])/u', $keyword, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $regex = '';
        foreach ($parts as $part) {
            $regex .= match ($part) {
                '*' => '.*',
                '?' => '.',
                default => preg_quote($part, '/'),
            };
        }

        return '/' . $regex . '/us';
    }

    /**
     * All matching rules, ordered by ascending position.
     *
     * @return array<int, array<string, mixed>>
     */
    public function matches(string $label): array
    {
        $label = self::normalize($label);
        $found = [];
        foreach ($this->rules as $rule) {
            if (preg_match($rule['regex'], $label) === 1) {
                unset($rule['regex']);
                $found[] = $rule;
            }
        }

        return $found;
    }

    /**
     * The winning rule (highest position) or null => "À classer".
     *
     * @return array<string, mixed>|null
     */
    public function winner(string $label): ?array
    {
        $found = $this->matches($label);

        return $found ? end($found) : null;
    }

    /**
     * Same as matching a free-text keyword against a label (fixed costs, alerts).
     */
    public static function keywordMatches(string $keyword, string $label): bool
    {
        $regex = self::toRegex($keyword);

        return $regex !== null && preg_match($regex, self::normalize($label)) === 1;
    }
}
