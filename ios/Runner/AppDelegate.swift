import Flutter
import UIKit
import GoogleMaps

@main
@objc class AppDelegate: FlutterAppDelegate {
  override func application(
    _ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
  ) -> Bool {
    let defines = Bundle.main.object(forInfoDictionaryKey: "SquadUpDartDefines") as? String ?? ""
    for entry in defines.split(separator: ",") {
      if let data = Data(base64Encoded: String(entry)),
         let value = String(data: data, encoding: .utf8),
         value.hasPrefix("GOOGLE_MAPS_API_KEY=") {
        let key = String(value.dropFirst("GOOGLE_MAPS_API_KEY=".count))
        if !key.isEmpty { GMSServices.provideAPIKey(key) }
      }
    }
    GeneratedPluginRegistrant.register(with: self)
    return super.application(application, didFinishLaunchingWithOptions: launchOptions)
  }
}
