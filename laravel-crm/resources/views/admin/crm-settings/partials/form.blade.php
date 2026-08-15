@csrf

<div class="mb-3">
    <label class="form-label" for="name">Name</label>
    <input class="form-control" id="name" name="name" value="{{ old('name', $value->name) }}" required maxlength="255">
</div>
<div class="mb-3">
    <label class="form-label" for="slug">Slug</label>
    <input class="form-control" id="slug" name="slug" value="{{ old('slug', $value->slug) }}" maxlength="255">
    <div class="form-text">Leave blank to generate from name.</div>
</div>
<div class="mb-3">
    <label class="form-label" for="description">Description</label>
    <textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $value->description) }}</textarea>
</div>
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="color">Color</label>
        <input class="form-control" id="color" type="color" name="color" value="{{ old('color', $value->color ?: '#0d6efd') }}">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="sort_order">Sort Order</label>
        <input class="form-control" id="sort_order" type="number" min="0" max="9999" name="sort_order" value="{{ old('sort_order', $value->sort_order) }}">
    </div>
</div>
<div class="mt-3">
    <label class="form-check">
        <input type="hidden" name="is_default" value="0">
        <input class="form-check-input" type="checkbox" name="is_default" value="1" @checked(old('is_default', $value->is_default))>
        <span class="form-check-label">Default value</span>
    </label>
</div>
<div class="mt-2">
    <label class="form-check">
        <input type="hidden" name="is_active" value="0">
        <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $value->is_active))>
        <span class="form-check-label">Active value</span>
    </label>
</div>

<div class="d-flex gap-2 mt-4">
    <button class="btn btn-primary" type="submit">{{ $submitLabel }}</button>
    <a class="btn btn-outline-secondary" href="{{ route('admin.crm-settings.show', $typeKey) }}">Cancel</a>
</div>
