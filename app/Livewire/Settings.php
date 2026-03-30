<?php

namespace App\Livewire;

use App\Models\RelationshipType;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Settings extends Component
{
    // Account tab
    public ?string $firstName = '';
    public ?string $lastName = '';

    // Appearance tab
    public array $genderColors = [];
    public array $significanceColors = [];
    public array $entryTypeColors = [];

    // Upcoming window
    public int $upcomingDaysWindow = 30;

    // Active tab
    public string $activeTab = 'account';

    // Relationship type form
    public bool $showRelTypeForm = false;
    public ?int $editingRelTypeId = null;
    public string $relTypeName = '';
    public string $relTypeInverseName = '';
    public bool $relTypeIsDirectional = false;
    public bool $relTypeIsGlobal = false;

    // User management (admin only)
    public ?int $managedUserId = null;
    public string $userSearch = '';
    public bool $managedUserDirty = false;

    // Managed user fields
    public string $managedFirstName = '';
    public string $managedLastName = '';
    public bool $managedIsActive = true;
    public bool $managedIsAdmin = false;

    protected $listeners = [
        'person-selected' => 'onPersonSelected',
    ];

    public function mount(): void
    {
        $user = auth()->user();

        $this->firstName          = $user->first_name ?? '';
        $this->lastName           = $user->last_name ?? '';
        $this->genderColors       = config('entry_types.gender_colors');
        $this->significanceColors = config('entry_types.significance');
        $this->entryTypeColors    = collect(config('entry_types.types'))
            ->mapWithKeys(fn($v, $k) => [$k => $v['color']])
            ->toArray();
        $this->upcomingDaysWindow = $user->settings['upcoming_days_window'] ?? 30;
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    // -------------------------------------------------------------------------
    // Account
    // -------------------------------------------------------------------------

    public function saveAccount(): void
    {
        $this->validate([
            'firstName' => 'required|string|max:255',
            'lastName'  => 'required|string|max:255',
        ]);

        auth()->user()->update([
            'first_name' => $this->firstName,
            'last_name'  => $this->lastName,
        ]);

        $this->dispatch('notify', message: 'Account updated.');
    }

    public function deleteOwnAccount(): void
    {
        $user = auth()->user();

        // Prevent deleting the last admin
        if ($user->is_admin && User::where('is_admin', true)->count() <= 1) {
            $this->dispatch('notify', message: 'Cannot delete the last administrator account.');
            return;
        }

        Auth::logout();
        $user->delete();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        redirect()->route('login');
    }

    // -------------------------------------------------------------------------
    // Appearance
    // -------------------------------------------------------------------------

    public function saveAppearance(): void
    {
        // Note: colour values are stored in user settings JSON.
        // CSS custom properties are set via a Blade include that reads from settings.
        // For now we persist to the user settings column.
        $settings = auth()->user()->settings ?? [];
        $settings['gender_colors']       = $this->genderColors;
        $settings['significance_colors']  = $this->significanceColors;
        $settings['entry_type_colors']    = $this->entryTypeColors;
        $settings['upcoming_days_window'] = $this->upcomingDaysWindow;

        auth()->user()->update(['settings' => $settings]);
        $this->dispatch('notify', message: 'Appearance settings saved.');
    }

    // -------------------------------------------------------------------------
    // Relationship Types
    // -------------------------------------------------------------------------

    public function openAddRelType(): void
    {
        $this->resetRelTypeForm();
        $this->showRelTypeForm = true;
    }

    public function editRelType(int $id): void
    {
        $rt = RelationshipType::findOrFail($id);

        // Allow admins to edit global types; others can only edit their own
        if (!$rt->is_preset) {
            abort_if(!auth()->user()->is_admin && $rt->user_id !== auth()->id(), 403);
        }

        $this->editingRelTypeId     = $id;
        $this->relTypeName          = $rt->name;
        $this->relTypeInverseName   = $rt->inverse_name ?? '';
        $this->relTypeIsDirectional = $rt->is_directional;
        $this->relTypeIsGlobal      = $rt->user_id === null;
        $this->showRelTypeForm      = true;
    }

    public function saveRelType(): void
    {
        $this->validate([
            'relTypeName'        => 'required|string|max:100',
            'relTypeInverseName' => 'nullable|string|max:100',
        ]);

        // Only admins can create global types
        $isGlobal = $this->relTypeIsGlobal && auth()->user()->is_admin;

        $data = [
            'user_id'        => $isGlobal ? null : auth()->id(),
            'name'           => $this->relTypeName,
            'inverse_name'   => $this->relTypeInverseName ?: null,
            'is_directional' => $this->relTypeIsDirectional,
            'is_preset'      => false,
        ];

        if ($this->editingRelTypeId) {
            $rt = RelationshipType::findOrFail($this->editingRelTypeId);
            abort_if(
                !auth()->user()->is_admin && $rt->user_id !== auth()->id(),
                403
            );
            $rt->update($data);
        } else {
            RelationshipType::create($data);
        }

        $this->resetRelTypeForm();
        $this->dispatch('notify', message: 'Relationship type saved.');
    }

    public function deleteRelType(int $id): void
    {
        $rt = RelationshipType::findOrFail($id);

        // Presets cannot be deleted by anyone
        if ($rt->is_preset) {
            $this->dispatch('notify', message: 'Global preset types cannot be deleted.');
            return;
        }

        abort_if(
            !auth()->user()->is_admin && $rt->user_id !== auth()->id(),
            403
        );

        $rt->delete();
        $this->dispatch('notify', message: 'Relationship type deleted.');
    }

    public function resetRelTypeForm(): void
    {
        $this->showRelTypeForm      = false;
        $this->editingRelTypeId     = null;
        $this->relTypeName          = '';
        $this->relTypeInverseName   = '';
        $this->relTypeIsDirectional = false;
        $this->relTypeIsGlobal      = false;
    }

    // -------------------------------------------------------------------------
    // User Management (admin only)
    // -------------------------------------------------------------------------

    public function loadManagedUser(int $userId): void
    {
        abort_if(!auth()->user()->is_admin, 403);

        $user = User::findOrFail($userId);

        $this->managedUserId    = $user->id;
        $this->managedFirstName = $user->first_name;
        $this->managedLastName  = $user->last_name;
        $this->managedIsActive  = $user->is_active;
        $this->managedIsAdmin   = $user->is_admin;
        $this->managedUserDirty = false;
    }

    public function updatedManagedFirstName(): void { $this->managedUserDirty = true; }
    public function updatedManagedLastName(): void  { $this->managedUserDirty = true; }
    public function updatedManagedIsActive(): void  { $this->managedUserDirty = true; }
    public function updatedManagedIsAdmin(): void   { $this->managedUserDirty = true; }

    public function saveManagedUser(): void
    {
        abort_if(!auth()->user()->is_admin, 403);

        $user = User::findOrFail($this->managedUserId);

        // Guard: cannot remove admin from last admin
        if ($user->is_admin && !$this->managedIsAdmin) {
            if (User::where('is_admin', true)->count() <= 1) {
                $this->dispatch('notify', message: 'Cannot remove admin from the last administrator.');
                return;
            }
        }

        // Guard: cannot remove own admin privileges
        if ($user->id === auth()->id() && !$this->managedIsAdmin) {
            $this->dispatch('notify', message: 'You cannot remove your own admin privileges.');
            return;
        }

        // Guard: cannot disable yourself
        if ($user->id === auth()->id() && !$this->managedIsActive) {
            $this->dispatch('notify', message: 'You cannot disable your own account.');
            return;
        }

        $user->update([
            'first_name' => $this->managedFirstName,
            'last_name'  => $this->managedLastName,
            'is_active'  => $this->managedIsActive,
            'is_admin'   => $this->managedIsAdmin,
        ]);

        $this->managedUserDirty = false;
        $this->dispatch('notify', message: 'User updated.');
    }

    public function deleteManagedUser(): void
    {
        abort_if(!auth()->user()->is_admin, 403);

        $user = User::findOrFail($this->managedUserId);

        // Guard: cannot delete last admin
        if ($user->is_admin && User::where('is_admin', true)->count() <= 1) {
            $this->dispatch('notify', message: 'Cannot delete the last administrator.');
            return;
        }

        $user->delete();

        $this->managedUserId    = null;
        $this->managedFirstName = '';
        $this->managedLastName  = '';
        $this->userSearch       = '';
        $this->managedUserDirty = false;

        $this->dispatch('notify', message: 'User deleted.');
    }

    public function render()
    {
        $customRelTypes = RelationshipType::where('user_id', auth()->id())->get();
        $globalCustomRelTypes = auth()->user()->is_admin
            ? RelationshipType::whereNull('user_id')->where('is_preset', false)->get()
            : collect();
        $presetRelTypes = RelationshipType::whereNull('user_id')->where('is_preset', true)->get();

        $users = auth()->user()->is_admin && strlen($this->userSearch) >= 2
            ? User::where('id', '!=', auth()->id())
                ->get()
                ->filter(function ($u) {
                    $search = strtolower($this->userSearch);
                    return str_contains(strtolower($u->first_name . ' ' . $u->last_name), $search)
                        || str_contains(strtolower($u->email), $search);
                })
                ->values()
            : collect();

        return view('livewire.settings', compact('customRelTypes', 'globalCustomRelTypes', 'presetRelTypes', 'users'))
            ->layout('layouts.app', ['title' => 'Settings — PastorEyes']);
    }
}