<?php

namespace App\Support;

use App\Support\Kehanet as K;

/**
 * Grup üyelerine gösterilen duyurular. Tablo yok — duyuru kodda tanımlanır,
 * 'until' tarihinden sonra kendiliğinden kalkar. Kullanıcı kapatırsa yalnızca
 * kendi tarayıcısında gizlenir (x-announcements, localStorage).
 *
 * Yeni duyuru: listeye benzersiz 'id' ile bir öğe ekle. id değişmedikçe
 * kapatan kişi aynı duyuruyu tekrar görmez.
 */
class Announcements
{
    /** @return list<array{id:string, title:string, lines:list<string>, link:?string, until:string}> */
    public static function all(): array
    {
        return [
            [
                // v2: ödül değişiklikleri eklendi — kimlik değişince kapatmış olanlara da tekrar görünür
                'id' => 'kehanet-limitler-2026-09-v2',
                'title' => '📢 Kehanet\'te yeni kurallar',
                'until' => '2026-10-07',
                'link' => 'kehanet',
                'lines' => [
                    '🎰 Kombine: en fazla '.K::MAX_PARLAY_STAKE.' Çim, oran tavanı '.(int) K::MAX_PARLAY_ODDS.'× (önceden 500 Çim / 500×)',
                    '📏 Maç başına toplam limit: '.number_format(K::MAX_MATCH_STAKE, 0, ',', '.').' Çim (tekli + o maçı içeren kombineler)',
                    '🙋 Kendine kupon artık serbest — gerginlik ve en geç gelen hariç',
                    '🧤 Günün kurtarışı oranı artık kalecilere göre hesaplanıyor',
                    '📈 En yüksek performans kuponu da MVP gibi 24 saatte, o ana kadarki puanlarla sonuçlanıyor',
                    '🎁 Maç ödülleri ×1,5 arttı; yeni: skor girildikten sonraki 24 saat içinde maçtaki herkesi puanlayana +'
                        .\App\Services\CimRewards::AWARDS['perf_vote']['amount'].' Çim',
                    '🛒 Mağaza fiyatları güncellendi; yeni: forma desenleri, gol sevinçleri, avatar halkaları, rozet vitrini ve hediye etme',
                ],
            ],
        ];
    }

    /** Süresi dolmamış duyurular. */
    public static function active(): array
    {
        return array_values(array_filter(
            self::all(),
            fn (array $d) => now()->lte(\Illuminate\Support\Carbon::parse($d['until'])->endOfDay()),
        ));
    }
}
