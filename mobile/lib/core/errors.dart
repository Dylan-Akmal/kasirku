import 'package:dio/dio.dart';

/// Mengubah error Dio/Laravel menjadi pesan yang ramah dibaca.
String errorMessage(Object error, [String fallback = 'Terjadi kesalahan. Coba lagi.']) {
  if (error is DioException) {
    final data = error.response?.data;

    if (data is Map) {
      final errors = data['errors'];

      if (errors is Map && errors.isNotEmpty) {
        final first = errors.values.first;
        if (first is List && first.isNotEmpty) {
          return first.first.toString();
        }
      }

      if (data['message'] is String) {
        return data['message'] as String;
      }
    }

    if (error.type == DioExceptionType.connectionError ||
        error.type == DioExceptionType.connectionTimeout ||
        error.type == DioExceptionType.receiveTimeout) {
      return 'Tidak dapat terhubung ke server. Pastikan backend berjalan.';
    }
  }

  return fallback;
}