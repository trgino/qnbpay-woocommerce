### Üye İşyeri Aktif Taksitleri

Üye işyerinin aktif taksitlerinin listelenmesini sağlar. İşyeri bunları kendi panelinde pasif ya da aktif duruma getirebilir.

**Not**

\*\* HEADER alanında mutlaka Authorization parametresi içinde "Bearer + Token" olacak şekilde token servisinden alınan değeri iletmeniz zorunludur.

**Post**(/api/installments)

| Test | https://test.qnbpay.com.tr/ccpayment/api/installments |
| --- | --- |
| Prod | https://portal.qnbpay.com.tr/ccpayment/api/installments |

### Yanıtlar

## **200** Başarılı Yanıt

## **400** Hata Yanıtı

### Request Samples

- Body

**Content type** 
application/json

```
{
"merchant_key": "$2y$10$N9IJkgazXMUwCzpn7NJrZePy3v.dIFOQUyW4yGfT3eWry6m.KxanK"
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
    "message": "Merchant Active Installment",
    "installments": [
        1,
        2,
        3,
        4,
        5,
        6,
        7,
        8,
        9,
        10,
        11,
        12,
        13,
        14,
        15,
        16,
        17,
        18
    ]
}
```

```
{
    "status_code": 14,
    "message": "Invalid merchant key"
}
```
