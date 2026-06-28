package com.packdava.app;

import android.os.Bundle;
import android.util.Log;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {

    private static final String TAG = "PakDava";

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        // Firebase را ایمن init کن — اگر google-services.json نیست crash نشود
        tryInitFirebase();
        super.onCreate(savedInstanceState);
    }

    private void tryInitFirebase() {
        try {
            Class<?> firebaseApp = Class.forName("com.google.firebase.FirebaseApp");
            java.lang.reflect.Method initApp = firebaseApp.getMethod(
                "initializeApp",
                android.content.Context.class
            );
            initApp.invoke(null, this);
            Log.i(TAG, "✅ Firebase initialized");
        } catch (ClassNotFoundException e) {
            Log.w(TAG, "Firebase SDK not found — push notifications disabled");
        } catch (Exception e) {
            Log.w(TAG, "Firebase init skipped: " + e.getMessage());
        }
    }
}
