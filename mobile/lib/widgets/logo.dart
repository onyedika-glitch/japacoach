import 'package:flutter/material.dart';

import '../theme.dart';

class Logo extends StatelessWidget {
  const Logo({super.key, this.size = 22});
  final double size;

  @override
  Widget build(BuildContext context) => Row(mainAxisSize: MainAxisSize.min, children: [
        ClipRRect(borderRadius: BorderRadius.circular(size * 0.3), child: Image.asset('assets/icon.png', width: size * 1.4, height: size * 1.4)),
        SizedBox(width: size * 0.4),
        Text.rich(TextSpan(children: [
          TextSpan(text: 'Japa', style: grotesk(size: size)),
          TextSpan(text: 'Coach', style: grotesk(size: size, color: AppColors.brand)),
        ])),
      ]);
}
