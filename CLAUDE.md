# CLAUDE.md — Kadro Kurma (Halısaha Takım Yönetim Sistemi)

## Proje Özeti
Halısaha grupları için kadro kurma ve oyuncu değerlendirme sistemi. Kullanıcılar
kayıt olup bir "grup" oluşturur, invite_code ile başka kullanıcıları gruba davet
eder. Grup içinde maçlar planlanır, oyuncular RSVP verir, kadro otomatik (puan +
pozisyon dengeli) kurulur, kadro %60 oy ile onaylanır, maç sonrası gol/MVP/
performans puanlaması yapılır ve bu puanlar oyuncu rating'ine yansır.

Köken: önce tek dosya HTML + local JSON olarak yazılmış basit bir kadro
dengeleyiciydi, şimdi Laravel + Livewire ile gruplar arası tam bir sisteme
dönüştürülüyor. Proje ileri seviyede, şu an optimizasyon ve detay işleri var.

## Stack
- Backend: Laravel
- Frontend: Livewire (full-page component'ler, örn. Matches\Show)
- DB: MySQL

## Grup / İzolasyon Modeli (resmi bir multi-tenancy paketi yok)
- groups: owner_id, invite_code (UNIQUE), capacity, match_day/match_time, auto_schedule
- group_members: group_id + user_id + role (pivot tablo)
- İzolasyon global scope/policy ile DEĞİL; **iki katmanla, component bazlı** sağlanıyor:
  1. **Yetki kapısı** — her full-page Livewire component `mount()`'unda
     `abort_unless($group->isMember(Auth::user()), 403)` (Matches\Show'da
     `$match->group->isMember`). Admin/yönetim aksiyonları ayrıca canlı doğrulanır:
     `$group->isAdmin(Auth::user())` veya `$match->canManage(Auth::user())`
     (= `created_by` ya da grup admini). MVP/performans gibi oylar katılımcılığı
     canlı kontrol eder.
  2. **İlişki-traversal kapsama** — ID alan metotlar yetkili ebeveynden zincirler:
     `$this->group->players()->findOrFail($id)`, `$this->match->group->squadTemplates()
     ->findOrFail($id)` gibi. Başka grubun ID'si → 404. Yani ham `where('group_id', ...)`
     değil, "yetkili `$group`/`$match`'ten ilişki üzerinden eriş" deseni kullanılıyor.
- Route-model binding (`maclar/{match}`, `gruplar/{group}`) modeli ID ile yükler; çapraz-grup
  erişimini engelleyen şey mount'taki üyelik kapısıdır (route'un kendisi filtrelemez).
- Filament admin paneli (`/admin`) kasıtlı istisnadır: `is_admin`/`canAccessPanel` ile
  yöneticiler tüm grupları görür (tenant izolasyonunun dışında).
- ⚠️ Yeni query/aksiyon eklerken: ya yetkili `$group`/`$match`'ten ilişki zincirle, ya da
  mount'ta `isMember`/`isAdmin`/`canManage` kapısı koy. Modeli doğrudan `Model::find($id)`
  ile grup kontrolü yapmadan çekme — sızıntıya (başka grubun verisini görme) yol açar.

## Tablolar
- groups — grup (owner_id, invite_code, capacity, match_day/time, auto_schedule)
- group_members — grup üyeliği + rolü
- players — oyuncular (pozisyon, foot, shirt_number)
- matches — maçlar (capacity, status: scheduled/completed/cancelled, formation_a/b,
  team_a_score/b_score, mvp_closes_at)
