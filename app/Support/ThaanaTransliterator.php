<?php

namespace App\Support;

/**
 * Best-effort phonetic Latin -> Thaana transliteration — the same
 * consonant+vowel syllable approach informal Dhivehi input methods use
 * (type the sound, get the script), not a dictionary of known words —
 * so it works on arbitrary names/titles, but is mechanical, not
 * linguistically validated. Used only for display (Bureau Roles
 * table's name/position columns); the underlying `name`/`position`
 * columns stay in Latin script. Same caveat as the rest of this
 * module's Dhivehi text (see lang/dv/bureau.php): provisional, needs a
 * native speaker's review, especially for names of foreign origin.
 */
class ThaanaTransliterator
{
    /** Checked longest-match-first (see matchLongest()). */
    private const CONSONANTS = [
        'sh' => 'ށ',
        'lh' => 'ޅ',
        'dh' => 'ދ',
        'th' => 'ތ',
        'gn' => 'ޏ',
        'ny' => 'ޏ',
        'ch' => 'ޗ',
        'h' => 'ހ',
        'n' => 'ނ',
        'r' => 'ރ',
        'b' => 'ބ',
        'k' => 'ކ',
        'v' => 'ވ',
        'm' => 'މ',
        'f' => 'ފ',
        'l' => 'ލ',
        'g' => 'ގ',
        's' => 'ސ',
        'd' => 'ޑ',
        'z' => 'ޒ',
        't' => 'ޓ',
        'y' => 'ޔ',
        'p' => 'ޕ',
        'j' => 'ޖ',
        'c' => 'ކ',
        'q' => 'ކ',
        'w' => 'ވ',
        'x' => 'ކސ',
    ];

    /**
     * Checked longest-match-first (see matchLongest()). Deliberately no
     * "oh" digraph: it reads as tempting for a long-o sound, but in
     * this roster's names "oh" is almost always o + a separate h
     * consonant (Mohamed, Shahida, ...), and mapping it as one long
     * vowel was swallowing that h entirely.
     */
    private const VOWELS = [
        'aa' => 'ާ',
        'ee' => 'ީ',
        'oo' => 'ޫ',
        'ey' => 'ޭ',
        'oa' => 'ޯ',
        'a' => 'ަ',
        'i' => 'ި',
        'u' => 'ު',
        'e' => 'ެ',
        'o' => 'ޮ',
    ];

    private const ALIF = 'އ';

    private const SUKUN = 'ް';

    public static function transliterate(string $text): string
    {
        $chunks = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];

        return implode('', array_map(
            fn (string $chunk): string => trim($chunk) === '' ? $chunk : static::transliterateWord($chunk),
            $chunks,
        ));
    }

    private static function transliterateWord(string $word): string
    {
        $lower = mb_strtolower($word);
        $length = mb_strlen($lower);
        $output = '';
        $pendingConsonant = null;
        $i = 0;

        while ($i < $length) {
            $consonant = static::matchLongest($lower, $i, array_keys(self::CONSONANTS));

            if ($consonant !== null) {
                if ($pendingConsonant !== null) {
                    $output .= self::CONSONANTS[$pendingConsonant].self::SUKUN;
                }
                $pendingConsonant = $consonant;
                $i += mb_strlen($consonant);

                continue;
            }

            $vowel = static::matchLongest($lower, $i, array_keys(self::VOWELS));

            if ($vowel !== null) {
                if ($pendingConsonant !== null) {
                    $output .= self::CONSONANTS[$pendingConsonant].self::VOWELS[$vowel];
                    $pendingConsonant = null;
                } else {
                    $output .= self::ALIF.self::VOWELS[$vowel];
                }
                $i += mb_strlen($vowel);

                continue;
            }

            // Unrecognized character (digit, punctuation, ...) passes through untouched.
            if ($pendingConsonant !== null) {
                $output .= self::CONSONANTS[$pendingConsonant].self::SUKUN;
                $pendingConsonant = null;
            }
            $output .= mb_substr($lower, $i, 1);
            $i++;
        }

        if ($pendingConsonant !== null) {
            $output .= self::CONSONANTS[$pendingConsonant].self::SUKUN;
        }

        return $output;
    }

    /**
     * @param  array<int, string>  $candidates
     */
    private static function matchLongest(string $haystack, int $offset, array $candidates): ?string
    {
        usort($candidates, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        foreach ($candidates as $candidate) {
            if (mb_substr($haystack, $offset, mb_strlen($candidate)) === $candidate) {
                return $candidate;
            }
        }

        return null;
    }
}
