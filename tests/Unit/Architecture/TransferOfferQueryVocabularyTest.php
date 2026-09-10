<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Offer state has one vocabulary: the scopes on App\Models\TransferOffer.
 *
 * Before it existed, "an agreed pre-contract", "an agreed incoming deal for
 * the user" and "a player leaving the user's club" were each spelled out as
 * inline where() chains at a dozen sites with subtly different status sets
 * and type allowlists, and the offering_team_id guard that keeps a loaned-in
 * player's own deal off the departures list was present at some of them and
 * missing at others. That drift is where #1364's direction-blind helpers and
 * #1366's forgotten AI lock came from.
 *
 * This test keeps the vocabulary closed: any query predicate on offer_type,
 * direction or triggered_release_clause outside the model is a new copy of
 * a definition that already exists (or should). Add a scope instead.
 */
class TransferOfferQueryVocabularyTest extends TestCase
{
    private const FORBIDDEN = [
        '/->\s*(?:or)?where(?:In|NotIn)?\(\s*[\'"](?:offer_type|direction|triggered_release_clause)[\'"]/' =>
            'inline offer-state predicate; use a TransferOffer scope (incoming(), ofType(), preContract(), locksPlayer(), …)',
    ];

    public function test_offer_state_predicates_live_only_on_the_model(): void
    {
        $appDir = dirname(__DIR__, 3) . '/app';
        $model = $appDir . '/Models/TransferOffer.php';

        $violations = [];

        $files = (new Finder())->files()->in($appDir)->name('*.php');
        foreach ($files as $file) {
            $path = $file->getRealPath();
            if ($path === realpath($model)) {
                continue;
            }

            $contents = $file->getContents();

            // Only files that talk about transfer offers are in scope — other
            // models (ManagerJobOffer) legitimately have an offer_type column.
            if (! str_contains($contents, 'TransferOffer')) {
                continue;
            }

            foreach (explode("\n", $contents) as $index => $line) {
                foreach (self::FORBIDDEN as $pattern => $reason) {
                    if (preg_match($pattern, $line)) {
                        $violations[] = sprintf('%s:%d — %s', $file->getRelativePathname(), $index + 1, $reason);
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Offer-state predicates must be expressed through TransferOffer scopes:\n  " . implode("\n  ", $violations),
        );
    }
}
