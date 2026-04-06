class UserProfile {
  const UserProfile({
    required this.id,
    required this.fullName,
    required this.phone,
    required this.status,
    required this.preferredLocale,
    required this.roles,
    required this.permissions,
    required this.scopes,
  });

  factory UserProfile.fromJson(Map<String, dynamic> json) {
    return UserProfile(
      id: json['id'] as int? ?? 0,
      fullName: json['full_name'] as String? ?? '',
      phone: json['phone'] as String? ?? '',
      status: json['status'] as String? ?? '',
      preferredLocale: json['preferred_locale'] as String? ?? 'ru',
      roles: _readStringList(json['roles']),
      permissions: _readStringList(json['permissions']),
      scopes: _readScopeList(json['scopes']),
    );
  }

  final int id;
  final String fullName;
  final String phone;
  final String status;
  final String preferredLocale;
  final List<String> roles;
  final List<String> permissions;
  final List<UserScope> scopes;

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'full_name': fullName,
      'phone': phone,
      'status': status,
      'preferred_locale': preferredLocale,
      'roles': roles,
      'permissions': permissions,
      'scopes': scopes.map((scope) => scope.toJson()).toList(growable: false),
    };
  }

  static List<String> _readStringList(dynamic value) {
    if (value is! List) {
      return const [];
    }

    return value.whereType<String>().toList(growable: false);
  }

  static List<UserScope> _readScopeList(dynamic value) {
    if (value is! List) {
      return const [];
    }

    return value
        .whereType<Map<String, dynamic>>()
        .map(UserScope.fromJson)
        .toList(growable: false);
  }
}

class UserScope {
  const UserScope({required this.type, required this.id});

  factory UserScope.fromJson(Map<String, dynamic> json) {
    return UserScope(
      type: json['type'] as String? ?? '',
      id: json['id'] as int? ?? 0,
    );
  }

  final String type;
  final int id;

  Map<String, dynamic> toJson() {
    return {'type': type, 'id': id};
  }
}
