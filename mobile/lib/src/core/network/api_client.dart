import 'dart:convert';

import 'package:http/http.dart' as http;

class ApiClient {
  ApiClient({required this.baseUrl, required this.userAgent});

  final String baseUrl;
  final String userAgent;

  Future<Map<String, dynamic>> get(String path, {String? bearerToken}) {
    return _send('GET', path, bearerToken: bearerToken);
  }

  Future<Map<String, dynamic>> post(
    String path, {
    Map<String, dynamic>? body,
    String? bearerToken,
  }) {
    return _send('POST', path, body: body, bearerToken: bearerToken);
  }

  Future<Map<String, dynamic>> _send(
    String method,
    String path, {
    Map<String, dynamic>? body,
    String? bearerToken,
  }) async {
    final uri = Uri.parse('$baseUrl$path');
    final headers = <String, String>{'Accept': 'application/json'};

    if (bearerToken != null && bearerToken.isNotEmpty) {
      headers['Authorization'] = 'Bearer $bearerToken';
    }

    if (body != null) {
      headers['Content-Type'] = 'application/json; charset=utf-8';
    }

    try {
      late final http.Response response;

      switch (method) {
        case 'GET':
          response = await http.get(uri, headers: headers);
        case 'POST':
          response = await http.post(
            uri,
            headers: headers,
            body: body == null ? null : jsonEncode(body),
          );
        default:
          throw ApiException(
            statusCode: 0,
            message: 'Unsupported HTTP method: $method',
          );
      }

      final rawBody = response.body;
      final jsonBody = rawBody.isEmpty
          ? <String, dynamic>{}
          : jsonDecode(rawBody) as Map<String, dynamic>;

      if (response.statusCode >= 200 && response.statusCode < 300) {
        return jsonBody;
      }

      throw ApiException(
        statusCode: response.statusCode,
        message: _extractErrorMessage(jsonBody),
        payload: jsonBody,
      );
    } on http.ClientException catch (error) {
      throw ApiException(
        statusCode: 0,
        message: 'Network error: ${error.message}',
      );
    } on FormatException {
      throw const ApiException(
        statusCode: 0,
        message: 'Invalid API response format.',
      );
    }
  }

  String _extractErrorMessage(Map<String, dynamic> jsonBody) {
    final message = jsonBody['message'];
    if (message is String && message.trim().isNotEmpty) {
      return message;
    }

    final errors = jsonBody['errors'];
    if (errors is Map<String, dynamic>) {
      for (final value in errors.values) {
        if (value is List && value.isNotEmpty && value.first is String) {
          return value.first as String;
        }
      }
    }

    return 'API request failed.';
  }
}

class ApiException implements Exception {
  const ApiException({
    required this.statusCode,
    required this.message,
    this.payload = const <String, dynamic>{},
  });

  final int statusCode;
  final String message;
  final Map<String, dynamic> payload;

  @override
  String toString() => 'ApiException($statusCode): $message';
}
