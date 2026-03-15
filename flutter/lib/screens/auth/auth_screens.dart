// ─────────────────────────────────────────────
//  lib/screens/auth/phone_screen.dart
// ─────────────────────────────────────────────
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

// ══════════════════════════════════
//  Auth BLoC
// ══════════════════════════════════
// Events
abstract class AuthEvent {}
class SendOtpEvent   extends AuthEvent { final String phone; SendOtpEvent(this.phone); }
class VerifyOtpEvent extends AuthEvent { final String phone, otp; VerifyOtpEvent(this.phone, this.otp); }
class RegisterEvent  extends AuthEvent { final Map<String,dynamic> data; RegisterEvent(this.data); }
class LogoutEvent    extends AuthEvent {}

// States
abstract class AuthState {}
class AuthInitial        extends AuthState {}
class AuthLoading        extends AuthState {}
class OtpSentState       extends AuthState { final String phone; OtpSentState(this.phone); }
class NeedsRegistration  extends AuthState { final String phone; NeedsRegistration(this.phone); }
class AuthSuccess        extends AuthState {}
class AuthError          extends AuthState { final String message; AuthError(this.message); }

class AuthBloc extends Bloc<AuthEvent, AuthState> {
  final ApiService _api;
  AuthBloc(this._api) : super(AuthInitial()) {
    on<SendOtpEvent>(_onSendOtp);
    on<VerifyOtpEvent>(_onVerifyOtp);
    on<RegisterEvent>(_onRegister);
    on<LogoutEvent>(_onLogout);
  }

  Future<void> _onSendOtp(SendOtpEvent e, Emitter<AuthState> emit) async {
    emit(AuthLoading());
    try {
      await _api.sendOtp(e.phone);
      emit(OtpSentState(e.phone));
    } catch (err) {
      emit(AuthError(_parseError(err)));
    }
  }

  Future<void> _onVerifyOtp(VerifyOtpEvent e, Emitter<AuthState> emit) async {
    emit(AuthLoading());
    try {
      final res  = await _api.verifyOtp(e.phone, e.otp);
      final data = res.data as Map<String,dynamic>;
      if (data['needs_registration'] == true) {
        emit(NeedsRegistration(e.phone));
        return;
      }
      await AuthStorage.saveToken(data['token']);
      emit(AuthSuccess());
    } catch (err) {
      emit(AuthError(_parseError(err)));
    }
  }

  Future<void> _onRegister(RegisterEvent e, Emitter<AuthState> emit) async {
    emit(AuthLoading());
    try {
      final res = await _api.register(e.data);
      await AuthStorage.saveToken(res.data['token']);
      emit(AuthSuccess());
    } catch (err) {
      emit(AuthError(_parseError(err)));
    }
  }

  Future<void> _onLogout(LogoutEvent e, Emitter<AuthState> emit) async {
    try { await _api.logout(); } catch (_) {}
    await AuthStorage.clearToken();
    emit(AuthInitial());
  }

  String _parseError(dynamic e) {
    if (e is DioException) {
      final msg = e.response?.data?['message'];
      return msg ?? 'Network error. Please try again.';
    }
    return 'Something went wrong.';
  }
}

// ══════════════════════════════════
//  Phone Screen
// ══════════════════════════════════
class PhoneScreen extends StatefulWidget {
  const PhoneScreen({super.key});
  @override State<PhoneScreen> createState() => _PhoneScreenState();
}

