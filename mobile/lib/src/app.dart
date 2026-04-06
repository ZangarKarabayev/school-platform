import 'package:flutter/material.dart';

import 'core/config/app_config.dart';
import 'core/storage/session_storage.dart';
import 'features/auth/data/auth_repository.dart';
import 'features/auth/presentation/auth_controller.dart';
import 'features/auth/presentation/auth_gate.dart';

class SchoolPlatformApp extends StatefulWidget {
  const SchoolPlatformApp({super.key});

  @override
  State<SchoolPlatformApp> createState() => _SchoolPlatformAppState();
}

class _SchoolPlatformAppState extends State<SchoolPlatformApp> {
  late final AuthController _authController;

  @override
  void initState() {
    super.initState();
    _authController = AuthController(
      repository: AuthRepository(
        baseUrl: AppConfig.apiBaseUrl,
        appVersion: AppConfig.appVersion,
      ),
      sessionStorage: SessionStorage(),
    )..loadProfile();
  }

  @override
  void dispose() {
    _authController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    const primary = Color(0xFF2876DD);
    const surface = Color(0xFFFFFFFF);
    const surfaceSoft = Color(0xFFF5F8FD);
    const line = Color(0xFFD4DDEA);
    const text = Color(0xFF17253C);

    final colorScheme = ColorScheme.fromSeed(
      seedColor: primary,
      brightness: Brightness.light,
      primary: primary,
      surface: surface,
    );

    return MaterialApp(
      title: 'AltynAs',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: colorScheme,
        scaffoldBackgroundColor: const Color(0xFFE7EDF6),
        useMaterial3: true,
        cardTheme: CardThemeData(
          color: surface,
          elevation: 0,
          shape: RoundedRectangleBorder(
            side: const BorderSide(color: line),
            borderRadius: BorderRadius.circular(20),
          ),
        ),
        appBarTheme: const AppBarTheme(
          backgroundColor: surface,
          foregroundColor: text,
          elevation: 0,
          surfaceTintColor: Colors.transparent,
        ),
        inputDecorationTheme: InputDecorationTheme(
          filled: true,
          fillColor: surface,
          labelStyle: const TextStyle(color: Color(0xFF5D6F88)),
          hintStyle: const TextStyle(color: Color(0xFF8A9AB0)),
          prefixIconColor: primary,
          suffixIconColor: const Color(0xFF5D6F88),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: line),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: line),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: primary, width: 1.5),
          ),
        ),
        filledButtonTheme: FilledButtonThemeData(
          style: FilledButton.styleFrom(
            backgroundColor: primary,
            foregroundColor: Colors.white,
            minimumSize: const Size.fromHeight(52),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(14),
            ),
            textStyle: const TextStyle(
              fontWeight: FontWeight.w700,
              fontSize: 15,
            ),
          ),
        ),
        textButtonTheme: TextButtonThemeData(
          style: TextButton.styleFrom(
            foregroundColor: primary,
            textStyle: const TextStyle(fontWeight: FontWeight.w700),
          ),
        ),
        textTheme: const TextTheme(
          bodyLarge: TextStyle(color: text),
          bodyMedium: TextStyle(color: text),
          titleMedium: TextStyle(color: text),
          headlineSmall: TextStyle(color: text),
        ),
        dividerColor: surfaceSoft,
      ),
      home: AuthGate(controller: _authController),
    );
  }
}
