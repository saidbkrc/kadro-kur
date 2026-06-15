<?php

namespace App\Livewire\Groups;

use App\Models\Group;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Join extends Component
{
    public Group $group;

    public function mount(string $code): void
    {
        $this->group = Group::where('invite_code', $code)->firstOrFail();

        if (Auth::guest()) {
            // Kayıt/giriş sonrası bu sayfaya geri dönülsün
            session(['url.intended' => route('groups.join', $code)]);

            return;
        }

        if ($this->group->isMember(Auth::user())) {
            $this->redirectRoute('groups.show', $this->group, navigate: true);
        }
    }

    public function join()
    {
        if (Auth::guest()) {
            return $this->redirectRoute('register', navigate: true);
        }

        if (! $this->group->isMember(Auth::user())) {
            $this->group->members()->attach(Auth::id(), ['role' => 'member']);
            $this->group->ensurePlayerFor(Auth::user());
        }

        return $this->redirectRoute('groups.show', $this->group, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.groups.join', [
            'memberCount' => $this->group->members()->count(),
        ]);
    }
}
