<?php

namespace App\Livewire;

use App\Models\ContactSyncReview;
use App\Models\ContactSyncState;
use App\Services\ContactSyncResolutionService;
use App\Services\ContactSyncService;
use Livewire\Component;

class ContactSyncReviews extends Component
{
    public string $filter = 'pending';
    public bool $resolving = false;

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
    }

    /** The hourly job's work for this user, on demand: every linked contact, not one batch. */
    public function syncNow(): void
    {
        $user = auth()->user();

        if (empty($user->google_oauth_refresh_token)) {
            $this->dispatch('notify', message: 'Connect Google first (Settings → Google).');
            return;
        }

        try {
            ContactSyncState::forUser($user)->reset(); // start from the first contact, not wherever the hourly job stopped
            $result = (new ContactSyncService($user, 99999))->runBatch();
            $this->dispatch('notify', message: "Synced {$result['processed']} contacts; {$result['flagged']} differences found.");
        } catch (\Exception $e) {
            $this->dispatch('notify', message: "Couldn't sync: " . $e->getMessage());
        }
    }

    public function pullToLocal(int $reviewId): void
    {
        $review = $this->authoriseReview($reviewId);
        if (!$review) return;

        try {
            (new ContactSyncResolutionService())->pullToLocal($review);
            $this->dispatch('notify', message: 'Updated in PastorEyes.');
        } catch (\Exception $e) {
            $this->dispatch('notify', message: "Couldn't update PastorEyes: " . $e->getMessage());
        }
    }

    public function pushToGoogle(int $reviewId): void
    {
        $review = $this->authoriseReview($reviewId);
        if (!$review) return;

        try {
            (new ContactSyncResolutionService())->pushToGoogle($review);
            $this->dispatch('notify', message: 'Pushed to Google Contacts.');
        } catch (\Exception $e) {
            $this->dispatch('notify', message: "Couldn't push to Google Contacts: " . $e->getMessage());
        }
    }

    public function ignore(int $reviewId): void
    {
        $review = $this->authoriseReview($reviewId);
        if (!$review) return;

        (new ContactSyncResolutionService())->ignore($review);
        $this->dispatch('notify', message: 'Difference ignored.');
    }

    public function resolveAll(string $resolution): void
    {
        $reviews = ContactSyncReview::where('user_id', auth()->id())
            ->pending()
            ->get();

        $service = new ContactSyncResolutionService();
        $count   = 0;

        foreach ($reviews as $review) {
            try {
                match($resolution) {
                    'pull'   => $service->pullToLocal($review),
                    'ignore' => $service->ignore($review),
                    default  => null,
                };
                $count++;
            } catch (\Exception $e) {
                // Continue with remaining reviews even if one fails
            }
        }

        $this->dispatch('notify', message: "Resolved {$count} differences.");
    }

    protected function authoriseReview(int $reviewId): ?ContactSyncReview
    {
        $review = ContactSyncReview::findOrFail($reviewId);
        abort_if($review->user_id !== auth()->id(), 403);
        return $review;
    }

    public function render()
    {
        $query = ContactSyncReview::where('user_id', auth()->id())
            ->with('person.primaryName');

        $query = match($this->filter) {
            'pending'  => $query->pending(),
            'resolved' => $query->resolved(),
            default    => $query,
        };

        $reviews = $query->orderByDesc('detected_at')->get();

        // Group pending reviews by person for cleaner display
        $grouped = $this->filter === 'pending'
            ? $reviews->groupBy('person_id')
            : null;

        $syncState = ContactSyncState::where('user_id', auth()->id())->first();

        $pendingCount = ContactSyncReview::where('user_id', auth()->id())->pending()->count();

        return view('livewire.contact-sync-reviews', compact(
            'reviews', 'grouped', 'syncState', 'pendingCount'
        ))->layout('layouts.app', ['title' => 'Contact Sync — PastorEyes']);
    }
}