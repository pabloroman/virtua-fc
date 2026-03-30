import './bootstrap';

import Alpine from 'alpinejs';
import Collapse from '@alpinejs/collapse';
import Tooltip from '@ryangjchandler/alpine-tooltip';
import liveMatch from './live-match';
import lineupManager from './lineup';
import negotiationChat from './negotiation-chat';
import squadSelection from './squad-selection';
import tournamentSummary from './tournament-summary';
import seasonSummary from './season-summary';

Alpine.plugin(Collapse);
Alpine.plugin(Tooltip);

Alpine.data('liveMatch', liveMatch);
Alpine.data('lineupManager', lineupManager);
Alpine.data('negotiationChat', negotiationChat);
Alpine.data('squadSelection', squadSelection);
Alpine.data('tournamentSummary', tournamentSummary);
Alpine.data('seasonSummary', seasonSummary);

window.Alpine = Alpine;

Alpine.start();
