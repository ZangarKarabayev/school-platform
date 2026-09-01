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
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
        }

        .student-edit-heading {
            min-width: 0;
        }

        .student-edit-photo,
        .student-edit-photo-placeholder {
            width: 128px;
            height: 128px;
            flex: 0 0 128px;
            border: 1px solid #d1d8e5;
            border-radius: 20px;
            background: #eef4fc;
            box-shadow: 0 8px 22px rgba(35, 64, 103, 0.12);
        }

        .student-edit-photo {
            display: block;
            object-fit: cover;
        }

        .student-edit-photo-placeholder {
            display: grid;
            place-items: center;
            color: #315b91;
            font-size: 42px;
            font-weight: 800;
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

        .classroom-combobox {
            position: relative;
            width: 100%;
        }

        .classroom-combobox-options {
            position: absolute;
            z-index: 100;
            top: calc(100% + 6px);
            left: 0;
            width: 100%;
            min-width: 100%;
            max-height: 240px;
            padding: 6px;
            overflow-y: auto;
            border: 1px solid #d1d8e5;
            border-radius: 12px;
            background: #fff !important;
            color: #000;
            box-shadow: 0 14px 30px rgba(17, 35, 62, 0.2);
        }

        .classroom-combobox-options[hidden],
        .classroom-combobox-option[hidden] {
            display: none;
        }

        .classroom-combobox-option {
            display: block;
            width: 100%;
            padding: 9px 10px;
            border: 0;
            border-radius: 8px;
            background: #fff;
            color: #000;
            text-align: left;
            cursor: pointer;
        }

        .classroom-combobox-option:hover,
        .classroom-combobox-option:focus {
            outline: none;
            background: #f1f1f1;
            color: #000;
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

        .student-qr-card {
            background: #fff;
            border: 1px solid #d1d8e5;
            border-radius: 20px;
            box-shadow: 0 12px 32px rgba(35, 64, 103, 0.08);
            padding: 24px;
            display: grid;
            gap: 14px;
        }

        .student-qr-title {
            margin: 0;
            font-size: 24px;
            line-height: 1.2;
            color: #1d3151;
        }

        .student-qr-body {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            flex-wrap: wrap;
        }

        .student-qr-meta {
            display: grid;
            gap: 10px;
            color: #71829a;
            font-size: 14px;
            line-height: 1.5;
            max-width: 420px;
        }

        .student-qr-image {
            width: 220px;
            height: 220px;
            padding: 14px;
            border-radius: 20px;
            border: 1px solid #dbe4f2;
            background: #fff;
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

            .student-edit-header {
                align-items: flex-start;
            }

            .student-edit-photo,
            .student-edit-photo-placeholder {
                width: 96px;
                height: 96px;
                flex-basis: 96px;
                border-radius: 16px;
            }
        }
    </style>

    <section class="student-edit-page">
        <div class="student-edit-card">
            <div class="student-edit-header">
                <div class="student-edit-heading">
                    <div class="muted">{{ __('ui.common.home') }} / <a
                            href="{{ route('students.index') }}">{{ __('ui.menu.students') }}</a></div>
                    <h1 class="student-edit-title">{{ $student->full_name ?: __('admin.labels.student') }}</h1>
                    <div class="student-edit-note">
                        {{ __('admin.labels.status') }}:
                        {{ $student->latestMealBenefit?->type ? str_replace('_', ' ', ucfirst($student->latestMealBenefit->type)) : '-' }}
                    </div>
                </div>

                @if ($student->photo)
                    <img class="student-edit-photo" src="{{ route('students.photo.show', $student) }}"
                        alt="{{ $student->full_name ?: __('admin.labels.student') }}">
                @else
                    <div class="student-edit-photo-placeholder" aria-label="{{ __('ui.common.photo') }}">
                        {{ mb_strtoupper(mb_substr($student->last_name ?: $student->first_name ?: 'S', 0, 1)) }}
                    </div>
                @endif
            </div>

            <div class="student-edit-body">
                <form method="POST" action="{{ route('students.update', $student) }}">
                    @csrf
                    @method('PUT')

                    <div class="student-edit-grid">
                        <div class="student-edit-field">
                            <label for="iin">{{ __('admin.labels.iin') }}</label>
                            <input id="iin" name="iin" type="text" maxlength="12"
                                value="{{ old('iin', $student->iin) }}">
                        </div>

                        <div class="student-edit-field">
                            <label for="student_number">{{ __('admin.labels.student_number') }}</label>
                            <input id="student_number" name="student_number" type="text"
                                value="{{ old('student_number', $student->student_number) }}">
                        </div>

                        <div class="student-edit-field">
                            <label for="last_name">{{ __('admin.labels.last_name') }}</label>
                            <input id="last_name" name="last_name" type="text"
                                value="{{ old('last_name', $student->last_name) }}">
                        </div>

                        <div class="student-edit-field">
                            <label for="first_name">{{ __('admin.labels.first_name') }}</label>
                            <input id="first_name" name="first_name" type="text"
                                value="{{ old('first_name', $student->first_name) }}">
                        </div>

                        <div class="student-edit-field">
                            <label for="middle_name">{{ __('admin.labels.middle_name') }}</label>
                            <input id="middle_name" name="middle_name" type="text"
                                value="{{ old('middle_name', $student->middle_name) }}">
                        </div>

                        <div class="student-edit-field">
                            <label for="birth_date">{{ __('admin.labels.birth_date') }}</label>
                            <input id="birth_date" name="birth_date" type="date"
                                value="{{ old('birth_date', optional($student->birth_date)->format('Y-m-d')) }}">
                        </div>

                        <div class="student-edit-field">
                            <label for="gender">{{ __('admin.labels.gender') }}</label>
                            <select id="gender" name="gender">
                                <option value="">-</option>
                                <option value="male" @selected(old('gender', $student->gender) === 'male')>{{ __('admin.labels.male') }}</option>
                                <option value="female" @selected(old('gender', $student->gender) === 'female')>{{ __('admin.labels.female') }}
                                </option>
                            </select>
                        </div>

                        <div class="student-edit-field">
                            <label for="phone">{{ __('admin.labels.phone') }}</label>
                            <input id="phone" name="phone" type="tel" value="{{ old('phone', $student->phone) }}"
                                placeholder="+7 777 123 45 67" inputmode="tel" autocomplete="tel" data-phone-input>
                        </div>

                        <div class="student-edit-field">
                            @php
                                $selectedClassroom = $classrooms->firstWhere('id', (int) old('classroom_id', $student->classroom_id));
                            @endphp
                            <label for="classroom_search">{{ __('admin.labels.class_full_name') }}</label>
                            <div class="classroom-combobox">
                                <input id="classroom_search" type="text"
                                    value="{{ $selectedClassroom?->full_name }}"
                                    data-classroom-combobox data-classroom-target="classroom_id"
                                    data-classroom-options="classroom_options"
                                    placeholder="{{ __('ui.classes.search_placeholder') }}" autocomplete="off">
                                <input id="classroom_id" type="hidden" name="classroom_id"
                                    value="{{ $selectedClassroom?->id }}">
                                <div id="classroom_options" class="classroom-combobox-options" hidden>
                                    @foreach ($classrooms as $classroom)
                                        <button class="classroom-combobox-option" type="button"
                                            data-classroom-id="{{ $classroom->id }}"
                                            data-classroom-label="{{ $classroom->full_name }}">{{ $classroom->full_name }}</button>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="student-edit-field">
                            <label for="school_display">{{ __('admin.labels.organization') }}</label>
                            <input id="school_display" type="text" value="{{ $formSchool?->display_name ?: '-' }}"
                                disabled>
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
                            <input id="school_year" name="school_year" type="text" required maxlength="9"
                                pattern="\d{4}-\d{4}" placeholder="2026-2027"
                                value="{{ old('school_year', $student->school_year) }}">
                        </div>

                        <div class="student-edit-field">
                            <label for="meal_benefit_type">{{ __('admin.labels.status') }}</label>
                            <select id="meal_benefit_type" name="meal_benefit_type">
                                <option value="">-</option>
                                @foreach (\App\Models\MealBenefit::TYPES as $mealBenefitType)
                                    @php
                                        $mealBenefitLabel = __('admin.meal_benefit_types.' . $mealBenefitType);
                                    @endphp
                                    <option value="{{ $mealBenefitType }}" @selected(old('meal_benefit_type', $student->latestMealBenefit?->type) === $mealBenefitType)>
                                        {{ $mealBenefitLabel !== 'admin.meal_benefit_types.' . $mealBenefitType ? $mealBenefitLabel : str_replace('_', ' ', ucfirst($mealBenefitType)) }}
                                    </option>
                                @endforeach
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

        <div class="student-qr-card">
            <h2 class="student-qr-title">{{ __('ui.students.qr_title') }}</h2>
            <div class="student-qr-body">
                <div class="student-qr-meta">
                    <div>{{ __('ui.students.qr_scan_hint') }}</div>
                    <div>{{ __('ui.students.qr_payload_hint') }}
                        <strong>student:{{ $student->id }}</strong>.
                    </div>
                    <div>
                        <a class="btn secondary" href="{{ route('students.qr', $student) }}" target="_blank"
                            rel="noopener noreferrer">{{ __('ui.students.qr_open') }}</a>
                    </div>
                </div>
                <img class="student-qr-image" src="{{ route('students.qr', $student) }}"
                    alt="{{ __('ui.students.qr_alt', ['name' => $student->full_name ?: $student->id]) }}">
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
                                    <td>
                                        @php
                                            $orderStatus = $order->status;
                                            $orderStatusLabel = $orderStatus
                                                ? __('ui.orders.statuses.' . $orderStatus)
                                                : null;
                                        @endphp
                                        {{ $orderStatus && $orderStatusLabel !== 'ui.orders.statuses.' . $orderStatus ? $orderStatusLabel : ($orderStatus ?: '-') }}
                                    </td>
                                    <td>
                                        @if ($order->transaction_status === null)
                                            <span class="student-orders-status inactive">
                                                {{ __('ui.orders.statuses.pending') }}
                                            </span>
                                        @else
                                            <span
                                                class="student-orders-status {{ $order->transaction_status ? '' : 'inactive' }}">
                                                {{ $order->transaction_status ? __('ui.orders.transaction_result.success') : __('ui.orders.transaction_result.failed') }}
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

    <script>
        (function() {
            document.querySelectorAll('[data-classroom-combobox]').forEach((searchInput) => {
                const classroomIdInput = document.getElementById(searchInput.dataset.classroomTarget);
                const optionsPanel = document.getElementById(searchInput.dataset.classroomOptions);
                const classroomOptions = Array.from(optionsPanel?.querySelectorAll('[data-classroom-id]') ?? []);

                if (!classroomIdInput || !optionsPanel) {
                    return;
                }

                const filterOptions = () => {
                    const search = searchInput.value.trim().toLocaleLowerCase();
                    const selectedOption = classroomOptions.find((option) => option.dataset.classroomLabel === searchInput.value.trim());
                    classroomIdInput.value = selectedOption?.dataset.classroomId ?? '';

                    classroomOptions.forEach((option) => {
                        option.hidden = !option.dataset.classroomLabel.toLocaleLowerCase().includes(search);
                    });

                    optionsPanel.hidden = false;
                };

                searchInput.addEventListener('focus', filterOptions);
                searchInput.addEventListener('input', filterOptions);

                classroomOptions.forEach((option) => {
                    option.addEventListener('click', () => {
                        searchInput.value = option.dataset.classroomLabel;
                        classroomIdInput.value = option.dataset.classroomId;
                        optionsPanel.hidden = true;
                    });
                });

                document.addEventListener('click', (event) => {
                    if (!searchInput.closest('.classroom-combobox').contains(event.target)) {
                        optionsPanel.hidden = true;
                    }
                });
            });
        })();
    </script>
@endsection
