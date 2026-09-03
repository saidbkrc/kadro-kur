<x-guest-layout>
    <div class="w-full sm:max-w-2xl text-pitch-ink space-y-5 text-sm leading-relaxed">

        <div>
            <h1 class="font-display uppercase tracking-wider text-2xl font-bold">Gizlilik ve Aydınlatma Metni</h1>
            <p class="text-xs text-pitch-muted mt-1">Son güncelleme: {{ now()->translatedFormat('d F Y') }}</p>
        </div>

        <p class="text-pitch-muted">
            Kadro Kur ("uygulama"), halı saha gruplarının maç organizasyonu için kullanılan bir uygulamadır.
            Bu metin, 6698 sayılı Kişisel Verilerin Korunması Kanunu (KVKK) kapsamında hangi verileri neden
            işlediğimizi açıklar.
        </p>

        <section>
            <h2 class="font-display uppercase tracking-wider text-base font-semibold text-bibB mb-1.5">İşlenen veriler</h2>
            <ul class="list-disc ps-5 space-y-1 text-pitch-muted">
                <li><strong class="text-pitch-ink">Hesap bilgileri:</strong> ad, e-posta adresi ve şifreniz (şifre geri döndürülemez şekilde şifrelenir).</li>
                <li><strong class="text-pitch-ink">Oyuncu profili:</strong> mevki, tercih edilen ayak, forma numarası ve isteğe bağlı profil fotoğrafınız.</li>
                <li><strong class="text-pitch-ink">Maç verileri:</strong> katılım bildirimleriniz, yer aldığınız kadrolar, attığınız goller ve maç sonuçları.</li>
                <li><strong class="text-pitch-ink">Değerlendirmeler:</strong> takım arkadaşlarınızın size verdiği özellik ve performans puanları, MVP oyları, nitelik onayları.</li>
                <li><strong class="text-pitch-ink">Uygulama içi etkinlik:</strong> Kehanet tahminleri ve sanal "Çim" bakiyeniz.</li>
                <li><strong class="text-pitch-ink">Bildirim aboneliği:</strong> bildirimlere izin verirseniz tarayıcınızın ürettiği teknik abonelik bilgisi.</li>
            </ul>
        </section>

        <section>
            <h2 class="font-display uppercase tracking-wider text-base font-semibold text-bibB mb-1.5">İşleme amacı</h2>
            <p class="text-pitch-muted">
                Veriler yalnızca uygulamanın işleyişi için kullanılır: maç organizasyonu, dengeli kadro kurma,
                istatistik ve rozet hesaplama, bildirim gönderme. <strong class="text-pitch-ink">Reklam amacıyla
                kullanılmaz, üçüncü kişilere satılmaz veya pazarlama amacıyla paylaşılmaz.</strong>
            </p>
        </section>

        <section>
            <h2 class="font-display uppercase tracking-wider text-base font-semibold text-bibB mb-1.5">Kimler görebilir</h2>
            <p class="text-pitch-muted">
                Verileriniz yalnızca <strong class="text-pitch-ink">üyesi olduğunuz grubun diğer üyelerine</strong> görünür.
                Puanlamalar ve oylar <strong class="text-pitch-ink">anonimdir</strong> — kimin kime kaç puan verdiği hiç kimseye gösterilmez.
                Farklı grupların verileri birbirinden yalıtılmıştır.
            </p>
        </section>

        <section>
            <h2 class="font-display uppercase tracking-wider text-base font-semibold text-bibB mb-1.5">Saklama ve silme</h2>
            <p class="text-pitch-muted">
                Veriler hesabınız var olduğu sürece saklanır. Hesabınızı profil sayfanızdan istediğiniz zaman silebilirsiniz;
                hesap silindiğinde kişisel bilgileriniz kaldırılır. Grubun maç geçmişi ve skorları, diğer üyelerin
                istatistikleri bozulmasın diye anonim biçimde kalabilir.
            </p>
        </section>

        <section>
            <h2 class="font-display uppercase tracking-wider text-base font-semibold text-bibB mb-1.5">Haklarınız</h2>
            <p class="text-pitch-muted">
                KVKK'nın 11. maddesi uyarınca; verilerinize erişme, düzeltilmesini veya silinmesini isteme ve işlemeye
                itiraz etme hakkına sahipsiniz. Talepleriniz için grup yöneticinize veya uygulama sorumlusuna
                ulaşabilirsiniz.
            </p>
        </section>

        <section>
            <h2 class="font-display uppercase tracking-wider text-base font-semibold text-bibB mb-1.5">Çerezler</h2>
            <p class="text-pitch-muted">
                Yalnızca oturumunuzun açık kalması için gerekli teknik çerezler kullanılır. Takip veya reklam çerezi yoktur.
            </p>
        </section>

        <div class="pt-2 border-t border-pitch-line">
            <a href="{{ route('login') }}" wire:navigate class="text-sm text-bibB hover:underline">← Giriş ekranına dön</a>
        </div>
    </div>
</x-guest-layout>
