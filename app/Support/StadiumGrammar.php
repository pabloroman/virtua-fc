<?php

namespace App\Support;

/**
 * Spanish grammatical article helpers for stadium names.
 *
 * Mirrors the pattern established by App\Models\Team grammar accessors
 * (nameWithEn / nameWithDe / nameWithEl). Stadium names don't live on a
 * dedicated model — they're a free-text column on Team — so this helper
 * encapsulates the article lookup and phrase-building via a hardcoded map.
 *
 * Default article is 'el' (masculine). Override to null for names that
 * read naturally without an article — toponyms ("Mendizorroza"), person
 * names ("San Mamés"), or names whose article is already part of the
 * proper noun and stays capitalized ("El Sadar", "La Rosaleda").
 */
class StadiumGrammar
{
    /**
     * @var array<string, string|null>
     */
    private const ARTICLES = [
        // No article — stadium names that read naturally without one.
        // Includes toponyms, person names, and names where "El"/"La" is
        // part of the proper noun and stays capitalized.
        'San Mamés' => null,
        'Butarque' => null,
        'El Plantío' => null,
        'Mestalla' => null,
        'La Cerámica' => null,
        'El Molinón - Enrique Castro \'Quini\'' => null,
        'El Sadar' => null,
        'El Alcoraz' => null,
        'Mendizorroza' => null,
        'Ipurua' => null,
        'La Rosaleda' => null,
        'Zubieta' => null,
        'La Cartuja' => null,
        'El Sardinero' => null,
        'Montilivi' => null,
        'Anduva' => null,

        'Selhurst Park' => null,
        'Anfield' => null,
        'Old Trafford' => null,
        'Stamford Bridge' => null,
        'Elland Road' => null,
        'St James\' Park' => null,
        'Craven Cottage' => null,
        'Villa Park' => null,
    ];

    /**
     * Resolve the grammatical article for a stadium name.
     * Returns 'el', 'la', or null. Default is 'el'.
     */
    public static function article(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }
        if (array_key_exists($name, self::ARTICLES)) {
            return self::ARTICLES[$name];
        }

        return 'el';
    }

    /**
     * Stadium name with "en" preposition.
     * "en el Santiago Bernabéu" / "en la Rosaleda" / "en San Mamés" / "en El Sadar"
     * Returns '' for null/empty input (caller handles missing venue).
     */
    public static function withEn(?string $name): string
    {
        if ($name === null || $name === '') {
            return '';
        }
        if (app()->getLocale() === 'en') {
            return 'at ' . $name;
        }

        return match (self::article($name)) {
            null => 'en ' . $name,
            default => 'en el ' . $name,
        };
    }

    /**
     * Stadium name with "de" preposition.
     * "del Santiago Bernabéu" / "de San Mamés" / "de El Sadar"
     */
    public static function withDe(?string $name): string
    {
        if ($name === null || $name === '') {
            return '';
        }
        if (app()->getLocale() === 'en') {
            return 'of ' . $name;
        }

        return match (self::article($name)) {
            null => 'de ' . $name,
            default => 'del ' . $name,
        };
    }

    /**
     * Stadium name with definite article as nominative.
     * "el Santiago Bernabéu" / "San Mamés" / "El Sadar"
     * Article is lowercase; ucfirst at the call site when starting a sentence.
     */
    public static function withEl(?string $name): string
    {
        if ($name === null || $name === '') {
            return '';
        }
        if (app()->getLocale() === 'en') {
            return $name;
        }

        return match (self::article($name)) {
            null => $name,
            default => 'el ' . $name,
        };
    }
}
