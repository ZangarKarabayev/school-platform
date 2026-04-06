import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../domain/dashboard_summary.dart';
import '../domain/student_summary.dart';
import 'auth_controller.dart';
import 'kz_phone_input_formatter.dart';

class AuthGate extends StatelessWidget {
  const AuthGate({required this.controller, super.key});

  final AuthController controller;

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: controller,
      builder: (context, _) {
        return switch (controller.step) {
          AuthStep.authenticated => MainShell(controller: controller),
          _ => AuthScreen(controller: controller),
        };
      },
    );
  }
}

class AuthScreen extends StatefulWidget {
  const AuthScreen({required this.controller, super.key});

  final AuthController controller;

  @override
  State<AuthScreen> createState() => _AuthScreenState();
}

class _AuthScreenState extends State<AuthScreen> {
  late final TextEditingController _phoneController;
  late final TextEditingController _otpController;
  late final TextEditingController _passwordController;
  bool _obscurePassword = true;

  @override
  void initState() {
    super.initState();
    _phoneController = TextEditingController(text: '+7 ');
    _otpController = TextEditingController();
    _passwordController = TextEditingController();
  }

  @override
  void dispose() {
    _phoneController.dispose();
    _otpController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final controller = widget.controller;

    return Scaffold(
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [Color(0xFFDDE7F3), Color(0xFFE7EDF6)],
          ),
        ),
        child: Stack(
          children: [
            Positioned(
              top: -120,
              left: -40,
              child: Container(
                width: 260,
                height: 260,
                decoration: const BoxDecoration(
                  shape: BoxShape.circle,
                  color: Color(0x1A2876DD),
                ),
              ),
            ),
            SafeArea(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(24),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 480),
                    child: Card(
                      child: Padding(
                        padding: const EdgeInsets.all(24),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 18,
                                vertical: 16,
                              ),
                              decoration: BoxDecoration(
                                color: const Color(0xFF2876DD),
                                borderRadius: BorderRadius.circular(18),
                              ),
                              child: Row(
                                children: [
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          'School Platform',
                                          style: theme.textTheme.headlineSmall
                                              ?.copyWith(
                                                color: Colors.white,
                                                fontWeight: FontWeight.w700,
                                              ),
                                        ),
                                        const SizedBox(height: 6),
                                        Text(
                                          controller.step == AuthStep.otp
                                              ? 'Подтвердите вход одноразовым кодом.'
                                              : 'Вход для родителя, сотрудника или администратора.',
                                          style: theme.textTheme.bodyMedium
                                              ?.copyWith(
                                                color: const Color(0xFFEAF2FF),
                                              ),
                                        ),
                                      ],
                                    ),
                                  ),
                                  const Icon(
                                    Icons.account_balance,
                                    color: Colors.white,
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(height: 20),
                            if (controller.step == AuthStep.phone) ...[
                              _MethodSwitch(controller: controller),
                              const SizedBox(height: 20),
                              TextField(
                                controller: _phoneController,
                                keyboardType: TextInputType.phone,
                                inputFormatters: [KzPhoneInputFormatter()],
                                decoration: const InputDecoration(
                                  labelText: 'Телефон',
                                  hintText: '+7 777 777 77 77',
                                  prefixIcon: Icon(Icons.phone_outlined),
                                ),
                              ),
                              const SizedBox(height: 16),
                              if (controller.loginMethod ==
                                  LoginMethod.password)
                                TextField(
                                  controller: _passwordController,
                                  obscureText: _obscurePassword,
                                  decoration: InputDecoration(
                                    labelText: 'Пароль',
                                    hintText: 'Введите пароль',
                                    prefixIcon: const Icon(Icons.lock_outline),
                                    suffixIcon: IconButton(
                                      onPressed: () {
                                        setState(() {
                                          _obscurePassword = !_obscurePassword;
                                        });
                                      },
                                      icon: Icon(
                                        _obscurePassword
                                            ? Icons.visibility_outlined
                                            : Icons.visibility_off_outlined,
                                      ),
                                    ),
                                  ),
                                )
                              else
                                _OtpHintCard(
                                  onPressed: controller.isBusy
                                      ? null
                                      : () => controller.requestOtp(
                                          _phoneController.text,
                                        ),
                                ),
                              const SizedBox(height: 16),
                              FilledButton(
                                onPressed: controller.isBusy
                                    ? null
                                    : controller.loginMethod ==
                                          LoginMethod.password
                                    ? () => controller.loginWithPassword(
                                        rawPhone: _phoneController.text,
                                        password: _passwordController.text,
                                      )
                                    : () => controller.requestOtp(
                                        _phoneController.text,
                                      ),
                                child: _BusyLabel(
                                  isBusy: controller.isBusy,
                                  text:
                                      controller.loginMethod ==
                                          LoginMethod.password
                                      ? 'Войти'
                                      : 'Получить код',
                                ),
                              ),
                            ] else ...[
                              _SummaryRow(
                                label: 'Телефон',
                                value: controller.phone,
                              ),
                              const SizedBox(height: 16),
                              TextField(
                                controller: _otpController,
                                keyboardType: TextInputType.number,
                                inputFormatters: [
                                  FilteringTextInputFormatter.digitsOnly,
                                  LengthLimitingTextInputFormatter(6),
                                ],
                                decoration: const InputDecoration(
                                  labelText: 'Код из SMS',
                                  hintText: '123456',
                                  prefixIcon: Icon(Icons.password_outlined),
                                ),
                              ),
                              if (controller.debugCode != null) ...[
                                const SizedBox(height: 12),
                                Container(
                                  padding: const EdgeInsets.all(12),
                                  decoration: BoxDecoration(
                                    color: const Color(0xFFEDF4FF),
                                    borderRadius: BorderRadius.circular(16),
                                  ),
                                  child: Text(
                                    'Тестовый код: ${controller.debugCode}',
                                    style: theme.textTheme.bodyMedium?.copyWith(
                                      color: const Color(0xFF22518F),
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                ),
                              ],
                              const SizedBox(height: 16),
                              FilledButton(
                                onPressed: controller.isBusy
                                    ? null
                                    : () => controller.verifyOtp(
                                        _otpController.text,
                                      ),
                                child: _BusyLabel(
                                  isBusy: controller.isBusy,
                                  text: 'Подтвердить',
                                ),
                              ),
                              const SizedBox(height: 12),
                              TextButton(
                                onPressed: controller.isBusy
                                    ? null
                                    : controller.resetToPhone,
                                child: const Text('Изменить телефон'),
                              ),
                            ],
                            if (controller.errorMessage != null) ...[
                              const SizedBox(height: 16),
                              _FeedbackBox(
                                color: const Color(0xFFFFF1F3),
                                textColor: const Color(0xFFD73D56),
                                text: controller.errorMessage!,
                              ),
                            ],
                            if (controller.infoMessage != null) ...[
                              const SizedBox(height: 16),
                              _FeedbackBox(
                                color: const Color(0xFFEDF4FF),
                                textColor: const Color(0xFF22518F),
                                text: controller.infoMessage!,
                              ),
                            ],
                            const SizedBox(height: 22),
                            Text(
                              'API: ${const String.fromEnvironment('API_BASE_URL', defaultValue: 'http://10.0.2.2:8000/api/v1')}',
                              style: theme.textTheme.bodySmall?.copyWith(
                                color: const Color(0xFF6E7F97),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class MainShell extends StatefulWidget {
  const MainShell({required this.controller, super.key});

  final AuthController controller;

  @override
  State<MainShell> createState() => _MainShellState();
}

class _MainShellState extends State<MainShell> {
  int _currentIndex = 0;

  @override
  Widget build(BuildContext context) {
    final pages = <Widget>[
      HomeScreen(controller: widget.controller),
      StudentsScreen(controller: widget.controller),
      const QrScreen(),
      ProfileScreen(controller: widget.controller),
    ];

    return Scaffold(
      body: IndexedStack(index: _currentIndex, children: pages),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _currentIndex,
        onDestinationSelected: (index) {
          setState(() {
            _currentIndex = index;
          });
          if (index == 1 && widget.controller.students.isEmpty) {
            widget.controller.refreshStudents();
          }
        },
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.home_outlined),
            selectedIcon: Icon(Icons.home),
            label: 'Главная',
          ),
          NavigationDestination(
            icon: Icon(Icons.groups_outlined),
            selectedIcon: Icon(Icons.groups),
            label: 'Ученики',
          ),
          NavigationDestination(
            icon: Icon(Icons.qr_code_2_outlined),
            selectedIcon: Icon(Icons.qr_code_2),
            label: 'QR',
          ),
          NavigationDestination(
            icon: Icon(Icons.person_outline),
            selectedIcon: Icon(Icons.person),
            label: 'Профиль',
          ),
        ],
      ),
    );
  }
}

class StudentsScreen extends StatefulWidget {
  const StudentsScreen({required this.controller, super.key});

  final AuthController controller;

  @override
  State<StudentsScreen> createState() => _StudentsScreenState();
}

class _StudentsScreenState extends State<StudentsScreen> {
  late final TextEditingController _searchController;

  @override
  void initState() {
    super.initState();
    _searchController = TextEditingController(text: widget.controller.studentsSearch);

    if (widget.controller.students.isEmpty && !widget.controller.isStudentsLoading) {
      widget.controller.refreshStudents();
    }
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final controller = widget.controller;
    final students = controller.students;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Ученики'),
        actions: [
          IconButton(
            onPressed: controller.isStudentsLoading
                ? null
                : () => controller.refreshStudents(search: controller.studentsSearch),
            tooltip: 'Обновить список',
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                children: [
                  TextField(
                    controller: _searchController,
                    textInputAction: TextInputAction.search,
                    onSubmitted: (value) => controller.refreshStudents(search: value),
                    decoration: InputDecoration(
                      labelText: 'Поиск ученика',
                      hintText: 'ФИО, ИИН, номер',
                      prefixIcon: const Icon(Icons.search),
                      suffixIcon: IconButton(
                        onPressed: controller.isStudentsLoading
                            ? null
                            : () {
                                _searchController.clear();
                                controller.refreshStudents(search: '');
                              },
                        icon: const Icon(Icons.close),
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton(
                      onPressed: controller.isStudentsLoading
                          ? null
                          : () => controller.refreshStudents(
                              search: _searchController.text,
                            ),
                      child: _BusyLabel(
                        isBusy: controller.isStudentsLoading,
                        text: 'Найти',
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
          if (controller.studentsErrorMessage != null) ...[
            const SizedBox(height: 16),
            _FeedbackBox(
              color: const Color(0xFFFFF1F3),
              textColor: const Color(0xFFD73D56),
              text: controller.studentsErrorMessage!,
            ),
          ],
          const SizedBox(height: 16),
          _ProfileCard(
            title: 'Список',
            child: students.isEmpty
                ? Text(
                    controller.isStudentsLoading
                        ? 'Загрузка...'
                        : 'Ученики не найдены.',
                  )
                : Column(
                    children: students
                        .map((student) => _StudentCard(student: student))
                        .toList(growable: false),
                  ),
          ),
        ],
      ),
    );
  }
}

class _StudentCard extends StatelessWidget {
  const _StudentCard({required this.student});

  final StudentSummary student;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFFF5F8FD),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFD4DDEA)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  student.fullName.isEmpty ? 'Без имени' : student.fullName,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                decoration: BoxDecoration(
                  color: student.hasPhoto
                      ? const Color(0xFFE9F7EE)
                      : const Color(0xFFFFF4E5),
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Text(
                  student.hasPhoto ? 'Фото есть' : 'Без фото',
                  style: TextStyle(
                    color: student.hasPhoto
                        ? const Color(0xFF2F9E44)
                        : const Color(0xFFB7791F),
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          _InfoRow(label: 'ИИН', value: student.iin),
          const SizedBox(height: 10),
          _InfoRow(label: 'Класс', value: student.classroom),
          const SizedBox(height: 10),
          _InfoRow(label: 'Школа', value: student.school),
          const SizedBox(height: 10),
          _InfoRow(label: 'Льгота', value: student.benefit),
        ],
      ),
    );
  }
}

class HomeScreen extends StatelessWidget {
  const HomeScreen({required this.controller, super.key});

  final AuthController controller;

  @override
  Widget build(BuildContext context) {
    final user = controller.user;
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Главная'),
        actions: [
          IconButton(
            onPressed: controller.isBusy ? null : controller.refreshProfile,
            tooltip: 'Обновить профиль',
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: user == null
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(24),
              children: [
                Container(
                  padding: const EdgeInsets.all(24),
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      colors: [Color(0xFF2876DD), Color(0xFF22518F)],
                    ),
                    borderRadius: BorderRadius.circular(28),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        user.fullName.isEmpty
                            ? 'Новый пользователь'
                            : user.fullName,
                        style: theme.textTheme.headlineSmall?.copyWith(
                          color: Colors.white,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        user.phone,
                        style: theme.textTheme.bodyLarge?.copyWith(
                          color: const Color(0xFFEAF2FF),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 24),
                _DashboardOverview(controller: controller),
                if (controller.infoMessage != null) ...[
                  const SizedBox(height: 16),
                  _FeedbackBox(
                    color: const Color(0xFFEDF4FF),
                    textColor: const Color(0xFF22518F),
                    text: controller.infoMessage!,
                  ),
                ],
                if (controller.errorMessage != null) ...[
                  const SizedBox(height: 16),
                  _FeedbackBox(
                    color: const Color(0xFFFFF1F3),
                    textColor: const Color(0xFFD73D56),
                    text: controller.errorMessage!,
                  ),
                ],
              ],
            ),
    );
  }
}

class _DashboardOverview extends StatelessWidget {
  const _DashboardOverview({required this.controller});

  final AuthController controller;

  @override
  Widget build(BuildContext context) {
    final summary = controller.dashboardSummary;

    if (controller.isDashboardLoading && summary == null) {
      return const Card(
        child: Padding(
          padding: EdgeInsets.all(24),
          child: Center(child: CircularProgressIndicator()),
        ),
      );
    }

    if (summary == null) {
      return _ProfileCard(
        title: 'Табло заказов',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(controller.dashboardErrorMessage ?? 'Данные табло пока недоступны.'),
            const SizedBox(height: 12),
            FilledButton(
              onPressed: controller.isDashboardLoading
                  ? null
                  : controller.refreshDashboard,
              child: const Text('Обновить табло'),
            ),
          ],
        ),
      );
    }

    return Column(
      children: [
        _ProfileCard(
          title: 'Табло заказов',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Период: ${summary.filters.dateFrom} - ${summary.filters.dateTo}',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: const Color(0xFF5D6F88),
                ),
              ),
              const SizedBox(height: 16),
              GridView.count(
                crossAxisCount: 2,
                crossAxisSpacing: 12,
                mainAxisSpacing: 12,
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                childAspectRatio: 1.45,
                children: [
                  _DashboardStatTile(
                    label: 'Всего заказов',
                    value: summary.stats.ordersCount,
                    color: const Color(0xFF2876DD),
                  ),
                  _DashboardStatTile(
                    label: 'Успешные',
                    value: summary.stats.successCount,
                    color: const Color(0xFF2F9E44),
                  ),
                  _DashboardStatTile(
                    label: 'Неуспешные',
                    value: summary.stats.failedCount,
                    color: const Color(0xFFD9485F),
                  ),
                  _DashboardStatTile(
                    label: 'Ошибки',
                    value: summary.stats.errorCount,
                    color: const Color(0xFFF59F00),
                  ),
                ],
              ),
              if (controller.dashboardErrorMessage != null) ...[
                const SizedBox(height: 12),
                Text(
                  controller.dashboardErrorMessage!,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: const Color(0xFFD9485F),
                  ),
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: 16),
        _DashboardEntryCard(title: 'Охват учеников', items: summary.coverage),
        const SizedBox(height: 16),
        _DashboardEntryCard(title: 'Льготы', items: summary.benefits),
        const SizedBox(height: 16),
        _DashboardEntryCard(title: 'Классы', items: summary.classGroups),
        const SizedBox(height: 16),
        _DashboardEntryCard(title: 'Транзакции', items: summary.transactions),
        const SizedBox(height: 16),
        _DashboardEntryCard(
          title: 'Школы',
          items: summary.ordersBySchool.take(5).toList(growable: false),
        ),
        const SizedBox(height: 16),
        _DashboardEntryCard(
          title: 'Районы',
          items: summary.ordersByDistrict.take(5).toList(growable: false),
        ),
      ],
    );
  }
}

class _DashboardStatTile extends StatelessWidget {
  const _DashboardStatTile({
    required this.label,
    required this.value,
    required this.color,
  });

  final String label;
  final int value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: color.withValues(alpha: 0.2)),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(
              '$value',
              style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                fontWeight: FontWeight.w800,
                color: const Color(0xFF17253C),
              ),
            ),
            const SizedBox(height: 6),
            Text(
              label,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: const Color(0xFF5D6F88),
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DashboardEntryCard extends StatelessWidget {
  const _DashboardEntryCard({required this.title, required this.items});

  final String title;
  final List<DashboardEntry> items;

  @override
  Widget build(BuildContext context) {
    final maxValue = items.fold<int>(0, (max, item) => item.value > max ? item.value : max);

    return _ProfileCard(
      title: title,
      child: items.isEmpty
          ? const Text('Нет данных')
          : Column(
              children: items
                  .map(
                    (item) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _DashboardProgressRow(
                        label: item.label,
                        value: item.value,
                        color: _parseDashboardColor(item.color),
                        progress: maxValue == 0 ? 0 : item.value / maxValue,
                      ),
                    ),
                  )
                  .toList(growable: false),
            ),
    );
  }
}

class _DashboardProgressRow extends StatelessWidget {
  const _DashboardProgressRow({
    required this.label,
    required this.value,
    required this.color,
    required this.progress,
  });

  final String label;
  final int value;
  final Color color;
  final double progress;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                label,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
            const SizedBox(width: 12),
            Text(
              '$value',
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: const Color(0xFF5D6F88),
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        ClipRRect(
          borderRadius: BorderRadius.circular(999),
          child: LinearProgressIndicator(
            value: progress.clamp(0, 1),
            minHeight: 10,
            backgroundColor: const Color(0xFFE8EEF7),
            valueColor: AlwaysStoppedAnimation<Color>(color),
          ),
        ),
      ],
    );
  }
}

