 <?php
  include 'Crypt/RSA.php';
  include 'Crypt/CryptoJSAES.php';
  /**
   * isSecurity : on/off security
   * 
   */
  class ApiService
  {
    public function __construct($isSecurity = false, $domain = '', $appId = '', $privateKey = '', $publicKey = '', $accessToken = '')
    {
      $this->isSecurity = $isSecurity;
      $this->domain = $domain;
      $this->privateKey = $privateKey;
      $this->accessToken = $accessToken;
      $this->publicKey = $publicKey;
      $this->appId = $appId;
    }
    public function PayMEApi($url, $method = 'POST', $payload = [])
    {
      if ($this->isSecurity === true) {
        return $this->RequestSecurity($url, $method, $payload);
      }
      return $this->Request($url, $method, $payload);
    }

    private function Request($url, $method = 'POST', $payload = [])
    {
      $curl = curl_init();
      curl_setopt_array($curl, array(
        CURLOPT_URL => $this->domain . $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_HEADER => 1,
        CURLOPT_HTTPHEADER => array(
          "Authorization: " . $this->accessToken,
          'Content-Type: application/json; charset=utf-8'
        ),
        CURLOPT_CUSTOMREQUEST => strtoupper($method)
      ));

      $response = curl_exec($curl);
      $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
      $body = substr($response, $header_size);

      return $body;
    }

    private function RequestSecurity($url, $method = 'POST', $payload = [])
    {
      $encryptKey = rand(10000000, 99999999);
      $rsa = new Crypt_RSA();
      $rsa->loadKey($this->publicKey);
      $xAPIKey = base64_encode($rsa->encrypt($encryptKey));

      $config = [
        'url' => $url,
        'publicKey' => $this->privateKey,
        'privateKey' => $this->privateKey,
        'isSecurity' => true,
        'x-api-client' => $this->appId // config of partner 
      ];

      $xApiAction = CryptoJSAES::encrypt($config['url'], $encryptKey);

      $payloadString = json_encode($payload, JSON_FORCE_OBJECT);

      if ($payloadString) {
        $xApiMessage =  CryptoJSAES::encrypt($payloadString, $encryptKey);
      }

      $objValidate = [
        'xApiAction' => $xApiAction,
        'method' => strtoupper($method),
        'accessToken' => $this->accessToken
      ];

      if($payload){
        $objValidate['x-api-message'] = $xApiMessage;
      }

      $xAPIValidate = md5(implode('', $objValidate) . $encryptKey);

      $curl = curl_init();

      $curlInfo = array(
        CURLOPT_URL => $this->domain,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_HEADER => 1,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => array(
          "Authorization:" . $objValidate['accessToken'],
          "x-api-client:". $this->appId,
          "x-api-key:" . $xAPIKey,
          "x-api-validate:" . $xAPIValidate,
          "x-api-action:" . $xApiAction,
          "Content-Type:application/json"
        )
        );
      if($payload){
        $curlInfo[CURLOPT_POSTFIELDS] = "{\"x-api-message\": \"" . $xApiMessage . "\" }";
      }
      curl_setopt_array($curl, $curlInfo );

      $response = curl_exec($curl);
      $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
      $body = substr($response, $header_size);
      $headers = [];
      $data = explode("\n", $response);
      $headers['status'] = $data[0];
      array_shift($data);

      foreach ($data as $part) {
        $middle = explode(":", $part, 2);
        if (!isset($middle[1])) {
          $middle[1] = null;
        }
        $headers[trim($middle[0])] = trim($middle[1]);
      }

      $xAPIKey = !empty($headers['x-api-key']) ? $headers['x-api-key'] : '';
      $xAPIValidate = !empty($headers['x-api-validate']) ? $headers['x-api-validate'] : '';
      $xApiAction = !empty($headers['x-api-action']) ? $headers['x-api-action'] : '';
      $xAPIMessage = json_decode($body, true)['x-api-message'];
      return $this->decryptResponse(strtoupper($method), $xAPIKey, $xAPIMessage, $xAPIValidate, $objValidate['accessToken'], $this->privateKey, $xApiAction);
    }
    private function decryptResponse($method, $xAPIKey, $xAPIMessage, $xAPIValidate, $accessToken, $privateKey, $xAPIAction)
    {
      try {
        if (empty($xAPIKey) || empty($xAPIMessage) || empty($xAPIValidate) || empty($xAPIAction)) {
          throw new Exception('Thông tin "x-api-validate" không chính xác');
        }
        $key = new Crypt_RSA();
        $key->loadKey($privateKey);
        $encryptKey = $key->decrypt(base64_decode($xAPIKey));
        if (!$encryptKey) {
          throw new Exception('Thông tin "x-api-key" không chính xác');
        }
        $objValidate = [
          'x-api-action' => $xAPIAction,
          'method' => $method,
          'accessToken' => $accessToken,
          'x-api-message' => $xAPIMessage
        ];
        $validate = md5(implode('', $objValidate) . $encryptKey);
        if ($validate !== $xAPIValidate) {
          throw new Exception('Thông tin "x-api-validate" không chính xác');
        }
        $result = null;
        $result = CryptoJSAES::decrypt($xAPIMessage, $encryptKey);
        if ($result == null) {
          throw new Exception('Thông tin "x-api-message" không chính xác');
        }
        return $result;
      } catch (Exception $e) {
        return $e;
      }
    }
    public function decryptResponseIPN($method, $xAPIKey, $xAPIMessage, $xAPIValidate, $accessToken, $privateKey, $xAPIAction)
    {
      try {
        if (empty($xAPIKey) || empty($xAPIValidate) || empty($xAPIAction)) {
          throw new Exception('Thông tin "x-api-validate" không chính xác');
        }
        $key = new Crypt_RSA();
        $key->loadKey($privateKey);
        $encryptKey = $key->decrypt(base64_decode($xAPIKey));
        if (!$encryptKey) {
          throw new Exception('Thông tin "x-api-key" không chính xác');
        }
        $objValidate = [
          'x-api-action' => $xAPIAction,
          'method' => $method,
          'accessToken' => $accessToken,
          'x-api-message' => !empty($xAPIMessage) ? $xAPIMessage : ''
        ];
        $validate = md5(implode('', $objValidate) . $encryptKey);
        
        $result = null;
        if (!empty($xAPIMessage)) {
          $result = CryptoJSAES::decrypt($xAPIMessage, $encryptKey);
        }
        return $result;
      } catch (Exception $e) {
        return $e;
      }
    }
  }

  ?>
  