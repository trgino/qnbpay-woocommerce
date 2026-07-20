### Hash Doğrulama Örneği

3D ödemede, ödeme tamamlandıktan sonra, kullanıcı üye işyerinin başarılı ya da başarısız bağlantısına yönlendirilir. Bu bağlantılarla ilgili sorun, onlara anonim bir kişi tarafından erişilebilmesidir. Bu sorunu önlemek için, QNBpay'den yönlendirme yapılırken status, invoice\_id, order\_id ve hash\_key gibi bağlantılara bazı parametreler eklendiğinden ve isteğin, hash anahtarı kullanılarak doğrulanması önerilir.

Ayrıca, yinelenen ödemede, her yinelemede, üye işyeri webhook'una bir gönderi talebi gönderilir. Genellikle webhook açık bir bağlantıdır. Dolayısıyla, yineleme isteği, karma anahtar kullanılarak da doğrulanabilir. Doğrulama süreci örnek kodu aşağıda verilmiştir.

Here, $hash\_key must be obtained from the request, and $secret\_key is the secret key (app\_secret) of the merchant app provided by QNBpay.

**Not**

3d ödeme için $status = 0 veya $status = 1 'dir. Fakat yinelenen webhook için $status = COMPLETED veya $status = FAIL 'dir.

### Hash Key Oluşturma Kod Örnekleri

- PHP
- Ruby
- Python
- NodeJs
- C#

```
// Validate Hash Key

f<?php

function validateHashKey($hashKey, $secretKey)
{
    $status = $currencyCode = "";
    $total = $invoiceId = $orderId = 0;

    if (!empty($hashKey)) {
        $hashKey = str_replace('__', '/', $hashKey);
        $password = sha1($secretKey);

        $components = explode(':', $hashKey);
        if (count($components) > 2) {
            $iv = isset($components[0]) ? $components[0] : "";
            $salt = isset($components[1]) ? $components[1] : "";
            $salt = hash('sha256', $password . $salt);
            $encryptedMsg = isset($components[2]) ? $components[2] : "";

            $decryptedMsg = openssl_decrypt($encryptedMsg, 'aes-256-cbc', $salt, null, $iv);

            if (strpos($decryptedMsg, '|') !== false) {
                $array = explode('|', $decryptedMsg);
                $status = isset($array[0]) ? $array[0] : '';
                $total = isset($array[1]) ? $array[1] : '';
                $invoiceId = isset($array[2]) ? $array[2] : '';
                $orderId = isset($array[3]) ? $array[3] : '';
                $currencyCode = isset($array[4]) ? $array[4] : '';
            }
        }
    }

    return [$status, $total, $invoiceId, $orderId, $currencyCode];
}

//Example data 
$total = '20';
$installment = '1';
$currency_code = 'TRY';
$merchant_key = '$2y$10$N9IJkgazXMUwCzpn7NJrZePy3v.dIFOQUyW4yGfT3eWry6m.KxanK';
$invoice_id = 'test-invoice-id';
$app_secret = '61d97b2cac247069495be4b16f8604db';

try{  
  $test_hash = "97c0cf085f6b80d4:6bfd:r9__JiB8t16noGHzssMOGjc6IAeSjyTXkFRXvFpu2HLF+pjoL6i4C7+owtA3NvQgpKRK3sOzKfNlX__JLQhA3__Vw==";
  [$return_status, $return_total, $return_invoice_id, $return_order_id, $return_currency_code] = validateHashKey($test_hash, $app_secret);
  
  if($return_invoice_id == $invoice_id && $return_total == $total && $return_currency_code == $currency_code)
  {
    if($return_status == "1")
    {
      echo "The payment was completed successfully.";
    }
    else
    {
      echo "Payment failed.";
    }
  }
  else
  {
    throw new Exception("The provided parameters do not match.");
  }
}
catch(Exception $e)
{
  echo "Unable to verify the request.";
  echo $e->getMessage();
}
```

```
require "digest"

def validateHashKey(hashKey , secretKey)
    status = currencyCode = "";
    total = invoiceId = orderId = 0;
    
    if hashKey != nil

        hashKey = hashKey.gsub("_" , "/")      
        password = Digest::SHA1.hexdigest secretKey
    
        components = hashKey.split(":")
        
        if components.length() > 2
            iv = components[0] != nil ? components[0] : ""
            salt = components[1] != nil ? components[1] : ""      

            saltSum = password + salt
            salt = Digest::SHA256.hexdigest saltSum
            
            encryptedMsg = components[2] != nil ? components[2] : ""
            
            decryptedMsg = ""
            
            if decryptedMsg.index("|") != nil
                array = decryptedMsg.split("|")
                status = array[0] != nil ? array[0] : 0
                total = array[1] != nil ? array[1] : 0
                invoiceId = array[2] != nil ? array[2] : "0"
                orderId = array[3] != nil ? array[3] : 0
                currencyCode = array[4] != nil ? array[4] : ""
                
            end
            
        end
        
        return status , total , invoiceId , orderId , currencyCode
        
    end
end
```

