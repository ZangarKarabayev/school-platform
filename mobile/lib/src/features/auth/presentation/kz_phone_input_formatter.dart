import 'package:flutter/services.dart';

class KzPhoneInputFormatter extends TextInputFormatter {
  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    final digits = newValue.text.replaceAll(RegExp(r'\D'), '');
    if (digits.isEmpty) {
      return const TextEditingValue(text: '+7 ');
    }

    var normalized = digits;
    if (normalized.startsWith('8')) {
      normalized = '7${normalized.substring(1)}';
    }

    if (!normalized.startsWith('7')) {
      normalized = '7$normalized';
    }

    if (normalized.length > 11) {
      normalized = normalized.substring(0, 11);
    }

    final buffer = StringBuffer('+${normalized[0]}');

    if (normalized.length > 1) {
      buffer.write(' ');
      buffer.write(normalized.substring(1, normalized.length.clamp(1, 4)));
    }
    if (normalized.length > 4) {
      buffer.write(' ');
      buffer.write(normalized.substring(4, normalized.length.clamp(4, 7)));
    }
    if (normalized.length > 7) {
      buffer.write(' ');
      buffer.write(normalized.substring(7, normalized.length.clamp(7, 9)));
    }
    if (normalized.length > 9) {
      buffer.write(' ');
      buffer.write(normalized.substring(9, normalized.length.clamp(9, 11)));
    }

    final text = buffer.toString();
    return TextEditingValue(
      text: text,
      selection: TextSelection.collapsed(offset: text.length),
    );
  }
}
