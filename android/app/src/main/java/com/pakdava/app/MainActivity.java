package com.pakdava.app;

import android.os.Bundle;
import com.getcapacitor.BridgeActivity;
import com.google.firebase.FirebaseApp;

public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        
        // ✅ مقداردهی Firebase
        FirebaseApp.initializeApp(this);
        
        // ثبت پلاگین PushNotifications
        registerPlugin(com.capacitorjs.plugins.pushnotifications.PushNotificationsPlugin.class);
    }
}
