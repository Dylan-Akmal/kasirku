import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../core/api_client.dart';

class AuthUser {
  const AuthUser({
    required this.id,
    required this.name,
    required this.email,
    required this.role,
  });

  final int id;
  final String name;
  final String email;
  final String role;

  bool get isAdmin => role == 'admin';

  factory AuthUser.fromJson(Map<String, dynamic> json) => AuthUser(
        id: json['id'] as int,
        name: json['name'] as String,
        email: json['email'] as String,
        role: json['role'] as String,
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'email': email,
        'role': role,
      };
}

class AuthProvider extends ChangeNotifier {
  AuthUser? user;

  bool get isLoggedIn => user != null && ApiClient.instance.token != null;

  /// Membaca sesi yang tersimpan di perangkat saat aplikasi dibuka.
  Future<void> restore() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString('kasirku_user');

    if (raw != null && ApiClient.instance.token != null) {
      user = AuthUser.fromJson(jsonDecode(raw) as Map<String, dynamic>);
    }
  }

  Future<void> login(String email, String password) async {
    final response = await ApiClient.instance.dio.post(
      '/login',
      data: {
        'email': email,
        'password': password,
        'device_name': 'android',
      },
    );

    final data = response.data['data'] as Map<String, dynamic>;

    await ApiClient.instance.setToken(data['token'] as String);
    user = AuthUser.fromJson(data['user'] as Map<String, dynamic>);

    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('kasirku_user', jsonEncode(user!.toJson()));

    notifyListeners();
  }

  Future<void> logout() async {
    try {
      await ApiClient.instance.dio.post('/logout');
    } catch (_) {
      // token mungkin sudah tidak berlaku, tetap bersihkan sesi lokal
    }

    await clear();
  }

  /// Membersihkan sesi lokal (dipakai saat logout atau token ditolak server).
  Future<void> clear() async {
    user = null;
    await ApiClient.instance.setToken(null);

    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('kasirku_user');

    notifyListeners();
  }
}