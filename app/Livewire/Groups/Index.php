<?php

namespace App\Livewire\Groups;

use App\Models\Group;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public bool $showForm = false;

    public string $name = '';

    public string $description = '';

    /** Takım başına oyuncu (Attributes::TEAM_FORMATS) — grubun kapasitesini belirler. */
    public int $format = 7;

    public function mount(): void
    {
        // Panel varsayılanı tanımlı bir formata denk geliyorsa onunla başla
        $varsayilan = \App\Support\Attributes::teamSizeFor(\App\Models\Setting::int('default_capacity', 14));
        $this->format = array_key_exists($varsayilan, \App\Support\Attributes::TEAM_FORMATS) ? $varsayilan : 7;
    }

    public function create()
    {
        $this->validate(
            [
                'name' => 'required|string|min:3|max:50',
                'description' => 'nullable|string|max:500',
                'format' => 'required|in:'.implode(',', array_keys(\App\Support\Attributes::TEAM_FORMATS)),
            ],
            [
                'name.required' => 'Grup adı zorunlu.',
                'name.min' => 'Grup adı en az 3 karakter olmalı.',
                'name.max' => 'Grup adı en fazla 50 karakter olabilir.',
                'description.max' => 'Açıklama en fazla 500 karakter olabilir.',
            ],
        );

        $group = Group::create([
            'owner_id' => Auth::id(),
            'name' => $this->name,
            'description' => $this->description !== '' ? $this->description : null,
            'capacity' => $this->format * 2,
        ]);

        $group->members()->attach(Auth::id(), ['role' => 'owner']);
        $group->ensurePlayerFor(Auth::user());

        return $this->redirectRoute('groups.show', $group, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.groups.index', [
            'groups' => Auth::user()->groups()->withCount('members')->latest('groups.created_at')->get(),
        ]);
    }
}
