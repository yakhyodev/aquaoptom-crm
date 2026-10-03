import 'package:flutter/material.dart';
import 'screens/home_screen.dart';

void main() {
  runApp(const AquaOptomApp());
}

class AquaOptomApp extends StatelessWidget {
  const AquaOptomApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'AquaOptom CRM',
      debugShowCheckedModeBanner: false,
      theme: ThemeData.dark().copyWith(
        scaffoldBackgroundColor: const Color(0xFF0F172A),
        colorScheme: const ColorScheme.dark(
          primary: Colors.blueAccent,
          secondary: Colors.cyanAccent,
        ),
      ),
      home: const HomeScreen(),
    );
  }
}
