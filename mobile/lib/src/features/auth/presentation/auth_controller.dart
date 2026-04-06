import 'package:flutter/foundation.dart';

import '../../../core/network/api_client.dart';
import '../../../core/storage/session_storage.dart';
import '../data/auth_repository.dart';
import '../domain/auth_session.dart';
import '../domain/dashboard_summary.dart';
import '../domain/student_summary.dart';
import '../domain/user_profile.dart';

enum AuthStep { phone, otp, authenticated }

enum LoginMethod { password, otp }

class AuthController extends ChangeNotifier {
  AuthController({
    required AuthRepository repository,
    required SessionStorage sessionStorage,
  }) : _repository = repository,
       _sessionStorage = sessionStorage;

  final AuthRepository _repository;
  final SessionStorage _sessionStorage;

  AuthStep _step = AuthStep.phone;
  LoginMethod _loginMethod = LoginMethod.password;
  bool _isBusy = false;
  String? _errorMessage;
  String? _infoMessage;
  String _phone = '';
  String? _debugCode;
  AuthSession? _session;
  DashboardSummary? _dashboardSummary;
  bool _isDashboardLoading = false;
  String? _dashboardErrorMessage;
  List<StudentSummary> _students = const [];
  bool _isStudentsLoading = false;
  String? _studentsErrorMessage;
  String _studentsSearch = '';

  AuthStep get step => _step;
  LoginMethod get loginMethod => _loginMethod;
  bool get isBusy => _isBusy;
  String? get errorMessage => _errorMessage;
  String? get infoMessage => _infoMessage;
  String get phone => _phone;
  String? get debugCode => _debugCode;
  AuthSession? get session => _session;
  UserProfile? get user => _session?.user;
  bool get isAuthenticated => _session != null;
  DashboardSummary? get dashboardSummary => _dashboardSummary;
  bool get isDashboardLoading => _isDashboardLoading;
  String? get dashboardErrorMessage => _dashboardErrorMessage;
  List<StudentSummary> get students => _students;
  bool get isStudentsLoading => _isStudentsLoading;
  String? get studentsErrorMessage => _studentsErrorMessage;
  String get studentsSearch => _studentsSearch;

  Future<void> loadProfile() async {
    _isBusy = true;
    _errorMessage = null;
    notifyListeners();

    final savedSession = await _sessionStorage.read();
    if (savedSession == null) {
      _isBusy = false;
      notifyListeners();
      return;
    }

    _session = savedSession;
    _phone = savedSession.user.phone;
    _step = AuthStep.authenticated;
    _infoMessage = 'Session restored.';
    _isBusy = false;
    notifyListeners();

    await refreshProfile();
  }

  void setLoginMethod(LoginMethod method) {
    if (_loginMethod == method) {
      return;
    }

    _loginMethod = method;
    if (_step != AuthStep.authenticated) {
      _step = AuthStep.phone;
    }
    _debugCode = null;
    _clearMessages();
    notifyListeners();
  }

  Future<void> loginWithPassword({
    required String rawPhone,
    required String password,
  }) async {
    final phone = _normalizePhone(rawPhone);

    if (!_isPhoneValid(phone)) {
      _setError('Enter phone as +7 777 777 77 77.');
      return;
    }

    if (password.trim().isEmpty) {
      _setError('Enter password.');
      return;
    }

    await _guard(() async {
      final session = await _repository.loginWithPassword(
        phone: phone,
        password: password,
        deviceName: _deviceName,
      );

      _phone = phone;
      _session = session;
      await _sessionStorage.write(session);
      _debugCode = null;
      _step = AuthStep.authenticated;
      _infoMessage = 'Signed in.';
      _dashboardSummary = null;
      _dashboardErrorMessage = null;
      _students = const [];
      _studentsErrorMessage = null;
      _studentsSearch = '';
      await refreshDashboard();
      await refreshStudents();
    });
  }

  Future<void> requestOtp(String rawPhone) async {
    final phone = _normalizePhone(rawPhone);

    if (!_isPhoneValid(phone)) {
      _setError('Enter phone as +7 777 777 77 77.');
      return;
    }

    await _guard(() async {
      final result = await _repository.requestOtp(phone);
      _phone = result.phone;
      _debugCode = result.debugCode;
      _step = AuthStep.otp;
      _loginMethod = LoginMethod.otp;
      _infoMessage = 'Code sent to $_phone.';
    });
  }

  Future<void> verifyOtp(String code) async {
    final normalizedCode = code.replaceAll(RegExp(r'\D'), '');
    if (normalizedCode.length != 6) {
      _setError('Code must contain 6 digits.');
      return;
    }

    await _guard(() async {
      final session = await _repository.verifyOtp(
        phone: _phone,
        code: normalizedCode,
        deviceName: _deviceName,
      );

      _session = session;
      await _sessionStorage.write(session);
      _debugCode = null;
      _step = AuthStep.authenticated;
      _infoMessage = 'Signed in.';
      _dashboardSummary = null;
      _dashboardErrorMessage = null;
      _students = const [];
      _studentsErrorMessage = null;
      _studentsSearch = '';
      await refreshDashboard();
      await refreshStudents();
    });
  }

