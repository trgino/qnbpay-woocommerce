### İşlem Durumu

İşlem durumu sorgulaması için endpoint ve http method bilgisi aşağıda verilmiştir.

**Not** HEADER alanında mutlaka Authorization parametresi içinde 'Bearer + Token' olacak şekilde token servisinden alınan değeri iletmeniz zorunludur.

**Post**(/api/checkstatus)

| Test | https://test.qnbpay.com.tr/ccpayment/api/checkstatus |
| --- | --- |
| Prod | https://portal.qnbpay.com.tr/ccpayment/api/checkstatus |

### Yanıtlar

## **200** Başarılı Yanıt

## **400** Hata Yanıtı

### Request Samples

- Body

**Content type** 
application/json

```
{
  "merchant_key": "$2y$10$HmRgYosneqcwHj.UH7upGuyCZqpQ1ITgSMj9Vvxn.t6f.Vdf2SQFO",
  "invoice_id": "Cs2Ghy621dsa42f1D2",
  "include_pending_status": true,
  "hash_key": "661ebbf2acc9d8bc:cb27:47tnM4SnmuVWRq9YMaHo2npFjXr7Nfe04poc_ri3g_R1NylhHZcj0Zu3Eul"
}
```

### Response Samples

- 200
- 400

**Content type** 
application/json

```
[
  {
    "status_code": 100,
    "status_description": "An order has been taken place for this invoice id: 33491162435928",
    "transaction_status": "Completed",
    "order_id": 162435932934307,
    "transaction_id": "SXY0m-o3kb-TC10-98950-220621",
    "message": "An order has been taken place for this invoice id: 33491162435928",
    "reason": "",
    "bank_status_code": "",
    "bank_status_description": "",
    "invoice_id": "33491162435928",
    "total_refunded_amount": 0,
    "product_price": 20,
    "transaction_amount": 22.1,
    "ref_number": "",
    "transaction_type": "Auth",
    "merchant_commission": "2.63",
    "user_commission": "0.00",
    "settlement_date": "2022-05-10",
    "md_status": 1,
    "recurring_id": "303 // Yalnızca yinelenen işlemlerde döner",
    "recurring_plan_code": "1601492241FdsraX  // Yalnızca yinelenen işlemlerde döner",
    "next_action_date": "2021-05-30 03:10:00 // Yalnızca yinelenen işlemlerde döner ",
    "recurring_status": "Active // Yalnızca yinelenen işlemlerde döner"
  }
]
```

```
[
  {
    "status_code": 41,
    "status_description": "Oder Failed",
    "transaction_status": "Failed",
    "order_id": 162435924998223,
    "transaction_id": "6jvQ7-l3kb-TC10-98950-220621",
    "message": "Oder Failed",
    "reason": "Failed # transaction failed",
    "bank_status_code": "Failed # transaction failed",
    "bank_status_description": "transaction failed",
    "invoice_id": "50781624359247",
    "total_refunded_amount": 0,
    "product_price": 20,
    "transaction_amount": 22.1,
    "ref_number": "",
    "transaction_type": "Auth",
    "merchant_commission": "2.63",
    "user_commission": "0.00",
    "settlement_date": "2022-05-10",
    "md_status": 0
  }
]
```

### Hash Key Oluşturma Kod Örnekleri

- PHP
- Ruby
- Python
- NodeJs
- C#

