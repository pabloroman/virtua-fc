import './bootstrap';

import Alpine from 'alpinejs';
import Tooltip from '@ryangjchandler/alpine-tooltip';
import liveMatch from './live-match';

Alpine.plugin(Tooltip);

Alpine.data('liveMatch', liveMatch);

window.Alpine = Alpine;

Alpine.start();
