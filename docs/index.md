### QNBpay Entegrasyonuna Hızlı Başlangıç Rehberi

Bu rehber QNBpay API ile entegrasyon sürecinizi hızlı ve sorunsuz bir şekilde tamamlamanıza yardımcı olmak için hazırlanmıştır. Lütfen aşağıdaki adımları takip ederek entegrasyon sürecinizi bizimle birlikte tamamlayın. 😊

1\. Test Ortam Üye İşyeri ve Kredi Kartı Bilgileri

• Entegrasyon süreçlerinizde ilk adım olarak, Üye İşyeri Bilgileri ve Test Kredi Kartı Bilgileri sayfalarını ziyaret edip test üye işyeri ve test ortam kart bilgilerini elde edebilirsiniz.

2\. QNBpay Postman Dokümanının İndirilmesi ve Kurulması

• Test üye işyeri ve test ortam kart bilgilerini öğrendikten sonra, Postman Koleksiyonu sayfasını ziyaret ederek postman dokümanını indirin ve bir API servis çağrım uygulaması üzerinde dokümanı açın.

• API servis çağırım uygulaması olarak Postman uygulamasını indirip entegrasyon adımlarını bu uygulama üzerinden tamamlayabilirsiniz. İndirdiğiniz Postman koleksiyonunda tüm servislerimiz sizler için hazır olacaktır.

3\. API Testleri

• Yazılım süreçlerinize başlamadan önce, bir önceki adımda kurulumunu yaptığınız Postman veya benzeri bir uygulama üzerinden QNBpay servislerini çağırıp istek ve cevap mesajlarınızdan emin olmanızı öneriyoruz. Test ortamda gözlemlenmeden canlı ortama implemente edilen servisler, canlı ortamda sizlere doğru hizmeti veremememize yol açabilir.

•• Test ortamda gözlemlenmeden canlı ortama implemente edilen servisler, canlı ortamda sizlere doğru hizmeti veremememize yol açabilir.

4\. Token API

• 3D Ödeme servisi hariç, QNBpay'e ait tüm servislerde, ödeme isteğinin HEADER alanında mutlaka Authorization parametresi içinde "Bearer + Token" olacak şekilde token servisinden alınan değeri iletmeniz zorunludur. Bu sebeple çağırmanız gereken ilk API Token Alma servisidir.

5\. QNBpay Servisleri

• Ödeme, işlem durumu sorgulama, iade gibi işlem tiplerini test ortam bilgilerini kullanarak başarılı olarak geçebildiğinizi kontrol edebilirsiniz. Bu kontrol sizlerin entegrasyonu doğru şekilde yapabilmenize imkan sağlayacaktır.

• Test ortamda kullanmak istediğiniz servisleri bir servis çağırım uygulaması üzerinde deneyimledikten sonra yazılım geliştirme süreçlerinize başlayabilirsiniz.

6\. WebHook Kullanımı

• Webhook, işlem sonuç bilgisini sizin belirlemiş olduğunuz URL adresine işlem sonrasında bildiren bir sistemdir. Webhook kullanmadan işlemlere ait sonuç bilgilerini işlemlere ait cevap mesajlarından da elde edebilirsiniz.

• Bu sebeple Webhook kullanımı QNBpay'de opsiyoneldir, kullanılmak istendiği durumda ise Webhook tanımınızı QNBpay panel üzerinden Ayarlar > Entegrasyon & API sayfası altından yapmalısınız.

• Tanımladığınız URL adresini destek ekiplerine ileterek bu adres için gerekli firewall tanımlarının yapılması talep edilmedilir. Bu tanımlar tamamlanmadan istenen URL adresine Webhook isteği iletilemeyecektir.

7\. Destek ve Güncellemeler

• Yenilikleri takip etmek için düzenli olarak entegrasyon portalimizi ziyaret edebilirsiniz. Sizler için kullanışlı olabilecek yeni özellikler eklenmiş olabilir. 😊
