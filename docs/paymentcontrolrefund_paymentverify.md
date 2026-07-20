### Ödeme Doğrulama

Ödeme Doğrulama API'si, önceden yetkilendirilmiş bir ödemeyi onaylamak için kullanılır. Bu API, daha önce yetkilendirilmiş bir işlemden fonları yakalamak için çağrılmalıdır.

**Not**

Bu API, müşterinin hesabından fonları yakalayarak önceden yetkilendirilmiş bir ödemeyi tamamlamak için kullanılır.

\*\* HEADER alanında mutlaka Authorization parametresi içinde token servisinden alınan değeri `Authorization` olarak iletmeniz zorunludur.

**Post**(/api/confirmPayment)

| Test | https://test.qnbpay.com.tr/ccpayment/api/confirmPayment |
| --- | --- |
| Prod | https://portal.qnbpay.com.tr/ccpayment/api/confirmPayment |

### Yanıtlar

## **200** Başarılı Yanıt

## **400** Hata Yanıtı

### Request Samples

- Body

**Content type** 
application/json

```
{
  "invoice_id": "Cs2Ghy621dsa42f1D2",
  "merchant_key": "$2y$10$HmRgYosneqcwHj.UH7upGuyCZqpQ1ITgSMj9Vvxn.t6f.Vdf2SQFO",
  "status": 1,
  "hash_key": "661ebbf2acc9d8bc:cb27:47tnM4SnmuVWRq9YMaHo2npFjXr7Nfe04poc_ri3g_R1NylhHZcj0Zu3Eul",
  "total": 10.25
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
  "status_description": "An order has been taken place for this invoice id: 33491162435928
",
  "transaction_status": "Completed",
  "order_id": 162435932934307,
  "invoice_id": 33491162435928
}
```

```
{
  "status_code": 105,
  "status_description": "The transaction is not Approved
",
  "transaction_status": "Pending",
  "order_id": 162435932934307,
  "invoice_id": 33491162435928
}
```

### Hash Key Oluşturma Kod Örnekleri

- PHP
- Ruby
- Python
- NodeJs
- C#

```
// Generate Hash Key

function generateConfrimPaymentHashKey($merchant_key, $invoice_id, $status, $app_secret)
{

  $data = $merchant_key . '|' . $invoice_id . '|' . $status;

  $iv = substr(sha1(mt_rand()), 0, 16);
  $password = sha1($app_secret);

  $salt = substr(sha1(mt_rand()), 0, 4);
  $saltWithPassword = hash('sha256', $password . $salt);

  $encrypted = openssl_encrypt(
    "$data", 'aes-256-cbc', "$saltWithPassword", null, $iv
  );
  $msg_encrypted_bundle = "$iv:$salt:$encrypted";
  $msg_encrypted_bundle = str_replace('/', '__', $msg_encrypted_bundle);
  return $msg_encrypted_bundle;
}
```

```
require "digest"

def generateConfrimPaymentHashKey(merchant_key , invoice_id , status , app_secret)
    data = merchant_key + "|" + invoice_id + "|" + status
    
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

def generateConfrimPaymentHashKey(merchant_key , invoice_id , status , app_secret) :
    data = merchant_key + "|" + invoice_id + "|" + status

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
    saltWithPassword = hashStr.hexdigest()

    encrypted = ""

    msg_encrypted_bundle = iv + ":" + salt + ":" + encrypted
    msg_encrypted_bundle = msg_encrypted_bundle.replace("/" , "_")

    return msg_encrypted_bundle
```

```
function generateConfrimPaymentHashKey(merchant_key , invoice_id , status , app_secret) {
    data = merchant_key + "|" + invoice_id + "|" + status;

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
    hashStr.create();
    var saltWithPassword = strPassSalt.hex();

    var encrypted = "";

    var msg_encrypted_bundle = iv + ":" + salt + ":" + encrypted;
    msg_encrypted_bundle = msg_encrypted_bundle.replaceAll("/" , "_");

    return msg_encrypted_bundle;
}
```

```
{{ $t('common.noSampleCode') }}
```