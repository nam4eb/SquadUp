import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../routes/app_routes.dart';
import '../../theme/app_theme.dart';
import '../../widgets/custom_icon_widget.dart';
import './widgets/auth_form_widget.dart';
import './widgets/auth_header_widget.dart';
import './widgets/social_auth_widget.dart';
import '../../features/authentication/application/auth_controller.dart';

class SignUpLoginScreen extends ConsumerStatefulWidget {
  const SignUpLoginScreen({super.key});

  @override
  ConsumerState<SignUpLoginScreen> createState() => _SignUpLoginScreenState();
}

class _SignUpLoginScreenState extends ConsumerState<SignUpLoginScreen>
    with SingleTickerProviderStateMixin {
  bool _isLogin = true;
  late AnimationController _entranceController;
  late Animation<double> _fadeAnim;
  late Animation<Offset> _slideAnim;

  @override
  void initState() {
    super.initState();
    _entranceController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 700),
    );
    _fadeAnim = CurvedAnimation(
      parent: _entranceController,
      curve: const Interval(0.0, 0.7, curve: Curves.easeOut),
    );
    _slideAnim = Tween<Offset>(begin: const Offset(0, 0.06), end: Offset.zero)
        .animate(
          CurvedAnimation(
            parent: _entranceController,
            curve: const Interval(0.0, 0.8, curve: Curves.easeOutCubic),
          ),
        );
    _entranceController.forward();
  }

  @override
  void dispose() {
    _entranceController.dispose();
    super.dispose();
  }

  void _toggleMode() {
    setState(() => _isLogin = !_isLogin);
  }

  void _onAuthSuccess() {
    final user = ref.read(authControllerProvider).user;
    context.go(
      user?.onboardingCompleted == true
          ? AppRoutes.homeFeed
          : AppRoutes.onboarding,
    );
  }

  @override
  Widget build(BuildContext context) {
    final size = MediaQuery.of(context).size;
    final isTablet = size.width >= 600;

    return Scaffold(
      backgroundColor: AppTheme.backgroundLight,
      body: SafeArea(
        child: isTablet ? _buildTabletLayout() : _buildPhoneLayout(),
      ),
    );
  }

  Widget _buildTabletLayout() {
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 480),
          child: _buildFormCard(),
        ),
      ),
    );
  }

  Widget _buildPhoneLayout() {
    return SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          FadeTransition(
            opacity: _fadeAnim,
            child: SlideTransition(
              position: _slideAnim,
              child: AuthHeaderWidget(isLogin: _isLogin),
            ),
          ),
          FadeTransition(
            opacity: _fadeAnim,
            child: SlideTransition(
              position: _slideAnim,
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 24),
                child: _buildFormCard(),
              ),
            ),
          ),
          const SizedBox(height: 32),
        ],
      ),
    );
  }

  Widget _buildFormCard() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AuthFormWidget(isLogin: _isLogin, onSuccess: _onAuthSuccess),
        const SizedBox(height: 24),
        _buildDivider(),
        const SizedBox(height: 20),
        const SocialAuthWidget(),
        const SizedBox(height: 28),
        _buildToggleRow(),
        const SizedBox(height: 16),
        _buildDemoCredentials(),
      ],
    );
  }

  Widget _buildDivider() {
    return Row(
      children: [
        Expanded(child: Divider(color: AppTheme.primary.withAlpha(38))),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: Text(
            'or continue with',
            style: GoogleFonts.dmSans(
              fontSize: 12,
              color: const Color(0xFF9CA3AF),
              fontWeight: FontWeight.w500,
            ),
          ),
        ),
        Expanded(child: Divider(color: AppTheme.primary.withAlpha(38))),
      ],
    );
  }

  Widget _buildToggleRow() {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Text(
          _isLogin ? "Don't have an account? " : 'Already have an account? ',
          style: GoogleFonts.dmSans(
            fontSize: 14,
            color: const Color(0xFF6B7280),
          ),
        ),
        GestureDetector(
          onTap: _toggleMode,
          child: Text(
            _isLogin ? 'Sign up' : 'Log in',
            style: GoogleFonts.dmSans(
              fontSize: 14,
              fontWeight: FontWeight.w700,
              color: AppTheme.primary,
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildDemoCredentials() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppTheme.primary.withAlpha(15),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppTheme.primary.withAlpha(38)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              CustomIconWidget(
                iconName: 'info',
                size: 14,
                color: AppTheme.primary,
              ),
              const SizedBox(width: 6),
              Text(
                'Demo credentials',
                style: GoogleFonts.dmSans(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: AppTheme.primary,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          _credentialRow('Email', 'alex.chen@squadup.app'),
          const SizedBox(height: 4),
          _credentialRow('Password', 'SquadUp2026!'),
        ],
      ),
    );
  }

  Widget _credentialRow(String label, String value) {
    return Row(
      children: [
        SizedBox(
          width: 64,
          child: Text(
            label,
            style: GoogleFonts.dmSans(
              fontSize: 12,
              color: const Color(0xFF6B7280),
            ),
          ),
        ),
        Expanded(
          child: Text(
            value,
            style: GoogleFonts.dmSans(
              fontSize: 12,
              fontWeight: FontWeight.w500,
              color: const Color(0xFF1A1A1A),
            ),
          ),
        ),
        GestureDetector(
          onTap: () {},
          child: CustomIconWidget(
            iconName: 'copy',
            size: 14,
            color: AppTheme.primary,
          ),
        ),
      ],
    );
  }
}
