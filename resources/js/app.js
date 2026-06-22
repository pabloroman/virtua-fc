import './bootstrap';

import Alpine from 'alpinejs';
import Collapse from '@alpinejs/collapse';
import Tooltip from '@ryangjchandler/alpine-tooltip';
import liveMatch from './live-match';
import lineupManager from './lineup';
import matchSummaryLineups from './match-summary-lineups';
import negotiationChat from './negotiation-chat';
import squadSelection from './squad-selection';
import squadOverview from './squad-overview';
import sortableTable from './sortable-table';
import tournamentSummary from './tournament-summary';
import seasonSummary from './season-summary';
import squadRegistration from './squad-registration';
import explore from './explore';
import seasonTicketEditor from './season-ticket-editor';

Alpine.plugin(Collapse);
Alpine.plugin(Tooltip);

Alpine.magic('fmt', () => (value) => {
    if (value === null || value === undefined || value === '') return '';
    return String(Math.round(Number(value))).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
});

Alpine.data('liveMatch', liveMatch);
Alpine.data('lineupManager', lineupManager);
Alpine.data('matchSummaryLineups', matchSummaryLineups);
Alpine.data('negotiationChat', negotiationChat);
Alpine.data('squadSelection', squadSelection);
Alpine.data('squadOverview', squadOverview);
Alpine.data('sortableTable', sortableTable);
Alpine.data('squadRegistration', squadRegistration);
Alpine.data('tournamentSummary', tournamentSummary);
Alpine.data('seasonSummary', seasonSummary);
Alpine.data('explore', explore);
Alpine.data('seasonTicketEditor', seasonTicketEditor);

window.Alpine = Alpine;

Alpine.start();
