<?php

namespace App\Http\Requests;

use App\Models\ActivityTopic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', $this->route('activity')) === true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'uuid', Rule::exists('activity_categories', 'id')->where('status', 'active')->where('is_visible', true)],
            'topic_id' => ['sometimes', 'uuid', Rule::exists('activity_topics', 'id')->where('status', 'active')->where('is_visible', true)],
            'sport_id' => ['nullable', 'uuid', Rule::exists('sports', 'id')->where('is_active', true)],
            'venue_id' => ['nullable', 'uuid', 'exists:venues,id'],
            'title' => ['sometimes', 'string', 'min:3', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'cover_path' => ['nullable', 'string', 'max:2048'],
            'starts_at' => ['sometimes', 'date', 'after:now'],
            'ends_at' => ['nullable', 'date'],
            'timezone' => ['sometimes', 'timezone'],
            'location_name' => ['sometimes', 'string', 'max:160'],
            'location_address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'skill_min' => ['nullable', 'integer', 'between:800,2400'],
            'skill_max' => ['nullable', 'integer', 'between:800,2400'],
            'match_format' => ['nullable', Rule::in(['singles', 'doubles', 'team', 'open'])],
            'fee' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'currency' => ['nullable', 'required_with:fee', 'string', 'size:3'],
            'min_participants' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'max_participants' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'visibility' => ['sometimes', Rule::in(['public', 'friends', 'clan', 'invite_only', 'private'])],
            'allow_waitlist' => ['sometimes', 'boolean'],
            'allow_friend_invitations' => ['sometimes', 'boolean'],
            'require_approval' => ['sometimes', 'boolean'],
            'allow_join_by_link' => ['sometimes', 'boolean'],
            'password' => ['nullable', 'string', 'min:6', 'max:72'],
            'remove_password' => ['sometimes', 'boolean'],
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
            $activity = $this->route('activity');
            $categoryId = $this->input('category_id', $activity->category_id);
            $topicId = $this->input('topic_id', $activity->topic_id);
            if (! ActivityTopic::query()->whereKey($topicId)->where('category_id', $categoryId)->exists()) {
                $validator->errors()->add('topic_id', 'The topic does not belong to the selected category.');
            }
            $startsAt = new \DateTimeImmutable($this->input('starts_at', $activity->starts_at->toISOString()));
            $endsValue = $this->input('ends_at', $activity->ends_at?->toISOString());
            if ($endsValue !== null && new \DateTimeImmutable($endsValue) <= $startsAt) {
                $validator->errors()->add('ends_at', 'The end time must be after the start time.');
            }
            $min = (int) $this->input('min_participants', $activity->min_participants);
            $max = (int) $this->input('max_participants', $activity->max_participants);
            if ($max < $min) {
                $validator->errors()->add('max_participants', 'Maximum participants must be at least the minimum.');
            }
            $skillMin = $this->input('skill_min', $activity->skill_min);
            $skillMax = $this->input('skill_max', $activity->skill_max);
            if ($skillMin !== null && $skillMax !== null && (int) $skillMax < (int) $skillMin) {
                $validator->errors()->add('skill_max', 'Maximum skill must be at least the minimum skill.');
            }
            $latitude = $this->input('latitude', $activity->latitude);
            $longitude = $this->input('longitude', $activity->longitude);
            if (($latitude === null) xor ($longitude === null)) {
                $validator->errors()->add('latitude', 'Latitude and longitude must be provided together.');
            }
        }];
    }
}
