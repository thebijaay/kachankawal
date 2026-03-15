// ─────────────────────────────────────────────
//  lib/main.dart
// ─────────────────────────────────────────────
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'app.dart';
import 'core/di/service_locator.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await dotenv.load(fileName: ".env");
  await Firebase.initializeApp();
  await setupServiceLocator();
  runApp(const KachankawalApp());
}

// ─────────────────────────────────────────────
//  lib/app.dart
// ─────────────────────────────────────────────
class KachankawalApp extends StatelessWidget {
  const KachankawalApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp.router(
      title: 'Kachankawal Gaunpalika',
      theme: AppTheme.light,
      darkTheme: AppTheme.dark,
      routerConfig: AppRouter.router,
      debugShowCheckedModeBanner: false,
    );
  }
}

// ─────────────────────────────────────────────
//  lib/core/theme/app_theme.dart
// ─────────────────────────────────────────────
class AppTheme {
  static const primaryColor = Color(0xFF006B3C);   // Nepal green
  static const accentColor  = Color(0xFFDC143C);   // Nepal red
  static const surfaceColor = Color(0xFFF5F5F5);

  static final light = ThemeData(
    useMaterial3: true,
    colorScheme: ColorScheme.fromSeed(
      seedColor: primaryColor,
      primary: primaryColor,
      secondary: accentColor,
      surface: surfaceColor,
    ),
    fontFamily: 'Hind',
    appBarTheme: const AppBarTheme(
      backgroundColor: primaryColor,
      foregroundColor: Colors.white,
      elevation: 0,
      centerTitle: true,
    ),
    elevatedButtonTheme: ElevatedButtonThemeData(
      style: ElevatedButton.styleFrom(
        backgroundColor: primaryColor,
        foregroundColor: Colors.white,
        minimumSize: const Size(double.infinity, 52),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
      filled: true,
      fillColor: Colors.white,
    ),
    cardTheme: CardTheme(
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
    ),
  );

  static final dark = light.copyWith(
    colorScheme: ColorScheme.fromSeed(
      seedColor: primaryColor,
      brightness: Brightness.dark,
    ),
  );
}

// ─────────────────────────────────────────────
//  lib/core/network/api_service.dart
// ─────────────────────────────────────────────
import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class ApiService {
  static const _baseUrl = String.fromEnvironment('API_BASE_URL',
      defaultValue: 'https://api.kachankawal.gov.np/api/v1');

  late final Dio _dio;
  final _storage = const FlutterSecureStorage();

  ApiService() {
    _dio = Dio(BaseOptions(
      baseUrl: _baseUrl,
      connectTimeout: const Duration(seconds: 30),
      receiveTimeout: const Duration(seconds: 30),
      headers: {'Accept': 'application/json', 'Content-Type': 'application/json'},
    ));

    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (opts, handler) async {
        final token = await _storage.read(key: 'jwt_token');
        if (token != null) opts.headers['Authorization'] = 'Bearer $token';
        handler.next(opts);
      },
      onError: (error, handler) async {
        if (error.response?.statusCode == 401) {
          await _storage.delete(key: 'jwt_token');
          // Navigate to login — handled in BLoC
        }
        handler.next(error);
      },
    ));
  }

  // AUTH
  Future<Response> sendOtp(String phone)    => _dio.post('/auth/send-otp', data: {'phone': phone});
  Future<Response> verifyOtp(String phone, String otp) =>
      _dio.post('/auth/verify-otp', data: {'phone': phone, 'otp': otp});
  Future<Response> register(Map<String,dynamic> data) => _dio.post('/auth/register', data: data);
  Future<Response> getMe()                 => _dio.get('/auth/me');
  Future<Response> logout()                => _dio.post('/auth/logout');

  // NOTICES
  Future<Response> getNotices({String? type, int? wardNo, int page = 1}) =>
      _dio.get('/notices', queryParameters: {'type': type, 'ward_no': wardNo, 'page': page});
  Future<Response> getNotice(int id) => _dio.get('/notices/$id');
  Future<Response> getWardNotices()  => _dio.get('/my-ward/notices');

  // SERVICES
  Future<Response> getMyServices({int page = 1}) =>
      _dio.get('/services', queryParameters: {'page': page});
  Future<Response> submitService(Map<String,dynamic> data) => _dio.post('/services', data: data);
  Future<Response> getService(String trackingNo)           => _dio.get('/services/$trackingNo');
  Future<Response> uploadServiceDoc(int id, FormData form) => _dio.post('/services/$id/documents', data: form);

  // COMPLAINTS
  Future<Response> getMyComplaints({int page = 1}) =>
      _dio.get('/complaints', queryParameters: {'page': page});
  Future<Response> submitComplaint(Map<String,dynamic> data) => _dio.post('/complaints', data: data);
  Future<Response> getComplaint(String trackingNo)           => _dio.get('/complaints/$trackingNo');
  Future<Response> uploadComplaintPhotos(int id, FormData form) =>
      _dio.post('/complaints/$id/photos', data: form);

  // WARDS
  Future<Response> getWards()          => _dio.get('/wards');
  Future<Response> getWard(int wardNo) => _dio.get('/wards/$wardNo');

  // REPRESENTATIVES
  Future<Response> getRepresentatives({int? wardNo}) =>
      _dio.get('/representatives', queryParameters: {'ward_no': wardNo});

  // EVENTS
  Future<Response> getEvents({int? wardNo}) =>
      _dio.get('/events', queryParameters: {'ward_no': wardNo});

  // PROFILE
  Future<Response> updateProfile(Map<String,dynamic> data) => _dio.put('/user/profile', data: data);
  Future<Response> updateDeviceToken(String token) =>
      _dio.post('/user/device-token', data: {'device_token': token});

  // FEEDBACK
  Future<Response> submitFeedback(Map<String,dynamic> data) => _dio.post('/feedback', data: data);
}

