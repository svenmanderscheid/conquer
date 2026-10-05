package com.unionofkingdoms.app;

import android.content.res.Configuration;
import android.os.Bundle;
import android.view.View;
import android.view.ViewTreeObserver;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowCompat;
import androidx.core.view.WindowInsetsCompat;
import androidx.core.view.WindowInsetsControllerCompat;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    private boolean keyboardWasVisible;
    private final ViewTreeObserver.OnGlobalLayoutListener keyboardListener = () -> {
        WindowInsetsCompat insets = ViewCompat.getRootWindowInsets(getWindow().getDecorView());
        if (insets == null) return;
        boolean keyboardVisible = insets.isVisible(WindowInsetsCompat.Type.ime());
        if (keyboardWasVisible && !keyboardVisible) scheduleImmersiveNavigation();
        keyboardWasVisible = keyboardVisible;
    };

    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        // Observe keyboard changes without replacing Capacitor's safe-area listener.
        getWindow().getDecorView().getViewTreeObserver().addOnGlobalLayoutListener(keyboardListener);
        scheduleImmersiveNavigation();
    }

    @Override
    public void onResume() {
        super.onResume();
        scheduleImmersiveNavigation();
    }

    @Override
    public void onWindowFocusChanged(boolean hasFocus) {
        super.onWindowFocusChanged(hasFocus);
        if (hasFocus) scheduleImmersiveNavigation();
    }

    @Override
    public void onConfigurationChanged(Configuration newConfig) {
        super.onConfigurationChanged(newConfig);
        scheduleImmersiveNavigation();
    }

    private void scheduleImmersiveNavigation() {
        View decor = getWindow().getDecorView();
        decor.post(() -> {
            if (isFinishing() || isDestroyed() || !decor.hasWindowFocus()) return;
            WindowInsetsCompat insets = ViewCompat.getRootWindowInsets(decor);
            // Android keeps its keyboard navigation available while entering text.
            if (insets != null && insets.isVisible(WindowInsetsCompat.Type.ime())) return;
            WindowInsetsControllerCompat controller = WindowCompat.getInsetsController(getWindow(), decor);
            controller.setSystemBarsBehavior(WindowInsetsControllerCompat.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE);
            controller.hide(WindowInsetsCompat.Type.navigationBars());
        });
    }

    @Override
    public void onDestroy() {
        getWindow().getDecorView().getViewTreeObserver().removeOnGlobalLayoutListener(keyboardListener);
        super.onDestroy();
    }
}
