class DashboardSummary {
  const DashboardSummary({
    required this.filters,
    required this.stats,
    required this.transactions,
    required this.classGroups,
    required this.benefits,
    required this.coverage,
    required this.ordersBySchool,
    required this.ordersByDistrict,
  });

  factory DashboardSummary.fromJson(Map<String, dynamic> json) {
    final charts = json['charts'] as Map<String, dynamic>? ?? const {};

    return DashboardSummary(
      filters: DashboardFilters.fromJson(
        json['filters'] as Map<String, dynamic>? ?? const {},
      ),
      stats: DashboardStats.fromJson(
        json['stats'] as Map<String, dynamic>? ?? const {},
      ),
      transactions: DashboardEntry.listFromJson(charts['transactions']),
      classGroups: DashboardEntry.listFromJson(charts['class_groups']),
      benefits: DashboardEntry.listFromJson(charts['benefits']),
      coverage: DashboardEntry.listFromJson(charts['coverage']),
      ordersBySchool: DashboardEntry.listFromJson(charts['orders_by_school']),
      ordersByDistrict: DashboardEntry.listFromJson(charts['orders_by_district']),
    );
  }

  final DashboardFilters filters;
  final DashboardStats stats;
  final List<DashboardEntry> transactions;
  final List<DashboardEntry> classGroups;
  final List<DashboardEntry> benefits;
  final List<DashboardEntry> coverage;
  final List<DashboardEntry> ordersBySchool;
  final List<DashboardEntry> ordersByDistrict;
}

class DashboardFilters {
  const DashboardFilters({required this.dateFrom, required this.dateTo});

  factory DashboardFilters.fromJson(Map<String, dynamic> json) {
    return DashboardFilters(
      dateFrom: json['date_from'] as String? ?? '',
      dateTo: json['date_to'] as String? ?? '',
    );
  }

  final String dateFrom;
  final String dateTo;
}

class DashboardStats {
  const DashboardStats({
    required this.ordersCount,
    required this.successCount,
    required this.failedCount,
    required this.errorCount,
  });

  factory DashboardStats.fromJson(Map<String, dynamic> json) {
    return DashboardStats(
      ordersCount: _asInt(json['orders_count']),
      successCount: _asInt(json['success_count']),
      failedCount: _asInt(json['failed_count']),
      errorCount: _asInt(json['error_count']),
    );
  }

  final int ordersCount;
  final int successCount;
  final int failedCount;
  final int errorCount;
}

class DashboardEntry {
  const DashboardEntry({
    required this.label,
    required this.value,
    this.color,
  });

  factory DashboardEntry.fromJson(Map<String, dynamic> json) {
    return DashboardEntry(
      label: json['label'] as String? ?? '',
      value: _asInt(json['value']),
      color: json['color'] as String?,
    );
  }

  final String label;
  final int value;
  final String? color;

  static List<DashboardEntry> listFromJson(dynamic value) {
    if (value is! List) {
      return const [];
    }

    return value
        .whereType<Map<String, dynamic>>()
        .map(DashboardEntry.fromJson)
        .toList(growable: false);
  }
}

int _asInt(dynamic value) {
  if (value is int) {
    return value;
  }

  if (value is num) {
    return value.toInt();
  }

  if (value is String) {
    return int.tryParse(value) ?? 0;
  }

  return 0;
}
