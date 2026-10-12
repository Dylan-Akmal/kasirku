import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'core/api_client.dart';
import 'providers/auth_provider.dart';
import 'screens/home_screen.dart';
import 'screens/login_screen.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  await ApiClient.instance.init();

  final auth = AuthProvider();
  await auth.restore();

  ApiClient.instance.onUnauthorized = () => auth.clear();

  runApp(
    ChangeNotifierProvider.value(
      value: auth,
      child: const KasirkuApp(),
    ),
  );
}

class KasirkuApp extends StatelessWidget {
  const KasirkuApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Kasirku',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: Colors.indigo),
        useMaterial3: true,
      ),
      home: Consumer<AuthProvider>(
        builder: (context, auth, _) =>
            auth.isLoggedIn ? const HomeScreen() : const LoginScreen(),
      ),
    );
  }
}