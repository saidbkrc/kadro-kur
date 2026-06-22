---
description: Oturumu özetle ve logs/ klasörüne kaydet
---

Bu oturumda yapılanları özetleyip logs/ klasörüne kaydet:

1. logs/ klasörü yoksa oluştur.
2. Bugünün tarihiyle dosya adı kullan: logs/YYYY-MM-DD.md
   - Bugün için dosya zaten varsa ÜZERİNE YAZMA, saat damgalı yeni bir bölüm
     olarak EKLE (append).
   - Yoksa yeni dosya oluştur.
3. İçerik formatı:

## [HH:MM] Oturum Özeti
- Bugün ne yapıldı (değişen dosyalar, eklenen özellikler, çözülen bug'lar)
- Alınan önemli kararlar ve neden öyle karar verildiği
- Yarım kalan iş / sıradaki net adım
- Dikkat edilmesi gereken noktalar (varsa, örn. test edilmemiş kısım)

4. Özeti kısa ve öz tut. Kod bloklarını sadece gerçekten kritikse ekle
   (örn. henüz commit edilmemiş bir karar varsa).
5. İşlem bitince hangi dosyaya yazdığını tek satırla bildir.