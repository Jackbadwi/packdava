package com.pakdava.app;

import android.os.Bundle;
import android.util.Log;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    private static final String TAG = "PakDava";

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        tryInitFirebase();
        super.onCreate(savedInstanceState);
    }

    private void tryInitFirebase() {
        try {
            com.google.firebase.FirebaseApp.initializeApp(this);
            Log.i(TAG, "✅ Firebase initialized successfully");
        } catch (Exception e) {
            Log.w(TAG, "⚠️ Firebase init skipped: " + e.getMessage());
        }
    }
}
