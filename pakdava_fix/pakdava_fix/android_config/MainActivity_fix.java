package ir.pakdava.app;

import android.os.Bundle;
import com.getcapacitor.BridgeActivity;
import android.util.Log;

public class MainActivity extends BridgeActivity {

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        // Firebase را به‌صورت اختیاری init کن
        initFirebaseSafe();
        super.onCreate(savedInstanceState);
    }

    private void initFirebaseSafe() {
        try {
            // تلاش برای init Firebase
            com.google.firebase.FirebaseApp.initializeApp(this);
            Log.d("PakDava", "Firebase initialized");
        } catch (Exception e) {
            // google-services.json نیست — بدون crash ادامه بده
            Log.w("PakDava", "Firebase not initialized (push disabled): " + e.getMessage());
        }
    }
}
