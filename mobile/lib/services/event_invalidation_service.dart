import 'dart:async';
import 'dart:convert';
import 'package:http/http.dart' as http;
import '../config/app_config.dart';
import 'session_service.dart';

class InvalidationResult {
  final int cursor;
  final List<String> invalidatedResources;
  final int eventsCount;
  final List<Map<String, dynamic>> events;

  const InvalidationResult({
    required this.cursor,
    required this.invalidatedResources,
    required this.eventsCount,
    required this.events,
  });
}

/// Event/cursor invalidation catch-up repository adapter
class EventInvalidationService {
  static final EventInvalidationService _instance =
      EventInvalidationService._internal();
  factory EventInvalidationService() => _instance;
  EventInvalidationService._internal();

  int _lastCursor = 0;
  int get lastCursor => _lastCursor;

  final _resourceInvalidatedController =
      StreamController<List<String>>.broadcast();

  Stream<List<String>> get onInvalidated =>
      _resourceInvalidatedController.stream;

  void resetCursor() {
    _lastCursor = 0;
  }

  void setCursor(int cursor) {
    if (cursor > _lastCursor) {
      _lastCursor = cursor;
    }
  }

  /// Cursor catch-up so'rovi yuborish
  Future<InvalidationResult> pollUpdates({http.Client? client}) async {
    final httpClient = client ?? http.Client();
    final token = SessionService().token;

    final headers = <String, String>{
      'Accept': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
    };

    final uri = Uri.parse('${AppConfig.apiBaseUrl}/events/invalidation')
        .replace(queryParameters: {'cursor': _lastCursor.toString()});

    try {
      final response = await httpClient.get(uri, headers: headers);
      if (response.statusCode == 200) {
        final data = json.decode(response.body) as Map<String, dynamic>;
        final nextCursor = (data['cursor'] as num?)?.toInt() ?? _lastCursor;
        final rawResources =
            (data['invalidated_resources'] as List<dynamic>?) ?? [];
        final resources = rawResources.map((e) => e.toString()).toList();
        final rawEvents = (data['events'] as List<dynamic>?) ?? [];
        final events = rawEvents
            .map((e) => e as Map<String, dynamic>)
            .toList();

        _lastCursor = nextCursor;

        if (resources.isNotEmpty) {
          _resourceInvalidatedController.add(resources);
        }

        return InvalidationResult(
          cursor: nextCursor,
          invalidatedResources: resources,
          eventsCount: (data['events_count'] as num?)?.toInt() ?? events.length,
          events: events,
        );
      }
    } catch (_) {
      // Tarmoq uzilishi vaqtida jim o'tkazib yuboriladi
    }

    return InvalidationResult(
      cursor: _lastCursor,
      invalidatedResources: const [],
      eventsCount: 0,
      events: const [],
    );
  }
}
