class StudentSummary {
  const StudentSummary({
    required this.id,
    required this.fullName,
    required this.iin,
    required this.classroom,
    required this.school,
    required this.benefit,
    required this.hasPhoto,
  });

  factory StudentSummary.fromJson(Map<String, dynamic> json) {
    return StudentSummary(
      id: _asInt(json['id']),
      fullName: json['full_name'] as String? ?? '',
      iin: json['iin'] as String? ?? '',
      classroom: json['classroom'] as String? ?? '',
      school: json['school'] as String? ?? '',
      benefit: json['benefit'] as String? ?? '',
      hasPhoto: json['photo'] as bool? ?? false,
    );
  }

  final int id;
  final String fullName;
  final String iin;
  final String classroom;
  final String school;
  final String benefit;
  final bool hasPhoto;

  static List<StudentSummary> listFromJson(dynamic value) {
    if (value is! List) {
      return const [];
    }

    return value
        .whereType<Map<String, dynamic>>()
        .map(StudentSummary.fromJson)
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
