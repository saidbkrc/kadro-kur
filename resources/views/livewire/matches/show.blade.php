@php
    use App\Support\Attributes;
    use App\Support\PitchLayout;
    $tier = fn (float $o) => $o >= 8 ? 'text-gold' : ($o >= 6.5 ? 'text-[#7DE39A]' : ($o >= 5 ? 'text-pitch-ink' : 'text-pitch-muted'));
@endphp

<div class="py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        {{-- Maç bilgisi --}}
        <div class="bg-pitch-surface border border-pitch-line rounded-xl p-6 space-y-3">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <a href="{{ route('groups.show', $group) }}" wire:navigate class="text-sm text-bibB hover:underline">← {{ $group->name }}</a>
                    <h2 class="font-display uppercase tracking-wider text-2xl font-bold mt-1">{{ $match->title }}</h2>
                    <p class="text-pitch-muted mt-1">
                        📅 {{ $match->starts_at->translatedFormat('d F Y, l H:i') }}
                        @if ($match->location) · 📍 {{ $match->location }} @endif
                    </p>
                </div>
                <div class="shrink-0 text-end">
                    @if ($match->status === 'scheduled')
                        <div class="font-display text-3xl font-bold {{ $going->count() >= $match->capacity ? 'text-bibA' : '' }}">
                            {{ $going->count() }}/{{ $match->capacity }}
                        </div>
                        <div class="text-xs tracking-[.2em] text-pitch-muted">KADRO</div>
                    @elseif ($match->status === 'completed')
                        <div class="font-display text-3xl font-bold"><span class="text-bibA">{{ $match->team_a_score }}</span> : <span class="text-bibB">{{ $match->team_b_score }}</span></div>
                        <div class="text-xs tracking-[.2em] text-pitch-muted">TURUNCU : YEŞİL</div>
                    @else
                        <span class="inline-flex px-2.5 py-1 rounded text-sm font-medium bg-red-500/15 text-[#FF8A8A]">İptal edildi</span>
                    @endif
                </div>
            </div>

            @if ($canManage && $match->status === 'scheduled')
                <div class="flex flex-wrap gap-2 pt-3 border-t border-pitch-line">
                    @if ($going->count() >= 4)
                        <x-primary-button wire:click="buildSquads" type="button"
                                wire:confirm="Kadrolar ortalama puanlara ve kurallara göre dağıtılacak, varsa mevcut oylama sıfırlanacak. Devam edilsin mi?">
                            ⚖️ Kadroları Kur
                        </x-primary-button>
                    @endif
                    <x-secondary-button wire:click="$toggle('showTemplates')">
                        🗂 Şablonlar
                    </x-secondary-button>
                    <x-secondary-button wire:click="$toggle('showResultForm')">
                        {{ $showResultForm ? 'Vazgeç' : '📝 Sonucu Gir' }}
                    </x-secondary-button>
                    <x-danger-button wire:click="cancelMatch" wire:confirm="Maç iptal edilecek. Emin misin?" type="button">
                        Maçı İptal Et
                    </x-danger-button>
                </div>
                <x-input-error :messages="$errors->get('squad')" />

                {{-- Alternatif kadrolar arası gezinme --}}
                @if (count($alternatives) > 1)
                    <div class="flex items-center gap-2 flex-wrap pt-1">
                        <span class="text-xs uppercase tracking-widest text-pitch-muted me-1">Alternatif kadro:</span>
                        <button wire:click="prevAlternative" class="w-8 h-8 rounded-md border border-pitch-line hover:bg-pitch-surface2" title="Önceki">‹</button>
                        @foreach ($alternatives as $i => $alt)
                            <button wire:click="goToAlternative({{ $i }})"
                                    class="w-8 h-8 rounded-md border text-sm font-semibold transition
                                           {{ $altIndex === $i ? 'bg-bibB text-pitch-bg border-bibB' : 'border-pitch-line text-pitch-muted hover:bg-pitch-surface2' }}">
                                {{ $i + 1 }}
                            </button>
                        @endforeach
                        <button wire:click="nextAlternative" class="w-8 h-8 rounded-md border border-pitch-line hover:bg-pitch-surface2" title="Sonraki">›</button>
                        <span class="text-xs text-pitch-muted ms-1">{{ $altIndex + 1 }}/{{ count($alternatives) }} — en dengeliden başlar</span>
                    </div>
                @endif

                {{-- Kadro şablonları --}}
                @if ($showTemplates)
                    <div class="border border-pitch-line rounded-lg p-4 space-y-3 mt-1">
                        <h4 class="font-display uppercase tracking-wider text-sm">Kadro Şablonları <span class="text-pitch-muted font-normal">({{ $templates->count() }}/{{ $maxTemplates }})</span></h4>
                        <p class="text-xs text-pitch-muted">Sık oynayan sabit kadroyu kaydet, sonraki maçta tek tıkla yükle. Yüklenen kadro yine %60 oylamasına gider.</p>

                        @if ($templates->isNotEmpty())
                            <div class="space-y-1.5">
                                @foreach ($templates as $tpl)
                                    <div class="flex items-center justify-between gap-3 bg-pitch-bg border border-pitch-line rounded-lg px-3 py-2">
                                        <span class="text-sm font-medium">{{ $tpl->name }} <span class="text-xs text-pitch-muted">({{ count($tpl->teams) }} oyuncu)</span></span>
                                        <div class="flex gap-2 shrink-0">
                                            <button wire:click="applyTemplate({{ $tpl->id }})" class="text-xs px-3 py-1.5 rounded-md bg-gradient-to-b from-[#2C7A48] to-[#1F5A35] border border-[#3E9A60] font-semibold hover:brightness-125">Yükle</button>
                                            <button wire:click="deleteTemplate({{ $tpl->id }})" wire:confirm="'{{ $tpl->name }}' şablonu silinsin mi?" class="text-xs px-3 py-1.5 rounded-md border border-[#6c3030] text-[#ffb3b3] hover:bg-red-900/30">Sil</button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if ($templates->count() < $maxTemplates)
                            <div class="flex items-center gap-2 flex-wrap">
                                <x-text-input wire:model="templateName" type="text" maxlength="40" class="w-56" placeholder="Şablon adı (örn. Çekirdek Kadro)" />
                                <x-secondary-button wire:click="saveTemplate">Mevcut kadroyu kaydet</x-secondary-button>
                            </div>
                        @else
                            <p class="text-xs text-gold">En fazla {{ $maxTemplates }} şablon — yeni kaydetmek için birini sil.</p>
                        @endif
                        <x-input-error :messages="$errors->get('template')" />
                    </div>
                @endif

                @if ($templateNotice)
                    <p class="text-sm text-bibB bg-bibB/10 border border-bibB/30 rounded-md p-3">{{ $templateNotice }}</p>
                @endif
            @endif
        </div>

        {{-- RSVP — kadro kurulduktan sonra kişisel "geliyor musun?" sorusu gizlenir --}}
        @if ($match->status === 'scheduled' && ($match->squad_status === 'none' || $canManage))
            <div class="bg-pitch-surface border border-pitch-line rounded-xl p-6 space-y-3">
                @if ($match->squad_status === 'none')
                    <h3 class="font-display uppercase tracking-wider text-lg font-semibold">Geliyor musun?</h3>
                    <div class="flex flex-wrap gap-2">
                        <button wire:click="rsvp('going')"
                                class="px-5 py-2.5 rounded-md font-semibold text-sm border transition
                                       {{ $myRsvp?->status === 'going' ? 'bg-[#2C7A48] text-pitch-ink border-[#3E9A60]' : 'bg-transparent text-bibB border-pitch-line hover:bg-pitch-surface2' }}">
                            ✅ Geliyorum
                        </button>
                        <button wire:click="rsvp('maybe')"
                                class="px-5 py-2.5 rounded-md font-semibold text-sm border transition
                                       {{ $myRsvp?->status === 'maybe' ? 'bg-gold text-pitch-bg border-gold' : 'bg-transparent text-gold border-pitch-line hover:bg-pitch-surface2' }}">
                            🤔 Belki
                        </button>
                        <button wire:click="rsvp('not_going')"
                                class="px-5 py-2.5 rounded-md font-semibold text-sm border transition
                                       {{ $myRsvp?->status === 'not_going' ? 'bg-red-700 text-pitch-ink border-red-600' : 'bg-transparent text-[#FF8A8A] border-pitch-line hover:bg-pitch-surface2' }}">
                            ❌ Gelmiyorum
                        </button>
                    </div>
                    @if ($myRsvp?->status === 'going' && $myRsvp->waitlist_position !== null)
                        <p class="text-sm text-gold bg-gold/10 border border-gold/30 rounded-md p-3">
                            Kadro dolu — <strong>yedek listesinde {{ $myRsvp->waitlist_position }}. sıradasın.</strong>
                            Biri çekilirse otomatik olarak kadroya geçersin.
                        </p>
                    @endif
                @else
                    <h3 class="font-display uppercase tracking-wider text-lg font-semibold">Katılım</h3>
                    <p class="text-sm text-pitch-muted">Kadro kuruldu. Katılımı aşağıdan yönetebilir, gerekirse kadroyu yeniden kurabilirsin.</p>
                @endif

                @if ($canManage)
                    <div class="border-t border-pitch-line pt-4">
                        <x-secondary-button wire:click="$toggle('showManageRsvp')">
                            👥 {{ $showManageRsvp ? 'Katılım yönetimini kapat' : 'Katılımı yönet (başkan)' }}
                        </x-secondary-button>
                        <p class="text-xs text-pitch-muted mt-2">Gelemeyenler adına da işaretleyebilirsin — WhatsApp'tan toplayıp tek tek girme derdi bitsin.</p>

                        @if ($showManageRsvp)
                            <div class="mt-3 space-y-1.5">
                                @foreach ($roster as $player)
                                    @php $st = $rsvpByPlayer[$player->id]->status ?? null; @endphp
                                    <div class="flex items-center justify-between gap-3 bg-pitch-bg border border-pitch-line rounded-lg px-3 py-2">
                                        <span class="text-sm">
                                            {{ $player->name }}
                                            @if ($player->isGuest())<span class="text-xs text-gold">(misafir)</span>@endif
                                        </span>
                                        <div class="flex gap-1 shrink-0">
                                            <button wire:click="setPlayerRsvp({{ $player->id }}, 'going')"
                                                    class="text-xs px-2.5 py-1 rounded-md border transition {{ $st === 'going' ? 'bg-[#2C7A48] border-[#3E9A60] text-pitch-ink' : 'border-pitch-line text-bibB hover:bg-pitch-surface2' }}">Geliyor</button>
                                            <button wire:click="setPlayerRsvp({{ $player->id }}, 'maybe')"
                                                    class="text-xs px-2.5 py-1 rounded-md border transition {{ $st === 'maybe' ? 'bg-gold border-gold text-pitch-bg' : 'border-pitch-line text-gold hover:bg-pitch-surface2' }}">Belki</button>
                                            <button wire:click="setPlayerRsvp({{ $player->id }}, 'not_going')"
                                                    class="text-xs px-2.5 py-1 rounded-md border transition {{ $st === 'not_going' ? 'bg-red-700 border-red-600 text-pitch-ink' : 'border-pitch-line text-[#FF8A8A] hover:bg-pitch-surface2' }}">Gelmiyor</button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endif

        {{-- Kadro + oylama + saha --}}
        @if ($teamA->isNotEmpty() || $teamB->isNotEmpty())
            {{-- Onay oylaması --}}
            @if ($voteSummary)
                <div class="bg-pitch-surface border {{ $match->squad_status === 'approved' ? 'border-[#3E9A60]' : 'border-gold/40' }} rounded-xl p-6 space-y-3">
                    @if ($match->squad_status === 'approved')
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">✅</span>
                            <div>
                                <h3 class="font-display uppercase tracking-wider text-lg font-semibold text-bibB">Kadro Onaylandı</h3>
                                <p class="text-sm text-pitch-muted">{{ $voteSummary['yes'] }} evet oyu ile %60 çoğunluk sağlandı.</p>
                            </div>
                        </div>
                    @else
                        <h3 class="font-display uppercase tracking-wider text-lg font-semibold">🗳️ Kadro Oylaması</h3>
                        <p class="text-sm text-pitch-muted">
                            Kadronun kesinleşmesi için kadrodaki oyuncuların <strong class="text-pitch-ink">%60'ının</strong> onayı gerekiyor:
                            <strong class="text-pitch-ink">{{ $voteSummary['yes'] }}/{{ $voteSummary['needed'] }}</strong> evet
                            ({{ $voteSummary['no'] }} hayır, {{ $voteSummary['eligible'] }} oy hakkı).
                        </p>
                        <div class="h-2.5 rounded-full bg-pitch-bg border border-pitch-line overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-[#2C7A48] to-bibB" style="width: {{ $voteSummary['needed'] > 0 ? min(100, round($voteSummary['yes'] / $voteSummary['needed'] * 100)) : 0 }}%"></div>
                        </div>
                        @if ($canVoteSquad)
                            <div class="flex gap-2 items-center">
                                <button wire:click="voteSquad(true)"
                                        class="px-4 py-2 rounded-md text-sm font-semibold border transition {{ $mySquadVote?->approve === true ? 'bg-[#2C7A48] border-[#3E9A60]' : 'border-pitch-line hover:bg-pitch-surface2 text-bibB' }}">
                                    👍 Onaylıyorum
                                </button>
                                <button wire:click="voteSquad(false)"
                                        class="px-4 py-2 rounded-md text-sm font-semibold border transition {{ $mySquadVote?->approve === false ? 'bg-red-700 border-red-600' : 'border-pitch-line hover:bg-pitch-surface2 text-[#FF8A8A]' }}">
                                    👎 Reddediyorum
                                </button>
                                @if ($mySquadVote)
                                    <span class="text-xs text-pitch-muted">Oyunu değiştirebilirsin.</span>
                                @endif
                            </div>
                        @endif
                        @if ($canManage && $voteSummary['no'] > $voteSummary['eligible'] - $voteSummary['needed'])
                            <p class="text-sm text-gold">%60'a ulaşmak artık mümkün değil — <strong>Alternatif Kadro</strong> ile yeni bir bölünme sun ya da elle takas yap.</p>
                        @endif
                    @endif
                </div>
            @endif

            {{-- Denge göstergesi + takım listeleri --}}
            <div class="bg-pitch-surface border border-pitch-line rounded-xl p-6 space-y-4">
                <div class="grid grid-cols-[1fr,auto,1fr] items-center gap-4">
                    <div class="font-display text-2xl font-bold text-bibA">TURUNCU <span class="block text-[11px] tracking-[.2em] text-pitch-muted font-semibold">{{ $teamA->count() }} OYUNCU · ORT {{ number_format($avgA, 1) }}</span></div>
                    @php $diff = $avgA - $avgB; $shift = max(-22, min(22, $diff * 12)); @endphp
                    <div class="min-w-[160px] sm:min-w-[260px]">
                        <div class="relative h-3.5 rounded-full bg-pitch-bg border border-pitch-line overflow-hidden">
                            <div class="absolute left-0 top-0 bottom-0 bg-gradient-to-r from-bibA to-bibA/50" style="width: {{ 50 + $shift }}%"></div>
                            <div class="absolute right-0 top-0 bottom-0 bg-gradient-to-l from-bibB to-bibB/50" style="width: {{ 50 - $shift }}%"></div>
                            <div class="absolute left-1/2 -top-0.5 -bottom-0.5 w-0.5 bg-white/85"></div>
                        </div>
                        <div class="text-center text-xs text-pitch-muted mt-1.5">
                            {{ abs($diff) < 0.05 ? 'Tam denge ✓' : 'Fark: '.number_format(abs($diff), 1).' puan ('.($diff > 0 ? 'Turuncu' : 'Yeşil').' önde)' }}
                        </div>
                    </div>
                    <div class="font-display text-2xl font-bold text-bibB text-end">YEŞİL <span class="block text-[11px] tracking-[.2em] text-pitch-muted font-semibold">{{ $teamB->count() }} OYUNCU · ORT {{ number_format($avgB, 1) }}</span></div>
                </div>

                @if ($canManage && $match->status === 'scheduled')
                    <p class="text-xs text-pitch-muted">Elle değişiklik: bir takımdan oyuncuya tıkla, sonra <strong class="text-gold">diğer takımdan</strong> birine tıkla — yer değiştirirler (oylama yeniden başlar).</p>
                @endif

                <div class="grid sm:grid-cols-2 gap-4">
                    @foreach ([['A', 'Turuncu Yelek', $teamA, 'border-bibA', 'bg-bibA/10 text-bibA'], ['B', 'Yeşil Yelek', $teamB, 'border-bibB', 'bg-bibB/10 text-bibB']] as [$side, $teamName, $team, $borderClass, $headClass])
                        <div class="border border-pitch-line rounded-xl overflow-hidden">
                            <div class="px-4 py-2.5 border-b-2 {{ $borderClass }} {{ $headClass }} font-display uppercase tracking-wider text-lg font-bold">{{ $teamName }}</div>
                            <ul>
                                @foreach ($team as $rsvp)
                                    <li @if ($canManage && $match->status === 'scheduled') wire:click="swap({{ $rsvp->player_id }})" @endif
                                        class="flex items-center gap-3 px-4 py-2.5 border-b border-pitch-line last:border-b-0 transition
                                               {{ $canManage && $match->status === 'scheduled' ? 'cursor-pointer hover:bg-pitch-surface2' : '' }}
                                               {{ $swapArmed === $rsvp->player_id ? 'bg-gold/10 shadow-[inset_3px_0_0_#FFC83D]' : '' }}">
                                        @if ($rsvp->player->overallIsPublic())
                                            <span class="font-display text-lg font-bold w-9 text-center {{ $tier($rsvp->player->overall()) }}">{{ number_format($rsvp->player->overall(), 1) }}</span>
                                        @else
                                            <span class="font-display text-lg font-bold w-9 text-center text-pitch-muted" title="Puan, {{ \App\Models\Player::minRatingsForVisibility() }} kişi oylayınca görünür">?</span>
                                        @endif
                                        <span class="font-semibold">{{ $rsvp->player->name }}
                                            @if ($rsvp->player->shirt_number)<span class="text-pitch-muted text-xs font-normal">#{{ $rsvp->player->shirt_number }}</span>@endif
                                            @if ($myPlayer && $rsvp->player_id === $myPlayer->id)<span class="text-xs text-pitch-muted font-normal">(sen)</span>@endif
                                        </span>
                                        <span class="ms-auto flex gap-1">
                                            @foreach ($rsvp->player->positions ?? [] as $i => $pos)
                                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-pitch-bg border border-pitch-line {{ $pos === 'KL' ? 'text-gold' : 'text-pitch-muted' }}">{{ count($rsvp->player->positions) > 1 ? ($i + 1).'·' : '' }}{{ $pos }}</span>
                                            @endforeach
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Saha dizilişi --}}
            <div class="bg-pitch-surface border border-pitch-line rounded-xl p-6 space-y-3" wire:ignore.self>
                <div class="flex items-center gap-4 flex-wrap">
                    <h3 class="font-display uppercase tracking-wider text-lg font-semibold">Saha Dizilişi</h3>
                    @if ($canManage)
                        <label class="inline-flex items-center gap-2 text-sm font-semibold text-bibA">🟠 Turuncu
                            <select wire:change="setFormation('a', $event.target.value)"
                                    class="bg-pitch-bg border-pitch-line text-pitch-ink rounded-md text-sm focus:border-bibB focus:ring-bibB/40">
                                <option value="auto" @selected($match->formation_a === null)>Otomatik</option>
                                @foreach (Attributes::FORMATIONS as $f)
                                    <option value="{{ $f }}" @selected($match->formation_a === $f)>{{ $f }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm font-semibold text-bibB">🟢 Yeşil
                            <select wire:change="setFormation('b', $event.target.value)"
                                    class="bg-pitch-bg border-pitch-line text-pitch-ink rounded-md text-sm focus:border-bibB focus:ring-bibB/40">
                                <option value="auto" @selected($match->formation_b === null)>Otomatik</option>
                                @foreach (Attributes::FORMATIONS as $f)
                                    <option value="{{ $f }}" @selected($match->formation_b === $f)>{{ $f }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                    <span class="grow"></span>
                    @if ($canManage)
                        <x-secondary-button wire:click="resetLayout">Dizilişi Sıfırla</x-secondary-button>
                    @endif
                    <button type="button" id="btnExportPng"
                            class="inline-flex items-center px-4 py-2 bg-pitch-surface2 border border-pitch-line rounded-md font-semibold text-xs text-pitch-ink uppercase tracking-widest hover:brightness-125">
                        🖼 PNG indir
                    </button>
                </div>

                <svg id="pitchSvg" viewBox="0 0 {{ PitchLayout::W }} {{ PitchLayout::H }}" role="img" aria-label="Takım dizilişi"
                     class="w-full h-auto block rounded-xl border border-pitch-line touch-none select-none">
                    <defs>
                        <pattern id="grass" width="100" height="{{ PitchLayout::H }}" patternUnits="userSpaceOnUse">
                            <rect width="50" height="{{ PitchLayout::H }}" fill="#15502F"/>
                            <rect x="50" width="50" height="{{ PitchLayout::H }}" fill="#196038"/>
                        </pattern>
                    </defs>
                    <rect width="{{ PitchLayout::W }}" height="{{ PitchLayout::H }}" fill="url(#grass)"/>
                    <g stroke="rgba(255,255,255,.8)" stroke-width="2.5" fill="none">
                        <rect x="14" y="14" width="{{ PitchLayout::W - 28 }}" height="{{ PitchLayout::H - 28 }}"/>
                        <line x1="{{ PitchLayout::W / 2 }}" y1="14" x2="{{ PitchLayout::W / 2 }}" y2="{{ PitchLayout::H - 14 }}"/>
                        <circle cx="{{ PitchLayout::W / 2 }}" cy="{{ PitchLayout::H / 2 }}" r="62"/>
                        <circle cx="{{ PitchLayout::W / 2 }}" cy="{{ PitchLayout::H / 2 }}" r="3" fill="rgba(255,255,255,.8)"/>
                        <rect x="14" y="{{ PitchLayout::H / 2 - 110 }}" width="105" height="220"/>
                        <rect x="{{ PitchLayout::W - 14 - 105 }}" y="{{ PitchLayout::H / 2 - 110 }}" width="105" height="220"/>
                        <rect x="14" y="{{ PitchLayout::H / 2 - 52 }}" width="42" height="104"/>
                        <rect x="{{ PitchLayout::W - 14 - 42 }}" y="{{ PitchLayout::H / 2 - 52 }}" width="42" height="104"/>
                        <path d="M 119 {{ PitchLayout::H / 2 - 46 }} A 52 52 0 0 1 119 {{ PitchLayout::H / 2 + 46 }}"/>
                        <path d="M {{ PitchLayout::W - 119 }} {{ PitchLayout::H / 2 - 46 }} A 52 52 0 0 0 {{ PitchLayout::W - 119 }} {{ PitchLayout::H / 2 + 46 }}"/>
                    </g>
                    @foreach ([['A', $pitchA, '#FF7A1A'], ['B', $pitchB, '#C8F04B']] as [$side, $nodes, $fill])
                        @foreach ($nodes as $node)
                            <g class="{{ $canManage ? 'pnode cursor-grab' : '' }}" data-id="{{ $node['id'] }}" transform="translate({{ $node['x'] }},{{ $node['y'] }})">
                                <circle r="17" fill="{{ $fill }}" stroke="rgba(0,0,0,.4)" stroke-width="2"/>
                                @php $label = $node['number'] ?? (($node['ovr_public'] ?? true) ? round($node['ovr']) : '–'); @endphp
                                <text y="4.5" text-anchor="middle" font-family="Arial, sans-serif" font-size="{{ strlen((string) $label) > 1 ? 12 : 13 }}" font-weight="800" fill="#10240F">{{ $label }}</text>
                                <text y="33" text-anchor="middle" font-family="Arial, sans-serif" font-size="11" font-weight="700" fill="#ffffff" paint-order="stroke" stroke="rgba(0,0,0,.65)" stroke-width="3">{{ $node['name'] }}</text>
                            </g>
                        @endforeach
                    @endforeach
                </svg>
                @if ($canManage)
                    <p class="text-xs text-pitch-muted">Oyuncuları saha üzerinde <strong class="text-pitch-ink">sürükleyip bırakarak</strong> dizilişi istediğin gibi kurabilirsin.</p>
                @endif
            </div>
        @endif

        {{-- Katılım listeleri --}}
        <div class="grid sm:grid-cols-3 gap-4">
            <div class="bg-pitch-surface border border-pitch-line rounded-xl p-5">
                <h3 class="font-semibold mb-3">✅ Kadro ({{ $going->count() }}/{{ $match->capacity }})</h3>
                <ol class="space-y-1.5 text-sm list-decimal list-inside">
                    @forelse ($going as $rsvp)
                        <li>{{ $rsvp->player->name }}
                            @if ($rsvp->player->isGuest())<span class="text-xs text-gold">(misafir)</span>@endif
                            @if ($myPlayer && $rsvp->player_id === $myPlayer->id)<span class="text-xs text-pitch-muted">(sen)</span>@endif
                        </li>
                    @empty
                        <p class="text-pitch-muted text-sm">Henüz kimse yok — ilk sen ol!</p>
                    @endforelse
                </ol>
                @if ($waitlist->isNotEmpty())
                    <h4 class="font-semibold text-gold mt-4 mb-2 text-sm">⏳ Yedek Listesi</h4>
                    <ol class="space-y-1.5 text-sm text-pitch-muted">
                        @foreach ($waitlist as $rsvp)
                            <li>{{ $rsvp->waitlist_position }}. {{ $rsvp->player->name }} @if ($myPlayer && $rsvp->player_id === $myPlayer->id)<span class="text-xs">(sen)</span>@endif</li>
                        @endforeach
                    </ol>
                @endif
            </div>
            <div class="bg-pitch-surface border border-pitch-line rounded-xl p-5">
                <h3 class="font-semibold mb-3">🤔 Belki ({{ $maybe->count() }})</h3>
                <ul class="space-y-1.5 text-sm">
                    @forelse ($maybe as $rsvp)
                        <li>{{ $rsvp->player->name }}</li>
                    @empty
                        <p class="text-pitch-muted text-sm">—</p>
                    @endforelse
                </ul>
            </div>
            <div class="bg-pitch-surface border border-pitch-line rounded-xl p-5">
                <h3 class="font-semibold mb-3">❌ Gelmiyor ({{ $notGoing->count() }})</h3>
                <ul class="space-y-1.5 text-sm">
                    @forelse ($notGoing as $rsvp)
                        <li>{{ $rsvp->player->name }}</li>
                    @empty
                        <p class="text-pitch-muted text-sm">—</p>
                    @endforelse
                </ul>
            </div>
        </div>

        {{-- Sonuç girme formu --}}
        @if ($showResultForm && $canManage)
            <div class="bg-pitch-surface border border-pitch-line rounded-xl">
                <form wire:submit="saveResult" class="p-6 space-y-4">
                    <h3 class="font-display uppercase tracking-wider text-lg font-semibold">Maç Bitti Mi?</h3>
                    <div class="flex items-center gap-3">
                        <span class="font-bold text-bibA">Turuncu</span>
                        <x-text-input wire:model="teamAScore" type="number" min="0" max="99" class="w-20 text-center text-lg font-bold" />
                        <span class="font-display text-2xl text-pitch-muted">:</span>
                        <x-text-input wire:model="teamBScore" type="number" min="0" max="99" class="w-20 text-center text-lg font-bold" />
                        <span class="font-bold text-bibB">Yeşil</span>
                    </div>
                    <x-input-error :messages="$errors->get('teamAScore')" />
                    <x-input-error :messages="$errors->get('teamBScore')" />

                    @if ($going->isNotEmpty())
                        <div>
                            <x-input-label value="⚽ Golleri atanlar (gol atanlara sayı gir, diğerlerini boş bırak)" class="mb-2" />
                            <div class="grid sm:grid-cols-2 gap-2">
                                @foreach ($going as $rsvp)
                                    <div class="flex items-center justify-between gap-2 border border-pitch-line rounded-md px-3 py-2">
                                        <span class="text-sm">
                                            {{ $rsvp->player->name }}
                                            @if ($rsvp->team)<span class="text-xs {{ $rsvp->team === 'A' ? 'text-bibA' : 'text-bibB' }}">({{ $rsvp->team === 'A' ? 'Turuncu' : 'Yeşil' }})</span>@endif
                                        </span>
                                        <input type="number" min="0" max="30" placeholder="0"
                                               wire:model="goals.{{ $rsvp->player_id }}"
                                               class="w-16 text-sm bg-pitch-bg border-pitch-line text-pitch-ink rounded-md focus:border-bibB focus:ring-bibB/40">
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <x-primary-button>🏁 Sonucu Kaydet ve Maçı Bitir</x-primary-button>
                    <p class="text-xs text-pitch-muted">Skor kaydedilince MVP oylaması <strong class="text-pitch-ink">24 saat</strong> açılır ve haftalık otomatik maç ayarlıysa sıradaki maç açılır.</p>
                </form>
            </div>
        @endif

        {{-- Maç sonu: golcüler + MVP --}}
        @if ($match->status === 'completed')
            @if ($matchGoals->isNotEmpty())
                <div class="bg-pitch-surface border border-pitch-line rounded-xl p-6">
                    <h3 class="font-display uppercase tracking-wider text-lg font-semibold mb-3">⚽ Golleri Atanlar</h3>
                    <ul class="space-y-1.5 text-sm">
                        @foreach ($matchGoals as $goal)
                            <li><strong class="text-gold">{{ $goal->count }}×</strong> {{ $goal->player?->name ?? 'Bilinmiyor' }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-pitch-surface border border-pitch-line rounded-xl p-6 space-y-4">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <h3 class="font-display uppercase tracking-wider text-lg font-semibold">🏆 Maçın Adamı (MVP)</h3>
                    @if ($match->mvpOpen())
                        <span class="text-xs text-gold bg-gold/10 border border-gold/30 rounded-full px-3 py-1">
                            Oylama açık — {{ (int) ceil(now()->diffInHours($match->mvp_closes_at, true)) }} saat kaldı
                        </span>
                    @elseif ($match->mvp_closes_at)
                        <span class="text-xs text-pitch-muted bg-pitch-bg border border-pitch-line rounded-full px-3 py-1">Oylama kapandı</span>
                    @endif
                </div>

                @if ($match->mvpOpen() && $isParticipant && ! $myMvpVote)
                    <p class="text-sm text-pitch-muted">Maçın yıldızı kimdi? <strong class="text-pitch-ink">Tek oy hakkın var ve değiştirilemez.</strong> Oylar anonim — kimse kime verdiğini görmez.</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($going as $rsvp)
                            @if (! $myPlayer || $rsvp->player_id !== $myPlayer->id)
                                <button wire:click="voteMvp({{ $rsvp->player_id }})"
                                        wire:confirm="{{ $rsvp->player->name }} için MVP oyu vereceksin. Bu oy değiştirilemez. Emin misin?"
                                        class="px-4 py-2 rounded-md text-sm font-medium border border-pitch-line hover:bg-pitch-surface2 hover:border-gold transition">
                                    {{ $rsvp->player->name }}
                                </button>
                            @endif
                        @endforeach
                    </div>
                @elseif ($mvpResults->isNotEmpty() && ($myMvpVote || ! $match->mvpOpen()))
                    <ul class="space-y-2">
                        @foreach ($mvpResults as $result)
                            <li class="flex items-center gap-3">
                                <span class="w-8 text-center">{{ $loop->first ? '👑' : $loop->iteration.'.' }}</span>
                                <span class="font-semibold {{ $loop->first ? 'text-gold' : '' }}">{{ $result->player->name }}</span>
                                <span class="text-sm text-pitch-muted">{{ $result->votes }} oy</span>
                                @if ($result->player_id === $myMvpVote?->player_id)
                                    <span class="text-xs text-bibB">(senin oyun)</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @elseif ($match->mvpOpen())
                    <p class="text-sm text-pitch-muted">Sonuçlar oylar verildikçe burada görünecek{{ $isParticipant ? '' : ' (kadroda olmadığın için oy kullanamazsın)' }}.</p>
                @else
                    <p class="text-sm text-pitch-muted">Hiç oy kullanılmadı.</p>
                @endif
            </div>
        @endif
    </div>

    @script
    <script>
        const PW = 1000, PH = 560;
        let drag = null;

        const coords = (e) => {
            const svg = document.getElementById('pitchSvg');
            const r = svg.getBoundingClientRect();
            return { x: (e.clientX - r.left) / r.width * PW, y: (e.clientY - r.top) / r.height * PH };
        };
        const nodeXY = (g) => {
            const m = /translate\(\s*([-\d.]+)[ ,]\s*([-\d.]+)\s*\)/.exec(g.getAttribute('transform'));
            return { x: +m[1], y: +m[2] };
        };

        const onDown = (e) => {
            const g = e.target.closest('#pitchSvg .pnode');
            if (!g) return;
            e.preventDefault();
            const pt = coords(e), cur = nodeXY(g);
            drag = { g, id: +g.dataset.id, dx: cur.x - pt.x, dy: cur.y - pt.y, moved: false };
            g.parentNode.appendChild(g); // öne getir
        };
        const onMove = (e) => {
            if (!drag) return;
            const pt = coords(e);
            const x = Math.min(PW - 30, Math.max(30, pt.x + drag.dx));
            const y = Math.min(PH - 32, Math.max(30, pt.y + drag.dy));
            drag.g.setAttribute('transform', `translate(${x},${y})`);
            drag.moved = true;
        };
        const onUp = () => {
            if (!drag) return;
            if (drag.moved) {
                const p = nodeXY(drag.g);
                $wire.movePlayer(drag.id, p.x, p.y);
            }
            drag = null;
        };

        document.addEventListener('pointerdown', onDown);
        document.addEventListener('pointermove', onMove);
        document.addEventListener('pointerup', onUp);
        document.addEventListener('pointercancel', onUp);

        // Saha dizilişini PNG olarak indir (SVG -> canvas -> PNG, 2x çözünürlük)
        const exportPng = () => {
            const svg = document.getElementById('pitchSvg');
            if (!svg) return;
            const svgStr = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${PW} ${PH}" width="${PW * 2}" height="${PH * 2}">${svg.innerHTML}</svg>`;
            const url = URL.createObjectURL(new Blob([svgStr], { type: 'image/svg+xml;charset=utf-8' }));
            const img = new Image();
            img.onload = () => {
                const c = document.createElement('canvas');
                c.width = PW * 2;
                c.height = PH * 2;
                c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
                URL.revokeObjectURL(url);
                c.toBlob((blob) => {
                    const a = document.createElement('a');
                    a.href = URL.createObjectURL(blob);
                    a.download = 'kadro-dizilis.png';
                    a.click();
                    URL.revokeObjectURL(a.href);
                });
            };
            img.onerror = () => URL.revokeObjectURL(url);
            img.src = url;
        };

        document.addEventListener('click', (e) => {
            if (e.target.closest('#btnExportPng')) exportPng();
        });
    </script>
    @endscript
</div>
