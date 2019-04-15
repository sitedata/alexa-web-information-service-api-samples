/*
 * Copyright 2019 Amazon.com, Inc. and its affiliates. All Rights Reserved.
 *
 * Licensed under the MIT License. See the LICENSE accompanying this file
 * for the specific language governing permissions and limitations under
 * the License.
 */

<?php
/**
 * Makes a request to AWIS for site info.
 */
require './aws.phar';

class UrlInfo {

    protected static $ActionName            = 'UrlInfo';
    protected static $ResponseGroupName     = 'Rank,LinksInCount';
    protected static $ServiceHost           = 'awis.api.alexa.com';
    protected static $ServiceEndpoint       = 'awis.api.alexa.com';
    protected static $NumReturn             = 10;
    protected static $StartNum              = 1;
    protected static $HashAlgorithm         = 'HmacSHA256';
    protected static $Region                = 'us-east-1';
    protected static $ServiceURI            = "/api";
    protected static $ServiceRegion         = "us-east-1";
    protected static $ServiceName           = "execute-api";
    protected static $CognitoUserPoolId     = "us-east-1_n8TiZp7tu";
    protected static $CognitoClientId       = "6clvd0v40jggbaa5qid2h6hkqf";
    protected static $CognitoIdentityPoolId = "us-east-1:bff024bb-06d0-4b04-9e5d-eb34ed07f884";
    protected static $CachedCredentials     = "./.alexa_credentials";

    public function UrlInfo($apiUser, $apiKey,  $site) {
        $now = time();
        $this->amzDate = gmdate("Ymd\THis\Z", $now);
        $this->dateStamp = gmdate("Ymd", $now);
        $this->site = $site;
        $this->apiKey = $apiKey;
        $this->apiUser = $apiUser;
        $this->apiPassword = "";
    }

    /**
       Save credentials in cache
    */

    protected function SaveCredentialsToCache($awsCredentials) {
        $fp = fopen(self::$CachedCredentials, "w");
        fwrite($fp, serialize($awsCredentials));
        fclose($fp);
    }

    protected function GetCredentials() {
      $awsCredentials = null;
      if (file_exists(self::$CachedCredentials)){
          $objData = file_get_contents(self::$CachedCredentials);
          $awsCredentials = unserialize($objData);
          if (!empty($awsCredentials)) {
            $expiresOn = strtotime ($awsCredentials["Expiration"]);
            $nowGMT = gmmktime();
            if ($nowGMT > $expiresOn) {
              $awsCredentials = null;
            }
          }
      }

      if ($awsCredentials == null) {
          $awsCredentials = self::GetCognitoCredentials();
          self::SaveCredentialsToCache($awsCredentials);
      }

      self::SetCredentials($awsCredentials);
    }

    /**
     * Set AWS credentials
    */
    protected function SetCredentials($awsCredentials) {
      $this->accessKeyId = $awsCredentials["AccessKeyId"];
      $this->secretAccessKey = $awsCredentials["SecretKey"];
      $this->sessionToken = $awsCredentials["SessionToken"];
      $this->expiration = $awsCredentials["Expiration"];
    }

    protected function hide_term() {
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN')
            system('stty -echo');
    }
 
    protected function restore_term() {
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN')
            system('stty echo');
    }

    /**
     * Get temporary $credentials
    */
    protected function GetCognitoCredentials() {

      $this->$idProviderClient = new Aws\CognitoIdentityProvider\CognitoIdentityProviderClient([
         'version' => '2016-04-18',
         'region' => self::$Region,
         'credentials' => false,
       ]);

       echo "Password: ";
       $this->hide_term();
       $this->apiPassword = rtrim(fgets(STDIN), PHP_EOL);
       $this->restore_term();
       try {
           $result = $this->$idProviderClient->initiateAuth([
               'AuthFlow' => 'USER_PASSWORD_AUTH',
               'ClientId' => self::$CognitoClientId,
               'UserPoolId' => self::$CognitoUserPoolId,
               'AuthParameters' => [
                   'USERNAME' => $this->apiUser,
                   'PASSWORD' => $this->apiPassword,
               ],
           ]);
           $accessToken = $result->get('AuthenticationResult')['AccessToken'];
           $idToken = $result->get('AuthenticationResult')['IdToken'];
           //echo "Access Token: $accessToken\n";
           //echo "ID Token: $idToken\n";

           $idClient = new Aws\CognitoIdentity\CognitoIdentityClient([
                                    'version' => '2014-06-30',
                                    'region' => self::$Region,
                                    'credentials' => false,
                                  ]);

          $provider = 'cognito-idp.us-east-1.amazonaws.com/'.self::$CognitoUserPoolId;

          $clientIDResponse = $idClient->getId([
                                  'IdentityPoolId' => self::$CognitoIdentityPoolId,
                                  'Logins' => [ $provider  => $idToken ]
                                ]);

          $clientId = $clientIDResponse->get('IdentityId');

          $result = $idClient->getCredentialsForIdentity([
                                  'IdentityId' => $clientId,
                                  'Logins' => [ $provider  => $idToken ]
                                ]);

          $awsCredentials = $result->get('Credentials');

          return $awsCredentials;

       } catch (\Exception $e) {
          $errorMessage = $e->getMessage();
           echo "Error: $errorMessage";
           return $e->getMessage();
       }
    }