```
import random
from Crypto.Hash import SHA1
from Crypto.Hash import SHA256
from Crypto.Cipher import AES
from Crypto.Util.Padding import pad, unpad
import base64

def validateHashKey(hashKey, secretKey):
    status = ""
    currencyCode = ""
    total = ""
    invoiceId = ""
    orderId = ""

    if hashKey:
        hashKey = hashKey.replace('__', '/')
        
        hashAppSec = SHA1.new()
        hashAppSec.update(app_secret.encode("UTF-8"))
        password = hashAppSec.hexdigest()

        components = hashKey.split(':')
        if len(components) > 2:
            iv = components[0]
            salt = components[1]
            encryptedMsg_b64 = components[2]

            strPassSalt = password + salt
            hashStr = SHA256.new()
            hashStr.update(strPassSalt.encode("UTF-8"))
            saltWithPassword = hashStr.hexdigest()[:32]

            encryptedMsg = base64.b64decode(encryptedMsg_b64)

            cipher = AES.new(saltWithPassword.encode('UTF-8'), AES.MODE_CBC, iv.encode('UTF-8'))
            decrypted_bytes = unpad(cipher.decrypt(encryptedMsg), AES.block_size)
            decryptedMsg = decrypted_bytes.decode('utf-8')
          
            if '|' in decryptedMsg:
                array = decryptedMsg.split('|')
                status = array[0] if len(array) > 0 else ""
                total = array[1] if len(array) > 1 else ""
                invoiceId = array[2] if len(array) > 2 else ""
                orderId = array[3] if len(array) > 3 else ""
                currencyCode = array[4] if len(array) > 4 else ""

    return [status, total, invoiceId, orderId, currencyCode]

#Example data
total = "20"
installment = "1"
currency_code = "TRY"
merchant_key = "$2y$10$N9IJkgazXMUwCzpn7NJrZePy3v.dIFOQUyW4yGfT3eWry6m.KxanK"
invoice_id = "test-invoice-id"
app_secret = "61d97b2cac247069495be4b16f8604db"

try:
    test_hash = "97c0cf085f6b80d4:6bfd:r9__JiB8t16noGHzssMOGjc6IAeSjyTXkFRXvFpu2HLF+pjoL6i4C7+owtA3NvQgpKRK3sOzKfNlX__JLQhA3__Vw=="

    return_status, return_total, return_invoice_id, return_order_id, return_currency_code = validateHashKey(test_hash, app_secret)
    
    if return_invoice_id == invoice_id and return_total == total and return_currency_code == currency_code:
        if return_status == "1":
            print("The payment was completed successfully.")
        else:
            print(f"Payment failed.")
    else:
        raise Exception("The provided parameters do not match.")

except Exception as e:
    print("Unable to verify the request.")
    print(e)
```

```
import CryptoJS from 'crypto-js';

function validateHashKey(hash_key, app_secret) {
    if(hash_key)
    {
        hash_key = hash_key.replaceAll("__", "/");
        let components = hash_key.split(':');

        if (components.length > 2) {
            let iv = components[0];
            let salt = components[1];
            let encrypted_msg = components[2];

            let password = CryptoJS.SHA1(app_secret);

            let saltWithPassword = CryptoJS.SHA256(password + salt).toString().substring(0, 32);

            let decrypted_msg = CryptoJS.AES.decrypt(
                encrypted_msg,
                CryptoJS.enc.Utf8.parse(saltWithPassword),
                {
                    iv: CryptoJS.enc.Utf8.parse(iv),
                    mode: CryptoJS.mode.CBC,
                    padding: CryptoJS.pad.Pkcs7,
                }
            ).toString(CryptoJS.enc.Utf8);

            if(decrypted_msg.indexOf('|'))
            {
                let array = decrypted_msg.split('|');
                return {
                    status: array[0] ?? '',
                    total: array[1] ?? '',
                    invoice_id: array[2] ?? '',
                    order_id: array[3] ?? '',
                    currency_code: array[4] ?? ''
                };
            }
        }
    }

    return {
        status: '',
        total: '',
        invoice_id: '',
        order_id: '',
        currency_code: '',
    };
}

//Example data
let total = '20',
    installment = '1',
    currency_code = 'TRY',
    merchant_key = '$2y$10$N9IJkgazXMUwCzpn7NJrZePy3v.dIFOQUyW4yGfT3eWry6m.KxanK',
    invoice_id = 'test-invoice-id',
    app_secret = '61d97b2cac247069495be4b16f8604db';

try {
    let test_hash = "97c0cf085f6b80d4:6bfd:r9__JiB8t16noGHzssMOGjc6IAeSjyTXkFRXvFpu2HLF+pjoL6i4C7+owtA3NvQgpKRK3sOzKfNlX__JLQhA3__Vw==";
    let return_data = validateHashKey(test_hash, app_secret);

    if(return_data.invoice_id == invoice_id && return_data.total == total && return_data.currency_code == currency_code)
    {
        if(return_data.status == "1")
        {
            console.log("The payment was completed successfully.");
        }
        else
        {
            console.log("Payment failed.");
        }
    }
    else
    {
        throw new Error("The provided parameters do not match.");
    }
    
} catch (e) {
    console.log("Unable to verify the request.")
    console.error(e);
}
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
 
        //Example verify
        try{
          
          var testHash = "97c0cf085f6b80d4:6bfd:r9__JiB8t16noGHzssMOGjc6IAeSjyTXkFRXvFpu2HLF+pjoL6i4C7+owtA3NvQgpKRK3sOzKfNlX__JLQhA3__Vw==";
          (string returnStatus, string returnTotal, string returnInvoiceId, string returnOrderId, string returnCurrencyCode) = ValidateHashKey(testHash, app_secret);
          
          if(returnInvoiceId == invoiceId && returnTotal == total.ToString() && returnCurrencyCode == currencyCode)
          {
            if(returnStatus == "1")
            {
              Console.WriteLine("The payment was completed successfully.");
            }
            else
            {
              Console.WriteLine("Payment failed.");
            }
          }
          else
          {
            throw new Exception("The provided parameters do not match.");
          }
        }
        catch(Exception e)
        {
          Console.WriteLine("Unable to verify the request.");
          Console.WriteLine(e);
        }
        
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
