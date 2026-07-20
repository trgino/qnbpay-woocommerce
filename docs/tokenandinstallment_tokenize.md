### Token Alma

API, üye iş yerini doğrulamak için diğer API'lerde kullanılacak bir token oluşturur. Ayrıca, üye iş yeri için ayarlanan ödeme entegrasyon seçeneğini de döndürür. Yanıt anahtarı "is\_3d" dir. İs\_3d'nin olası değerleri: 0, 1, 2 ve 4.

`0 =` Yalnızca whitelabel 2D

`1 =` Whitelabel 2D veya 3D

`2 =` Yalnızca whitelabel 3D

`4 =` Markalı ödeme çözümü

Token API dönüşü 1 ise, üye iş yeri web sitesinin kullanıcının 2D veya 3D'yi seçmesi için bir onay kutusu görüntülemesi gerekir. Her token için geçerlilik süresi 2 saattir.

Token alındıktan sonra geçerlilik süresi boyunca tekrar tekrar kullanılabilir. Token'ın tekrarlı kullanımı sisteminize performans açısından fayda sağlayabilir.

**Post**(/api/token)

| Test | https://test.qnbpay.com.tr/ccpayment/api/token |
| --- | --- |
| Canlı | https://portal.qnbpay.com.tr/ccpayment/api/token |

### Yanıtlar

## **200** Başarılı Yanıt

## **400** Hata Yanıtı

### Request Samples

- Body

**Content type** 
application/json

```
{
"app_id": "07fb70f9d8de575f32baa6518e38c5d6",
"app_secret": "61d97b2cac247069495be4b16f8604db"
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
    "status_description": "Successfully Generated token",
    "data": {
        "token": "******************************************************",
        "is_3d": 1,
        "expires_at": "2023-02-06 18:02:48"
    }
}
```

```
[
  {
    "status_code": 2,
    "status_description": "Invalid credentials"
  }
]
```