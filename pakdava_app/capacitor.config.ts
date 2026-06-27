import { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId:   'ir.pakdava.app',
  appName: 'پک دوا',
  webDir:  'dist',
  server: {
    // در حالت development به سرور PHP وصل شو
    // در production از dist استفاده می‌شود
    androidScheme: 'https',
    // url: 'https://your-server.ir',  // uncomment for live reload
  },
  plugins: {
    SplashScreen: {
      launchShowDuration:     2000,
      launchAutoHide:         true,
      backgroundColor:        '#1A7A4A',
      androidSplashResourceName: 'splash',
      showSpinner:            false,
    },
    PushNotifications: {
      presentationOptions: ['badge', 'sound', 'alert'],
    },
    StatusBar: {
      style:           'DARK',
      backgroundColor: '#1A7A4A',
    },
  },
  android: {
    allowMixedContent:     false,
    captureInput:          true,
    webContentsDebuggingEnabled: false,
  },
};

export default config;
