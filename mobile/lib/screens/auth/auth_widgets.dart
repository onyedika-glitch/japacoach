import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../api/api_client.dart';
import '../../config.dart';
import '../../state/auth.dart';
import '../../theme.dart';

class GoogleButton extends StatefulWidget {
  const GoogleButton({super.key, this.referralCode, required this.onError});
  final String? referralCode;
  final void Function(String message) onError;

  @override
  State<GoogleButton> createState() => _GoogleButtonState();
}

class _GoogleButtonState extends State<GoogleButton> {
  bool _busy = false;

  @override
  Widget build(BuildContext context) {
    final auth = context.read<AuthState>();
    if (!auth.googleEnabled) return const SizedBox.shrink();
    return Column(children: [
      OutlinedButton.icon(
        onPressed: _busy
            ? null
            : () async {
                setState(() => _busy = true);
                try {
                  await auth.signInWithGoogle(referralCode: widget.referralCode);
                } on ApiException catch (e) {
                  widget.onError(e.message);
                } catch (_) {
                  widget.onError('Google sign-in failed. Please try again or use your email.');
                } finally {
                  if (mounted) setState(() => _busy = false);
                }
              },
        icon: _busy
            ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
            : const Text('G', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18, color: Color(0xFF4285F4))),
        label: const Text('Continue with Google'),
      ),
      const Padding(
        padding: EdgeInsets.symmetric(vertical: 16),
        child: Row(children: [
          Expanded(child: Divider()),
          Padding(padding: EdgeInsets.symmetric(horizontal: 12), child: Text('or', style: TextStyle(color: AppColors.muted))),
          Expanded(child: Divider()),
        ]),
      ),
    ]);
  }
}

class LegalNote extends StatelessWidget {
  const LegalNote({super.key});

  @override
  Widget build(BuildContext context) {
    void open(String path) => launchUrl(Uri.parse('${AppConfig.websiteUrl}$path'), mode: LaunchMode.externalApplication);
    return Wrap(alignment: WrapAlignment.center, crossAxisAlignment: WrapCrossAlignment.center, children: [
      const Text('By continuing you agree to our ', style: TextStyle(color: AppColors.muted, fontSize: 13)),
      GestureDetector(onTap: () => open('/terms'), child: const Text('Terms', style: TextStyle(color: AppColors.brandDark, fontSize: 13, fontWeight: FontWeight.w600))),
      const Text(' and ', style: TextStyle(color: AppColors.muted, fontSize: 13)),
      GestureDetector(
          onTap: () => open('/privacy'), child: const Text('Privacy policy', style: TextStyle(color: AppColors.brandDark, fontSize: 13, fontWeight: FontWeight.w600))),
    ]);
  }
}
