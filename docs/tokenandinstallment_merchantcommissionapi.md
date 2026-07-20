### Üye İşyeri Komisyon API

Üye işyeri komisyonu API'si, kart programına göre üye işyeri komisyonunu ve son kullanıcı komisyonunu döner.

Authorization gereklidir ve yöntem Bearerolmalıdır.

**Not**

Taksitli bir kart programı için komisyon "x" gelirse, bu taksit için kart programının etkin olmadığı anlamına gelir.

\*\* HEADER alanında mutlaka Authorization parametresi içinde "Bearer + Token" olacak şekilde token servisinden alınan değeri iletmeniz zorunludur.

QNB Bank için Kart Programı ve Banka Adı Güncellemesi QNBpay altyapısında sunulan taksit sorgulama servisinde, kart programı ve banka adı bilgileri 4 Eylül 2025 tarihi itibarıyla aşağıdaki şekilde güncellenecektir: "card\_program": "CARD\_FNS" → "card\_program": "QNB"

**Post**(/api/commission)

| Test | https://test.qnbpay.com.tr/ccpayment/api/commissions |
| --- | --- |
| Prod | https://portal.qnbpay.com.tr/ccpayment/api/commissions |

### Yanıtlar

## **200** Başarılı Yanıt

## **400** Hata Yanıtı

### Request Samples

- Body

**Content type** 
application/json

```
{
  "currency_code": "TRY"
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
    "status_description": "Successful",
    "data": {
        "1": [
            {
                "title": "Regular Commission",
                "card_program": "CardFinans",
                "merchant_commission_percentage": "0.0000",
                "merchant_commission_fixed": "0.0000",
                "user_commission_percentage": 0,
                "user_commission_fixed": 0,
                "currency_code": "TRY",
                "installment": 1
            },
            {
                "title": "Save Card Token Payment Commission",
                "card_program": "",
                "merchant_commission_percentage": 0,
                "merchant_commission_fixed": 0,
                "user_commission_percentage": "-",
                "user_commission_fixed": "-",
                "currency_code": "TRY",
                "installment": 1
            },
            {
                "title": "Foreign Card Commission",
                "card_program": "",
                "merchant_commission_percentage": 0,
                "merchant_commission_fixed": 0,
                "user_commission_percentage": 0,
                "user_commission_fixed": 0,
                "currency_code": "TRY",
                "installment": 1
            }
        ],
        "2": [
            {
                "title": "Regular Commission",
                "card_program": "CardFinans",
                "merchant_commission_percentage": "0.0000",
                "merchant_commission_fixed": "0.0000",
                "user_commission_percentage": 0,
                "user_commission_fixed": 0,
                "currency_code": "TRY",
                "installment": 2
            }
        ],
        "3": [
            {
                "title": "Regular Commission",
                "card_program": "CardFinans",
                "merchant_commission_percentage": "0.0000",
                "merchant_commission_fixed": "0.0000",
                "user_commission_percentage": 0,
                "user_commission_fixed": 0,
                "currency_code": "TRY",
                "installment": 3
            }
        ],
        "4": [
            {
                "title": "Regular Commission",
                "card_program": "CardFinans",
                "merchant_commission_percentage": "0.0000",
                "merchant_commission_fixed": "0.0000",
                "user_commission_percentage": 0,
                "user_commission_fixed": 0,
                "currency_code": "TRY",
                "installment": 4
            }
        ],
        "5": [
            {
                "title": "Regular Commission",
                "card_program": "CardFinans",
                "merchant_commission_percentage": "0.0000",
                "merchant_commission_fixed": "0.0000",
                "user_commission_percentage": 0,
                "user_commission_fixed": 0,
                "currency_code": "TRY",
                "installment": 5
            }
        ],
        "6": [
            {
                "title": "Regular Commission",
                "card_program": "CardFinans",
                "merchant_commission_percentage": "0.0000",
                "merchant_commission_fixed": "0.0000",
                "user_commission_percentage": 0,
                "user_commission_fixed": 0,
                "currency_code": "TRY",
                "installment": 6
            }
        ]
    }
}
```

```
{
    "status_code": 0,
    "status_description": "No Data Found"
}
```