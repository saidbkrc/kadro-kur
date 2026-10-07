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
                'id' => 'kehanet-limitler-2026-10',
                'title' => '📢 Kehanet limitleri güncellendi',
                'until' => '2026-10-21',
                'link' => 'kehanet',
                'lines' => [
                    '⬆️ Tekli kupon en fazla '.K::MAX_STAKE.' Çim, kombine '.K::MAX_PARLAY_STAKE.' Çim, maç başına toplam '
                        .number_format(K::MAX_MATCH_STAKE, 0, ',', '.').' Çim',
                    '🎭 Öznel tahminler (başkanın işaretlediği olaylar, MVP, en yüksek performans): en fazla '
                        .K::MAX_STAKE_SUBJECTIVE.' Çim, oran en çok '.(int) K::MAX_ODDS_SUBJECTIVE.'×',
                    '🗳️ MVP ve performans kuponunda 24 saatte '.K::MIN_VOTERS_SUBJECTIVE.' kişiden az oy/puan verilirse kupon iade edilir',
                ],
            ],
            [
                'id' => 'sezon-sistemi-2026-09',
                'title' => '🏁 Sezonlar başladı',
                'until' => '2026-10-20',
                'link' => null,
                'lines' => [
                    '📅 Yıl 3 aylık sezonlara bölündü — şu an '.Season::current()->name().' ('.Season::current()->daysLeft().' gün kaldı)',
                    '📊 İstatistikler ve rozetler her sezon sıfırlanır; oyuncu puanları (OVR) sıfırlanmaz',
                    '🏆 Sezon sonunda Gol Kralı, Sezonun MVP\'si ve Demirbaş belli olur — İstatistikler sayfasından eski sezonlara da bakabilirsin',
                    '🏟️ Sezonda en çok maça çıkan(lar) çıktığı her maç için '.\App\Services\CimRewards::AWARDS['season_attendance']['amount'].' Çim kazanır',
                    '🗳️ Sezon bitince 7 gün oylama: en iyi oyuncu, takım oyuncusu, en çok gelişen, centilmen — kazananlara '
                        .\App\Services\CimRewards::AWARDS['season_vote_win']['amount'].' Çim ve profilde kalıcı unvan',
                ],
            ],
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
