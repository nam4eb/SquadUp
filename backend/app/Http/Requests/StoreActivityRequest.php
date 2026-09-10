<?php

namespace App\Http\Requests;

use App\Models\ActivityTopic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasVerifiedEmail() === true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'uuid', Rule::exists('activity_categories', 'id')->where('status', 'active')->where('is_visible', true)],
            'topic_id' => ['required', 'uuid', Rule::exists('activity_topics', 'id')->where('status', 'active')->where('is_visible', true)],
            'sport_id' => ['nullable', 'uuid', Rule::exists('sports', 'id')->where('is_active', true)],
            'venue_id' => ['nullable', 'uuid', 'exists:venues,id'],
            'clan_id' => ['nullable', 'uuid', 'exists:clans,id'],
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'cover_path' => ['nullable', 'string', 'max:2048'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'timezone' => ['required', 'timezone'],
            'location_name' => ['required_without:venue_id', 'string', 'max:160'],
            'location_address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'skill_min' => ['nullable', 'integer', 'between:800,2400'],
            'skill_max' => ['nullable', 'integer', 'between:800,2400'],
            'match_format' => ['nullable', Rule::in(['singles', 'doubles', 'team', 'open'])],
            'fee' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'currency' => ['nullable', 'required_with:fee', 'string', 'size:3'],
            'min_participants' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'max_participants' => ['required', 'integer', 'min:1', 'max:10000'],
            'visibility' => ['sometimes', Rule::in(['public', 'friends', 'clan', 'invite_only', 'private'])],
            'allow_waitlist' => ['sometimes', 'boolean'],
            'allow_friend_invitations' => ['sometimes', 'boolean'],
            'require_approval' => ['sometimes', 'boolean'],
            'allow_join_by_link' => ['sometimes', 'boolean'],
            'password' => ['nullable', 'string', 'min:6', 'max:72'],
            'minimum_age' => ['nullable', 'integer', 'min:13', 'max:100'],
            'rules' => ['nullable', 'array', 'max:20'],
            'rules.*' => ['string', 'max:500'],
            'equipment_requirements' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['category_id', 'topic_id'])) {
                return;
            }
            $matches = ActivityTopic::query()
                ->whereKey($this->input('topic_id'))
                ->where('category_id', $this->input('category_id'))
                ->exists();
            if (! $matches) {
                $validator->errors()->add('topic_id', 'The topic does not belong to the selected category.');
            }
            if ((int) $this->input('max_participants') < (int) $this->input('min_participants', 1)) {
                $validator->errors()->add('max_participants', 'Maximum participants must be at least the minimum.');
            }
            if ($this->filled('skill_min') && $this->filled('skill_max') && (int) $this->input('skill_max') < (int) $this->input('skill_min')) {
                $validator->errors()->add('skill_max', 'Maximum skill must be at least the minimum skill.');
            }
            if ($this->filled('latitude') xor $this->filled('longitude')) {
                $validator->errors()->add('latitude', 'Latitude and longitude must be provided together.');
            }
        }];
    }
}