Color _parseDashboardColor(String? value) {
  if (value == null) {
    return const Color(0xFF2876DD);
  }

  final normalized = value.replaceFirst('#', '');
  if (normalized.length != 6) {
    return const Color(0xFF2876DD);
  }

  final parsed = int.tryParse(normalized, radix: 16);
  if (parsed == null) {
    return const Color(0xFF2876DD);
  }

  return Color(0xFF000000 | parsed);
}

class QrScreen extends StatelessWidget {
  const QrScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('QR / Пропуск')),
      body: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 420),
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Card(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 180,
                      height: 180,
                      decoration: BoxDecoration(
                        color: const Color(0xFFF5F8FD),
                        borderRadius: BorderRadius.circular(24),
                      ),
                      child: const Icon(
                        Icons.qr_code_2,
                        size: 112,
                        color: Color(0xFF2876DD),
                      ),
                    ),
                    const SizedBox(height: 20),
                    Text(
                      'Модуль QR готов к следующему этапу.',
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Здесь можно показать пропуск ученика, родителя или сотрудника.',
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: const Color(0xFF5D6F88),
                      ),
                      textAlign: TextAlign.center,
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class ProfileScreen extends StatelessWidget {
  const ProfileScreen({required this.controller, super.key});

  final AuthController controller;

  @override
  Widget build(BuildContext context) {
    final user = controller.user;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Профиль'),
        actions: [
          IconButton(
            onPressed: controller.isBusy ? null : controller.refreshProfile,
            tooltip: 'Обновить профиль',
            icon: const Icon(Icons.refresh),
          ),
          IconButton(
            onPressed: controller.isBusy ? null : controller.logout,
            tooltip: 'Выйти',
            icon: const Icon(Icons.logout),
          ),
        ],
      ),
      body: user == null
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(24),
              children: [
                _ProfileCard(
                  title: 'Основные данные',
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _InfoRow(label: 'ФИО', value: user.fullName),
                      const SizedBox(height: 12),
                      _InfoRow(label: 'Телефон', value: user.phone),
                      const SizedBox(height: 12),
                      _InfoRow(label: 'Статус', value: user.status),
                      const SizedBox(height: 12),
                      _InfoRow(label: 'Язык', value: user.preferredLocale),
                    ],
                  ),
                ),
                if (controller.infoMessage != null) ...[
                  const SizedBox(height: 16),
                  _FeedbackBox(
                    color: const Color(0xFFEDF4FF),
                    textColor: const Color(0xFF22518F),
                    text: controller.infoMessage!,
                  ),
                ],
                if (controller.errorMessage != null) ...[
                  const SizedBox(height: 16),
                  _FeedbackBox(
                    color: const Color(0xFFFFF1F3),
                    textColor: const Color(0xFFD73D56),
                    text: controller.errorMessage!,
                  ),
                ],
              ],
            ),
    );
  }
}

