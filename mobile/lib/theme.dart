import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class AppColors {
  static const brand = Color(0xFF01B8C6);
  static const brandDark = Color(0xFF00A0AD);
  static const brand50 = Color(0xFFF2FDFF);
  static const brand100 = Color(0xFFD9F7FA);
  static const ink = Color(0xFF1D2B34);
  static const muted = Color(0xFF6A767D);
  static const accent = Color(0xFFF5A524);
  static const good = Color(0xFF16A34A);
  static const bad = Color(0xFFDC2626);
  static const border = Color(0xFFE5E7EB);
  static const surface = Color(0xFFF7F9FA);

  static const modules = {
    'speaking': Color(0xFF01B8C6),
    'writing': Color(0xFFF5A524),
    'reading': Color(0xFF6366F1),
    'listening': Color(0xFFEC4899),
  };
}

TextStyle grotesk({double size = 16, FontWeight weight = FontWeight.w700, Color? color}) =>
    GoogleFonts.spaceGrotesk(fontSize: size, fontWeight: weight, color: color ?? AppColors.ink);

ThemeData buildTheme() {
  final base = ThemeData(
    useMaterial3: true,
    colorScheme: ColorScheme.fromSeed(
      seedColor: AppColors.brand,
      primary: AppColors.brand,
      onPrimary: Colors.white,
      secondary: AppColors.accent,
      surface: Colors.white,
      error: AppColors.bad,
    ),
    scaffoldBackgroundColor: AppColors.surface,
  );
  final text = GoogleFonts.figtreeTextTheme(base.textTheme).apply(bodyColor: AppColors.ink, displayColor: AppColors.ink);

  return base.copyWith(
    textTheme: text,
    appBarTheme: AppBarTheme(
      backgroundColor: Colors.white,
      foregroundColor: AppColors.ink,
      elevation: 0,
      scrolledUnderElevation: 0.5,
      centerTitle: false,
      titleTextStyle: grotesk(size: 20),
    ),
    cardTheme: CardThemeData(
      color: Colors.white,
      elevation: 0,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18), side: const BorderSide(color: AppColors.border)),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: AppColors.brand,
        foregroundColor: Colors.white,
        minimumSize: const Size(64, 52),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        textStyle: GoogleFonts.figtree(fontSize: 16, fontWeight: FontWeight.w600),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: AppColors.ink,
        minimumSize: const Size(64, 52),
        side: const BorderSide(color: AppColors.border, width: 1.5),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        textStyle: GoogleFonts.figtree(fontSize: 16, fontWeight: FontWeight.w600),
      ),
    ),
    textButtonTheme: TextButtonThemeData(
      style: TextButton.styleFrom(foregroundColor: AppColors.brandDark, textStyle: GoogleFonts.figtree(fontWeight: FontWeight.w600)),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Colors.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: AppColors.border)),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: AppColors.border)),
      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: AppColors.brand, width: 2)),
    ),
    chipTheme: base.chipTheme.copyWith(side: BorderSide.none),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: Colors.white,
      indicatorColor: AppColors.brand100,
      labelTextStyle: WidgetStatePropertyAll(GoogleFonts.figtree(fontSize: 12, fontWeight: FontWeight.w600)),
    ),
    dividerTheme: const DividerThemeData(color: AppColors.border, space: 1),
  );
}
