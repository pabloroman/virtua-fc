import './bootstrap';

import Alpine from 'alpinejs';
import Tooltip from '@ryangjchandler/alpine-tooltip';

Alpine.plugin(Tooltip);

window.Alpine = Alpine;

Alpine.start();
