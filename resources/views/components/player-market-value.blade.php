{{--
    Renders a player's "market reference" value for list surfaces: the release
    clause in mandatory-clause countries (e.g. Spain), otherwise the market
    value. The swap decision and the formatted value both come from the
    GamePlayer model (single source of truth).

    On mixed-owner lists (explore, scouting) leave `tooltip` on so swapped values
    are flagged with an info icon. On uniform user-owned lists (your own squad)
    pass `:tooltip="false"` and relabel the column header to "Cláusula" instead.
--}}
@props(['player', 'game', 'tooltip' => true])

@if($tooltip && $player->displaysReleaseClauseAsMarketReference($game))
<span class="inline-flex items-center gap-1">{{ $player->marketReferenceValue($game) }}<x-info-icon :tooltip="__('transfers.market_reference_is_clause')" /></span>
@else
{{ $player->marketReferenceValue($game) }}
@endif
