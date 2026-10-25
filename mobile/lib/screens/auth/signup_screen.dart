import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../api/api_client.dart';
import '../../state/auth.dart';
import '../../theme.dart';
import '../../widgets/common.dart';
import 'auth_widgets.dart';

class SignupScreen extends StatefulWidget {
  const SignupScreen({super.key});

  @override
  State<SignupScreen> createState() => _SignupScreenState();
}

class _SignupScreenState extends State<SignupScreen> {
  final _form = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _referral = TextEditingController();
  bool _busy = false;
  bool _hide = true;
  bool _showReferral = false;
  String _testType = 'academic';
  String? _error;

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await context.read<AuthState>().register(
        name: _name.text,
        email: _email.text,
        password: _password.text,
        testType: _testType,
        referralCode: _referral.text,
      );
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: AppColors.surface),
      body: SafeArea(
        child: ListView(padding: const EdgeInsets.fromLTRB(24, 8, 24, 24), children: [
          Text('Create your free account', style: grotesk(size: 30)),
          const SizedBox(height: 6),
          const Text('Take your first AI-marked speaking test in minutes. No card needed.', style: TextStyle(color: AppColors.muted, fontSize: 16)),
          const SizedBox(height: 28),
          GoogleButton(referralCode: _referral.text, onError: (m) => setState(() => _error = m)),
          Form(
            key: _form,
            child: AutofillGroup(
              child: Column(children: [
                TextFormField(
                  controller: _name,
                  textCapitalization: TextCapitalization.words,
                  autofillHints: const [AutofillHints.name],
                  textInputAction: TextInputAction.next,
                  decoration: const InputDecoration(labelText: 'Full name'),
                  validator: (v) => (v ?? '').trim().isEmpty ? 'Enter your name' : null,
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: _email,
                  keyboardType: TextInputType.emailAddress,
                  autofillHints: const [AutofillHints.email],
                  textInputAction: TextInputAction.next,
                  decoration: const InputDecoration(labelText: 'Email'),
                  validator: (v) => (v ?? '').contains('@') ? null : 'Enter a valid email address',
                ),
                const SizedBox(height: 14),
                Align(
                  alignment: Alignment.centerLeft,
                  child: Text('Which IELTS are you taking?', style: TextStyle(fontWeight: FontWeight.w600, color: Colors.grey.shade800)),
                ),
                const SizedBox(height: 8),
                Row(children: [
                  for (final (value, label, hint) in const [
                    ('academic', 'Academic', 'University, nursing, professional registration'),
                    ('general', 'General Training', 'Work and migration (UK, Canada, Australia)'),
                  ]) ...[
                    if (value == 'general') const SizedBox(width: 10),
                    Expanded(
                      child: InkWell(
                        borderRadius: BorderRadius.circular(14),
                        onTap: () => setState(() => _testType = value),
                        child: Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: _testType == value ? AppColors.brand50 : Colors.white,
                            borderRadius: BorderRadius.circular(14),
                            border: Border.all(color: _testType == value ? AppColors.brand : AppColors.border, width: _testType == value ? 2 : 1),
                          ),
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text(label, style: const TextStyle(fontWeight: FontWeight.w700)),
                            const SizedBox(height: 2),
                            Text(hint, style: const TextStyle(color: AppColors.muted, fontSize: 12)),
                          ]),
                        ),
                      ),
                    ),
                  ],
                ]),
                const SizedBox(height: 14),
                TextFormField(
                  controller: _password,
                  obscureText: _hide,
                  autofillHints: const [AutofillHints.newPassword],
                  decoration: InputDecoration(
                    labelText: 'Password',
                    helperText: 'At least 8 characters',
                    suffixIcon: IconButton(icon: Icon(_hide ? Icons.visibility_outlined : Icons.visibility_off_outlined), onPressed: () => setState(() => _hide = !_hide)),
                  ),
                  validator: (v) => (v ?? '').length < 8 ? 'Use at least 8 characters' : null,
                ),
                const SizedBox(height: 8),
                if (_showReferral)
                  Padding(
                    padding: const EdgeInsets.only(top: 6),
                    child: TextFormField(
                      controller: _referral,
                      textCapitalization: TextCapitalization.characters,
                      decoration: const InputDecoration(labelText: 'Referral code (optional)'),
                    ),
                  )
                else
                  Align(
                    alignment: Alignment.centerLeft,
                    child: TextButton(onPressed: () => setState(() => _showReferral = true), child: const Text('Have a referral code?')),
                  ),
              ]),
            ),
          ),
          const SizedBox(height: 12),
          if (_error != null) ...[Notice(_error!, tone: 'error'), const SizedBox(height: 14)],
          FilledButton(onPressed: _busy ? null : _submit, child: Text(_busy ? 'Creating account…' : 'Create account')),
          const SizedBox(height: 14),
          const LegalNote(),
          const SizedBox(height: 8),
          TextButton(onPressed: () => context.pushReplacement('/login'), child: const Text('Already have an account? Log in')),
        ]),
      ),
    );
  }
}
