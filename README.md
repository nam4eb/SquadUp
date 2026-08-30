# SquadUP

SquadUP is currently a runnable Flutter application backed by a Laravel API
under active phased development. Identity, social graph, activities, clans,
messaging and realtime presence use the real API. Social provider exchange and
the later roadmap phases still require implementation and production setup.

## Backend development

Docker provides PHP 8.3, PostgreSQL 16, Redis 7, Laravel Reverb and a queue
worker:

```bash
docker compose up -d --build
docker compose exec api php artisan migrate --force
docker compose exec api php artisan storage:link
docker compose exec api php artisan test
```

Migrations are a deliberate release/development step and are not run by the
API container startup command. This avoids concurrent migration races when
containers are restarted or scaled.

The API is available at `http://localhost:8000/api/v1`. Architecture and
implementation decisions are documented in [`docs/`](docs/).

A modern Flutter-based mobile application utilizing the latest mobile development technologies and tools for building responsive cross-platform applications.

## 📋 Prerequisites

- Flutter SDK compatible with Dart ^3.9.0
- Dart SDK
- Android Studio / VS Code with Flutter extensions
- Android SDK / Xcode (for iOS development)

## 🛠️ Installation

1. Install dependencies:
```bash
flutter pub get
```

2. Run the application:
```bash
flutter run -d chrome
```

The Flutter client defaults to the local API. For an Android emulator append
these options to `flutter run`:

```bash
--dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1 --dart-define=BROADCAST_AUTH_URL=http://10.0.2.2:8000/api/broadcasting/auth --dart-define=REVERB_HOST=10.0.2.2
```
`env.json` is only consumed when explicitly passed with
`--dart-define-from-file=env.json`; it is never loaded at runtime.

## 📁 Project Structure

```
flutter_app/
├── android/            # Android-specific configuration
├── ios/                # iOS-specific configuration
├── lib/
│   ├── core/           # Core utilities and services
│   │   └── utils/      # Utility classes
│   ├── presentation/   # UI screens and widgets
│   │   └── splash_screen/ # Splash screen implementation
│   ├── routes/         # Application routing
│   ├── theme/          # Theme configuration
│   ├── widgets/        # Reusable UI components
│   └── main.dart       # Application entry point
├── assets/             # Static assets (images, fonts, etc.)
├── pubspec.yaml        # Project dependencies and configuration
└── README.md           # Project documentation
```

## 🧩 Adding Routes

To add new routes to the application, update the `lib/routes/app_routes.dart` file:

```dart
import 'package:flutter/material.dart';
import 'package:package_name/presentation/home_screen/home_screen.dart';

class AppRoutes {
  static const String initial = '/';
  static const String home = '/home';

  static Map<String, WidgetBuilder> routes = {
    initial: (context) => const SplashScreen(),
    home: (context) => const HomeScreen(),
    // Add more routes as needed
  }
}
```

## 🎨 Theming

This project includes a comprehensive theming system with both light and dark themes:

```dart
// Access the current theme
ThemeData theme = Theme.of(context);

// Use theme colors
Color primaryColor = theme.colorScheme.primary;
```

The theme configuration includes:
- Color schemes for light and dark modes
- Typography styles
- Button themes
- Input decoration themes
- Card and dialog themes

## 📱 Responsive Design

The app is built with responsive design using the Sizer package:

```dart
// Example of responsive sizing
Container(
  width: 50.w, // 50% of screen width
  height: 20.h, // 20% of screen height
  child: Text('Responsive Container'),
)
```
## 📦 Deployment

Build the application for production:

```bash
# For Android
flutter build apk --release

# For iOS
flutter build ios --release
```

Before publishing to an app store, replace the placeholder Android/iOS bundle
identifiers, configure a private Android release signing key, connect a real
authentication/data backend, and add production secrets through a secure
configuration mechanism. Do not place private service keys in the client app.

## 🙏 Acknowledgments
- Built with [Rocket.new](https://rocket.new)
- Powered by [Flutter](https://flutter.dev) & [Dart](https://dart.dev)
- Styled with Material Design

Built with ❤️ on Rocket.new
