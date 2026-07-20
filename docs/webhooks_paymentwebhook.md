### Satış WebHook

Satış WebHook Öncelikle https://test.qnbpay.com.tr/merchant/apisetting adresindeki qnbpay üye işyeri panelinde satış web hook URL'nizi (anahtar, değer) ayarlamanız gerekir. Bu özelliği almak için, üye iş yerinin satın alma talebini gönderirken sale\_web\_hook\_key ile faturayı göndermesi gerekir. Bu anahtar isteğe bağlıdır, ancak gönderilirse geçerli bir anahtar olmalıdır.

Her ödeme için, aşağıda verilen aşağıdaki parametrelerle satış webhook url'sine bir POST isteği gönderiyoruz.

**Yanıtta Özel Durumlar**

Durum 1 `payment_status == 1` ve `transaction_type == "Auth"` // İşlem başarılıdır ve işlem tutarı anında karttan düşülür.

Durum 2 `payment_status == 1` ve `transaction_type == "Pre-Authorization"` // İşlem başarılı olup, işlem tutarı kredi kartından bloke edilir. Bloke tutarın karttan çekilmesi için /api/confirmPayment çağırılmalıdır.

Durum 3 `payment_status == 0` ise işlem başarısız demektir.

Hash\_key, isteğin QNBpay'den geldiğini doğrulamak için üye iş yeri tarafında doğrulanmalıdır. Ayrıca İşlem Durumu servisi işlemin başarılı veya Başarısız olduğunu onaylar. Örnek doğrulama işlemi için bkz: [Hash Doğrulama Örneği](https://apidocs.qnbpay.com.tr/#/paymentcontrolrefund/hashverify)

**Nasıl Eklenir?**

• QNBpay paneline giriş yapılır. Ayarları > Entegrasyon & API sayfasına erişilir. Ekle botonuna basılır.

• Anahtar türü "Satış Web Hook" olarak seçilir. Daha sonra bir anahtar adı girilir. Örneğin "satis", "salewebhook" gibi anahtar isimleri kullanılabilir. Eğer bir entegrator ile çalışılıyorsa entegrator tarafından temin edilecek olan URL ilgili alana girilir. Yazılım süreçlerini siz yönetiyorsanız webhook isteğinin iletilmesini istediğiniz URL bilgisi ilgili alana girilir. Bu işlem tamamlanınca "Ekle" butonuna basılır. Eklenecek URL adresine QNBpay Operasyon Ekipleri ile iletişime geçilerek firewall izni verilmelidir. Aksi halde ödeme sonucu ilgili adrese iletilemeyecektir.

• Bu sürecin tamamlanmasının ardından ödeme isteğinde sale\_web\_hook\_key parametresinde iletilen anahtar adı ile eşleştirdiğiniz URL adresine POST metodu ile ödeme sonuç bilgisi iletilecektir.