class _MethodSwitch extends StatelessWidget {
  const _MethodSwitch({required this.controller});

  final AuthController controller;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: _MethodButton(
            label: 'Пароль',
            selected: controller.loginMethod == LoginMethod.password,
            onTap: controller.isBusy
                ? null
                : () => controller.setLoginMethod(LoginMethod.password),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: _MethodButton(
            label: 'SMS-код',
            selected: controller.loginMethod == LoginMethod.otp,
            onTap: controller.isBusy
                ? null
                : () => controller.setLoginMethod(LoginMethod.otp),
          ),
        ),
      ],
    );
  }
}

class _MethodButton extends StatelessWidget {
  const _MethodButton({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        padding: const EdgeInsets.symmetric(vertical: 14),
        decoration: BoxDecoration(
          color: selected ? const Color(0xFF2876DD) : const Color(0xFFF5F8FD),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: selected ? const Color(0xFF2876DD) : const Color(0xFFD4DDEA),
          ),
        ),
        child: Center(
          child: Text(
            label,
            style: TextStyle(
              color: selected ? Colors.white : const Color(0xFF234067),
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ),
    );
  }
}

class _OtpHintCard extends StatelessWidget {
  const _OtpHintCard({required this.onPressed});

  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFFF5F8FD),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFD4DDEA)),
      ),
      child: Row(
        children: [
          const Icon(Icons.sms_outlined, color: Color(0xFF2876DD)),
          const SizedBox(width: 12),
          const Expanded(
            child: Text('Запросите одноразовый код и подтвердите вход.'),
          ),
          TextButton(onPressed: onPressed, child: const Text('Запросить')),
        ],
      ),
    );
  }
}

