import 'package:flutter/material.dart';
import '../services/session_service.dart';
import 'login_screen.dart';
import 'main_navigation_screen.dart';

class HomeScreen extends StatelessWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final session = SessionService();
    if (session.isAuthenticated && session.currentUser != null) {
      return MainNavigationScreen(user: session.currentUser!);
    }
    return const LoginScreen();
  }
}
