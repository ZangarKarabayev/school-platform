import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/src/app.dart';

void main() {
  testWidgets('renders auth screen', (tester) async {
    await tester.pumpWidget(const SchoolPlatformApp());

    expect(find.text('School Platform'), findsOneWidget);
    expect(find.text('Телефон'), findsOneWidget);
    expect(find.text('Пароль'), findsWidgets);
    expect(find.text('SMS-код'), findsOneWidget);
    expect(find.text('Войти'), findsOneWidget);
  });
}
