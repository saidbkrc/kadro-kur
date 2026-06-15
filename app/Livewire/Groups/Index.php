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

    public function create()
    {
        $this->validate(
            [
                'name' => 'required|string|min:3|max:50',
                'description' => 'nullable|string|max:500',
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
            'capacity' => \App\Models\Setting::int('default_capacity', 14),
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
