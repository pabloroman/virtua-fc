import './bootstrap';

import Alpine from 'alpinejs';
import Tooltip from '@ryangjchandler/alpine-tooltip';
import liveMatch from './live-match';
import lineupManager from './lineup';
import shareCard from './share-card';

Alpine.plugin(Tooltip);

Alpine.data('liveMatch', liveMatch);
Alpine.data('lineupManager', lineupManager);
Alpine.data('shareCard', shareCard);

window.Alpine = Alpine;

Alpine.start();
