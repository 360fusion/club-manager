import Capacitor
import LocalAuthentication

/// The app's root view: Capacitor's, plus the plugins that live in this project.
class AppViewController: CAPBridgeViewController {
    override open func capacitorDidLoad() {
        bridge?.registerPluginInstance(AppLockPlugin())
    }
}

/// Face ID, Touch ID or the passcode, for the optional app lock (resources/js/Support/nativeApp.js).
@objc(AppLockPlugin)
public class AppLockPlugin: CAPPlugin, CAPBridgedPlugin {
    public let identifier = "AppLockPlugin"
    public let jsName = "AppLock"
    public let pluginMethods: [CAPPluginMethod] = [
        CAPPluginMethod(name: "isAvailable", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "authenticate", returnType: CAPPluginReturnPromise),
    ]

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
}
