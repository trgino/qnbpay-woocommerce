### QNBpay ile Güvenli Ödeme Sayfası

Ödeme API'si, sipariş ve kredi kartı ayrıntı bilgilerini QNBPay ödeme entegrasyon sistemine göndermek için kullanılır. Ödemenin ardından [İşlem Durumu](https://apidocs.qnbpay.com.tr/#/paymentcontrolrefund/transactionstatus) API'si tetiklenerek işlem durumu sorgulanmalıdır.

**Not**

Vade farkının kart hamili tarafından ödenmesi istenen durumlarda ödeme isteğine "is\_comission\_from\_user" parametresi "1" değeri ile, üye işyeri tarafından ödenmesi istenen durumlarda ise "0" değeri ile iletilmelidir.

\*\* HEADER alanında mutlaka Authorization parametresi içinde "Bearer + Token" olacak şekilde token servisinden alınan değeri iletmeniz zorunludur.

**Post**(/purchase/link)

| Test | https://test.qnbpay.com.tr/ccpayment/purchase/link |
| --- | --- |
| Prod | https://portal.qnbpay.com.tr/ccpayment/purchase/link |

undefined

### Yanıtlar

## **200** Başarılı Yanıt

## **400** Hata Yanıtı

### Request Samples

- Body

**Content type** 
application/json

```
{
    "merchant_key": "$2y$10$N9IJkgazXMUwCzpn7NJrZePy3v.dIFOQUyW4yGfT3eWry6m.KxanK",
    "currency_code": "TRY",
    "invoice": {
        "invoice_id": "sample_invoice_id_12345",
        "invoice_description": "sample_invoice_description_12345",
        "total": "100",
        "return_url": "https://sample1.com.tr",
        "cancel_url": "https://sample2.com.tr",
        "response_method": "POST",
        "items": [
            {
               "name": "order",
                "price": "100",
                "quantity": 1,
                "description": "order"
            }
        ]
    },
    "name": "John",
    "surname": "Doe",
    "is_comission_from_user": "1",
    "selected_installments":[2,3]
}
```

### Response Samples

- 200
- 400

**Content type** 
application/json

```
{
    "status": true,
    "status_code": 100,
    "success_message": "Success !",
    "link": "https://test.qnbpay.com.tr/ccpayment/makepayment/171472505462841",
    "order_id": "171472505462841"
}
```

### Hash Key Oluşturma Kod Örnekleri

- PHP
- Ruby
- Python
- NodeJs
- C#

```
// Generate Hash Key Function

function generateHashKey($total, $installment, $currency_code,
$merchant_key, $invoice_id, $app_secret)

{
    $data = $total . '|' . $installment . '|' . $currency_code . '|' . $merchant_key . '|' . $invoice_id;

    $iv = substr(sha1(mt_rand()), 0, 16);
    $password = sha1($app_secret);

    $salt = substr(sha1(mt_rand()), 0, 4);
    $saltWithPassword = hash('sha256', $password . $salt);

    $encrypted = openssl_encrypt("$data", 'aes-256-cbc', "$saltWithPassword", null, $iv);

    $msg_encrypted_bundle = "$iv:$salt:$encrypted";
    $msg_encrypted_bundle = str_replace('/', '__', $msg_encrypted_bundle);

    return $msg_encrypted_bundle;
```

```
require "digest"

def generateHashKey(total, installment, currency_code, merchant_key, invoice_id , app_secret)
    data = total + "|" + installment + "|" + currency_code + "|" + merchant_key + "|" + invoice_id

    randNumIv = rand(10000000000000000..99999999999999999).to_s
    hashNumber = Digest::SHA1.hexdigest randNumIv
    iv = hashNumber[0,16]
    
    password = Digest::SHA1.hexdigest app_secret
    
    randNumSalt = rand(10000000000000000..99999999999999999).to_s
    hashSalt = Digest::SHA1.hexdigest randNumSalt
    salt = hashSalt[0,4]
    
    strPassSalt = password + salt
    saltWithPassword = Digest::SHA256.hexdigest strPassSalt
    
    encrypted = ""
    
    msg_encrypted_bundle = iv + ":" + salt + ":" + encrypted
    msg_encrypted_bundle = msg_encrypted_bundle.gsub("/" , "_")
    
    return msg_encrypted_bundle
end
```

```
import random
from Crypto.Hash import SHA1
from Crypto.Hash import SHA256
from Crypto.Cipher import AES
from Crypto.Util.Padding import pad
import base64

def generateHashKey(total, installment, currency_code, merchant_key, invoice_id , app_secret) :
    data = total + "|" + installment + "|" + currency_code + "|" + merchant_key + "|" + invoice_id
    
    randNumIv = str(random.randint(10000000000000000,99999999999999999))
    hashNumIv = SHA1.new()
    hashNumIv.update(randNumIv.encode("UTF-8"))
    hashNumber = hashNumIv.hexdigest()
    iv = hashNumber[:16]
    
    hashAppSec = SHA1.new()
    hashAppSec.update(app_secret.encode("UTF-8"))
    password = hashAppSec.hexdigest()
    
    randNumSalt = str(random.randint(10000000000000000,99999999999999999))
    hashNumSalt = SHA1.new()
    hashNumSalt.update(randNumSalt.encode("UTF-8"))
    hashSalt = hashNumSalt.hexdigest()
    salt = hashSalt[:4]

    strPassSalt = password + salt
    hashStr = SHA256.new()
    hashStr.update(strPassSalt.encode("UTF-8"))
    saltWithPassword = hashStr.hexdigest()[:32]
    
    plain_text_bytes = data.encode('UTF-8')
    aes = AES.new(saltWithPassword.encode('UTF-8'), AES.MODE_CBC, iv.encode('UTF-8'))
    padded_plain_text = pad(plain_text_bytes, AES.block_size)
    encrypted = aes.encrypt(padded_plain_text)
    encoded = base64.b64encode(encrypted).decode('UTF-8')
    
    msg_encrypted_bundle = iv + ":" + salt + ":" + encoded
    msg_encrypted_bundle = msg_encrypted_bundle.replace("/" , "__")
    
    return msg_encrypted_bundle
```

