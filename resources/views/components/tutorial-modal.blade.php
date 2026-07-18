@props(['autoOpen' => false])

@php
    // Tanıtım turu: sade ama eksiksiz — her slayt tek kavram
    $slides = [
        ['icon' => '👋', 'title' => "Kadro Kur'a Hoş Geldin", 'text' => 'Halısaha grubunun tüm derdi tek yerde: maç organizasyonu, dengeli kadro, puanlama ve istatistik. Bu kısa tur neler yapabileceğini gösterir — oklarla ilerleyebilirsin.'],
        ['icon' => '👥', 'title' => 'Grubunu Kur, Arkadaşlarını Çağır', 'text' => "Grup kur, davet linkini WhatsApp'tan paylaş — tıklayan kayıt olup direkt gruba katılır. Hesabı olmayanları misafir oyuncu olarak ekle; kayıt olunca geçmişi korunarak hesabıyla eşleşir."],
        ['icon' => '📅', 'title' => 'Maç ve Katılım', 'text' => 'Haftalık maç gününü bir kere ayarla, sistem her hafta maçı otomatik açsın. "Geliyorum / Belki / Gelmiyorum" ile katılımını bildir; kadro dolunca yedek listesi devreye girer, biri çekilince sıradaki otomatik kadroya geçer.'],
        ['icon' => '⚖️', 'title' => 'Dengeli Kadro', 'text' => 'Tek tıkla puanlara ve kurallara göre dengeli iki takım kurulur. Alternatif kadrolar arasında gezin, elle takas yap. Kadro, oyuncuların %60 onayıyla kesinleşir; saha dizilişini sürükle-bırak ayarlarsın.'],
        ['icon' => '⭐', 'title' => 'Puanlama', 'text' => 'Takım arkadaşlarını mevkilerine göre anonim puanla — kimse kimin ne verdiğini görmez. Maç sonrası performans puanların son 5 maç formu olarak genel puana yansır (▲/▼ rozetiyle).'],
        ['icon' => '🏆', 'title' => 'Maç Sonu', 'text' => 'Başkan skoru ve golleri girer; MVP oylaması açılır (tek oy, değiştirilemez, 1 hafta). Ertesi gün maç özeti — skor, MVP, golcü — herkese bildirim olarak gider.'],
        ['icon' => '🏅', 'title' => 'Rozetler ve Nitelikler', 'text' => 'Rozetler maç verisinden otomatik kazanılır (Golcü, Seri, Duvar…). Nitelikleri ise takım arkadaşların onaylar: Maestro, Keskin Nişancı, Ciğersiz… Profilinde FIFA tarzı oyuncu kartın ve kafa kafaya kıyas da seni bekler.'],
        ['icon' => '🔔', 'title' => 'Bildirimler ve Uygulama', 'text' => 'Menüden "Bildirimleri Aç" — yeni maç, kadro oylaması ve MVP hatırlatmaları telefonuna gelsin. "Uygulamayı Yükle" ile ana ekranına kur. Hazırsan sahaya! 🎉'],
    ];
@endphp

<div x-data="{
        open: @js($autoOpen),
        slide: 0,
        total: {{ count($slides) }},
        close() { this.open = false; $wire.markTutorialSeen(); },
    }"
     x-on:open-tutorial.window="open = true; slide = 0"
     x-on:keydown.escape.window="open && close()">

    <div x-show="open" x-cloak
         class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 p-4"
         @click.self="close()">
        <div class="w-full max-w-md rounded-2xl bg-pitch-surface border border-pitch-line p-5 sm:p-6 shadow-xl">

            <div class="flex items-start justify-between gap-2">
                <span class="text-[10px] tracking-[.2em] text-pitch-muted" x-text="`${slide + 1} / ${total}`"></span>
                <button type="button" @click="close()" class="text-pitch-muted hover:text-pitch-ink text-xl leading-none -mt-1">&times;</button>
            </div>

            <div class="min-h-[240px] sm:min-h-[210px] mt-2">
                @foreach ($slides as $i => $s)
                    <div x-show="slide === {{ $i }}" x-cloak>
                        <div class="text-4xl mb-2">{{ $s['icon'] }}</div>
                        <h3 class="font-display uppercase tracking-wider text-lg font-bold mb-2">{{ $s['title'] }}</h3>
                        <p class="text-sm text-pitch-muted leading-relaxed">{{ $s['text'] }}</p>
                    </div>
                @endforeach
            </div>

            {{-- Noktalar --}}
            <div class="flex justify-center gap-1.5 my-4">
                @foreach ($slides as $i => $s)
                    <button type="button" @click="slide = {{ $i }}"
                            class="w-2 h-2 rounded-full transition"
                            :class="slide === {{ $i }} ? 'bg-bibB' : 'bg-pitch-line hover:bg-pitch-muted'"></button>
                @endforeach
            </div>

            {{-- İleri / geri --}}
            <div class="flex items-center justify-between gap-2">
                <button type="button" @click="slide--" x-show="slide > 0"
                        class="px-4 py-2 rounded-md border border-pitch-line text-sm font-semibold hover:bg-pitch-surface2 transition">‹ Geri</button>
                <span x-show="slide === 0"></span>

                <button type="button" @click="slide++" x-show="slide < total - 1"
                        class="px-4 py-2 rounded-md bg-gradient-to-b from-pitch-green2 to-pitch-green border border-pitch-green2 text-sm font-semibold text-white hover:brightness-125 transition">İleri ›</button>
                <button type="button" @click="close()" x-show="slide === total - 1" x-cloak
                        class="px-4 py-2 rounded-md bg-gradient-to-b from-pitch-green2 to-pitch-green border border-pitch-green2 text-sm font-semibold text-white hover:brightness-125 transition">Başlayalım 🎉</button>
            </div>
        </div>
    </div>
</div>
