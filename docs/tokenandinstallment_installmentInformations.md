### Taksit Bilgisi Alma

Kullanıcı kart numarasının ilk 8 hanesini girdiğinde, API çağrılmalıdır. GetPos API, ödeme sayfasındaki verilen kart numarasına ve tutar bilgisine göre taksit listesini sağlamaktan sorumludur.

Authorization gereklidir ve yöntem Bearerolmalıdır.

Vade farkının kart hamili tarafından ödenmesi istenen durumlarda ödenecek tutarın ve taksitlerin kart sahibine gösterilmesi için istek mesajına aşağıdaki örnekteki gibi 'is\_comission\_from\_user : 1 ve 'is\_single\_payment\_allowed': true parametreleri ve değerleri eklenmelidir. Eklenmediği durumda vade farkının üye işyerine yansıyacağı senaryo ekranda gösterilecektir.

GetPos API sadece vade farkının son kullanıcı tarafından ödeneceği veya ödenmeyeceği senaryolarda seçilebilecek taksit ve komisyon oranları bilgisini elde etmek için kullanılır. Kart sahibinden vade farkının alınabilmesi için ödeme isteğinde de aynı parametreler iletilmelidir. Aksi halde kart sahibine vade farkı yansımayacaktır.

**Notlar**

Veri değerleri bir dizi olabilir. Bu durumda, üye iş yeri web sitesi kullanıcının seçmesi için tüm taksit seçeneklerini göstermelidir. Varsayılan olarak, ilk taksit seçilecektir. Her durumda, cevapta en az bir taksit olacak.

\*\* HEADER alanında mutlaka Authorization parametresi içinde 'Bearer + Token' olacak şekilde token servisinden alınan değeri iletmeniz zorunludur.

QNB Bank için Kart Programı ve Banka Adı Güncellemesi QNBpay altyapısında sunulan taksit sorgulama servisinde, kart programı ve banka adı bilgileri 4 Eylül 2025 tarihi itibarıyla aşağıdaki şekilde güncellenecektir: "card\_program": "CARD\_FNS" → "card\_program": "QNB"

**Post**(/api/getpos)

| Test | https://test.qnbpay.com.tr/ccpayment/api/getpos |
| --- | --- |
| Prod | https://portal.qnbpay.com.tr/ccpayment/api/getpos |

### Yanıtlar

## **200** Başarılı Yanıt

## **400** Hata Yanıtı

### Request Samples

- Body

**Content type** 
application/json

```
{
      "credit_card":"402278",
      "amount":"100",
      "currency_code":"TRY",
      "merchant_key":"$2y$10$xLuWJ1ZGJXombYl5Qb4qie0Eo5SZwmdX6k7uo0ntmSi2z.gVx2hea",
      "is_comission_from_user": "1",
      //"commission_for_installment": "2,4",
      "is_single_payment_allowed":true
  }
  
```

### Response Samples

- 200
- 400

**Content type** 
application/json

```
{
      "status_code": 100,
      "status_description": "Successfull",
      "data": [
          {
              "pos_id": 131,
              "campaign_id": 0,
              "allocation_id": 0,
              "installments_number": 2,
              "card_type": "CREDIT CARD",
              "card_program": "CARD_FNS",
              "card_scheme": "visa",
              "is_commercial": "FALSE",
              "payable_amount": 102.04081632653,
              "hash_key": "1a05a03d146091bd:5041:2qsn09Vb+FOZs6HsnAXAsUPjWyKqMEW5Tmkm__9qfYJ8Ql1lyA4dLe2mqHqTmxj3U2vMZZQPzC7X4i71gxWNkp690__ia6zSyQu+6XqybFuKs=",
              "amount_to_be_paid": "102.04",
              "currency_code": "TRY",
              "currency_id": 1,
              "title": 2
          },
          {
              "pos_id": 131,
              "campaign_id": 0,
              "allocation_id": 0,
              "installments_number": 3,
              "card_type": "CREDIT CARD",
              "card_program": "CARD_FNS",
              "card_scheme": "visa",
              "is_commercial": "FALSE",
              "payable_amount": 103.09278350515,
              "hash_key": "5abc3bce2ab67f05:ec43:SI0__zNlSTfLouZpUq6r__gpoJd2pcNoAo3UZhJopLtx2BY1UpsPzJjicb6esE__r4r4Oab9TCb7cRVe9Csj4RWiv9WzPRN0W4uVv__zozz6ne4=",
              "amount_to_be_paid": "103.09",
              "currency_code": "TRY",
              "currency_id": 1,
              "title": 3
          }
      ]
  }
  
```

```
{
      "status_code": 1,
      "status_description": "credit card 6 ile 19 arasında bir rakam olmalıdır."
  }
```