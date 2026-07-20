### Uygulama Durum Kodları

| Status Code | Açıklama |
| --- | --- |
| 1 | Temel doğrulama |
| 3 | Fatura kimliği çoktan işlendi. Bu fatura kimliğine sahip sipariş işlem devam ediyor, lütfen bekleyin veya yeni fatura kimliğiyle sipariş oluşturun. |
| 12 | Öğeler bir dizi olmalı, Geçersiz Para Birimi kodu, Geçersiz öğe biçimi. |
| 13 | Ürün fiyatınızın toplamı ("5"), fatura toplamına ("10") eşit değil. |
| 14 | Üye işyeri bulunamadı! |
| 30 | Geçersiz kimlik bilgileri |
| 31 | İşlem bulunamadı |
| 32 | Geçersiz fatura kimliği, sipariş tamamlanmadı. |
| 33 | Miktar tam sayı olmalıdır. |
| 34 | Ödeme entegrasyon yöntemine izin verilmiyor. Lütfen desteğe başvurunuz. |
| 35 | Kredi Kartı Ödeme Seçeneği tanımlanmadı. |
| 36 | Pos bulunamadı. |
| 37 | Üye işyeri Pos Komisyonu ayarlanmadı. Lütfen servis sağlayıcı ile iletişime geçiniz. |
| 38 | Bu para birimi ve ödeme yöntemi için Üye İşyeri Komisyonu belirlenmedi. Lütfen başka bir ödeme yöntemi deneyiniz. |
| 39 | Komisyon bulunamadı. |
| 40 | Taksit bulunamadı. |
| 41 | Sipariş başarısız |
| 41 | Ödeme başarısız |
| 42 | Ürün fiyatı komisyondan az, Ürün fiyatı maliyetten düşük |
| 43 | Ödeme vadesi ayarlanmamış |
| 44 | Bu kredi kartı bloke edilmiştir. |
| 44 | Üye İşyeri günlük işlem sayısı sınırı aşıldı |
| 45 | Üye İşyeri günlük işlem tutarı sınırı aşıldı |
| 46 | Üye İşyeri aylık işlem sayısı sınırı aşıldı |
| 47 | Üye İşyeri aylık işlem tutarı sınırı aşıldı |
| 48 | İşlem başına minimum işlem limiti ihlal edildi |
| 49 | İade Başarısız, Toplam iade tutarı net tutarı geçmemelidir, Satış İşlemi Bulunamadı, Yetersiz bakiye, İade Yapılmayan İşlem Durumu, Lütfen başka bir iade için en az 30 saniye bekleyiniz, İşlem başına maksimum işlem limiti ihlal edildi |
| 55 | Yinelenen ödemede taksitli satış olamaz, Yinelenen numara boş olamaz, Yinelenen sayı bir tamsayı olmalıdır, Yinelenen sayı 1'den büyük olmalıdır, Yinelenen sayı 121'den büyük olmamalıdır, Yinelenen döngü boş olamaz, Yinelenen döngü birimi geçerli değil. "D", "M" veya "Y" olmalıdır, Yinelenen aralık boş olamaz, Yinelenen aralık bir tamsayı olmalıdır, Yinelenen aralık 0'dan büyük olmalıdır, Yinelenen aralık 99'dan büyük olmamalıdır, Geçersiz yinelenen webhook anahtarı! Lütfen QNBpay'deki anahtar adını kontrol ediniz, Yinelenen webhook anahtarı boş olamaz. Lütfen webhook anahtarınızı QNBpay'e atayınız |
| 56 | Geçersiz satış webhook anahtarı! Lütfen QNBpay'deki anahtar adını kontrol ediniz |
| 60 | Üye iş yerinin bu kartı kullanarak işlem yapmasına izin verilmez. |
| 68 | Hash key ile toplam tutar uyuşmazlığı, Hash key ile para birimi uyuşmazlığı, Hash key ile üye işyeri anahtarı uyuşmazlığı, Hash key ile taksit sayısı uyuşmazlığı, Hash key ile pos ID uyuşmazlığı, Hash key ile fatura ID uyuşmazlığı. |
| 69 | Sipariş henüz işlenmedi |
| 70 | Kart programı uyuşmazlığı |
| 71 | Yinelenen plan güncellemesi başarısız oldu, Geçersiz Yanıt veya Bilinmeyen Hata |
| 72 | Silinemedi, Eski ödeme işlenemedi |
| 73 | Yinelenen plan kartı eklenemedi, Geçersiz Yanıt veya Bilinmeyen Hata |
| 76 | Bu Üye İşyeri için Yabancı Kartlara İzin Verilmemektedir. |
| 76 | Bu Üye İşyeri için Yabancı Kartlara İzin Verilmemektedir. |
| 77 | Bu Üye İşyeri için Yabancı Kart Komisyonu belirlenmemiş |
| 79 | Üye işyerinin kart tokenı ile ödeme yapmasına izin verilmez |
| 80 | Alt Üye işyeri bulunamadı |
| 81 | API'den para birimi dönüştürme başarısız oldu |
| 85 | Geçersiz karakter |
| 86 | Kart token kaydetme başarısız oldu |
| 87 | Geçersiz token veya müşteri numarası |
| 88 | Kart tokenı silinemedi |
| 89 | Kart tokenı güncellenemedi |
| 90 | Geçersiz Hash key |
| 91 | Üye İşyeri anahtarı ile uyuşmayan Hash key |
| 92 | Müşteri numarası ile uyuşmayan Hash key |
| 93 | Kart Sahibinin Adı ile uyuşmayan Hash key |
| 94 | Kart Numarası ile uyuşmayan Hash key |
| 95 | Son kullanma ayı ile uyuşmayan Hash key |
| 96 | Son kullanma yılı ile uyuşmayan Hash key |
| 97 | Kart tokenı ile uyuşmayan Hash key |
| 99 | Bilinmeyen hata |
| 100 | Başarılı |
| 101 | İade talebiniz başarıyla oluşturuldu. Ekibimiz iade işlemini tamamlayacaktır |
| 103 | Paybytoken komisyon oranı oluşturulmadı |
| 104 | İade işlem ID si benzersiz olmalı |
| 105 | İşlem onaylanmadı |
| 106 | Geçersiz üye işyeri tipi |
| 107 | Gönderilen api opt doğrulanamadı |
| 108 | Geçersiz kart numarası |
| 109 | Dosya işleme hatası |
| 110 | Parçalı iade bu işlem için izin verilmemektedir |
| 112 | Kısmen başarılı |
| 404 | Fatura kimliği zaten işlendi |
| 113 | İptal URL'si boş bırakılmamalıdır |