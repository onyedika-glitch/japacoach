import 'package:dio/dio.dart';

import '../config.dart';

/// Thrown for any failed API call, with a message that is safe to show to the user.
class ApiException implements Exception {
  ApiException(this.message, {this.status, this.code, this.feature});

  final String message;
  final int? status;
  final String? code;
  final String? feature;

  bool get isLimitReached => status == 402 && code == 'limit_reached';

  @override
  String toString() => message;
}

class ApiClient {
  ApiClient()
      : dio = Dio(BaseOptions(
          baseUrl: AppConfig.apiUrl,
          connectTimeout: const Duration(seconds: 20),
          receiveTimeout: const Duration(seconds: 60),
          headers: {'Accept': 'application/json'},
        )) {
    dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) {
        if (token != null) options.headers['Authorization'] = 'Bearer $token';
        handler.next(options);
      },
      onError: (error, handler) {
        if (error.response?.statusCode == 401 && token != null) onUnauthorized?.call();
        handler.next(error);
      },
    ));
  }

  final Dio dio;
  String? token;

  /// Called when the server rejects the saved token (expired or revoked).
  void Function()? onUnauthorized;

  /// Called when a free-plan limit is hit, so the app can explain it wherever it happens.
  void Function(ApiException error)? onLimitReached;

  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? query}) =>
      _send(() => dio.get(path, queryParameters: query));

  Future<Map<String, dynamic>> post(String path, {Object? data, Duration? timeout}) =>
      _send(() => dio.post(path, data: data, options: timeout == null ? null : Options(sendTimeout: timeout, receiveTimeout: timeout)));

  Future<Map<String, dynamic>> patch(String path, {Object? data}) => _send(() => dio.patch(path, data: data));

  Future<Map<String, dynamic>> put(String path, {Object? data}) => _send(() => dio.put(path, data: data));

  Future<Map<String, dynamic>> delete(String path, {Object? data}) => _send(() => dio.delete(path, data: data));

  Future<Map<String, dynamic>> _send(Future<Response<dynamic>> Function() request) async {
    try {
      final response = await request();
      final data = response.data;
      return data is Map<String, dynamic> ? data : <String, dynamic>{};
    } on DioException catch (e) {
      final error = _toApiException(e);
      if (error.isLimitReached) onLimitReached?.call(error);
      throw error;
    }
  }

  ApiException _toApiException(DioException e) {
    final response = e.response;
    if (response == null) {
      return ApiException('Network error. Check your connection and try again.');
    }
    final data = response.data;
    String message = 'Something went wrong. Please try again.';
    String? code;
    String? feature;
    if (data is Map) {
      final errors = data['errors'];
      if (errors is Map && errors.isNotEmpty) {
        final first = errors.values.first;
        if (first is List && first.isNotEmpty) message = first.first.toString();
      } else if (data['message'] is String && (data['message'] as String).isNotEmpty) {
        message = data['message'] as String;
      }
      code = data['code'] as String?;
      feature = data['feature'] as String?;
    }
    if (response.statusCode == 429) message = 'Too many tries. Please wait a minute and try again.';
    if ((response.statusCode ?? 0) >= 500) message = 'Our server had a problem. Please try again shortly.';
    return ApiException(message, status: response.statusCode, code: code, feature: feature);
  }
}