```
<?php

function generateHashKey($data, $app_secret)
{
    $iv = substr(sha1(mt_rand()), 0, 16);
    $salt = substr(sha1(mt_rand()), 0, 4);
    $password = sha1($app_secret);

    $saltWithPassword = hash('sha256', $password . $salt);

    $encrypted = openssl_encrypt("$data", 'aes-256-cbc', "$saltWithPassword", null, $iv);

    $msg_encrypted_bundle = "$iv:$salt:$encrypted";
    $msg_encrypted_bundle = str_replace('/', '__', $msg_encrypted_bundle);

    return $msg_encrypted_bundle;
}

//Example data 
$total = '20';
$installment = '1';
$currency_code = 'TRY';
$merchant_key = '$2y$10$N9IJkgazXMUwCzpn7NJrZePy3v.dIFOQUyW4yGfT3eWry6m.KxanK';
$invoice_id = 'test-invoice-id';
$app_secret = '61d97b2cac247069495be4b16f8604db';

//Payment Hash
$paymentHashData = $total . '|' . $installment . '|' . $currency_code . '|' . $merchant_key . '|' . $invoice_id;

echo "Payment Hash: " . generateHashKey($paymentHashData, $app_secret);
echo "

";


//Complete Payment Hash
$order_id = "order-id-from-payment-response";
$complete_status = "complete"; // "complete" || "cancel"

$completeHashData = $merchant_key . '|' . $invoice_id . '|' . $order_id . '|' . $complete_status;

echo "Complete Payment Hash: " . generateHashKey($completeHashData, $app_secret);
echo "

";


//Refund Hash
$refundHashData = $total . '|' . $invoice_id . '|' . $merchant_key;

echo "Refund Hash: " . generateHashKey($refundHashData, $app_secret);
echo "

";


//Check Status Hash
$checkStatusHashData = $invoice_id . '|' . $merchant_key;

echo "Check Status Hash: " . generateHashKey($checkStatusHashData, $app_secret);
echo "

";

//Confirm Payment Hash
$confirm_status = "1"; // 1 => completed, 2 => cancel
$confirmHashData = $merchant_key . '|' . $invoice_id . '|' . $confirm_status;

echo "Confirm Payment Hash: " . generateHashKey($confirmHashData, $app_secret);
echo "

";
```

```
require 'openssl'
require 'digest'

def generateHashKey(data, app_secret)
  randNumIv = rand(10000000000000000..99999999999999999).to_s
  hashNumber = Digest::SHA1.hexdigest randNumIv
  iv = hashNumber[0,16]
  
  randNumSalt = rand(10000000000000000..99999999999999999).to_s
  hashSalt = Digest::SHA1.hexdigest randNumSalt
  salt = hashSalt[0,4]
  
  password = Digest::SHA1.hexdigest(app_secret)
  salt_with_password = Digest::SHA256.hexdigest(password + salt)

  cipher = OpenSSL::Cipher.new('aes-256-cbc')
  cipher.encrypt # Set cipher to encryption mode
  cipher.key = salt_with_password[0, 32]
  cipher.iv = iv

  encrypted_data = cipher.update(data) + cipher.final
  encrypted_base64 = [encrypted_data].pack('m0').gsub(/
/, '')

  msg_encrypted_bundle = "#{iv}:#{salt}:#{encrypted_base64}"
  msg_encrypted_bundle = msg_encrypted_bundle.gsub('/', '__')

  return msg_encrypted_bundle
end


#Example data 
total = "20"
installment = "1"
currency_code = "TRY"
merchant_key = "$2y$10$N9IJkgazXMUwCzpn7NJrZePy3v.dIFOQUyW4yGfT3eWry6m.KxanK"
invoice_id = "test-invoice-id"
app_secret = "61d97b2cac247069495be4b16f8604db"

#Payment Hash
paymentHashData = "#{total}|#{installment}|#{currency_code}|#{merchant_key}|#{invoice_id}"

puts sprintf("Payment Hash: %s 

", generateHashKey(paymentHashData, app_secret))


#Complete Payment Hash
order_id = "order-id-from-payment-response";
complete_status = "complete"; # "complete" || "cancel"

completeHashData = "#{merchant_key}|#{invoice_id}|#{order_id}|#{complete_status}"

puts sprintf("Complete Payment Hash: %s 

", generateHashKey(completeHashData, app_secret))

#Refund Hash
refundHashData = "#{total}|#{invoice_id}|#{merchant_key}"

puts sprintf("Refund Hash: %s 

", generateHashKey(refundHashData, app_secret))


#Check Status Hash
checkStatusHashData = "#{invoice_id}|#{merchant_key}"

puts sprintf("Check Status Hash: %s 

", generateHashKey(checkStatusHashData, app_secret))


#Confirm Payment Hash
confirm_status = "1"; # 1 => completed, 2 => cancel
confirmHashData = "#{merchant_key}|#{invoice_id}|#{confirm_status}"

puts sprintf("Confirm Payment Hash: %s 

", generateHashKey(confirmHashData, app_secret))
```

