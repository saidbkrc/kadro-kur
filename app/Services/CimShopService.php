<?php

namespace App\Services;

use App\Models\CimPurchase;
use App\Models\Player;
use App\Models\User;
use App\Support\CimShop;

/** Çim mağazası: satın alma ve kuşanma. Ürünler yalnızca görünüm değiştirir. */
class CimShopService
{
    /**
     * Ürünü satın alır. Şarta bağlı ürünlerde rozet kontrolü için $player gerekir —
     * verilmezse şartlı ürünler alınamaz (koşulun doğrulanacağı grup belli değildir).
     *
     * @return array{ok: bool, message: string}
     */
    public function buy(User $user, string $itemKey, ?Player $player = null): array
    {
        $urun = CimShop::ITEMS[$itemKey] ?? null;

        if ($urun === null) {
            return ['ok' => false, 'message' => 'Böyle bir ürün yok.'];
        }

        if (CimPurchase::where('user_id', $user->id)->where('item_key', $itemKey)->exists()) {
            return ['ok' => false, 'message' => 'Bu ürün zaten senin.'];
        }

        if (! CimShop::onSale($itemKey)) {
            return ['ok' => false, 'message' => 'Bu ürün şu an satışta değil — '.CimShop::saleNote($itemKey).'.'];
        }

        if (($kosul = CimShop::requirement($itemKey)) !== null && ! $this->meetsRequirement($player, $kosul['badge'])) {
            return ['ok' => false, 'message' => '🔒 Bu ürün kilitli — '.$kosul['label'].' gerekiyor.'];
        }

        if ($user->cim_balance < $urun['price']) {
            $eksik = $urun['price'] - $user->cim_balance;

            return ['ok' => false, 'message' => "Yeterli Çim yok — {$eksik} Çim daha lazım."];
        }

        CimPurchase::create(['user_id' => $user->id, 'item_key' => $itemKey, 'price' => $urun['price']]);
        app(KehanetService::class)->adjustBalance($user->id, -$urun['price'], 'shop', $urun['name']);

        // Satın alınan ürün otomatik kuşanılır
        $this->equip($user, $itemKey);

        return ['ok' => true, 'message' => "✅ {$urun['name']} alındı ve kuşanıldı."];
    }

    /** Sahip olunan ürünü kuşanır; null verilirse o türü çıkarır. */
    public function equip(User $user, ?string $itemKey, ?string $type = null): array
    {
        if ($itemKey === null) {
            if ($type === null || ! array_key_exists($type, CimShop::TYPES)) {
                return ['ok' => false, 'message' => 'Geçersiz istek.'];
            }

            $user->forceFill(['equipped_'.$type => null])->save();

            return ['ok' => true, 'message' => 'Çıkarıldı.'];
        }

        $urun = CimShop::ITEMS[$itemKey] ?? null;

        if ($urun === null) {
            return ['ok' => false, 'message' => 'Böyle bir ürün yok.'];
        }

        if (! CimPurchase::where('user_id', $user->id)->where('item_key', $itemKey)->exists()) {
            return ['ok' => false, 'message' => 'Bu ürüne sahip değilsin.'];
        }

        $user->forceFill(['equipped_'.$urun['type'] => $itemKey])->save();

        return ['ok' => true, 'message' => "{$urun['name']} kuşanıldı."];
    }

    /** Kullanıcının sahip olduğu ürün anahtarları. */
    public function owned(User $user): array
    {
        return CimPurchase::where('user_id', $user->id)->pluck('item_key')->all();
    }

    /** Oyuncu bu rozeti kazanmış mı? (şarta bağlı ürünlerin kilidi) */
    public function meetsRequirement(?Player $player, string $badgeKey): bool
    {
        if ($player === null) {
            return false;
        }

        foreach (app(PlayerBadges::class)->forPlayer($player) as $rozet) {
            if ($rozet['key'] === $badgeKey) {
                return (bool) $rozet['earned'];
            }
        }

        return false;
    }

    /**
     * Mağaza ekranı için ürün kilit durumu.
     *
     * @return array<string, bool> [item_key => kilitli mi]
     */
    public function lockedFor(?Player $player): array
    {
        $out = [];

        foreach (CimShop::ITEMS as $key => $urun) {
            if (($kosul = CimShop::requirement($key)) !== null) {
                $out[$key] = ! $this->meetsRequirement($player, $kosul['badge']);
            }
        }

        return $out;
    }
}