class _PhoneScreenState extends State<PhoneScreen> {
  final _formKey  = GlobalKey<FormState>();
  final _phoneCtrl= TextEditingController();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: BlocConsumer<AuthBloc, AuthState>(
        listener: (ctx, state) {
          if (state is OtpSentState) ctx.push('/auth/otp', extra: state.phone);
          if (state is AuthError)   _showError(ctx, state.message);
        },
        builder: (ctx, state) => SafeArea(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  const SizedBox(height: 60),
                  // Logo
                  Container(
                    width: 100, height: 100,
                    decoration: BoxDecoration(
                      color: AppTheme.primaryColor.withOpacity(0.1),
                      borderRadius: BorderRadius.circular(50),
                    ),
                    child: const Icon(Icons.account_balance, size: 56, color: AppTheme.primaryColor),
                  ),
                  const SizedBox(height: 24),
                  Text('Kachankawal\nGaunpalika',
                    textAlign: TextAlign.center,
                    style: Theme.of(ctx).textTheme.headlineMedium?.copyWith(
                      fontWeight: FontWeight.bold, color: AppTheme.primaryColor,
                    )),
                  const SizedBox(height: 8),
                  Text('कचानकवल गाउँपालिका', style: Theme.of(ctx).textTheme.titleMedium?.copyWith(color: Colors.grey[600])),
                  const SizedBox(height: 48),
                  Text('Enter your mobile number', style: Theme.of(ctx).textTheme.titleLarge),
                  const SizedBox(height: 8),
                  Text('We will send a 6-digit OTP to verify your number.',
                    textAlign: TextAlign.center,
                    style: Theme.of(ctx).textTheme.bodyMedium?.copyWith(color: Colors.grey[600])),
                  const SizedBox(height: 24),
                  TextFormField(
                    controller: _phoneCtrl,
                    keyboardType: TextInputType.phone,
                    decoration: const InputDecoration(
                      labelText: 'Mobile Number',
                      hintText: '98XXXXXXXX',
                      prefixIcon: Icon(Icons.phone),
                      prefixText: '+977 ',
                    ),
                    validator: (v) {
                      if (v == null || v.isEmpty) return 'Phone number required';
                      if (v.length < 10)          return 'Enter a valid number';
                      return null;
                    },
                  ),
                  const SizedBox(height: 24),
                  ElevatedButton(
                    onPressed: state is AuthLoading ? null : () {
                      if (_formKey.currentState!.validate()) {
                        ctx.read<AuthBloc>().add(SendOtpEvent(_phoneCtrl.text.trim()));
                      }
                    },
                    child: state is AuthLoading
                        ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                        : const Text('Send OTP'),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  void _showError(BuildContext ctx, String msg) =>
      ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content: Text(msg), backgroundColor: Colors.red));
}

// ══════════════════════════════════
//  OTP Screen
// ══════════════════════════════════
class OtpScreen extends StatefulWidget {
  final String phone;
  const OtpScreen({super.key, required this.phone});
  @override State<OtpScreen> createState() => _OtpScreenState();
}

class _OtpScreenState extends State<OtpScreen> {
  String _otp = '';
  int _resendSeconds = 60;

  @override
  void initState() {
    super.initState();
    _startResendTimer();
  }

