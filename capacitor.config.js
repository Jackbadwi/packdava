const config = {
  appId: 'com.pakdava.app',
  appName: 'پک دوا',
  webDir: 'dist',
  server: {
    androidScheme: 'https',
    hostname: 'myhealthcare.ir',
  },
  plugins: {
    SplashScreen: {
      launchShowDuration: 2000,
      launchAutoHide: true,
      backgroundColor: '#1A7A4A',
      showSpinner: false,
    },
    StatusBar: {
      style: 'DARK',
      backgroundColor: '#1A7A4A',
    },
  },
};
module.exports = config;
