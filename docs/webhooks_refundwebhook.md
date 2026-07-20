### İade WebHook

Üye iş yerinin, http://app.qnbpay.com.tr/merchant/apisetting adresindeki QNBpay üye iş yeri panelinde geri ödeme web hook URL'nizi (anahtar/key, değer/value) ayarlaması gerekir. Geri ödeme için geri ödeme isteği parametresi ile refund\_web\_hook\_key anahtarı gönderilmelidir. QNBpay, iade talebinde bulunurken anahtarın veritabanında bulunduğunu doğrular. Geri ödeme onayında, aşağıda belirtilen parametrelerle bir üye iş yeri geri ödeme web hook url'sine bir POST isteği gönderilir.

Hash Anahtarını Kullanarak WebHook İade Yanıt Doğrulaması İade Onayında QNBpay, web hook url'sine bir gönderi isteği gönderir. Bu bağlantılarla ilgili sorun, anonim bir kişi tarafından erişilebilmesidir. Bu sorunu önlemek için, istek parametreleriyle birlikte durum, invoice\_id, order\_id, amount ve hash\_key gibi bağlantılara bazı parametreler eklendiğinden, talebin hash anahtarı kullanılarak doğrulanması önerilir.

`$hash_key` istekten alınmalı, `$secret_key` üye işyeri app secret bilgisi QNBpay tarafından sağlanmaktadır.

### Hash Key Oluşturma Kod Örnekleri

- PHP
- Ruby
- Python
- NodeJs
- C#

```
// Validate Hash Key

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
                $status = isset($array[0]) ? $array[0] : 0;
                $total = isset($array[1]) ? $array[1] : 0;
                $invoiceId = isset($array[2]) ? $array[2] : '0';
                $orderId = isset($array[3]) ? $array[3] : 0;
                $currencyCode = isset($array[4]) ? $array[4] : '';
            }
        }
    }

    return [$status, $total, $invoiceId, $orderId, $currencyCode];
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
from Crypto.Hash import SHA1
from Crypto.Hash import SHA256

def validateHashKey (hashKey , secretKey) :
    status = currencyCode = ""
    total = invoiceId = orderId = 0
    
    if hashKey != None :
        
        hashKey = hashKey.replace("_" , "/")

        hashStr = SHA1.new()
        hashStr.update(secretKey.encode("UTF-8"))
        password = hashStr.hexdigest()
        
        components = hashKey.split(":")

        if len(components) > 2 :
            iv = components[0] if components[0] != None else ""

            salt = components[1] if components[1] != None else ""
            
            strSum = password + salt
            hashSalt = SHA256.new()
            hashSalt.update(strSum.encode("UTF-8"))
            salt = hashSalt.hexdigest()
          
            encryptedMsg = components[2] if components[2] != None else ""

            decryptedMsg = ""
            
            if "|" in decryptedMsg is not False :
                array = decryptedMsg.split("|")
                status = array[0] if array[0] != None else 0
                total = array[1] if array[1] != None else 0
                invoiceId  = array[2] if array[2] != None else "0"
                orderId = array[3] if array[3] != None else 0
                currencyCode = array[4] if array[4] != None else ""

    return [status , total , invoiceId , orderId , currencyCode]
```

```
function validateHashKey(hashKey , secretKey) {
    var status = currencyCode = "";
    var total = invoiceId = orderId = 0;

    if (hashKey !== "") {
        var hashKey = 
        hashKey.replaceAll("_" , "/");
        var hashPass = sha1(secretKey);
        hashPass = hashPass.create();
        var password = hashPass.hex()

        components = hashKey.split(":")

        if (components.length > 2) {
            iv = components[0] !== null ? components[0] : "";
            salt = components[1] !== null ? components[1] : "";
            strSalt = password + salt;
            hashSalt = sha256(strSalt);
            hashSalt = hashSalt.create()
            salt = hashSalt.hex()

            encryptedMsg = components[2] !== null ? components[2] : "";

            decryptedMsg = "";

            if (decryptedMsg.find("|") != false){
                array = decryptedMsg.split("|");
                status = array[0] != null ? array[0] : 0;
                total = array[1] != null ? array[1] : 0;
                invoiceId = array[2] != null ? array[2] : "0";
                orderId = array[3] != null ? array[3] : 0;
                currencyCode = array[4] != null ? array[4] : 0;
            }
        }
    }
    return [status , total , invoiceId , orderId , currencyCode];
}
```

```
using System;
using System.Collections.Generic;
using System.IO;
using System.Security.Cryptography;
using System.Text;

namespace Sipay
{
    class Program
    {
        public static (int, int, string, int, string) ValidateHashKey(string hashKey, string secretKey)
        {
            string currencyCode = "", invoiceId="";
            int total = 0, orderId = 0, status=0;
            if (!string.IsNullOrEmpty(secretKey))
            {
                hashKey = hashKey.Replace('_','/');
                string password = GetSHA1(secretKey);
                var components = hashKey.Split(':');
                if (components.Length > 2)
                {
                    var iv = components[0] != null ? components[0] : "";
                    var salt = components[1] != null ? components[1] : "";
                    var sha256 = new SHA256Managed();
                    salt = sha256.ComputeHash(Encoding.UTF8.GetBytes(password + salt)).ToString();
                    var encryptedMsg = components[2] != null ? components[2] : "";
                    //var decryptedMsg = Execute(Encoding.UTF8.GetBytes(password + salt),iv,encryptedMsg);
                    var decryptedMsg = "asdasd|asdasdasd";
                    if (decryptedMsg.Contains('|') != false)
                    {
                        var array = decryptedMsg.Split('|');
                        status = array[0] != null ? int.Parse(array[0]) : 0;
                        total = array[1] != null ? int.Parse(array[1]) : 0;
                        invoiceId = array[2] != null ? array[2] : "";
                        orderId = array[3] != null ? int.Parse(array[3]) : 0;
                        currencyCode = array[4] != null ? array[4] : "";
                    }
                }
            }
            var tuple = (status,total,invoiceId,orderId,currencyCode);
            return tuple;
        }

        public static string GetSHA1(string value)
        {
            var data = Encoding.ASCII.GetBytes(value);
            var hashData = new SHA1Managed().ComputeHash(data);
            var hash = string.Empty;
            foreach (var b in hashData)
            {
                hash += b.ToString("X2");
            }
            return hash;
        }


        public static string Execute(byte[] key,string iv, string encryptedMsg)
        {
            var aes = new AesCryptoServiceProvider();
            aes.KeySize = 256;

            // Fixed password in code
            aes.Key = key;
            // API = IV
            aes.IV = Encoding.UTF8.GetBytes(iv);

            aes.Mode = CipherMode.CBC;

            // Trying to encrypt "36" in this case
            byte[] src = Encoding.Unicode.GetBytes(encryptedMsg);

            // Actual encryption
            using (var encrypt = aes.CreateEncryptor())
            {
                byte[] dest = encrypt.TransformFinalBlock(src, 0, src.Length);

                // Convert byte array to Base64 strings
                return Convert.ToBase64String(dest);
            }
        }
    }
}
```