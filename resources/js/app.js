import './bootstrap';

import Alpine from 'alpinejs';
import Tooltip from '@ryangjchandler/alpine-tooltip';
import liveMatch from './live-match';
import lineupManager from './lineup';
import negotiationChat from './negotiation-chat';
import squadSelection from './squad-selection';
import * as canvasImage from './modules/canvas-image';

Alpine.plugin(Tooltip);

Alpine.data('liveMatch', liveMatch);
Alpine.data('lineupManager', lineupManager);
Alpine.data('negotiationChat', negotiationChat);
Alpine.data('squadSelection', squadSelection);

window.canvasImage = canvasImage;
window.Alpine = Alpine;

Alpine.start();