    /**
     * Get site info from AWIS.
     */
    public function getUrlInfo() {
        self::GetCredentials();

        $canonicalQuery = $this->buildQueryParams();
        $canonicalHeaders =  $this->buildHeaders(true);
        $signedHeaders = $this->buildHeaders(false);
        $payloadHash = hash('sha256', "");
        $canonicalRequest = "GET" . "\n" . self::$ServiceURI . "\n" . $canonicalQuery . "\n" . $canonicalHeaders . "\n" . $signedHeaders . "\n" . $payloadHash;
        $algorithm = "AWS4-HMAC-SHA256";
        $credentialScope = $this->dateStamp . "/" . self::$ServiceRegion . "/" . self::$ServiceName . "/" . "aws4_request";
        $stringToSign = $algorithm . "\n" .  $this->amzDate . "\n" .  $credentialScope . "\n" .  hash('sha256', $canonicalRequest);
        $signingKey = $this->getSignatureKey();
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);
        $authorizationHeader = $algorithm . ' ' . 'Credential=' . $this->accessKeyId . '/' . $credentialScope . ', ' .  'SignedHeaders=' . $signedHeaders . ', ' . 'Signature=' . $signature;

        $url = 'https://' . self::$ServiceHost . self::$ServiceURI . '?' . $canonicalQuery;
        $ret = self::makeRequest($url, $authorizationHeader);
        echo "\nResults for " . $this->site .":\n\n";
        echo $ret;
    }

    protected function sign($key, $msg) {
        return hash_hmac('sha256', $msg, $key, true);
    }

    protected function getSignatureKey() {
        $kSecret = 'AWS4' . $this->secretAccessKey;
        $kDate = $this->sign($kSecret, $this->dateStamp);
        $kRegion = $this->sign($kDate, self::$ServiceRegion);
        $kService = $this->sign($kRegion, self::$ServiceName);
        $kSigning = $this->sign($kService, 'aws4_request');
        return $kSigning;
    }

    /**
     * Builds headers for the request to AWIS.
     * @return String headers for the request
     */
    protected function buildHeaders($list) {
        $params = array(
            'host'            => self::$ServiceEndpoint,
            'x-amz-date'      => $this->amzDate
        );
        ksort($params);
        $keyvalue = array();
        foreach($params as $k => $v) {
            if ($list)
              $keyvalue[] = $k . ':' . $v;
            else {
              $keyvalue[] = $k;
            }
        }
        return ($list) ? implode("\n",$keyvalue) . "\n" : implode(';',$keyvalue) ;
    }

    /**
     * Builds query parameters for the request to AWIS.
     * Parameter names will be in alphabetical order and
     * parameter values will be urlencoded per RFC 3986.
     * @return String query parameters for the request
     */
    protected function buildQueryParams() {
        $params = array(
            'Action'            => self::$ActionName,
            'Count'             => self::$NumReturn,
            'ResponseGroup'     => self::$ResponseGroupName,
            'Start'             => self::$StartNum,
            'Url'               => $this->site
        );
        ksort($params);
        $keyvalue = array();
        foreach($params as $k => $v) {
            $keyvalue[] = $k . '=' . rawurlencode($v);
        }
        return implode('&',$keyvalue);
    }

    /**
     * Makes request to AWIS
     * @param String $url   URL to make request to
     * @param String authorizationHeader  Authorization string
     * @return String       Result of request
     */
    protected function makeRequest($url, $authorizationHeader) {
        echo "\nMaking request to:\n$url\n";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
          'Accept: application/xml',
          'Content-Type: application/xml',
          'X-Amz-Date: ' . $this->amzDate,
          'Authorization: ' . $authorizationHeader,
          'x-api-key: ' . $this->apiKey,
          'x-amz-security-token: ' . $this->sessionToken
        ));
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    /**
     * Parses XML response from AWIS and displays selected data
     * @param String $response    xml response from AWIS
     */
    public static function parseResponse($response) {
        $xml = new SimpleXMLElement($response,LIBXML_ERR_ERROR,false,'http://awis.amazonaws.com/doc/2005-07-11');
        if($xml->count() && $xml->Response->UrlInfoResult->Alexa->count()) {
            $info = $xml->Response->UrlInfoResult->Alexa;
            $nice_array = array(
                'Links In Count' => $info->ContentData->LinksInCount,
                'Rank'           => $info->TrafficData->Rank
            );
        }
        foreach($nice_array as $k => $v) {
            echo $k . ': ' . $v ."\n";
        }
    }

}

if (count($argv) < 3) {
    echo "Usage: $argv[0] USER API_KEY SITE\n";
    exit(-1);
}
else {
    $apiUser = $argv[1];
    $apiKey = $argv[2];
    $site = $argv[3];
}

$urlInfo = new UrlInfo($apiUser, $apiKey, $site);
$urlInfo->getUrlInfo();

?>
