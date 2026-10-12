import 'package:dio/dio.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'config.dart';

class ApiClient {
  ApiClient._();

  static final ApiClient instance = ApiClient._();

  late final Dio dio;
  String? _token;

  /// Dipanggil saat server menjawab 401 (token tidak berlaku lagi).
  void Function()? onUnauthorized;

  String? get token => _token;

  Future<void> init() async {
    final prefs = await SharedPreferences.getInstance();
    _token = prefs.getString('kasirku_token');

    dio = Dio(
      BaseOptions(
        baseUrl: apiBaseUrl,
        connectTimeout: const Duration(seconds: 10),
        receiveTimeout: const Duration(seconds: 20),
        headers: {'Accept': 'application/json'},
      ),
    );

    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          if (_token != null) {
            options.headers['Authorization'] = 'Bearer $_token';
          }
          handler.next(options);
        },
        onError: (error, handler) {
          final isLogin = error.requestOptions.path.endsWith('/login');

          if (error.response?.statusCode == 401 && !isLogin) {
            onUnauthorized?.call();
          }

          handler.next(error);
        },
      ),
    );
  }

  Future<void> setToken(String? token) async {
    _token = token;

    final prefs = await SharedPreferences.getInstance();
    if (token == null) {
      await prefs.remove('kasirku_token');
    } else {
      await prefs.setString('kasirku_token', token);
    }
  }
}