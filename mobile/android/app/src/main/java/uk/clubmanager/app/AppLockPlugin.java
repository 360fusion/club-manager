package uk.clubmanager.app;

import android.app.KeyguardManager;
import android.content.Context;
import android.hardware.biometrics.BiometricManager;
import android.hardware.biometrics.BiometricPrompt;
import android.os.Build;
import android.os.CancellationSignal;
import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;

/**
 * Fingerprint, face or the screen lock PIN, for the optional app lock (resources/js/Support/nativeApp.js).
 * Uses the system prompt, so it needs Android 10 or later; older phones simply don't offer the lock.
 */
@CapacitorPlugin(name = "AppLock")
public class AppLockPlugin extends Plugin {

    @PluginMethod
    public void isAvailable(PluginCall call) {
        KeyguardManager keyguard = (KeyguardManager) getContext().getSystemService(Context.KEYGUARD_SERVICE);
        JSObject result = new JSObject();
        result.put("available", Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q && keyguard != null && keyguard.isDeviceSecure());
        result.put("kind", "fingerprint");
        call.resolve(result);
    }

    @PluginMethod
    @SuppressWarnings("deprecation")
    public void authenticate(PluginCall call) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.Q) {
            call.reject("The app lock needs Android 10 or later");
            return;
        }

        BiometricPrompt.Builder builder = new BiometricPrompt.Builder(getContext()).setTitle(call.getString("reason", "Unlock Club Manager"));
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            builder.setAllowedAuthenticators(BiometricManager.Authenticators.BIOMETRIC_WEAK | BiometricManager.Authenticators.DEVICE_CREDENTIAL);
        } else {
            builder.setDeviceCredentialAllowed(true);
        }

        builder.build().authenticate(new CancellationSignal(), getContext().getMainExecutor(), new BiometricPrompt.AuthenticationCallback() {
            @Override
            public void onAuthenticationSucceeded(BiometricPrompt.AuthenticationResult result) {
                call.resolve();
            }

            @Override
            public void onAuthenticationError(int errorCode, CharSequence message) {
                call.reject(String.valueOf(message));
            }
        });
    }
}
