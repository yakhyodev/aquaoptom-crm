import 'package:flutter/material.dart';
import 'screens/login_screen.dart';
import 'screens/main_navigation_screen.dart';
import 'services/api_service.dart';
import 'services/session_service.dart';
import 'services/theme_service.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await SessionService().restoreSession();
  await ThemeService.instance.restore();
  runApp(const AquaOptomApp());
}

class AquaOptomApp extends StatelessWidget {
  final ApiService? apiService;

  const AquaOptomApp({super.key, this.apiService});

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<ThemeMode>(
      valueListenable: ThemeService.instance,
      builder: (context, mode, _) => MaterialApp(
        title: 'AquaOptom CRM',
        debugShowCheckedModeBanner: false,
        themeMode: mode,
        theme: ThemeData(
          colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF2563EB)),
          useMaterial3: true,
        ),
        darkTheme: ThemeData.dark().copyWith(
          scaffoldBackgroundColor: const Color(0xFF0F172A),
          colorScheme: const ColorScheme.dark(
            primary: Colors.blueAccent,
            secondary: Colors.cyanAccent,
            surface: Color(0xFF1E293B),
          ),
        ),
        home: ListenableBuilder(
          listenable: SessionService(),
          builder: (context, _) {
            final session = SessionService();
            if (session.isAuthenticated && session.currentUser != null) {
              return MainNavigationScreen(
                user: session.currentUser!,
                apiService: apiService,
              );
            }
            return LoginScreen(apiService: apiService);
          },
        ),
      ),
    );
  }
}
