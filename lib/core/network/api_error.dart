import 'package:dio/dio.dart';

String apiErrorMessage(Object error, {required String fallback}) {
  if (error is! DioException) return fallback;
  final data = error.response?.data;
  if (data is Map) {
    final message = data['message'];
    if (message is String && message.trim().isNotEmpty) return message;
    final errors = data['errors'];
    if (errors is Map) {
      for (final value in errors.values) {
        if (value is List && value.isNotEmpty && value.first is String) {
          return value.first as String;
        }
      }
    }
  }
  if (error.type == DioExceptionType.connectionTimeout ||
      error.type == DioExceptionType.receiveTimeout ||
      error.type == DioExceptionType.connectionError) {
    return 'Unable to connect. Check your network and try again.';
  }
  return fallback;
}