```
function generateHashKey(total , installment , currency_code , merchant_key , invoice_id , app_secret) {
    var data = total + "|" + installment + "|" + currency_code + "|" + merchant_key + "|" + invoice_id;

    var randNumIv = Math.floor(Math.random() * (99999999999999999 - 10000000000000000) + 10000000000000000);
    var hashNumIv = sha1(randNumIv);
    hashNumIv = hashNumIv.create();
    var iv = hashNumIv.slice(0,16);

    var hashPass = sha1(app_secret);
    hashPass = hashPass.create();
    var password = hashPass.hex();

    var randNumSalt = Math.floor(Math.random() * (99999999999999999 - 10000000000000000) + 10000000000000000);
    var hashNumSalt = sha1(randNumIv);
    hashNumSalt = hashNumSalt.create();
    var salt = hashNumSalt.hex();

    var strPassSalt = password + salt;
    var hashStr = sha1(strPassSalt);
    strPassSalt.create();
    var saltWithPassword = strPassSalt.hex();

    var encrypted = "";

    var msg_encrypted_bundle = iv + ":" + salt + ":" + encrypted;
    msg_encrypted_bundle = msg_encrypted_bundle.replaceAll("/" , "_");

    return msg_encrypted_bundle;
}
```

```
using System;
using System.Security.Cryptography;
using System.Text;
using System.Linq;

class HashGenerator
    {
        static void Main()
        {

            decimal total = 100;
            int installment = 1;
            string currencyCode = "TRY";
            string merchantKey = @"$2y$10$JrUqf69whjCYyIqgQCgu1uBGtRuEA4HDPV3MTTR5FqQfeMdA.zWDS";
            string invoiceId = "sample_invoice_id_2024_01";

            string hashData = total + "|" + installment + "|" + currencyCode + "|" + merchantKey + "|" + invoiceId;

            var hash = GenerateHashKey(hashData, "6dc170d87a74ac8bf440c77a6d5acb7c");
            Console.WriteLine(hash);
        }
        public static string GenerateHashKey(string data, string appsecret)
        {
            string iv = Sha1Hash(RandomNumberGenerator.GetInt32(999999999).ToString()).Substring(0, 16);
            string password = Sha1Hash(appsecret);

            string salt = Sha1Hash(RandomNumberGenerator.GetInt32(999999999).ToString()).Substring(0, 4);

            string saltWithPassword = "";
            using (SHA256 sha256Hash = SHA256.Create())
            {
                saltWithPassword = GetHash(sha256Hash, password + salt);
            }

            string encrypted = Encryptor(data, saltWithPassword.Substring(0, 32), iv);

            string msg_encrypted_bundle = iv + ":" + salt + ":" + encrypted;
            msg_encrypted_bundle = msg_encrypted_bundle.Replace("/", "__");

            return msg_encrypted_bundle;
        }


        private static string Sha1Hash(string password)
        {
            return string.Join("", SHA1.Create().ComputeHash(Encoding.UTF8.GetBytes(password)).Select(x => x.ToString("x2")));
        }

        private static string GetHash(HashAlgorithm hashAlgorithm, string input)
        {
            byte[] data = hashAlgorithm.ComputeHash(Encoding.UTF8.GetBytes(input));
            var sBuilder = new StringBuilder();

            for (int i = 0; i < data.Length; i++)
            {
                sBuilder.Append(data[i].ToString("x2"));
            }
            return sBuilder.ToString();
        }

        private static string Encryptor(string TextToEncrypt, string strKey, string strIV)
        {
           byte[] PlainTextBytes = Encoding.UTF8.GetBytes(TextToEncrypt);

            AesCryptoServiceProvider aesProvider = new AesCryptoServiceProvider();
            aesProvider.BlockSize = 128;
            aesProvider.KeySize = 256;
            aesProvider.Key = Encoding.UTF8.GetBytes(strKey);
            aesProvider.IV = Encoding.UTF8.GetBytes(strIV);
            aesProvider.Padding = PaddingMode.PKCS7;
            aesProvider.Mode = CipherMode.CBC;

            ICryptoTransform cryptoTransform = aesProvider.CreateEncryptor(aesProvider.Key, aesProvider.IV);
            byte[] EncryptedBytes = cryptoTransform.TransformFinalBlock(PlainTextBytes, 0, PlainTextBytes.Length);
            return Convert.ToBase64String(EncryptedBytes);
        }


    }
```