  void _startResendTimer() {
    Future.doWhile(() async {
      await Future.delayed(const Duration(seconds: 1));
      if (!mounted) return false;
      setState(() => _resendSeconds = (_resendSeconds - 1).clamp(0, 60));
      return _resendSeconds > 0;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Verify OTP'), backgroundColor: Colors.transparent, elevation: 0, foregroundColor: AppTheme.primaryColor),
      body: BlocConsumer<AuthBloc, AuthState>(
        listener: (ctx, state) {
          if (state is AuthSuccess)        ctx.go('/home');
          if (state is NeedsRegistration)  ctx.push('/auth/register', extra: state.phone);
          if (state is AuthError)          ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content: Text(state.message), backgroundColor: Colors.red));
        },
        builder: (ctx, state) => Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              const SizedBox(height: 40),
              const Icon(Icons.sms_outlined, size: 64, color: AppTheme.primaryColor),
              const SizedBox(height: 24),
              Text('OTP Sent', style: Theme.of(ctx).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.bold)),
              const SizedBox(height: 8),
              Text('Enter the 6-digit code sent to\n+977 ${widget.phone}',
                textAlign: TextAlign.center,
                style: Theme.of(ctx).textTheme.bodyMedium?.copyWith(color: Colors.grey[600])),
              const SizedBox(height: 40),
              Pinput(
                length: 6,
                onCompleted: (pin) => setState(() => _otp = pin),
                onChanged: (pin) => setState(() => _otp = pin),
                defaultPinTheme: PinTheme(
                  width: 52, height: 60,
                  textStyle: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
                  decoration: BoxDecoration(
                    border: Border.all(color: Colors.grey[300]!),
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                focusedPinTheme: PinTheme(
                  width: 52, height: 60,
                  textStyle: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
                  decoration: BoxDecoration(
                    border: Border.all(color: AppTheme.primaryColor, width: 2),
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
              const SizedBox(height: 32),
              ElevatedButton(
                onPressed: (state is AuthLoading || _otp.length < 6) ? null : () {
                  ctx.read<AuthBloc>().add(VerifyOtpEvent(widget.phone, _otp));
                },
                child: state is AuthLoading
                    ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                    : const Text('Verify & Continue'),
              ),
              const SizedBox(height: 16),
              TextButton(
                onPressed: _resendSeconds == 0 ? () {
                  setState(() => _resendSeconds = 60);
                  _startResendTimer();
                  ctx.read<AuthBloc>().add(SendOtpEvent(widget.phone));
                } : null,
                child: Text(_resendSeconds > 0 ? 'Resend OTP in ${_resendSeconds}s' : 'Resend OTP'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ══════════════════════════════════
//  Register Screen
// ══════════════════════════════════
class RegisterScreen extends StatefulWidget {
  final String phone;
  const RegisterScreen({super.key, required this.phone});
  @override State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _formKey    = GlobalKey<FormState>();
  final _nameCtrl   = TextEditingController();
  final _emailCtrl  = TextEditingController();
  final _citizenCtrl= TextEditingController();
  int?  _wardNo;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Create Account')),
      body: BlocConsumer<AuthBloc, AuthState>(
        listener: (ctx, state) {
          if (state is AuthSuccess) ctx.go('/home');
          if (state is AuthError)   ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content: Text(state.message), backgroundColor: Colors.red));
        },
        builder: (ctx, state) => SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Form(
            key: _formKey,
            child: Column(
              children: [
                const SizedBox(height: 16),
                CircleAvatar(radius: 40, backgroundColor: AppTheme.primaryColor.withOpacity(0.1),
                  child: const Icon(Icons.person, size: 48, color: AppTheme.primaryColor)),
                const SizedBox(height: 24),
                TextFormField(
                  controller: _nameCtrl,
                  decoration: const InputDecoration(labelText: 'Full Name *', prefixIcon: Icon(Icons.person_outline)),
                  validator: (v) => v!.isEmpty ? 'Full name required' : null,
                ),
                const SizedBox(height: 16),
                TextFormField(
                  initialValue: widget.phone,
                  enabled: false,
                  decoration: const InputDecoration(labelText: 'Mobile Number', prefixIcon: Icon(Icons.phone)),
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _emailCtrl,
                  keyboardType: TextInputType.emailAddress,
                  decoration: const InputDecoration(labelText: 'Email (optional)', prefixIcon: Icon(Icons.email_outlined)),
                ),
                const SizedBox(height: 16),
                DropdownButtonFormField<int>(
                  value: _wardNo,
                  decoration: const InputDecoration(labelText: 'Ward No. *', prefixIcon: Icon(Icons.location_on_outlined)),
                  items: List.generate(7, (i) => DropdownMenuItem(value: i+1, child: Text('Ward ${i+1}'))),
                  onChanged: (v) => setState(() => _wardNo = v),
                  validator: (v) => v == null ? 'Please select your ward' : null,
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _citizenCtrl,
                  decoration: const InputDecoration(labelText: 'Citizenship No. (optional)', prefixIcon: Icon(Icons.badge_outlined)),
                ),
                const SizedBox(height: 32),
                ElevatedButton(
                  onPressed: state is AuthLoading ? null : () {
                    if (_formKey.currentState!.validate()) {
                      ctx.read<AuthBloc>().add(RegisterEvent({
                        'name':           _nameCtrl.text.trim(),
                        'phone':          widget.phone,
                        'email':          _emailCtrl.text.trim().isNotEmpty ? _emailCtrl.text.trim() : null,
                        'ward_no':        _wardNo,
                        'citizenship_no': _citizenCtrl.text.trim().isNotEmpty ? _citizenCtrl.text.trim() : null,
                      }));
                    }
                  },
                  child: state is AuthLoading
                      ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                      : const Text('Create Account'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
