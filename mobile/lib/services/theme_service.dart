import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'session_service.dart';

class ThemeService extends ValueNotifier<ThemeMode> {
  ThemeService._() : super(ThemeMode.system);
  static final instance = ThemeService._();

  Future<void> restore() async {
    try {
      final saved = await SessionService.channel.invokeMethod<String>(
        'readTheme',
      );
      value = ThemeMode.values.firstWhere(
        (mode) => mode.name == saved,
        orElse: () => ThemeMode.system,
      );
    } on PlatformException {
      /* Use the device theme. */
    } on MissingPluginException {
      /* Use the device theme. */
    }
  }

  Future<void> toggle(Brightness current) async {
    value = current == Brightness.dark ? ThemeMode.light : ThemeMode.dark;
    try {
      await SessionService.channel.invokeMethod('writeTheme', value.name);
    } on MissingPluginException {
      /* Memory-only preference. */
    }
  }
}
