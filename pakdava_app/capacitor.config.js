/** @type {import('@capacitor/cli').CapacitorConfig} */
const config = {
  appId:   'ir.pakdava.app',
  appName: 'پک دوا',
  webDir:  'dist',
  server: {
    androidScheme: 'https',
  },
  plugins: {
    SplashScreen: {
      launchShowDuration:     2000,
      launchAutoHide:         true,
      backgroundColor:        '#1A7A4A',
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
};

module.exports = config;