```
import random
from Crypto.Hash import SHA1
from Crypto.Hash import SHA256
from Crypto.Cipher import AES
from Crypto.Util.Padding import pad
import base64

def generateHashKey(data, app_secret) :
    
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
    
#Example data 
total = "20"
installment = "1"
currency_code = "TRY"
merchant_key = "$2y$10$N9IJkgazXMUwCzpn7NJrZePy3v.dIFOQUyW4yGfT3eWry6m.KxanK"
invoice_id = "test-invoice-id"
app_secret = "61d97b2cac247069495be4b16f8604db"

#Payment Hash
paymentHashData = total + '|' + installment + '|' + currency_code + '|' + merchant_key + '|' + invoice_id;

print("Payment Hash: %s 
" % generateHashKey(paymentHashData, app_secret))


#Complete Payment Hash
order_id = "order-id-from-payment-response";
complete_status = "complete"; # "complete" || "cancel"

completeHashData = merchant_key + '|' + invoice_id + '|' + order_id + '|' + complete_status;

print("Complete Payment Hash: %s 
" % generateHashKey(completeHashData, app_secret))


#Refund Hash
refundHashData = total + '|' + invoice_id + '|' + merchant_key;

print("Refund Hash: %s 
" % generateHashKey(refundHashData, app_secret))


#Check Status Hash
checkStatusHashData = invoice_id + '|' + merchant_key;

print("Check Status Hash: %s 
" % generateHashKey(checkStatusHashData, app_secret))


#Confirm Payment Hash
confirm_status = "1"; # 1 => completed, 2 => cancel
confirmHashData = merchant_key + '|' + invoice_id + '|' + confirm_status;

print("Confirm Payment Hash: %s 
" % generateHashKey(confirmHashData, app_secret))
```

```
import CryptoJS from 'crypto-js';

function generateHashKey(data, app_secret) {

  let iv = CryptoJS.SHA1(Math.random().toString()).toString().substring(0, 16);
  let salt = CryptoJS.SHA1(Math.random().toString()).toString().substring(0, 4);
  let password = CryptoJS.SHA1(app_secret);

  let saltWithPassword = CryptoJS.SHA256(password + salt).toString().substring(0, 32);

  let encrypted = CryptoJS.AES.encrypt(data, CryptoJS.enc.Utf8.parse(saltWithPassword), {
    iv: CryptoJS.enc.Utf8.parse(iv),
    mode: CryptoJS.mode.CBC,
    padding: CryptoJS.pad.Pkcs7,
  });

  let msg_encrypted_bundle = iv + ":" + salt + ":" + encrypted.toString();
  msg_encrypted_bundle = msg_encrypted_bundle.replaceAll("/" , "__");

  return msg_encrypted_bundle;
}

//Example data
let total = '20',
    installment = '1',
    currency_code = 'TRY',
    merchant_key = '$2y$10$N9IJkgazXMUwCzpn7NJrZePy3v.dIFOQUyW4yGfT3eWry6m.KxanK',
    invoice_id = 'test-invoice-id',
    app_secret = '61d97b2cac247069495be4b16f8604db';

//Payment Hash
let paymentHashData = total + "|" + installment + "|" + currency_code + "|" + merchant_key + "|" + invoice_id;

console.log("Payment Hash", generateHashKey(paymentHashData, app_secret), "
");


//Complete Payment Hash
let order_id = "order-id-from-payment-response";
let complete_status = "complete"; // "complete" || "cancel"

let completeHashData = merchant_key + "|" + invoice_id + "|" + order_id + "|" + complete_status;

console.log("Complete Payment Hash", generateHashKey(completeHashData, app_secret), "
");

//Refund Hash
let refundHashData = total + "|" + invoice_id + "|" + merchant_key;

console.log("Refund Hash", generateHashKey(refundHashData, app_secret), "
");

//Check Status Hash
let checkStatusHashData = invoice_id + "|" + merchant_key;

console.log("Check Status Hash", generateHashKey(checkStatusHashData, app_secret), "
");

//Confirm Payment Hash
let confirm_status = "1"; // 1 => completed, 2 => cancel
let confirmHashData = merchant_key + "|" + invoice_id + "|" + confirm_status;

console.log("Confirm Payment Hash", generateHashKey(confirmHashData, app_secret), "
");
```

