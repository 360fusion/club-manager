import Capacitor
import LocalAuthentication
import Security

/// The app's root view: Capacitor's, plus the plugins that live in this project.
class AppViewController: CAPBridgeViewController {
    override open func capacitorDidLoad() {
        bridge?.registerPluginInstance(AppLockPlugin())
    }
}

/// Face ID, Touch ID or the passcode, for the optional app lock (resources/js/Support/nativeApp.js); and the sign-in key
/// for Face ID / Touch ID sign-in, kept in the Keychain so only this phone's enrolled face or finger can read it.
@objc(AppLockPlugin)
public class AppLockPlugin: CAPPlugin, CAPBridgedPlugin {
    public let identifier = "AppLockPlugin"
    public let jsName = "AppLock"
    public let pluginMethods: [CAPPluginMethod] = [
        CAPPluginMethod(name: "isAvailable", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "authenticate", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "signInAvailable", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "hasSignInKey", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "saveSignInKey", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "readSignInKey", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "deleteSignInKey", returnType: CAPPluginReturnPromise),
    ]

    private func keyQuery() -> [String: Any] {
        return [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrService as String: "uk.clubmanager.app.sign-in",
            kSecAttrAccount as String: "device-sign-in",
        ]
    }

    @objc func isAvailable(_ call: CAPPluginCall) {
        let context = LAContext()
        // Face ID or Touch ID, falling back to the phone's passcode; biometryType is only set once this is asked.
        let available = context.canEvaluatePolicy(.deviceOwnerAuthentication, error: nil)
        _ = context.canEvaluatePolicy(.deviceOwnerAuthenticationWithBiometrics, error: nil)

        let kind: String
        switch context.biometryType {
        case .faceID: kind = "face"
        case .touchID: kind = "fingerprint"
        default: kind = "passcode"
        }

        call.resolve(["available": available, "kind": kind])
    }

    @objc func authenticate(_ call: CAPPluginCall) {
        let reason = call.getString("reason") ?? "Unlock Club Manager"

        LAContext().evaluatePolicy(.deviceOwnerAuthentication, localizedReason: reason) { success, error in
            if success {
                call.resolve()
            } else {
                call.reject(error?.localizedDescription ?? "Not unlocked")
            }
        }
    }

    /// Sign-in needs Face ID or Touch ID itself (not the passcode), so it is only offered when one is set up.
    @objc func signInAvailable(_ call: CAPPluginCall) {
        let context = LAContext()
        let available = context.canEvaluatePolicy(.deviceOwnerAuthenticationWithBiometrics, error: nil)
        call.resolve(["available": available, "kind": context.biometryType == .touchID ? "fingerprint" : "face"])
    }

    /// Whether a key is stored, without asking for Face ID. A key whose face or finger has since changed is gone.
    @objc func hasSignInKey(_ call: CAPPluginCall) {
        let context = LAContext()
        context.interactionNotAllowed = true
        var query = keyQuery()
        query[kSecUseAuthenticationContext as String] = context
        let status = SecItemCopyMatching(query as CFDictionary, nil)
        call.resolve(["exists": status == errSecSuccess || status == errSecInteractionNotAllowed])
    }

    /// Stored so that only the faces or fingers enrolled right now can read it, on this phone only, never backed up.
    @objc func saveSignInKey(_ call: CAPPluginCall) {
        guard let key = call.getString("key"), let data = key.data(using: .utf8) else {
            call.reject("No key")
            return
        }
        guard let access = SecAccessControlCreateWithFlags(nil, kSecAttrAccessibleWhenPasscodeSetThisDeviceOnly, .biometryCurrentSet, nil) else {
            call.reject("Face ID is not set up")
            return
        }

        SecItemDelete(keyQuery() as CFDictionary)
        var query = keyQuery()
        query[kSecValueData as String] = data
        query[kSecAttrAccessControl as String] = access
        let status = SecItemAdd(query as CFDictionary, nil)
        status == errSecSuccess ? call.resolve() : call.reject("The key was not saved (\(status))")
    }

    /// Shows Face ID or Touch ID; only a match hands the key back. The check is made here first and the same context then
    /// opens the Keychain item, so there is one prompt, and the face or finger is asked for even where the Keychain itself
    /// would not ask (the simulator).
    @objc func readSignInKey(_ call: CAPPluginCall) {
        let context = LAContext()
        context.localizedCancelTitle = "Use password"
        let reason = call.getString("reason") ?? "Sign in to Club Manager"

        context.evaluatePolicy(.deviceOwnerAuthenticationWithBiometrics, localizedReason: reason) { [weak self] success, _ in
            guard success, let self = self else {
                call.reject("Not signed in", "CANCELLED")
                return
            }

            var query = self.keyQuery()
            query[kSecReturnData as String] = true
            query[kSecMatchLimit as String] = kSecMatchLimitOne
            query[kSecUseAuthenticationContext as String] = context

            var item: CFTypeRef?
            let status = SecItemCopyMatching(query as CFDictionary, &item)
            if status == errSecSuccess, let data = item as? Data, let key = String(data: data, encoding: .utf8) {
                call.resolve(["key": key])
            } else if status == errSecItemNotFound {
                call.reject("No sign-in key on this phone", "NOT_FOUND")
            } else {
                call.reject("Not signed in (\(status))", "CANCELLED")
            }
        }
    }

    @objc func deleteSignInKey(_ call: CAPPluginCall) {
        SecItemDelete(keyQuery() as CFDictionary)
        call.resolve()
    }
}
