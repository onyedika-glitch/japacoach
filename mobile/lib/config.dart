/// Build-time settings. Override with --dart-define, for example:
/// flutter run --dart-define=API_URL=http://10.0.2.2:8000
class AppConfig {
  static const apiUrl = String.fromEnvironment('API_URL', defaultValue: 'https://japacoach.com');

  /// The Google OAuth *web* client ID (the same one the website uses; not a secret). Google sign-in is hidden when empty.
  static const googleServerClientId = String.fromEnvironment(
    'GOOGLE_SERVER_CLIENT_ID',
    defaultValue: '671451700757-ekv0pamep93sdablqohkqada5385mis2.apps.googleusercontent.com',
  );

  static const websiteUrl = 'https://japacoach.com';
  static const supportEmail = 'hello@japacoach.com';

  /// Turns API paths like /storage/x.mp3 into absolute URLs.
  static String absolute(String url) => url.startsWith('http') ? url : '$apiUrl$url';
}