```
using System;
using System.Security.Cryptography;
using System.Text;
using System.Linq;
using System.Collections.Generic;

class HashGenerator
{
    static void Main()
    {
        //Example data
        decimal total = 20;
        int installment = 1;
        string currencyCode = "TRY";
        string merchantKey = "$2y$10$N9IJkgazXMUwCzpn7NJrZePy3v.dIFOQUyW4yGfT3eWry6m.KxanK";
        string invoiceId = "test-invoice-id";
        string app_secret = "61d97b2cac247069495be4b16f8604db";

        //Payment Hash
        string paymentHashData = total + "|" + installment + "|" + currencyCode + "|" + merchantKey + "|" + invoiceId;
        var paymentHash = GenerateHashKey(paymentHashData, app_secret);
        Console.WriteLine("Payment Hash: " + paymentHash);
        Console.WriteLine();


        //Complete Payment Hash
        string orderId = "order-id-from-payment-response";
        string completeStatus = "complete"; // "complete" || "cancel"

        string completeHashData = merchantKey + "|" + invoiceId + "|" + orderId + "|" + completeStatus;
        
        Console.WriteLine("Complete Payment Hash: " + GenerateHashKey(completeHashData, app_secret));
        Console.WriteLine();
        
        
        //Refund Hash
        string refundHashData = total + "|" + invoiceId + "|" + merchantKey;
        
        Console.WriteLine("Refund Hash: " + GenerateHashKey(refundHashData, app_secret));
        Console.WriteLine();
        
        //Check Status Hash
        string checkStatusHashData = invoiceId + "|" + merchantKey;
        
        Console.WriteLine("Refund Hash: " + GenerateHashKey(checkStatusHashData, app_secret));
        Console.WriteLine();
        
        //Confirm Payment Hash
        string confirmStatus = "1"; // 1 => completed, 2 => cancel

        string confirmHashData = merchantKey + "|" + invoiceId + "|" + confirmStatus;
        
        Console.WriteLine("Confirm Payment Hash: " + GenerateHashKey(confirmHashData, app_secret));
        Console.WriteLine();
        
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
    
    public static (string status, string total, string invoiceId, string orderId, string currencyCode) ValidateHashKey(string hashKey, string app_secret)
    {
        hashKey = hashKey.Replace("__", "/");

        string password = Sha1Hash(app_secret);

        IList<string> mainStringArray = hashKey.Split(':').ToList<string>();
        
        string status, total, invoiceId, orderId, currencyCode;

        if (mainStringArray.Count == 3)
        {
            string iv = mainStringArray[0];
            string salt = mainStringArray[1];
            string mainKey = mainStringArray[2];

            string saltWithPassword = "";
            using (SHA256 sha256Hash = SHA256.Create())
            {
                saltWithPassword = GetHash(sha256Hash, password + salt);
            }
            string orginalValues = Decryptor(mainKey, saltWithPassword.Substring(0, 32), iv);
            var valueArray = orginalValues.Split('|');
            
              status = valueArray[0] ?? "";
              total = valueArray[1] ?? "";
              invoiceId = valueArray[2] ?? "";
              orderId = valueArray[3] ?? "";
              currencyCode = valueArray[4] ?? "";
        }
        
        return (status, total, invoiceId, orderId, currencyCode);

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
    

    private static string Decryptor(string TextToDecrypt, string strKey, string strIV)
    {
        byte[] EncryptedBytes = Convert.FromBase64String(TextToDecrypt);
     
        using (AesCryptoServiceProvider aesProvider = new AesCryptoServiceProvider())
        {
            aesProvider.BlockSize = 128;
            aesProvider.KeySize = 256;
            
            aesProvider.Key = Encoding.UTF8.GetBytes(strKey);
            aesProvider.IV = Encoding.UTF8.GetBytes(strIV);
            aesProvider.Padding = PaddingMode.PKCS7;
            aesProvider.Mode = CipherMode.CBC;

            ICryptoTransform cryptoTransform = aesProvider.CreateDecryptor(aesProvider.Key, aesProvider.IV);
            byte[] DecryptedBytes = cryptoTransform.TransformFinalBlock(EncryptedBytes, 0, EncryptedBytes.Length);
            return Encoding.UTF8.GetString(DecryptedBytes);
        }
    }
}
```