// ─────────────────────────────────────────────
//  lib/core/storage/auth_storage.dart
// ─────────────────────────────────────────────
class AuthStorage {
  static const _storage = FlutterSecureStorage();

  static Future<void>   saveToken(String token) => _storage.write(key: 'jwt_token', value: token);
  static Future<String?> getToken()             => _storage.read(key: 'jwt_token');
  static Future<void>   clearToken()            => _storage.delete(key: 'jwt_token');
  static Future<bool>   isLoggedIn() async     => (await getToken()) != null;
}

// ─────────────────────────────────────────────
//  lib/core/router/app_router.dart
// ─────────────────────────────────────────────
import 'package:go_router/go_router.dart';

class AppRouter {
  static final router = GoRouter(
    initialLocation: '/',
    redirect: (context, state) async {
      final isLoggedIn = await AuthStorage.isLoggedIn();
      if (!isLoggedIn && !state.matchedLocation.startsWith('/auth')) {
        return '/auth/phone';
      }
      if (isLoggedIn && state.matchedLocation.startsWith('/auth')) {
        return '/home';
      }
      return null;
    },
    routes: [
      GoRoute(path: '/auth/phone',    builder: (c, s) => const PhoneScreen()),
      GoRoute(path: '/auth/otp',      builder: (c, s) => OtpScreen(phone: s.extra as String)),
      GoRoute(path: '/auth/register', builder: (c, s) => RegisterScreen(phone: s.extra as String)),
      ShellRoute(
        builder: (c, s, child) => MainShell(child: child),
        routes: [
          GoRoute(path: '/home',             builder: (c, s) => const HomeScreen()),
          GoRoute(path: '/notices',          builder: (c, s) => const NoticesScreen()),
          GoRoute(path: '/notices/:id',      builder: (c, s) => NoticeDetailScreen(id: int.parse(s.pathParameters['id']!))),
          GoRoute(path: '/services',         builder: (c, s) => const ServicesScreen()),
          GoRoute(path: '/services/new',     builder: (c, s) => NewServiceScreen(type: s.extra as String)),
          GoRoute(path: '/services/:track',  builder: (c, s) => ServiceDetailScreen(tracking: s.pathParameters['track']!)),
          GoRoute(path: '/complaints',       builder: (c, s) => const ComplaintsScreen()),
          GoRoute(path: '/complaints/new',   builder: (c, s) => const NewComplaintScreen()),
          GoRoute(path: '/complaints/:track',builder: (c, s) => ComplaintDetailScreen(tracking: s.pathParameters['track']!)),
          GoRoute(path: '/wards',            builder: (c, s) => const WardsScreen()),
          GoRoute(path: '/wards/:no',        builder: (c, s) => WardDetailScreen(wardNo: int.parse(s.pathParameters['no']!))),
          GoRoute(path: '/profile',          builder: (c, s) => const ProfileScreen()),
        ],
      ),
    ],
  );
}
