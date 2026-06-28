const config = {
  appId:   'com.packdava.app',
  appName: 'پک دوا',
  webDir:  'dist',
  server: {
    androidScheme: 'https',
    hostname:      'myhealthcare.ir',
  },
  plugins: {
    SplashScreen: {
      launchShowDuration: 2000,
      launchAutoHide:     true,
      backgroundColor:    '#1A7A4A',
      showSpinner:        false,
    },
    StatusBar: {
      style:           'DARK',
      backgroundColor: '#1A7A4A',
    },
    // PushNotifications حذف شد — نیاز به google-services.json دارد
    // بعد از اضافه کردن Firebase این را uncomment کنید:
    // PushNotifications: {
    //   presentationOptions: ['badge', 'sound', 'alert'],
    // },
  },
};

module.exports = config;
