<div class="form-grid">
    <div class="field field-span-two">
        <label for="title">Event name <span class="required-mark">*</span></label>
        <input id="title" name="title" value="{{ old('title', $event->title ?? '') }}" maxlength="150" placeholder="e.g. Spring Arts Festival" required>
        @error('title')<span class="field-error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label for="category">Category <span class="optional-label">Optional</span></label>
        <select id="category" name="category">
            <option value="">Select a category</option>
            @foreach ($categories as $value => $label)
                <option value="{{ $value }}" @selected(old('category', $event->category ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('category')<span class="field-error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label for="location">Location <span class="required-mark">*</span></label>
        <input id="location" name="location" value="{{ old('location', $event->location ?? '') }}" maxlength="255" placeholder="Where is it happening?" required>
        @error('location')<span class="field-error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label for="start_at">Starts <span class="required-mark">*</span></label>
        <input id="start_at" name="start_at" type="datetime-local" data-future-datetime @isset($event) data-existing-value="{{ $event->start_at->format('Y-m-d\TH:i') }}" @endisset @if ($minimumScheduleAt !== null) min="{{ $minimumScheduleAt }}" @endif value="{{ old('start_at', isset($event) ? $event->start_at->format('Y-m-d\TH:i') : '') }}" required>
        @error('start_at')<span class="field-error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label for="end_at">Ends <span class="required-mark">*</span></label>
        <input id="end_at" name="end_at" type="datetime-local" data-future-datetime data-after-field="start_at" @isset($event) data-existing-value="{{ $event->end_at->format('Y-m-d\TH:i') }}" @endisset @if ($minimumScheduleAt !== null) min="{{ $minimumScheduleAt }}" @endif value="{{ old('end_at', isset($event) ? $event->end_at->format('Y-m-d\TH:i') : '') }}" required>
        @error('end_at')<span class="field-error">{{ $message }}</span>@enderror
    </div>
    <div class="field field-span-two">
        <label for="description">A little more about it</label>
        <textarea id="description" name="description" rows="5" maxlength="5000" placeholder="What are you bringing to life?">{{ old('description', $event->description ?? '') }}</textarea>
        @error('description')<span class="field-error">{{ $message }}</span>@enderror
    </div>
    <div class="field field-span-two">
        <label for="status">Status</label>
        <select id="status" name="status" required>
            @foreach (['draft' => 'Draft — keep planning privately', 'published' => 'Published — ready to share', 'cancelled' => 'Cancelled', 'completed' => 'Completed'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $event->status ?? 'draft') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status')<span class="field-error">{{ $message }}</span>@enderror
    </div>
</div>
<div class="form-actions">
    <a class="button button-quiet" href="{{ isset($event) ? route('events.show', $event) : route('events.index') }}">Cancel</a>
    <button class="button button-primary" type="submit">{{ isset($event) ? 'Save changes' : 'Create event' }} <span aria-hidden="true">→</span></button>
</div>