  Future<void> refreshProfile() async {
    final token = _session?.accessToken;
    if (token == null || token.isEmpty) {
      return;
    }

    await _guard(() async {
      final user = await _repository.me(token);
      _session = AuthSession(
        accessToken: _session!.accessToken,
        tokenType: _session!.tokenType,
        user: user,
      );
      await _sessionStorage.write(_session!);
      _infoMessage = 'Profile refreshed.';
      await refreshDashboard(notify: false);
      await refreshStudents(search: _studentsSearch, notify: false);
    }, clearInfo: false);
  }

  Future<void> refreshDashboard({bool notify = true}) async {
    final token = _session?.accessToken;
    if (token == null || token.isEmpty) {
      return;
    }

    _isDashboardLoading = true;
    _dashboardErrorMessage = null;
    if (notify) {
      notifyListeners();
    }

    try {
      _dashboardSummary = await _repository.dashboard(token);
    } on ApiException catch (error) {
      _dashboardErrorMessage = error.message;
    } catch (_) {
      _dashboardErrorMessage = 'Failed to load dashboard.';
    } finally {
      _isDashboardLoading = false;
      notifyListeners();
    }
  }

  Future<void> refreshStudents({
    String? search,
    bool notify = true,
  }) async {
    final token = _session?.accessToken;
    if (token == null || token.isEmpty) {
      return;
    }

    final nextSearch = search ?? _studentsSearch;
    _studentsSearch = nextSearch;
    _isStudentsLoading = true;
    _studentsErrorMessage = null;
    if (notify) {
      notifyListeners();
    }

    try {
      _students = await _repository.students(token, search: nextSearch);
    } on ApiException catch (error) {
      _studentsErrorMessage = error.message;
    } catch (_) {
      _studentsErrorMessage = 'Failed to load students.';
    } finally {
      _isStudentsLoading = false;
      notifyListeners();
    }
  }

  void resetToPhone() {
    _step = AuthStep.phone;
    _phone = '';
    _debugCode = null;
    _clearMessages();
    notifyListeners();
  }

  Future<void> logout() async {
    await _sessionStorage.clear();
    _session = null;
    _dashboardSummary = null;
    _dashboardErrorMessage = null;
    _isDashboardLoading = false;
    _students = const [];
    _studentsErrorMessage = null;
    _isStudentsLoading = false;
    _studentsSearch = '';
    _step = AuthStep.phone;
    _phone = '';
    _debugCode = null;
    _infoMessage = 'Session cleared locally.';
    _errorMessage = null;
    notifyListeners();
  }

  Future<void> _guard(
    Future<void> Function() action, {
    bool clearInfo = true,
  }) async {
    _isBusy = true;
    _errorMessage = null;
    if (clearInfo) {
      _infoMessage = null;
    }
    notifyListeners();

    try {
      await action();
    } on ApiException catch (error) {
      if (error.statusCode == 401) {
        await _sessionStorage.clear();
        _session = null;
        _step = AuthStep.phone;
        _phone = '';
        _debugCode = null;
      }
      _errorMessage = error.message;
    } catch (_) {
      _errorMessage = 'Unexpected error. Check API settings.';
    } finally {
      _isBusy = false;
      notifyListeners();
    }
  }

  void _setError(String message) {
    _errorMessage = message;
    _infoMessage = null;
    notifyListeners();
  }

  void _clearMessages() {
    _errorMessage = null;
    _infoMessage = null;
  }

  String _normalizePhone(String input) {
    final digits = input.replaceAll(RegExp(r'\D'), '');
    if (digits.isEmpty) {
      return '';
    }

    var normalized = digits;
    if (normalized.startsWith('8') && normalized.length == 11) {
      normalized = '7${normalized.substring(1)}';
    }

    if (!normalized.startsWith('7') && normalized.length == 10) {
      normalized = '7$normalized';
    }

    return '+$normalized';
  }

  bool _isPhoneValid(String phone) {
    return RegExp(r'^\+?[0-9]{11,15}$').hasMatch(phone);
  }

  String get _deviceName {
    if (kIsWeb) {
      return 'web';
    }

    return switch (defaultTargetPlatform) {
      TargetPlatform.android => 'android',
      TargetPlatform.iOS => 'ios',
      TargetPlatform.macOS => 'macos',
      TargetPlatform.windows => 'windows',
      TargetPlatform.linux => 'linux',
      _ => 'mobile',
    };
  }
}
