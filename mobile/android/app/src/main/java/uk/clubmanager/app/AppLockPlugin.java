package uk.clubmanager.app;

import android.annotation.TargetApi;
import android.app.KeyguardManager;
import android.content.Context;
import android.content.SharedPreferences;
import android.hardware.biometrics.BiometricManager;
import android.hardware.biometrics.BiometricPrompt;
import android.os.Build;
import android.os.CancellationSignal;
import android.security.keystore.KeyGenParameterSpec;
import android.security.keystore.KeyPermanentlyInvalidatedException;
import android.security.keystore.KeyProperties;
import android.util.Base64;
import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import java.nio.charset.StandardCharsets;
import java.security.KeyStore;
import javax.crypto.Cipher;
import javax.crypto.KeyGenerator;
import javax.crypto.SecretKey;
import javax.crypto.spec.GCMParameterSpec;

/**
 * Fingerprint, face or the screen lock PIN, for the optional app lock (resources/js/Support/nativeApp.js).
 * Uses the system prompt, so it needs Android 10 or later; older phones simply don't offer the lock.
 *
 * Also fingerprint / face sign-in: the sign-in key is encrypted with an Android Keystore key that only a strong
 * biometric check can unlock, and that Android destroys when a new fingerprint or face is enrolled. Android 11 or later.
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

    private static final String KEY_ALIAS = "cm_sign_in";
    private static final String PREFS = "cm_sign_in";

    private SharedPreferences prefs() {
        return getContext().getSharedPreferences(PREFS, Context.MODE_PRIVATE);
    }

    @PluginMethod
    public void signInAvailable(PluginCall call) {
        boolean available = false;
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            BiometricManager manager = getContext().getSystemService(BiometricManager.class);
            available = manager != null && manager.canAuthenticate(BiometricManager.Authenticators.BIOMETRIC_STRONG) == BiometricManager.BIOMETRIC_SUCCESS;
        }
        JSObject result = new JSObject();
        result.put("available", available);
        result.put("kind", "fingerprint");
        call.resolve(result);
    }

    @PluginMethod
    public void hasSignInKey(PluginCall call) {
        JSObject result = new JSObject();
        result.put("exists", prefs().contains("data"));
        call.resolve(result);
    }

    @PluginMethod
    public void saveSignInKey(PluginCall call) {
        String key = call.getString("key");
        if (key == null || Build.VERSION.SDK_INT < Build.VERSION_CODES.R) {
            call.reject("Fingerprint sign-in needs Android 11 or later");
            return;
        }
        try {
            // A fresh Keystore key each time it is turned on; any earlier key and its data go first.
            forget();
            Cipher cipher = Cipher.getInstance("AES/GCM/NoPadding");
            cipher.init(Cipher.ENCRYPT_MODE, newKey());
            prompt(call, cipher, call.getString("reason", "Turn on fingerprint sign-in"), unlocked -> {
                byte[] data = unlocked.doFinal(key.getBytes(StandardCharsets.UTF_8));
                prefs().edit()
                    .putString("data", Base64.encodeToString(data, Base64.NO_WRAP))
                    .putString("iv", Base64.encodeToString(unlocked.getIV(), Base64.NO_WRAP))
                    .apply();
                call.resolve();
            });
        } catch (Exception e) {
            call.reject("The key was not saved: " + e.getMessage());
        }
    }

    @PluginMethod
    public void readSignInKey(PluginCall call) {
        String data = prefs().getString("data", null);
        String iv = prefs().getString("iv", null);
        if (data == null || iv == null || Build.VERSION.SDK_INT < Build.VERSION_CODES.R) {
            call.reject("No sign-in key on this phone", "NOT_FOUND");
            return;
        }
        try {
            KeyStore store = KeyStore.getInstance("AndroidKeyStore");
            store.load(null);
            SecretKey secret = (SecretKey) store.getKey(KEY_ALIAS, null);
            if (secret == null) {
                forget();
                call.reject("No sign-in key on this phone", "NOT_FOUND");
                return;
            }
            Cipher cipher = Cipher.getInstance("AES/GCM/NoPadding");
            cipher.init(Cipher.DECRYPT_MODE, secret, new GCMParameterSpec(128, Base64.decode(iv, Base64.NO_WRAP)));
            prompt(call, cipher, call.getString("reason", "Sign in to Club Manager"), unlocked -> {
                JSObject result = new JSObject();
                result.put("key", new String(unlocked.doFinal(Base64.decode(data, Base64.NO_WRAP)), StandardCharsets.UTF_8));
                call.resolve(result);
            });
        } catch (KeyPermanentlyInvalidatedException e) {
            // A fingerprint or face was added since: the old key no longer opens, so it is dropped.
            forget();
            call.reject("No sign-in key on this phone", "NOT_FOUND");
        } catch (Exception e) {
            call.reject("Not signed in: " + e.getMessage(), "CANCELLED");
        }
    }

    @PluginMethod
    public void deleteSignInKey(PluginCall call) {
        forget();
        call.resolve();
    }

    private void forget() {
        prefs().edit().clear().apply();
        try {
            KeyStore store = KeyStore.getInstance("AndroidKeyStore");
            store.load(null);
            store.deleteEntry(KEY_ALIAS);
        } catch (Exception ignored) {
            // Nothing stored.
        }
    }

    @TargetApi(Build.VERSION_CODES.R)
    private SecretKey newKey() throws Exception {
        KeyGenerator generator = KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES, "AndroidKeyStore");
        generator.init(new KeyGenParameterSpec.Builder(KEY_ALIAS, KeyProperties.PURPOSE_ENCRYPT | KeyProperties.PURPOSE_DECRYPT)
            .setBlockModes(KeyProperties.BLOCK_MODE_GCM)
            .setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE)
            .setUserAuthenticationRequired(true)
            .setUserAuthenticationParameters(0, KeyProperties.AUTH_BIOMETRIC_STRONG)
            .setInvalidatedByBiometricEnrollment(true)
            .build());
        return generator.generateKey();
    }

    private interface Unlocked {
        void use(Cipher cipher) throws Exception;
    }

    /** The system fingerprint / face prompt, tied to the cipher so the Keystore key only works after a match. */
    @TargetApi(Build.VERSION_CODES.R)
    private void prompt(PluginCall call, Cipher cipher, String title, Unlocked then) {
        new BiometricPrompt.Builder(getContext())
            .setTitle(title)
            .setAllowedAuthenticators(BiometricManager.Authenticators.BIOMETRIC_STRONG)
            .setNegativeButton("Cancel", getContext().getMainExecutor(), (dialog, which) -> call.reject("Cancelled", "CANCELLED"))
            .build()
            .authenticate(new BiometricPrompt.CryptoObject(cipher), new CancellationSignal(), getContext().getMainExecutor(), new BiometricPrompt.AuthenticationCallback() {
                @Override
                public void onAuthenticationSucceeded(BiometricPrompt.AuthenticationResult result) {
                    try {
                        then.use(result.getCryptoObject().getCipher());
                    } catch (Exception e) {
                        call.reject("Not signed in: " + e.getMessage(), "CANCELLED");
                    }
                }

                @Override
                public void onAuthenticationError(int errorCode, CharSequence message) {
                    call.reject(String.valueOf(message), "CANCELLED");
                }
            });
    }
}
