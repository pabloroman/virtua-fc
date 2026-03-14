import './bootstrap';

import Alpine from 'alpinejs';
import Tooltip from '@ryangjchandler/alpine-tooltip';
import liveMatch from './live-match';
import lineupManager from './lineup';
import { isNativeApp, registerPushNotifications, configureStatusBar, configureBackButton } from './native-bridge';

Alpine.plugin(Tooltip);

Alpine.data('liveMatch', liveMatch);
Alpine.data('lineupManager', lineupManager);

window.Alpine = Alpine;

Alpine.start();

// Native app setup (no-op in browsers)
if (isNativeApp) {
    configureStatusBar();
    configureBackButton();
    registerPushNotifications();
}
