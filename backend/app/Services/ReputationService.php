<?php

namespace App\Services;

use App\Models\ActivityParticipant;
use App\Models\ActivityRating;
use App\Models\UserReputation;
use App\Models\UserSportProfile;

class ReputationService
{
    public function refresh(string $userId, ?string $sportId = null): UserReputation
    {
        $ratings = ActivityRating::query()->where('ratee_id', $userId)->whereNull('invalidated_at');
        $attendance = ActivityParticipant::query()->where('user_id', $userId)
            ->whereIn('status', ['attended', 'absent']);
        $attended = (clone $attendance)->where('status', 'attended')->count();
        $absent = (clone $attendance)->where('status', 'absent')->count();
        $count = (clone $ratings)->count();
        $reputation = UserReputation::updateOrCreate(['user_id' => $userId], [
            'ratings_count' => $count,
            'sportsmanship_average' => round((float) ((clone $ratings)->avg('sportsmanship') ?? 0), 2),
            'skill_average' => round((float) ((clone $ratings)->avg('skill') ?? 0), 2),
            'reliability_average' => round((float) ((clone $ratings)->avg('reliability') ?? 0), 2),
            'attended_count' => $attended,
            'absent_count' => $absent,
            'attendance_rate' => $attended + $absent > 0 ? round($attended / ($attended + $absent), 4) : 0,
        ]);

        if ($sportId) {
            $profile = UserSportProfile::query()->where(['user_id' => $userId, 'sport_id' => $sportId])->first();
            if ($profile) {
                $sportRatings = ActivityRating::query()
                    ->where(['ratee_id' => $userId, 'sport_id' => $sportId])
                    ->whereNull('invalidated_at');
                $sportCount = $sportRatings->count();
                $observed = 400 + ((float) ($sportRatings->avg('skill') ?? 1) * 400);
                $confidence = min(1, $sportCount / 10);
                $baseline = (int) config("skills.levels.{$profile->self_declared_level}", 800);
                $profile->update([
                    'skill_rating' => (int) round($baseline * (1 - $confidence) + $observed * $confidence),
                    'matches_played' => $sportCount,
                    'rating_confidence' => $confidence,
                ]);
            }
        }

        return $reputation;
    }
}
