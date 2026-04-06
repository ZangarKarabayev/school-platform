import '../../../core/network/api_client.dart';
import '../domain/auth_session.dart';
import '../domain/dashboard_summary.dart';
import '../domain/student_summary.dart';
import '../domain/user_profile.dart';

class AuthRepository {
  AuthRepository({required String baseUrl, required String appVersion})
    : _client = ApiClient(
        baseUrl: baseUrl,
        userAgent: 'school-platform-mobile/$appVersion',
      );

  final ApiClient _client;

  Future<AuthSession> loginWithPassword({
    required String phone,
    required String password,
    required String deviceName,
  }) async {
    final response = await _client.post(
      '/auth/phone/login',
      body: {'phone': phone, 'password': password, 'device_name': deviceName},
    );

    return _sessionFromResponse(response);
  }

  Future<OtpRequestResult> requestOtp(String phone) async {
    final response = await _client.post(
      '/auth/phone/request-otp',
      body: {'phone': phone, 'purpose': 'login'},
    );

    return OtpRequestResult(
      phone: response['phone'] as String? ?? phone,
      status: response['status'] as String? ?? 'pending_otp',
      debugCode: response['debug_code'] as String?,
    );
  }

  Future<AuthSession> verifyOtp({
    required String phone,
    required String code,
    required String deviceName,
  }) async {
    final response = await _client.post(
      '/auth/phone/verify-otp',
      body: {
        'phone': phone,
        'code': code,
        'purpose': 'login',
        'device_name': deviceName,
      },
    );

    return _sessionFromResponse(response);
  }

  Future<UserProfile> me(String accessToken) async {
    final response = await _client.get('/auth/me', bearerToken: accessToken);
    final user = response['user'] as Map<String, dynamic>? ?? {};
    return UserProfile.fromJson(user);
  }

  Future<DashboardSummary> dashboard(String accessToken) async {
    final response = await _client.get('/auth/dashboard', bearerToken: accessToken);
    return DashboardSummary.fromJson(response);
  }

  Future<List<StudentSummary>> students(
    String accessToken, {
    String search = '',
  }) async {
    final path = search.trim().isEmpty
        ? '/auth/students'
        : '/auth/students?search=${Uri.encodeQueryComponent(search.trim())}';
    final response = await _client.get(path, bearerToken: accessToken);
    return StudentSummary.listFromJson(response['students']);
  }

  AuthSession _sessionFromResponse(Map<String, dynamic> response) {
    final token = response['token'] as Map<String, dynamic>? ?? {};
    final user = response['user'] as Map<String, dynamic>? ?? {};

    return AuthSession(
      accessToken: token['token'] as String? ?? '',
      tokenType: token['token_type'] as String? ?? 'Bearer',
      user: UserProfile.fromJson(user),
    );
  }
}

class OtpRequestResult {
  const OtpRequestResult({
    required this.phone,
    required this.status,
    this.debugCode,
  });

  final String phone;
  final String status;
  final String? debugCode;
}
