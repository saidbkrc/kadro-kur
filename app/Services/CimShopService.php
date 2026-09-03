<?php

namespace App\Services;

use App\Models\CimPurchase;
use App\Models\User;
use App\Support\CimShop;

/** Çim mağazası: satın alma ve kuşanma. Ürünler yalnızca görünüm değiştirir. */
class CimShopService
{
    /** @return array{ok: bool, message: string} */
    public function buy(User $user, string $itemKey): array
    {
        $urun = CimShop::ITEMS[$itemKey] ?? null;

        if ($urun === null) {
            return ['ok' => false, 'message' => 'Böyle bir ürün yok.'];
        }

        if (CimPurchase::where('user_id', $user->id)->where('item_key', $itemKey)->exists()) {
            return ['ok' => false, 'message' => 'Bu ürün zaten senin.'];
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
}
