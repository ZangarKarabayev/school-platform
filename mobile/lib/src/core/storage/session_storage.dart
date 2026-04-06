import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../../features/auth/domain/auth_session.dart';
import '../../features/auth/domain/user_profile.dart';

class SessionStorage {
  static const _tokenKey = 'auth.access_token';
  static const _tokenTypeKey = 'auth.token_type';
  static const _userKey = 'auth.user';

  Future<AuthSession?> read() async {
    final preferences = await SharedPreferences.getInstance();
    final accessToken = preferences.getString(_tokenKey);
    final tokenType = preferences.getString(_tokenTypeKey);
    final userJson = preferences.getString(_userKey);

    if (accessToken == null || tokenType == null || userJson == null) {
      return null;
    }

    try {
      final decoded = jsonDecode(userJson);
      if (decoded is! Map<String, dynamic>) {
        await clear();
        return null;
      }

      return AuthSession(
        accessToken: accessToken,
        tokenType: tokenType,
        user: UserProfile.fromJson(decoded),
      );
    } catch (_) {
      await clear();
      return null;
    }
  }

  Future<void> write(AuthSession session) async {
    final preferences = await SharedPreferences.getInstance();
    await preferences.setString(_tokenKey, session.accessToken);
    await preferences.setString(_tokenTypeKey, session.tokenType);
    await preferences.setString(_userKey, jsonEncode(session.user.toJson()));
  }

  Future<void> clear() async {
    final preferences = await SharedPreferences.getInstance();
    await preferences.remove(_tokenKey);
    await preferences.remove(_tokenTypeKey);
    await preferences.remove(_userKey);
  }
}
