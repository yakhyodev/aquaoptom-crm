// ignore_for_file: use_super_parameters

/// Typed API Errors for AquaOptom Mobile
abstract class ApiException implements Exception {
  final String message;
  final int? statusCode;
  final String? errorCode;
  final Map<String, dynamic>? details;

  const ApiException(
    this.message, {
    this.statusCode,
    this.errorCode,
    this.details,
  });

  @override
  String toString() => message;
}

/// 401 Unauthorized / Sessiya tugagan
class UnauthorizedException extends ApiException {
  const UnauthorizedException([
    String message = "Sessiya muddati tugadi yoki login xato!",
    Map<String, dynamic>? details,
  ]) : super(
          message,
          statusCode: 401,
          errorCode: 'UNAUTHORIZED',
          details: details,
        );
}

/// 403 Forbidden / Ruxsat berilmagan
class ForbiddenException extends ApiException {
  const ForbiddenException([
    String message =
        "Ushbu amalni bajarish uchun sizda yetarli ruxsat yo'q!",
    Map<String, dynamic>? details,
  ]) : super(
          message,
          statusCode: 403,
          errorCode: 'FORBIDDEN',
          details: details,
        );
}

/// 422 Validation Error
class ValidationException extends ApiException {
  final Map<String, List<String>> errors;

  ValidationException(
    String message, {
    this.errors = const {},
    Map<String, dynamic>? details,
  }) : super(
          message,
          statusCode: 422,
          errorCode: 'VALIDATION_ERROR',
          details: details,
        );

  String get firstError {
    if (errors.isNotEmpty && errors.values.first.isNotEmpty) {
      return errors.values.first.first;
    }
    return message;
  }
}

/// Tarmoq uzilishi yoki ulanish xatosi
class NetworkException extends ApiException {
  const NetworkException([
    String message =
        "Server bilan aloqa uzildi. Internet yoki tarmoq sozlamalarini tekshiring.",
  ]) : super(
          message,
          statusCode: 0,
          errorCode: 'NETWORK_ERROR',
        );
}

/// 500 Server Error
class ServerException extends ApiException {
  const ServerException([
    String message = "Serverda kutilmagan ichki xatolik yuz berdi.",
    int? statusCode = 500,
    Map<String, dynamic>? details,
  ]) : super(
          message,
          statusCode: statusCode,
          errorCode: 'SERVER_ERROR',
          details: details,
        );
}
