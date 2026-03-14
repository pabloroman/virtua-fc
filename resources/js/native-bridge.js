import { Capacitor } from '@capacitor/core';

export const isNativeApp = Capacitor.isNativePlatform();

/**
 * Register for push notifications and send the device token to the server.
 * Only runs inside the native Capacitor shell — no-op in browsers.
 */
export async function registerPushNotifications() {
    if (!isNativeApp) return;

    const { PushNotifications } = await import('@capacitor/push-notifications');

    const permission = await PushNotifications.requestPermissions();
    if (permission.receive !== 'granted') return;

    await PushNotifications.register();

    PushNotifications.addListener('registration', async (token) => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (!csrfToken) return;

        try {
            await fetch('/api/device-token', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    token: token.value,
                    platform: Capacitor.getPlatform(), // 'ios' | 'android'
                }),
            });
        } catch (e) {
            console.warn('Failed to register device token:', e);
        }
    });

    PushNotifications.addListener('registrationError', (error) => {
        console.warn('Push registration failed:', error);
    });

    // Handle notification tap — navigate to the relevant screen
    PushNotifications.addListener('pushNotificationActionPerformed', (notification) => {
        const data = notification.notification.data;
        if (data?.url) {
            window.location.href = data.url;
        }
    });
}

/**
 * Trigger haptic feedback. Falls back silently in browsers.
 */
export async function triggerHaptic(style = 'Medium') {
    if (!isNativeApp) return;

    const { Haptics, ImpactStyle } = await import('@capacitor/haptics');
    await Haptics.impact({ style: ImpactStyle[style] || ImpactStyle.Medium });
}

/**
 * Configure the native status bar appearance.
 */
export async function configureStatusBar() {
    if (!isNativeApp) return;

    const { StatusBar, Style } = await import('@capacitor/status-bar');
    await StatusBar.setStyle({ style: Style.Dark });
    await StatusBar.setBackgroundColor({ color: '#0f172a' });
}

/**
 * Handle the Android hardware back button.
 */
export async function configureBackButton() {
    if (!isNativeApp) return;

    const { App } = await import('@capacitor/app');
    App.addListener('backButton', ({ canGoBack }) => {
        if (canGoBack) {
            window.history.back();
        } else {
            App.exitApp();
        }
    });
}
