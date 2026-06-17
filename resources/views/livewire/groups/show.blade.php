@php
    use App\Support\Attributes;
    $tier = fn (float $o) => $o >= 8 ? 'text-gold' : ($o >= 6.5 ? 'text-[#7DE39A]' : ($o >= 5 ? 'text-pitch-ink' : 'text-pitch-muted'));
@endphp

<div class="py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        {{-- Başlık + davet linki + ayarlar --}}
        <div class="bg-pitch-surface border border-pitch-line rounded-xl p-6 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 sm:gap-4">
                <div class="min-w-0">
                    <h2 class="font-display uppercase tracking-wider text-2xl font-bold">{{ $group->name }}</h2>
                    @if ($group->description)
                        <p class="text-pitch-muted mt-1">{{ $group->description }}</p>
                    @endif
                </div>
                <div class="shrink-0 flex items-center gap-2 flex-wrap">
                    <span class="text-sm text-pitch-muted">{{ $players->count() }} oyuncu</span>
                    @if ($isAdmin)
                        <x-secondary-button wire:click="$toggle('showSettings')">⚙️ Ayarlar</x-secondary-button>
                    @endif
                    @if (auth()->id() === $group->owner_id)
                        <x-danger-button wire:click="deleteGroup" type="button"
                                         wire:confirm="DİKKAT: '{{ $group->name }}' grubu, tüm maçları, oyuncuları ve puanlarıyla birlikte kalıcı olarak silinecek. Emin misin?">
                            Grubu Sil
                        </x-danger-button>
                    @else
                        <x-danger-button wire:click="leaveGroup" type="button"
                                         wire:confirm="'{{ $group->name }}' grubundan ayrılmak istediğine emin misin?">
                            Gruptan Ayrıl
                        </x-danger-button>
                    @endif
                </div>
            </div>

            <div x-data="{ copied: false }" class="flex items-center gap-2 bg-pitch-bg border border-pitch-line rounded-lg p-3">
                <span class="text-sm text-pitch-muted shrink-0">Davet linki:</span>
                <code class="text-sm text-bibB truncate">{{ route('groups.join', $group->invite_code) }}</code>
                <button type="button"
                        @click="navigator.clipboard.writeText('{{ route('groups.join', $group->invite_code) }}'); copied = true; setTimeout(() => copied = false, 2000)"
                        class="ms-auto shrink-0 inline-flex items-center px-3 py-1.5 bg-pitch-surface2 border border-pitch-line rounded-md text-xs font-semibold uppercase tracking-widest hover:brightness-125">
                    <span x-show="!copied">Kopyala</span>
                    <span x-show="copied" x-cloak class="text-bibB">Kopyalandı ✓</span>
                </button>
            </div>

            @if ($showSettings && $isAdmin)
                <form wire:submit="saveSettings" class="border border-pitch-line rounded-lg p-4 space-y-4">
                    <h3 class="font-display uppercase tracking-wider text-lg">Haftalık Maç Ayarları</h3>
                    <p class="text-sm text-pitch-muted">Bir kere ayarla, sistem her hafta maçı kendisi açsın — her hafta elle oluşturma derdi bitsin.</p>
                    @php $fieldClasses = 'block w-full bg-pitch-bg border-pitch-line text-pitch-ink placeholder-pitch-muted/60 focus:border-bibB focus:ring-bibB/40 rounded-md shadow-sm'; @endphp
                    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label for="matchDay" class="block font-semibold text-xs uppercase tracking-widest text-pitch-muted mb-1.5">Maç günü</label>
                            <select wire:model="matchDay" id="matchDay" style="height:2.625rem" class="{{ $fieldClasses }}">
                                <option value="">— seç —</option>
                                @foreach ([1 => 'Pazartesi', 2 => 'Salı', 3 => 'Çarşamba', 4 => 'Perşembe', 5 => 'Cuma', 6 => 'Cumartesi', 7 => 'Pazar'] as $day => $label)
                                    <option value="{{ $day }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="matchTime" class="block font-semibold text-xs uppercase tracking-widest text-pitch-muted mb-1.5">Saat</label>
                            <input wire:model="matchTime" id="matchTime" type="time" style="height:2.625rem" class="{{ $fieldClasses }}">
                        </div>
                        <div>
                            <label for="defaultLocation" class="block font-semibold text-xs uppercase tracking-widest text-pitch-muted mb-1.5">Saha</label>
                            <input wire:model="defaultLocation" id="defaultLocation" type="text" placeholder="Yıldız Halı Saha" style="height:2.625rem" class="{{ $fieldClasses }}">
                        </div>
                        <div>
                            <label for="groupCapacity" class="block font-semibold text-xs uppercase tracking-widest text-pitch-muted mb-1.5">Kapasite</label>
                            <input wire:model="groupCapacity" id="groupCapacity" type="number" min="4" max="24" style="height:2.625rem" class="{{ $fieldClasses }}">
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="autoSchedule"
                               class="rounded bg-pitch-bg border-pitch-line text-bibB focus:ring-bibB/40">
                        Maçları her hafta otomatik oluştur
                    </label>
                    <x-input-error :messages="$errors->get('matchDay')" />
                    <x-input-error :messages="$errors->get('matchTime')" />
                    <x-primary-button>Kaydet</x-primary-button>
                </form>
            @endif
        </div>

        {{-- Yaklaşan maçlar --}}
        <div class="bg-pitch-surface border border-pitch-line rounded-xl p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="font-display uppercase tracking-wider text-lg font-semibold">Yaklaşan Maçlar</h3>
                @if ($isAdmin)
                    <x-secondary-button wire:click="$toggle('showMatchForm')">
                        {{ $showMatchForm ? 'Vazgeç' : '+ Maç Aç' }}
                    </x-secondary-button>
                @endif
            </div>

            @if ($showMatchForm && $isAdmin)
                <form wire:submit="createMatch" class="space-y-4 border border-pitch-line rounded-lg p-4">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="title" value="Başlık" />
                            <x-text-input wire:model="title" id="title" type="text" class="mt-1 block w-full" placeholder="Salı 21:00 maçı" />
                            <x-input-error :messages="$errors->get('title')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="location" value="Saha (isteğe bağlı)" />
                            <x-text-input wire:model="location" id="location" type="text" class="mt-1 block w-full" />
                        </div>
                        <div>
                            <x-input-label for="starts_at" value="Tarih ve saat" />
                            <x-text-input wire:model="starts_at" id="starts_at" type="datetime-local" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('starts_at')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="capacity" value="Kadro kapasitesi" />
                            <x-text-input wire:model="capacity" id="capacity" type="number" min="4" max="24" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('capacity')" class="mt-2" />
                        </div>
                    </div>
                    <x-primary-button>Maçı Aç</x-primary-button>
                </form>
            @endif

            @forelse ($upcoming as $match)
                <a href="{{ route('matches.show', $match) }}" wire:navigate
                   class="block border border-pitch-line rounded-lg p-4 hover:bg-pitch-surface2 transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-semibold">{{ $match->title }}</div>
                            <div class="text-sm text-pitch-muted mt-0.5">
                                {{ $match->starts_at->translatedFormat('d F Y, l H:i') }}
                                @if ($match->location) · {{ $match->location }} @endif
                            </div>
                        </div>
                        <div class="font-display text-lg font-bold shrink-0 ms-4 {{ $match->going_count >= $match->capacity ? 'text-bibA' : '' }}">
                            {{ $match->going_count }}/{{ $match->capacity }}
                        </div>
                    </div>
                </a>
            @empty
                <p class="text-pitch-muted">
                    Yaklaşan maç yok.
                    @if ($isAdmin && ! $group->auto_schedule)
                        <strong class="text-pitch-ink">Ayarlar</strong>'dan haftalık otomatik maçı aç ya da <strong class="text-pitch-ink">Maç Aç</strong> ile elle oluştur.
                    @endif
                </p>
            @endforelse
        </div>

        {{-- Oyuncu havuzu --}}
        <div class="bg-pitch-surface border border-pitch-line rounded-xl p-6 space-y-4">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <h3 class="font-display uppercase tracking-wider text-lg font-semibold">Oyuncu Havuzu</h3>
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="{{ route('groups.rate', $group) }}" wire:navigate>
                        <x-primary-button type="button">⭐ Oyuncuları Puanla</x-primary-button>
                    </a>
                    <a href="{{ route('groups.stats', $group) }}" wire:navigate>
                        <x-secondary-button>📊 İstatistikler</x-secondary-button>
                    </a>
                    @if ($isAdmin)
                        <x-secondary-button wire:click="$toggle('showGuestForm')">
                            {{ $showGuestForm ? 'Vazgeç' : '+ Misafir Oyuncu' }}
                        </x-secondary-button>
                    @endif
                </div>
            </div>

            <p class="text-sm text-pitch-muted">
                Puanlar <strong class="text-pitch-ink">anonimdir</strong> — kimin kime kaç verdiği görünmez.
                Bir oyuncunun ortalaması, en az <strong class="text-pitch-ink">{{ \App\Models\Player::minRatingsForVisibility() }} kişi</strong> puanlayınca herkese açılır.
                Kadrolar bu ortalamalara göre dengelenir.
            </p>

            @if ($showGuestForm && $isAdmin)
                <form wire:submit="addGuest" class="border border-pitch-line rounded-lg p-4 space-y-3">
                    <div class="flex flex-wrap items-center gap-3">
                        <x-text-input wire:model="guestName" id="guestName" type="text" maxlength="24"
                                      class="w-full sm:w-64" placeholder="Ad / Lakap (örn. Mahmut)" />
                        <x-text-input wire:model="guestNumber" id="guestNumber" type="number" min="1" max="99"
                                      class="w-28" placeholder="Forma No" />
                        <select wire:model="guestFoot" class="bg-pitch-bg border-pitch-line text-pitch-ink rounded-md text-sm focus:border-bibB focus:ring-bibB/40">
                            @foreach (\App\Support\Attributes::FEET as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-primary-button>+ Ekle</x-primary-button>
                    </div>
                    <x-input-error :messages="$errors->get('guestName')" />
                    <x-input-error :messages="$errors->get('guestNumber')" />
                    <p class="text-xs text-pitch-muted">Misafir: henüz hesabı olmayan oyuncu. Kayıt olunca listeden hesabıyla eşleştirebilirsin, puanları korunur.</p>
                </form>
            @endif

            <div class="divide-y divide-pitch-line">
                @foreach ($players as $player)
                    @php
                        $ovr = $player->overall();
                        $ovrPublic = $player->overallIsPublic();
                        $minRatings = \App\Models\Player::minRatingsForVisibility();
                    @endphp
                    <div class="py-3">
                        <div class="flex items-center justify-between gap-4 flex-wrap">
                            <div class="flex items-center gap-3">
                                @if ($ovrPublic)
                                    <span class="font-display text-2xl font-bold w-12 text-center {{ $tier($ovr) }}" title="Genel puan ({{ $player->ratingCount() }} oylama)">{{ number_format($ovr, 1) }}</span>
                                @else
                                    <span class="font-display text-2xl font-bold w-12 text-center text-pitch-muted" title="Puan, {{ $minRatings }} kişi oylayınca görünür">?</span>
                                @endif
                                <div>
                                    <span class="font-semibold">{{ $player->name }}</span>
                                    @if ($player->shirt_number)
                                        <span class="text-pitch-muted text-sm">#{{ $player->shirt_number }}</span>
                                    @endif
                                    @if ($player->isGuest())
                                        <span class="ms-1 inline-flex px-2 py-0.5 rounded text-xs font-medium bg-gold/15 text-gold">Misafir</span>
                                    @elseif ($player->user_id === $group->owner_id)
                                        <span class="ms-1 inline-flex px-2 py-0.5 rounded text-xs font-medium bg-bibB/15 text-bibB">Başkan</span>
                                    @endif
                                    <div class="flex gap-1 mt-1">
                                        @foreach ($player->positions ?? [] as $i => $pos)
                                            <span class="text-[10.5px] font-bold tracking-wide px-2 py-0.5 rounded-full bg-pitch-bg border border-pitch-line {{ $pos === 'KL' ? 'text-gold' : 'text-pitch-muted' }}">
                                                {{ count($player->positions) > 1 ? ($i + 1).'·' : '' }}{{ $pos }}
                                            </span>
                                        @endforeach
                                        <span class="text-[10.5px] font-bold tracking-wide px-2 py-0.5 rounded-full bg-pitch-bg border border-pitch-line text-bibB" title="{{ \App\Support\Attributes::FEET[$player->foot] ?? 'Sağ ayak' }}">
                                            🦶 {{ $player->footBadge() }}
                                        </span>
                                        <span class="text-xs text-pitch-muted ms-1">
                                            {{ $ovrPublic ? $player->ratingCount().' oylama' : $player->ratingCount().'/'.$minRatings.' oylama' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                @if ($player->user_id !== auth()->id())
                                    <a href="{{ route('groups.rate', ['group' => $group, 'oyuncu' => $player->id]) }}" wire:navigate
                                       class="text-xs px-3 py-1.5 rounded-md bg-gradient-to-b from-[#2C7A48] to-[#1F5A35] border border-[#3E9A60] font-semibold hover:brightness-125">
                                        ⭐ Puanla
                                    </a>
                                @endif
                                @if ($isAdmin)
                                    <button wire:click="editPositions({{ $player->id }})"
                                            class="text-xs px-3 py-1.5 rounded-md bg-pitch-surface2 border border-pitch-line hover:brightness-125">
                                        Düzenle
                                    </button>
                                @endif
                                @if ($player->isGuest() && $isAdmin)
                                    @if ($unlinkedMembers->isNotEmpty())
                                        <select wire:change="linkGuest({{ $player->id }}, $event.target.value)"
                                                class="text-xs bg-pitch-bg border-pitch-line text-pitch-muted rounded-md focus:border-bibB focus:ring-bibB/40">
                                            <option value="" disabled selected>Hesapla eşleştir…</option>
                                            @foreach ($unlinkedMembers as $member)
                                                <option value="{{ $member->id }}">{{ $member->name }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    <button wire:click="removeGuest({{ $player->id }})"
                                            wire:confirm="{{ $player->name }} gruptan silinsin mi?"
                                            class="text-xs px-3 py-1.5 rounded-md border border-[#6c3030] text-[#ffb3b3] hover:bg-red-900/30">
                                        Sil
                                    </button>
                                @elseif ($isAdmin && ! $player->isGuest() && $player->user_id !== $group->owner_id && $player->user_id !== auth()->id())
                                    <button wire:click="removeMember({{ $player->user_id }})"
                                            wire:confirm="{{ $player->name }} gruptan çıkarılsın mı? (Maç geçmişi ve puanları korunur, oyuncu misafire döner.)"
                                            class="text-xs px-3 py-1.5 rounded-md border border-[#6c3030] text-[#ffb3b3] hover:bg-red-900/30">
                                        Çıkar
                                    </button>
                                @endif
                            </div>
                        </div>

                        @if ($editingPlayerId === $player->id)
                            <div class="mt-3 border border-pitch-line rounded-lg p-4 space-y-3">
                                <div class="text-xs uppercase tracking-widest text-pitch-muted">Pozisyonlar — tıklama sırası önceliği belirler (1. → 2. → 3.). Kaleci başka pozisyonlarla birleşebilir.</div>
                                <div class="flex gap-2 flex-wrap">
                                    @foreach (Attributes::POSITIONS as $code => $label)
                                        @php $rank = array_search($code, $posOrder, true); @endphp
                                        <button type="button" wire:click="togglePosition('{{ $code }}')"
                                                class="relative px-3 py-1.5 rounded-full border text-sm font-semibold transition
                                                       {{ $rank !== false ? 'bg-bibB/15 border-bibB text-bibB' : 'bg-pitch-bg border-pitch-line text-pitch-muted hover:brightness-125' }}">
                                            {{ $label }}
                                            @if ($rank !== false && count($posOrder) > 1)
                                                <span class="absolute -top-2 -right-1 w-4 h-4 rounded-full bg-gold text-pitch-bg text-[10px] font-extrabold leading-4">{{ $rank + 1 }}</span>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                                <div class="flex items-center gap-3 flex-wrap">
                                    <x-text-input wire:model="editName" type="text" maxlength="24" class="w-48" placeholder="Ad / Lakap" />
                                    <x-text-input wire:model="editNumber" type="number" min="1" max="99" class="w-28" placeholder="Forma No" />
                                    <select wire:model="editFoot" class="bg-pitch-bg border-pitch-line text-pitch-ink rounded-md text-sm focus:border-bibB focus:ring-bibB/40" aria-label="Tercih edilen ayak">
                                        @foreach (\App\Support\Attributes::FEET as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <x-primary-button wire:click="savePositions" type="button">Kaydet</x-primary-button>
                                    <x-secondary-button wire:click="$set('editingPlayerId', null)">Vazgeç</x-secondary-button>
                                </div>
                                <x-input-error :messages="$errors->get('posOrder')" />
                                <x-input-error :messages="$errors->get('editName')" />
                                <x-input-error :messages="$errors->get('editNumber')" />
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Eşleşme kuralları --}}
        @if ($isAdmin && $players->count() >= 2)
            <div class="bg-pitch-surface border border-pitch-line rounded-xl p-6 space-y-4">
                <h3 class="font-display uppercase tracking-wider text-lg font-semibold">Eşleşme Kuralları <span class="text-xs text-pitch-muted tracking-widest font-normal">DENGELEME BU KURALLARA UYAR</span></h3>
                <div class="flex flex-wrap items-center gap-2">
                    <select wire:model="ruleA" class="bg-pitch-bg border-pitch-line text-pitch-ink rounded-md text-sm focus:border-bibB focus:ring-bibB/40">
                        <option value="">— oyuncu —</option>
                        @foreach ($players->sortBy('name') as $player)
                            <option value="{{ $player->id }}">{{ $player->name }}</option>
                        @endforeach
                    </select>
                    <select wire:model="ruleType" class="bg-pitch-bg border-pitch-line text-pitch-ink rounded-md text-sm focus:border-bibB focus:ring-bibB/40">
                        <option value="apart">ayrı takımlarda olsun</option>
                        <option value="together">aynı takımda olsun</option>
                    </select>
                    <select wire:model="ruleB" class="bg-pitch-bg border-pitch-line text-pitch-ink rounded-md text-sm focus:border-bibB focus:ring-bibB/40">
                        <option value="">— oyuncu —</option>
                        @foreach ($players->sortBy('name') as $player)
                            <option value="{{ $player->id }}">{{ $player->name }}</option>
                        @endforeach
                    </select>
                    <x-secondary-button wire:click="addRule">+ Kural Ekle</x-secondary-button>
                </div>
                <x-input-error :messages="$errors->get('ruleA')" />
                <div class="flex flex-wrap gap-2">
                    @forelse ($rules as $rule)
                        <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-pitch-bg border text-sm {{ $rule->type === 'apart' ? 'border-[#a05a30]' : 'border-[#3E9A60]' }}">
                            {{ $rule->type === 'apart' ? '↔' : '🔗' }}
                            <b>{{ $rule->playerA->name }}</b> + <b>{{ $rule->playerB->name }}</b>
                            <span class="text-pitch-muted text-xs">{{ $rule->type === 'apart' ? 'ayrı takımlarda' : 'aynı takımda' }}</span>
                            <button wire:click="deleteRule({{ $rule->id }})" class="px-2 rounded-full hover:bg-pitch-surface2">×</button>
                        </span>
                    @empty
                        <span class="text-sm text-pitch-muted">Kural yok — örn. "kardeşler ayrı takımlarda olsun" gibi şartlar ekleyebilirsin.</span>
                    @endforelse
                </div>
            </div>
        @endif

        {{-- Geçmiş maçlar --}}
        @if ($past->isNotEmpty())
            <div class="bg-pitch-surface border border-pitch-line rounded-xl p-6 space-y-3">
                <h3 class="font-display uppercase tracking-wider text-lg font-semibold">Geçmiş Maçlar</h3>
                @foreach ($past as $match)
                    <a href="{{ route('matches.show', $match) }}" wire:navigate
                       class="block border border-pitch-line rounded-lg p-4 hover:bg-pitch-surface2 transition">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-semibold">{{ $match->title }}</div>
                                <div class="text-sm text-pitch-muted mt-0.5">{{ $match->starts_at->translatedFormat('d F Y H:i') }}</div>
                            </div>
                            <div class="text-sm shrink-0 ms-4">
                                @if ($match->status === 'completed' && $match->team_a_score !== null)
                                    <span class="font-display text-xl font-bold"><span class="text-bibA">{{ $match->team_a_score }}</span> : <span class="text-bibB">{{ $match->team_b_score }}</span></span>
                                @elseif ($match->status === 'cancelled')
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-red-500/15 text-[#FF8A8A]">İptal</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-gold/15 text-gold">Sonuç bekleniyor</span>
                                @endif
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
