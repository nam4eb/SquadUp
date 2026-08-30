import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../../features/authentication/application/auth_controller.dart';
import '../../../features/authentication/data/auth_repository.dart';
import '../../../theme/app_theme.dart';
import '../../../widgets/custom_icon_widget.dart';

class AuthFormWidget extends ConsumerStatefulWidget {
  final bool isLogin;
  final VoidCallback onSuccess;

  const AuthFormWidget({
    required this.isLogin,
    required this.onSuccess,
    super.key,
  });

  @override
  ConsumerState<AuthFormWidget> createState() => _AuthFormWidgetState();
}

class _AuthFormWidgetState extends ConsumerState<AuthFormWidget> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _usernameController = TextEditingController();
  final _displayNameController = TextEditingController();
  bool _obscurePassword = true;
  bool _isLoading = false;
  bool _rememberMe = false;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    _usernameController.dispose();
    _displayNameController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _isLoading = true);
    try {
      await ref
          .read(authControllerProvider.notifier)
          .authenticate(
            isLogin: widget.isLogin,
            email: _emailController.text.trim(),
            password: _passwordController.text,
            username: widget.isLogin ? null : _usernameController.text.trim(),
            displayName: widget.isLogin
                ? null
                : _displayNameController.text.trim(),
          );
      if (mounted) widget.onSuccess();
    } on AuthFailure catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Form(
      key: _formKey,
      child: AnimatedSwitcher(
        duration: const Duration(milliseconds: 300),
        transitionBuilder: (child, anim) =>
            FadeTransition(opacity: anim, child: child),
        child: Column(
          key: ValueKey(widget.isLogin),
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (!widget.isLogin) ...[
              _FloatingLabelField(
                controller: _displayNameController,
                label: 'Display Name',
                iconName: 'person_outline',
                validator: (v) => (v == null || v.trim().isEmpty)
                    ? 'Enter your display name'
                    : null,
              ),
              const SizedBox(height: 14),
              _FloatingLabelField(
                controller: _usernameController,
                label: 'Username',
                iconName: 'tag',
                validator: (v) {
                  if (v == null || v.trim().isEmpty) return 'Choose a username';
                  if (v.contains(' ')) return 'No spaces in username';
                  if (v.length < 3) return 'At least 3 characters';
                  return null;
                },
              ),
              const SizedBox(height: 14),
            ],
            _FloatingLabelField(
              controller: _emailController,
              label: 'Email address',
              iconName: 'email',
              keyboardType: TextInputType.emailAddress,
              validator: (v) {
                if (v == null || v.trim().isEmpty) return 'Enter your email';
                if (!v.contains('@')) return 'Enter a valid email address';
                return null;
              },
            ),
            const SizedBox(height: 14),
            _FloatingLabelField(
              controller: _passwordController,
              label: 'Password',
              iconName: 'lock',
              obscureText: _obscurePassword,
              suffixIcon: GestureDetector(
                onTap: () =>
                    setState(() => _obscurePassword = !_obscurePassword),
                child: CustomIconWidget(
                  iconName: _obscurePassword ? 'visibility' : 'visibility_off',
                  size: 20,
                  color: const Color(0xFF9CA3AF),
                ),
              ),
              validator: (v) {
                if (v == null || v.isEmpty) return 'Enter your password';
                if (!widget.isLogin && v.length < 8) {
                  return 'Password must be at least 8 characters';
                }
                if (!widget.isLogin &&
                    (!v.contains(RegExp('[A-Z]')) ||
                        !v.contains(RegExp('[a-z]')) ||
                        !v.contains(RegExp('[0-9]')))) {
                  return 'Use upper/lowercase letters and a number';
                }
                return null;
              },
            ),
            if (widget.isLogin) ...[
              const SizedBox(height: 12),
              _buildLoginExtras(),
            ],
            if (!widget.isLogin) ...[
              const SizedBox(height: 12),
              _buildTermsRow(),
            ],
            const SizedBox(height: 20),
            _buildSubmitButton(),
          ],
        ),
      ),
    );
  }

  Widget _buildLoginExtras() {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Row(
          children: [
            SizedBox(
              width: 20,
              height: 20,
              child: Checkbox(
                value: _rememberMe,
                onChanged: (v) => setState(() => _rememberMe = v ?? false),
                activeColor: AppTheme.primary,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(4),
                ),
                side: const BorderSide(color: Color(0xFFD1D5DB), width: 1.5),
              ),
            ),
            const SizedBox(width: 8),
            Text(
              'Remember me',
              style: GoogleFonts.dmSans(
                fontSize: 13,
                color: const Color(0xFF6B7280),
              ),
            ),
          ],
        ),
        GestureDetector(
          onTap: () {},
          child: Text(
            'Forgot password?',
            style: GoogleFonts.dmSans(
              fontSize: 13,
              fontWeight: FontWeight.w600,
              color: AppTheme.primary,
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildTermsRow() {
    return RichText(
      text: TextSpan(
        style: GoogleFonts.dmSans(
          fontSize: 12,
          color: const Color(0xFF6B7280),
          height: 1.5,
        ),
        children: [
          const TextSpan(text: 'By creating an account, you agree to our '),
          TextSpan(
            text: 'Terms of Service',
            style: GoogleFonts.dmSans(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: AppTheme.primary,
            ),
          ),
          const TextSpan(text: ' and '),
          TextSpan(
            text: 'Privacy Policy',
            style: GoogleFonts.dmSans(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: AppTheme.primary,
            ),
          ),
          const TextSpan(text: '.'),
        ],
      ),
    );
  }

  Widget _buildSubmitButton() {
    return SizedBox(
      height: 52,
      child: ElevatedButton(
        onPressed: _isLoading ? null : _submit,
        style: ElevatedButton.styleFrom(
          backgroundColor: AppTheme.primary,
          foregroundColor: Colors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(100),
          ),
          elevation: 0,
          disabledBackgroundColor: AppTheme.primary.withAlpha(153),
        ),
        child: AnimatedSwitcher(
          duration: const Duration(milliseconds: 200),
          child: _isLoading
              ? const SizedBox(
                  key: ValueKey('loading'),
                  width: 22,
                  height: 22,
                  child: CircularProgressIndicator(
                    strokeWidth: 2.5,
                    valueColor: AlwaysStoppedAnimation(Colors.white),
                  ),
                )
              : Text(
                  key: const ValueKey('label'),
                  widget.isLogin ? 'Sign in to SquadUp' : 'Create My Account',
                  style: GoogleFonts.dmSans(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: Colors.white,
                  ),
                ),
        ),
      ),
    );
  }
}

class _FloatingLabelField extends StatefulWidget {
  final TextEditingController controller;
  final String label;
  final String iconName;
  final bool obscureText;
  final Widget? suffixIcon;
  final TextInputType? keyboardType;
  final String? Function(String?)? validator;

  const _FloatingLabelField({
    required this.controller,
    required this.label,
    required this.iconName,
    this.obscureText = false,
    this.suffixIcon,
    this.keyboardType,
    this.validator,
  });

  @override
  State<_FloatingLabelField> createState() => _FloatingLabelFieldState();
}

class _FloatingLabelFieldState extends State<_FloatingLabelField> {
  final _focusNode = FocusNode();
  bool _isFocused = false;

  @override
  void initState() {
    super.initState();
    _focusNode.addListener(() {
      setState(() => _isFocused = _focusNode.hasFocus);
    });
  }

  @override
  void dispose() {
    _focusNode.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      controller: widget.controller,
      focusNode: _focusNode,
      obscureText: widget.obscureText,
      keyboardType: widget.keyboardType,
      validator: widget.validator,
      style: GoogleFonts.dmSans(
        fontSize: 15,
        fontWeight: FontWeight.w400,
        color: const Color(0xFF1A1A1A),
      ),
      decoration: InputDecoration(
        labelText: widget.label,
        prefixIcon: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12),
          child: CustomIconWidget(
            iconName: widget.iconName,
            size: 20,
            color: _isFocused ? AppTheme.primary : const Color(0xFF9CA3AF),
          ),
        ),
        prefixIconConstraints: const BoxConstraints(
          minWidth: 44,
          minHeight: 44,
        ),
        suffixIcon: widget.suffixIcon != null
            ? Padding(
                padding: const EdgeInsets.only(right: 12),
                child: widget.suffixIcon,
              )
            : null,
        suffixIconConstraints: const BoxConstraints(
          minWidth: 44,
          minHeight: 44,
        ),
      ),
    );
  }
}