class _ProfileCard extends StatelessWidget {
  const _ProfileCard({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: theme.textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 12),
            child,
          ],
        ),
      ),
    );
  }
}


class _SummaryRow extends StatelessWidget {
  const _SummaryRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFFF5F8FD),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: theme.textTheme.bodySmall?.copyWith(
              color: const Color(0xFF6E7F97),
            ),
          ),
          const SizedBox(height: 4),
          Text(
            value,
            style: theme.textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: theme.textTheme.bodySmall?.copyWith(
            color: const Color(0xFF6E7F97),
          ),
        ),
        const SizedBox(height: 4),
        Text(
          value.isEmpty ? 'Не указано' : value,
          style: theme.textTheme.titleMedium?.copyWith(
            fontWeight: FontWeight.w600,
          ),
        ),
      ],
    );
  }
}

class _FeedbackBox extends StatelessWidget {
  const _FeedbackBox({
    required this.color,
    required this.textColor,
    required this.text,
  });

  final Color color;
  final Color textColor;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Text(
        text,
        style: TextStyle(color: textColor, fontWeight: FontWeight.w600),
      ),
    );
  }
}

class _BusyLabel extends StatelessWidget {
  const _BusyLabel({required this.isBusy, required this.text});

  final bool isBusy;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      mainAxisSize: MainAxisSize.min,
      children: [
        if (isBusy) ...[
          const SizedBox(
            width: 16,
            height: 16,
            child: CircularProgressIndicator(
              strokeWidth: 2,
              color: Colors.white,
            ),
          ),
          const SizedBox(width: 12),
        ],
        Flexible(
          child: Text(text, overflow: TextOverflow.ellipsis, softWrap: false),
        ),
      ],
    );
  }
}
