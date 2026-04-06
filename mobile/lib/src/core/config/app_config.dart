class AppConfig {
  const AppConfig._();

  static const String appVersion = '0.1.0';
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api/v1',
  );
}
