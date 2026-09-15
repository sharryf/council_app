<?php

namespace App\Services\Bureau;

use App\Enums\BureauDecisionStatus;
use App\Enums\BureauVoteChoice;
use App\Models\BureauDecisionRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Decision requests for one agenda item are voted on in the order they
 * were raised (sort_order). The Bureau Admin records every attendee's
 * Yes/No vote in one go; as soon as a request passes by simple majority
 * of total council membership (not just the attendees present — see
 * User::bureauCouncilMembershipCount()), every other still-Pending
 * request for that same agenda item is marked Skipped and is never
 * voted on.
 */
class DecisionVotingService
{
    /**
     * Also used to re-record votes on an already-decided request (see
     * RecordMinutes::editVotes()/recordVotes()) — deliberately not
     * restricted to isPending() requests, since votes stay editable
     * for as long as the minutes itself is (i.e. until approved).
     * Re-deciding recomputes status/decided_at the same way a first
     * vote does; it doesn't retroactively un-skip any sibling request
     * that was skipped when this one first passed.
     *
     * @param  array<int, string>  $votes  user_id => BureauVoteChoice::value
     */
    public function recordVotes(BureauDecisionRequest $request, array $votes): BureauDecisionRequest
    {
        if (empty($votes)) {
            throw ValidationException::withMessages([
                'votes' => 'Record at least one vote.',
            ]);
        }

        DB::transaction(function () use ($request, $votes): void {
            foreach ($votes as $userId => $choice) {
                $request->votes()->updateOrCreate(
                    ['user_id' => $userId],
                    ['vote' => BureauVoteChoice::from($choice)],
                );
            }

            $yesCount = $request->votes()->where('vote', BureauVoteChoice::Yes)->count();
            $threshold = intdiv(max(User::bureauCouncilMembershipCount(), 1), 2) + 1;
            $passed = $yesCount >= $threshold;

            $request->update([
                'status' => $passed ? BureauDecisionStatus::Passed : BureauDecisionStatus::Failed,
                'decided_at' => now(),
            ]);

            if ($passed) {
                BureauDecisionRequest::query()
                    ->where('minutes_id', $request->minutes_id)
                    ->where('agenda_item_id', $request->agenda_item_id)
                    ->where('id', '!=', $request->id)
                    ->where('status', BureauDecisionStatus::Pending)
                    ->update(['status' => BureauDecisionStatus::Skipped, 'decided_at' => now()]);
            }
        });

        return $request->fresh(['votes']);
    }
}
