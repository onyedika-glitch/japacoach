# Japa Coach mobile app (Flutter)

Android app for IELTS practice on top of the Laravel API (`api/`, routes under `/api`, sign-in
with tokens via `/api/mobile/*`). Package: `com.japacoach.app`.

## Releases and over-the-air updates (Shorebird)

Releases go to the Play Store; afterwards, Dart-only changes ship as Shorebird **patches** that
phones download in the background and apply the next time Japa Coach is opened.

```bash
shorebird login                    # once per machine (already done on this machine)
scripts/shorebird.sh release       # new Play Store version → upload the .aab it prints
scripts/shorebird.sh patch         # OTA update for users on the latest release
```

- **Patch vs release.** Patches can change Dart code and assets only. Anything native needs a
  new release and a Play Store update: new packages with Android code, `AndroidManifest.xml`,
  Gradle files, the app icon, the splash screen, the Flutter version. Bump `version:` in
  `pubspec.yaml` for every release.
- **Same settings every time.** `scripts/shorebird.sh` reads `release.env` and passes the same
  `--dart-define`s to releases and patches. Never patch with different values.
- A patch only reaches users on the release it was built against. After a new Play Store
  release, patch that one.
- Builds made with plain `flutter build` (versions up to 1.0.4) cannot receive patches. The
  first Shorebird release is 1.0.5.

## Signing

`android/key.properties` points at the Play **upload key** in `~/japacoach-keys/` (gitignored,
never commit it). **Back up** that folder. If lost, Play support can reset the upload key, but it
takes days.

## release.env

Copy `release.env.example` to `release.env` (gitignored).

| Key | Where it comes from |
|---|---|
| `API_URL` | Optional. Defaults to `https://japacoach.com` |
| `GOOGLE_SERVER_CLIENT_ID` | Optional. The **web** OAuth client ID; the default is built in |
| `FIREBASE_*` | Firebase console → Project settings → General → Your apps (Android). Empty = push off |

## One-time setup outside the code

1. **Google sign-in:** in Google Cloud Console → Credentials, an **Android** OAuth client for
   `com.japacoach.app` with the upload key's SHA-1, and another with the SHA-1 from Play
   Console → App signing.
2. **Push notifications:** create a Firebase project (or add Firebase to the existing Google
   Cloud project `japa-511508`), add the Android app `com.japacoach.app`, and copy the values
   into `release.env`. On the server, set `FCM_SERVICE_ACCOUNT` (base64 of a Firebase service
   account JSON key) in `api/.env`. If Firebase is added to `japa-511508`, you can instead give
   the existing Google Play service account the **Firebase Cloud Messaging API Admin** role and
   leave `FCM_SERVICE_ACCOUNT` empty. Test from the website: Admin → Overview → "Send test push".
3. **Splash screen:** generated from `assets/splash-*.png` with
   `dart run flutter_native_splash:create` (settings at the bottom of `pubspec.yaml`).

## Developing

```bash
flutter run                                                   # against production API
flutter run --dart-define=API_URL=http://10.0.2.2:8000        # local API on the emulator
flutter analyze && flutter test
```

Never point a local API at the production database; use the throwaway test Postgres.
