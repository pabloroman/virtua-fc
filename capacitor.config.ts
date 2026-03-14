import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.virtuafc.app',
  appName: 'VirtuaFC',
  webDir: 'public',

  // Load from remote server (SSR app — not a bundled SPA)
  server: {
    url: 'https://virtuafc.com',
    cleartext: false,
  },

  plugins: {
    SplashScreen: {
      launchShowDuration: 2000,
      launchAutoHide: true,
      backgroundColor: '#0f172a',
      showSpinner: false,
      launchFadeOutDuration: 500,
    },
    StatusBar: {
      style: 'DARK',
      backgroundColor: '#0f172a',
    },
    PushNotifications: {
      presentationOptions: ['badge', 'sound', 'alert'],
    },
  },

  ios: {
    scheme: 'VirtuaFC',
    contentInset: 'automatic',
  },

  android: {
    allowMixedContent: false,
    backgroundColor: '#0f172a',
  },
};

export default config;
