export 'maps_loader_native.dart'
    if (dart.library.js_interop) 'maps_loader_web.dart';

const googleMapsApiKey = String.fromEnvironment('GOOGLE_MAPS_API_KEY');
