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

    /**
     * A status write. Legal inside a create() (the initial status); a
     * violation inside an update(), which must go through transitionTo() /
     * transitionAll() so the move is checked and resolved_at is stamped.
     */
    private const STATUS_WRITE = '/[\'"]status[\'"]\s*=>\s*TransferOffer::STATUS_/';

    /**
     * How far back from a status write to look for the update()/create()
     * that owns it. Creates list a dozen columns before 'status', so this is
     * generous; an update that writes status is always a few lines long.
     */
    private const OWNER_LOOKBACK_LINES = 20;

    public function test_offer_state_predicates_live_only_on_the_model(): void
    {
        $violations = $this->scan(function (array $lines, int $index) {
            foreach (self::FORBIDDEN as $pattern => $reason) {
                if (preg_match($pattern, $lines[$index])) {
                    return $reason;
                }
            }

            return null;
        });

        $this->assertSame(
            [],
            $violations,
            "Offer-state predicates must be expressed through TransferOffer scopes:\n  " . implode("\n  ", $violations),
        );
    }

    public function test_offer_status_is_only_written_through_transition_helpers(): void
    {
        $violations = $this->scan(function (array $lines, int $index) {
            if (! preg_match(self::STATUS_WRITE, $lines[$index])) {
                return null;
            }

            $window = implode("\n", array_slice($lines, max(0, $index - self::OWNER_LOOKBACK_LINES), self::OWNER_LOOKBACK_LINES + 1));

            if (str_contains($window, '::create(') || str_contains($window, 'create([')) {
                return null;
            }

            return str_contains($window, '->update(')
                ? 'status written via update(); use $offer->transitionTo() or TransferOffer::transitionAll()'
                : 'status written outside create(); use $offer->transitionTo() or TransferOffer::transitionAll()';
        });

        $this->assertSame(
            [],
            $violations,
            "Offer status must only change through TransferOffer::transitionTo()/transitionAll():\n  " . implode("\n  ", $violations),
        );
    }

    /**
     * Run $rule over every line of every app/ file that mentions TransferOffer
     * (other models such as ManagerJobOffer legitimately have an offer_type
     * and a status), excluding the model itself. $rule returns a reason string
     * for a violation or null.
     *
     * @param  callable(list<string>, int): ?string  $rule
     * @return list<string>
     */
    private function scan(callable $rule): array
    {
        $appDir = dirname(__DIR__, 3) . '/app';
        $model = realpath($appDir . '/Models/TransferOffer.php');

        $violations = [];

        $files = (new Finder())->files()->in($appDir)->name('*.php');
        foreach ($files as $file) {
            if ($file->getRealPath() === $model) {
                continue;
            }

            $contents = $file->getContents();
            if (! str_contains($contents, 'TransferOffer')) {
                continue;
            }

            $lines = explode("\n", $contents);
            foreach ($lines as $index => $line) {
                $reason = $rule($lines, $index);
                if ($reason !== null) {
                    $violations[] = sprintf('%s:%d — %s', $file->getRelativePathname(), $index + 1, $reason);
                }
            }
        }

        return $violations;
    }
}
