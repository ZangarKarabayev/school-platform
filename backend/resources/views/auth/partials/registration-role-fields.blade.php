@php
    use App\Modules\Access\Enums\RoleCode;

    $roleInputId = $prefix.'role';
    $schoolInputId = $prefix.'school_id';
    $districtRegionFilterInputId = $prefix.'district_region_id';
    $districtInputId = $prefix.'district_id';
    $regionInputId = $prefix.'region_id';
    $selectedDistrict = $districts->firstWhere('id', (int) old('district_id'));
    $selectedDistrictRegionId = old('district_region_id') ?? $selectedDistrict?->region_id;
@endphp

<div class="field">
    <label for="{{ $roleInputId }}">{{ __('admin.labels.role') }} *</label>
    <select
        id="{{ $roleInputId }}"
        name="role"
        data-registration-role
        data-role-school='@json([RoleCode::Teacher->value, RoleCode::Director->value])'
        data-role-district="{{ RoleCode::DistrictOperator->value }}"
        data-role-region="{{ RoleCode::RegionOperator->value }}"
        required
    >
        <option value="">--</option>
        @foreach ($roles as $role)
            <option value="{{ $role->code }}" @selected(old('role') === $role->code)>{{ $role->display_name }}</option>
        @endforeach
    </select>
</div>

<div class="field" data-registration-scope="school" @if (! in_array(old('role'), [RoleCode::Teacher->value, RoleCode::Director->value], true)) style="display: none;" @endif>
    <label for="{{ $schoolInputId }}">{{ __('admin.labels.school') }} *</label>
    <select id="{{ $schoolInputId }}" name="school_id" data-registration-input="school">
        <option value="">--</option>
        @foreach ($schools as $school)
            <option value="{{ $school->id }}" @selected((string) old('school_id') === (string) $school->id)>{{ $school->display_name }}</option>
        @endforeach
    </select>
</div>

<div class="field" data-registration-scope="district-region" @if (old('role') !== RoleCode::DistrictOperator->value) style="display: none;" @endif>
    <label for="{{ $districtRegionFilterInputId }}">{{ __('admin.labels.region') }} *</label>
    <select
        id="{{ $districtRegionFilterInputId }}"
        name="district_region_id"
        data-registration-input="district-region"
    >
        <option value="">--</option>
        @foreach ($regions as $region)
            <option value="{{ $region->id }}" @selected((string) $selectedDistrictRegionId === (string) $region->id)>{{ $region->display_name }}</option>
        @endforeach
    </select>
</div>

<div class="field" data-registration-scope="district" @if (old('role') !== RoleCode::DistrictOperator->value) style="display: none;" @endif>
    <label for="{{ $districtInputId }}">{{ __('admin.labels.district') }} *</label>
    <select id="{{ $districtInputId }}" name="district_id" data-registration-input="district">
        <option value="">--</option>
        @foreach ($districts as $district)
            <option
                value="{{ $district->id }}"
                data-region-id="{{ $district->region_id }}"
                @selected((string) old('district_id') === (string) $district->id)
            >{{ $district->display_name }}</option>
        @endforeach
    </select>
</div>

<div class="field" data-registration-scope="region" @if (old('role') !== RoleCode::RegionOperator->value) style="display: none;" @endif>
    <label for="{{ $regionInputId }}">{{ __('admin.labels.region') }} *</label>
    <select id="{{ $regionInputId }}" name="region_id" data-registration-input="region">
        <option value="">--</option>
        @foreach ($regions as $region)
            <option value="{{ $region->id }}" @selected((string) old('region_id') === (string) $region->id)>{{ $region->display_name }}</option>
        @endforeach
    </select>
</div>