- rsvps — maça katılım (going/maybe/not_going, waitlist_position)
- rules — grup başkanının koyduğu kadro kurma kuralları (örn. her takımda 1 kaleci zorunlu)
- squad_templates — kayıtlı kadro şablonları (sayı sınırlı)
- squad_votes — kadro onay oylaması (%60 eşiği)
- attribute_ratings — oyuncu özellik puanlaması
- match_performance_ratings — maç sonrası performans puanı (1-10, anonim,
  son 5 maç ortalaması rating'e %20 ağırlıkla yansıyor)
- mvp_votes — maçın adamı oylaması (24 saat açık, tek oy, değiştirilemez)
- goals — maç sonu gol istatistiği

## Domain Terimleri
- Kadro: bir maç için oluşturulan oyuncu listesi/takım dağılımı (Team A "Turuncu" / Team B "Yeşil")
- Kadro kurma: oyuncuları ortalama puana ve kurallara göre iki takıma dengeli bölme
  (özel kural örneği: her takımda en az 1 kaleci olmalı)
- Kadro oylaması: kadronun kesinleşmesi için katılımcıların %60'ının onayı gerekir
- Alternatif kadro: dengeli bölünmenin birden fazla varyasyonu, en dengeliden başlayarak gezilebilir
- RSVP: oyuncunun maça gelip gelmeyeceğini bildirmesi, kapasite dolunca yedek listesi (waitlist) işler
- Performans puanı: maç sonrası oyuncuların birbirini anonim 1-10 puanlaması
- MVP oylaması: maçın adamı için anonim, tek ve değiştirilemez oy
- Kadro şablonu: sık kullanılan sabit kadronun kaydedilip tek tıkla tekrar yüklenmesi

## UI / Tasarım Sistemi
- Custom Tailwind tema token'ları kullanılıyor — yeni renk icat etme, mevcutları kullan:
  pitch-bg, pitch-surface, pitch-surface2, pitch-line, pitch-ink, pitch-muted,
  bibA (turuncu takım), bibB (yeşil takım), gold
- Tekrar kullanılabilir Blade component'ler: x-primary-button, x-secondary-button,
  x-danger-button, x-text-input, x-input-error, x-input-label, x-ovr-badge —
  yeni UI elemanı eklerken önce bunlardan biri uyar mı diye bak
- font-display + uppercase tracking-wider başlık stili tüm sayfalarda tutarlı kullanılıyor
- Livewire component'ler "full-page" tarzda yazılıyor (örn. matches/show.blade.php) —
  tek component içinde çok sayıda state ve action (wire:click metodları) barınıyor,
  küçük nested component'lere bölünmüyor
- Karmaşık görsel öğeler (saha üzerinde sürükle-bırak diziliş) @script bloğu içinde
  vanilla JS + $wire çağrılarıyla yapılıyor (Livewire 3 @script pattern'i)

## Konvansiyonlar
- Minimal, cerrahi değişiklik tercih edilir: dosyanın tamamını yeniden yazma,
  sadece değişen kısmı/satırları göster
- Mevcut kod stiline (Türkçe arayüz metinleri, emoji kullanımı, mevcut renk
  token'ları) sadık kal
- Yeni bir query/feature eklerken izolasyonu koru: yetkili `$group`/`$match`'ten ilişki
  zincirle ya da mount'ta `isMember`/`isAdmin`/`canManage` kapısı koy (bkz. Grup / İzolasyon Modeli)

## Bilinen Zayıf Nokta
Frontend/UI tarafı (CSS, layout, Livewire component görsel detayları) zayıf nokta.
Bu tür işlerde varsayılan tasarım kararı almadan önce kısa seçenekler sun,
mevcut tasarım sistemine (pitch-* token'ları, x-component'ler) sadık kal.

## Yapma
- Yetki kapısı (mount'ta isMember/isAdmin/canManage) veya ilişki-traversal olmadan,
  modeli doğrudan `Model::find($id)` ile grup kontrolsüz çekip kullanma
- Tüm dosyayı yeniden yazıp "değişen satırlar" diye sunma — sadece diff göster
- Onay almadan migration/şema değişikliği yapma
- Mevcut tasarım token'larını yok sayıp yeni renk/stil icat etme