# Backend Architecture

## Principles

- Modular monolith on Laravel 12
- API-first for web, mobile, devices, and integrations
- Role-based access with scope-based visibility
- Asynchronous processing for device and integration events

## Core Modules

- `Identity`: phone OTP, EDS login, auth identities, sessions
- `Access`: roles, permissions, user scopes, policies
- `Organizations`: regions, districts, schools
- `Students`: student profiles, guardians, classes, cards
- `Devices`: terminal registry, credentials, heartbeats, callbacks
- `Cafeteria`: menu, meal issuance, wallet, purchases
- `Library`: catalog, items, loans, returns
- `Attendance`: school entry and exit events
- `Notifications`: SMS, push, delivery logs
- `Reporting`: dashboards, exports, tabular reports
- `Audit`: immutable activity and data access logs

## Integration Channels

- Web API for cabinets and Filament back office
- Mobile API for parent and staff applications
- HTTP callbacks for FaceID devices
- MQTT for device telemetry and operational events

## Initial Delivery Stages

1. Identity, Access, Organizations
2. Users, Students, base scopes
3. Devices, FaceID, Attendance
4. Cafeteria, wallet, notifications
5. Library and reporting
