@extends('layouts.app')

@section('content')
    <style>
        .student-edit-page {
            padding: 24px 0;
            display: grid;
            gap: 18px;
        }

        .student-edit-card {
            background: #fff;
            border: 1px solid #d1d8e5;
            border-radius: 20px;
            box-shadow: 0 12px 32px rgba(35, 64, 103, 0.08);
        }

        .student-edit-header {
            padding: 24px;
            border-bottom: 1px solid #e4e9f1;
        }

        .student-edit-title {
            margin: 8px 0 0;
            font-size: 30px;
            line-height: 1.1;
        }

        .student-edit-body {
            padding: 24px;
        }

        .student-edit-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .student-edit-field {
            display: grid;
            gap: 6px;
        }

        .student-edit-field.full {
            grid-column: 1 / -1;
        }

        .student-edit-field label {
            font-size: 13px;
            font-weight: 700;
            color: #4e607d;
        }

        .student-edit-field input,
        .student-edit-field select,
        .student-edit-field textarea {
            width: 100%;
            min-height: 44px;
            padding: 10px 12px;
            border: 1px solid #d1d8e5;
            border-radius: 12px;
            background: #fff;
            color: #16253d;
        }

        .student-edit-field textarea {
            min-height: 110px;
            resize: vertical;
        }

        .student-edit-actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .student-edit-note {
            margin-top: 10px;
            color: #71829a;
            font-size: 14px;
        }

        .student-orders-card {
            background: #fff;
            border: 1px solid #d1d8e5;
            border-radius: 20px;
            box-shadow: 0 12px 32px rgba(35, 64, 103, 0.08);
            overflow: hidden;
        }

        .student-orders-header {
            padding: 24px;
            border-bottom: 1px solid #e4e9f1;
        }

        .student-orders-title {
            margin: 0;
            font-size: 24px;
            line-height: 1.2;
        }

        .student-orders-count {
            margin-top: 8px;
            color: #71829a;
            font-size: 14px;
        }

        .student-orders-table-wrap {
            overflow-x: auto;
        }

        .student-orders-table {
            width: 100%;
            min-width: 760px;
            border-collapse: collapse;
        }

        .student-orders-table th,
        .student-orders-table td {
            padding: 14px 18px;
            border-bottom: 1px solid #e8edf5;
            text-align: left;
            vertical-align: top;
        }

        .student-orders-table th {
            background: #f7f9fc;
            color: #4e607d;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .student-orders-empty {
            padding: 24px;
            color: #71829a;
        }

        .student-orders-status {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 999px;
            background: #eef5ff;
            color: #1f5cb8;
            font-size: 12px;
            font-weight: 700;
            line-height: 1;
        }

        .student-orders-status.inactive {
            background: #f0f2f6;
            color: #697991;
        }

        @media (max-width: 820px) {
            .student-edit-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <section class="student-edit-page">
        <div class="student-edit-card">
            <div class="student-edit-header">
                <div class="muted">{{ __('ui.common.home') }} / <a href="{{ route('students.index') }}">{{ __('ui.menu.students') }}</a></div>
                <h1 class="student-edit-title">{{ $student->full_name ?: __('admin.labels.student') }}</h1>
                <div class="student-edit-note">
                    {{ __('admin.labels.status') }}:
                    {{ $student->latestMealBenefit?->type ? str_replace('_', ' ', ucfirst($student->latestMealBenefit->type)) : '-' }}
                </div>
            </div>

            <div class="student-edit-body">
                <form method="POST" action="{{ route('students.update', $student) }}">
                    @csrf
                    @method('PUT')

                    <div class="student-edit-grid">
                        <div class="student-edit-field">
                            <label for="iin">{{ __('admin.labels.iin') }}</label>
                            <input id="iin" name="iin" type="text" maxlength="12" value="{{ old('iin', $student->iin) }}">
                        </div>

                        <div class="student-edit-field">
                            <label for="student_number">{{ __('admin.labels.student_number') }}</label>
                            <input id="student_number" name="student_number" type="text" value="{{ old('student_number', $student->student_number) }}">
                        </div>

                        <div class="student-edit-field">
                            <label for="last_name">{{ __('admin.labels.last_name') }}</label>
                            <input id="last_name" name="last_name" type="text" value="{{ old('last_name', $student->last_name) }}">
                        </div>

                        <div class="student-edit-field">
                            <label for="first_name">{{ __('admin.labels.first_name') }}</label>
                            <input id="first_name" name="first_name" type="text" value="{{ old('first_name', $student->first_name) }}">
                        </div>

                        <div class="student-edit-field">
                            <label for="middle_name">{{ __('admin.labels.middle_name') }}</label>
                            <input id="middle_name" name="middle_name" type="text" value="{{ old('middle_name', $student->middle_name) }}">
                        </div>

                        <div class="student-edit-field">
                            <label for="birth_date">{{ __('admin.labels.birth_date') }}</label>
                            <input id="birth_date" name="birth_date" type="date" value="{{ old('birth_date', optional($student->birth_date)->format('Y-m-d')) }}">
                        </div>

                        <div class="student-edit-field">
                            <label for="gender">{{ __('admin.labels.gender') }}</label>
                            <select id="gender" name="gender">
                                <option value="">-</option>
                                <option value="male" @selected(old('gender', $student->gender) === 'male')>{{ __('admin.labels.male') }}</option>
                                <option value="female" @selected(old('gender', $student->gender) === 'female')>{{ __('admin.labels.female') }}</option>
                            </select>
                        </div>

                        <div class="student-edit-field">
                            <label for="phone">{{ __('admin.labels.phone') }}</label>
                            <input id="phone" name="phone" type="text" value="{{ old('phone', $student->phone) }}">
                        </div>

                        <div class="student-edit-field">
                            <label for="classroom_id">{{ __('admin.labels.class_full_name') }}</label>
                            <select id="classroom_id" name="classroom_id">
                                <option value="">-</option>
                                @foreach ($classrooms as $classroom)
                                    <option value="{{ $classroom->id }}" @selected((string) old('classroom_id', $student->classroom_id) === (string) $classroom->id)>
                                        {{ $classroom->full_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="student-edit-field">
                            <label for="school_id">{{ __('admin.labels.organization') }}</label>
                            <select id="school_id" name="school_id">
                                <option value="">-</option>
                                @foreach ($schools as $school)
                                    <option value="{{ $school->id }}" @selected((string) old('school_id', $student->school_id) === (string) $school->id)>
                                        {{ $school->display_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="student-edit-field">
                            <label for="language">{{ __('admin.labels.language') }}</label>
                            <select id="language" name="language">
                                <option value="">-</option>
                                <option value="ru" @selected(old('language', $student->language) === 'ru')>RU</option>
                                <option value="kk" @selected(old('language', $student->language) === 'kk')>KK</option>
                            </select>
                        </div>

                        <div class="student-edit-field">
                            <label for="shift">{{ __('admin.labels.shift') }}</label>
                            <select id="shift" name="shift">
                                <option value="">-</option>
                                <option value="1" @selected((string) old('shift', $student->shift) === '1')>1</option>
                                <option value="2" @selected((string) old('shift', $student->shift) === '2')>2</option>
                            </select>
                        </div>

                        <div class="student-edit-field">
                            <label for="school_year">{{ __('admin.labels.school_year') }}</label>
                            <input id="school_year" name="school_year" type="text" value="{{ old('school_year', $student->school_year) }}">
                        </div>

                        <div class="student-edit-field">
                            <label for="status">{{ __('admin.labels.status') }}</label>
                            <select id="status" name="status">
                                <option value="">-</option>
                                <option value="active" @selected(old('status', $student->status) === 'active')>{{ __('admin.status.active') }}</option>
                                <option value="archived" @selected(old('status', $student->status) === 'archived')>{{ __('admin.labels.archived') }}</option>
                            </select>
                        </div>

                        <div class="student-edit-field full">
                            <label for="address">{{ __('admin.labels.address') }}</label>
                            <textarea id="address" name="address">{{ old('address', $student->address) }}</textarea>
                        </div>
                    </div>

                    <div class="student-edit-actions">
                        <button class="btn" type="submit">{{ __('ui.common.save') }}</button>
                        <a class="btn secondary" href="{{ route('students.index') }}">{{ __('ui.common.back') }}</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="student-orders-card">
            <div class="student-orders-header">
                <h2 class="student-orders-title">{{ __('ui.menu.orders') }}</h2>
                <div class="student-orders-count">{{ __('ui.menu.orders') }}: {{ $student->orders->count() }}</div>
            </div>

            @if ($student->orders->isEmpty())
                <div class="student-orders-empty">{{ __('ui.menu.orders') }}: 0</div>
            @else
                <div class="student-orders-table-wrap">
                    <table class="student-orders-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>{{ __('admin.labels.dish') }}</th>
                                <th>{{ __('ui.orders.date') }}</th>
                                <th>{{ __('ui.common.time') }}</th>
                                <th>{{ __('admin.labels.status') }}</th>
                                <th>{{ __('ui.orders.transaction_status') }}</th>
                                <th>{{ __('ui.orders.transaction_error') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($student->orders as $order)
                                <tr>
                                    <td>{{ $order->id }}</td>
                                    <td>{{ $order->dish?->name ?: '-' }}</td>
                                    <td>{{ optional($order->order_date)->format('Y-m-d') ?: '-' }}</td>
                                    <td>{{ $order->order_time ? substr($order->order_time, 0, 5) : '-' }}</td>
                                    <td>{{ $order->status ?: '-' }}</td>
                                    <td>
                                        @if ($order->transaction_status === null)
                                            -
                                        @else
                                            <span class="student-orders-status {{ $order->transaction_status ? '' : 'inactive' }}">
                                                {{ $order->transaction_status ? __('ui.common.active') : __('ui.common.inactive') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td>{{ $order->transaction_error ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
@endsection
