@include('library.field', ['name'=>'barcode','required'=>true,'maxlength'=>64,'value'=>$editingBook?->barcode])
@include('library.field', ['name'=>'title','label'=>'title_field','required'=>true,'maxlength'=>255,'value'=>$editingBook?->title])
@foreach(['author','publisher'] as $field) @include('library.field', ['name'=>$field,'value'=>$editingBook?->$field]) @endforeach
@include('library.select', ['name'=>'subject', 'value'=>$editingBook?->subject, 'options'=>__('library.subject_options')])
@include('library.select', ['name'=>'language', 'value'=>$editingBook?->language, 'options'=>__('library.language_options')])
@include('library.field', ['name'=>'publication_year','type'=>'number','min'=>1000,'max'=>2100,'value'=>$editingBook?->publication_year])
@include('library.select', ['name'=>'grade', 'value'=>$editingBook?->grade, 'options'=>array_combine(range(1, 12), range(1, 12))])
<div class="library-field"><label for="literature_type">{{ __('library.literature_type') }}</label><select id="literature_type" name="literature_type">@foreach(['educational','fiction'] as $type)<option value="{{ $type }}" @selected(old('literature_type', $editingBook?->literature_type) === $type)>{{ __('library.'.$type) }}</option>@endforeach</select></div>
